(function () {
    const PRICE_BUDI95 = 1.99;
    const PRICE_RON95 = 2.05;

    const config = window.transportationLogConfig || {};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const money = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    let statusTimer = null;
    let editingVehicleId = null;
    let editingFuelLogId = null;
    let editingCommuteLogId = null;

    const state = {
        vehicles: [],
        fuelLogs: [],
        commuteLogs: [],
    };

    function setStatus(message, isError) {
        const el = document.getElementById('statusMessage');
        if (!el) return;
        el.textContent = message;
        el.classList.toggle('is-error', Boolean(isError));
        el.classList.add('is-visible');
        if (statusTimer) clearTimeout(statusTimer);
        statusTimer = setTimeout(() => el.classList.remove('is-visible'), 3200);
    }

    function toNumber(value, fallback) {
        const normalized = String(value ?? '').replace(/,/g, '').trim();
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function normalizeVehicle(vehicle) {
        return {
            ...vehicle,
            id: String(vehicle.id),
        };
    }

    function normalizeFuelLog(log) {
        return {
            ...log,
            id: String(log.id),
            vehicle_id: String(log.vehicle_id),
        };
    }

    function normalizeCommuteLog(log) {
        return {
            ...log,
            id: String(log.id),
            vehicle_id: String(log.vehicle_id),
        };
    }

    function applySnapshot(snapshot) {
        state.vehicles = Array.isArray(snapshot?.vehicles) ? snapshot.vehicles.map(normalizeVehicle) : [];
        state.fuelLogs = Array.isArray(snapshot?.fuelLogs) ? snapshot.fuelLogs.map(normalizeFuelLog) : [];
        state.commuteLogs = Array.isArray(snapshot?.commuteLogs) ? snapshot.commuteLogs.map(normalizeCommuteLog) : [];

        if (editingFuelLogId && !state.fuelLogs.some((log) => log.id === editingFuelLogId)) {
            editingFuelLogId = null;
        }
        if (editingCommuteLogId && !state.commuteLogs.some((log) => log.id === editingCommuteLogId)) {
            editingCommuteLogId = null;
        }

        renderAll();
    }

    async function apiRequest(method, url, payload) {
        const options = {
            method,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        };

        if (payload !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }

        const response = await fetch(url, options);
        const contentType = response.headers.get('content-type') || '';
        const data = contentType.includes('application/json') ? await response.json() : null;

        if (!response.ok) {
            if (data?.message) {
                if (data.errors && typeof data.errors === 'object') {
                    const firstError = Object.values(data.errors).flat()[0];
                    if (firstError) {
                        throw new Error(String(firstError));
                    }
                }
                throw new Error(String(data.message));
            }
            throw new Error('Request failed.');
        }

        return data;
    }

    async function refreshSnapshot() {
        if (!config.snapshotEndpoint) return;
        const data = await apiRequest('GET', config.snapshotEndpoint);
        applySnapshot(data);
    }

    function monthKeyFromDate(dateLike) {
        const d = new Date(dateLike);
        if (Number.isNaN(d.getTime())) return '';
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    }

    function vehicleName(vehicleId) {
        const vehicle = state.vehicles.find((v) => v.id === String(vehicleId));
        return vehicle ? vehicle.name : 'Unknown';
    }

    function formatDateTime(dateLike) {
        const d = new Date(dateLike);
        if (Number.isNaN(d.getTime())) return '-';
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }

    function splitIsoDateTime(isoDateTime) {
        const d = new Date(isoDateTime);
        if (Number.isNaN(d.getTime())) {
            return {
                date: defaultDateValue(),
                time: defaultTimeValue(),
            };
        }

        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');

        return {
            date: `${year}-${month}-${day}`,
            time: `${hours}:${minutes}`,
        };
    }

    function resolveFuelPriceByType(type, currentPrice) {
        if (type === 'budi95') return PRICE_BUDI95;
        if (type === 'ron95') return currentPrice > 0 ? currentPrice : PRICE_RON95;
        return currentPrice > 0 ? currentPrice : 0;
    }

    function resolveDrivePricePerLitre(vehicleId, drivenAtIso, explicitPrice) {
        if (toNumber(explicitPrice, 0) > 0) {
            return toNumber(explicitPrice, PRICE_BUDI95);
        }

        const candidateLogs = state.fuelLogs
            .filter((row) => row.vehicle_id === String(vehicleId))
            .sort((a, b) => new Date(a.fuelled_at).getTime() - new Date(b.fuelled_at).getTime());

        if (!candidateLogs.length) {
            return PRICE_BUDI95;
        }

        const drivenAt = new Date(drivenAtIso).getTime();
        if (!Number.isNaN(drivenAt)) {
            const historical = candidateLogs.filter((row) => new Date(row.fuelled_at).getTime() <= drivenAt);
            if (historical.length) {
                return toNumber(historical[historical.length - 1].price_per_litre, PRICE_BUDI95);
            }
        }

        return toNumber(candidateLogs[candidateLogs.length - 1].price_per_litre, PRICE_BUDI95);
    }

    function driveRowDateTime(row) {
        if (row.driven_at) return row.driven_at;
        return '';
    }

    function deriveFuelRows() {
        const byVehicle = new Map();
        state.fuelLogs.forEach((log) => {
            const logs = byVehicle.get(log.vehicle_id) || [];
            logs.push(log);
            byVehicle.set(log.vehicle_id, logs);
        });

        const derived = [];
        byVehicle.forEach((logs) => {
            logs.sort((a, b) => new Date(a.fuelled_at).getTime() - new Date(b.fuelled_at).getTime());
            for (let i = 0; i < logs.length; i++) {
                const current = logs[i];
                const previous = i > 0 ? logs[i - 1] : null;
                const distance = previous ? current.odometer_km - previous.odometer_km : null;
                const canComputeDistance = Number.isFinite(distance) && distance > 0;
                const litres = toNumber(current.fuel_litres, 0);
                const total = toNumber(current.total_amount, 0);
                const lPer100 = canComputeDistance && litres > 0 ? (litres / distance) * 100 : null;
                const kmPerL = canComputeDistance && litres > 0 ? distance / litres : null;
                const costPerKm = canComputeDistance ? total / distance : null;

                derived.push({
                    ...current,
                    distance_km: canComputeDistance ? distance : null,
                    estimated_l_per_100km: lPer100,
                    estimated_km_per_l: kmPerL,
                    cost_per_km: costPerKm,
                });
            }
        });

        derived.sort((a, b) => new Date(b.fuelled_at).getTime() - new Date(a.fuelled_at).getTime());
        return derived;
    }

    function deriveDriveRow(row) {
        const distance = toNumber(row.distance_km, 0);
        const mileageLPer100Km = toNumber(row.consumption_value, 0);
        const drivenAt = driveRowDateTime(row);
        const pricePerLitre = resolveDrivePricePerLitre(row.vehicle_id, drivenAt, row.price_per_litre);

        let estimatedFuelLitres = null;
        if (distance > 0 && mileageLPer100Km > 0) {
            estimatedFuelLitres = (mileageLPer100Km / 100) * distance;
        }

        const estimatedCost = estimatedFuelLitres !== null ? estimatedFuelLitres * pricePerLitre : null;
        const estimatedCostPerKm = estimatedCost !== null && distance > 0 ? estimatedCost / distance : null;

        return {
            ...row,
            driven_at: drivenAt,
            mileageLPer100Km,
            estimated_fuel_litres: estimatedFuelLitres,
            estimated_fuel_cost: estimatedCost,
            estimated_cost_per_km: estimatedCostPerKm,
        };
    }

    function thisMonthKey() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    }

    function thisMonthSummary() {
        const month = thisMonthKey();
        const fuelRows = deriveFuelRows();
        const driveRows = state.commuteLogs.map(deriveDriveRow);

        const fuelRowsMonth = fuelRows.filter((row) => monthKeyFromDate(row.fuelled_at) === month);
        const driveRowsMonth = driveRows.filter((row) => monthKeyFromDate(row.driven_at) === month);

        const actualFuelSpending = fuelRowsMonth.reduce((carry, row) => carry + toNumber(row.total_amount, 0), 0);
        const totalFuelLitres = fuelRowsMonth.reduce((carry, row) => carry + toNumber(row.fuel_litres, 0), 0);
        const avgLPer100 = (() => {
            const eligible = driveRowsMonth.filter((row) => toNumber(row.distance_km, 0) > 0 && toNumber(row.mileageLPer100Km, 0) > 0);
            if (!eligible.length) return null;
            const totalDistance = eligible.reduce((carry, row) => carry + toNumber(row.distance_km, 0), 0);
            if (totalDistance <= 0) return null;
            const weightedMileageSum = eligible.reduce(
                (carry, row) => carry + (toNumber(row.mileageLPer100Km, 0) * toNumber(row.distance_km, 0)),
                0,
            );
            return weightedMileageSum / totalDistance;
        })();
        const estimatedCommuteCost = driveRowsMonth.reduce((carry, row) => carry + toNumber(row.estimated_fuel_cost, 0), 0);
        const estimatedCommuteDistance = driveRowsMonth.reduce((carry, row) => carry + toNumber(row.distance_km, 0), 0);

        return {
            actualFuelSpending,
            estimatedCommuteCost,
            totalFuelLitres,
            avgLPer100,
            estimatedCommuteDistance,
        };
    }

    function renderVehicleSelects() {
        const selects = [document.getElementById('fuelVehicleId'), document.getElementById('commuteVehicleId')];
        selects.forEach((select) => {
            if (!select) return;
            const selected = select.value;
            select.innerHTML = '';
            if (!state.vehicles.length) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Add a vehicle first';
                select.appendChild(option);
                return;
            }
            state.vehicles.forEach((vehicle) => {
                const option = document.createElement('option');
                option.value = vehicle.id;
                option.textContent = vehicle.name;
                select.appendChild(option);
            });
            select.value = state.vehicles.some((v) => v.id === selected) ? selected : state.vehicles[0].id;
        });
    }

    function renderVehicleListRows() {
        const container = document.getElementById('vehicleListCards');
        if (!container) return;

        container.innerHTML = '';
        if (!state.vehicles.length) {
            container.innerHTML = '<div class="text-center text-secondary py-3">No vehicles added yet.</div>';
            return;
        }

        state.vehicles.forEach((vehicle) => {
            const item = document.createElement('div');
            item.className = 'vehicle-list-item';

            const description = String(vehicle.description || '').trim();
            const unitLabel = vehicle.consumption_unit_default === 'KM_PER_L' ? 'km/L' : 'L/100km';

            item.innerHTML = `
                <div class="vehicle-list-card">
                    <div class="vehicle-list-row">
                        <div>
                            <div class="vehicle-list-name">${vehicle.name || '-'}</div>
                            <div class="vehicle-list-description">${description || '&nbsp;'}</div>
                        </div>
                        <div class="vehicle-list-right">
                            <div><strong>Capacity: ${money.format(toNumber(vehicle.tank_capacity_l, 0))} L</strong></div>
                            <div class="vehicle-list-description">Unit: ${unitLabel}</div>
                        </div>
                    </div>
                </div>
                <div class="vehicle-list-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-vehicle-action="edit" data-vehicle-id="${vehicle.id}" aria-label="Edit vehicle" title="Edit" data-bs-title="Edit" data-bs-placement="top">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-vehicle-action="delete" data-vehicle-id="${vehicle.id}" aria-label="Delete vehicle" title="Delete" data-bs-title="Delete" data-bs-placement="top">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                </div>
            `;
            container.appendChild(item);
        });

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            container.querySelectorAll('[data-vehicle-action]').forEach((btn) => {
                const existing = bootstrap.Tooltip.getInstance(btn);
                if (!existing) {
                    new bootstrap.Tooltip(btn, {
                        trigger: 'hover focus',
                    });
                }
            });
        }
    }

    function setVehicleFormMode(isEdit) {
        const saveBtn = document.getElementById('saveVehicleBtn');
        if (!saveBtn) return;
        saveBtn.textContent = isEdit ? 'Update' : 'Save';
    }

    function fillVehicleForm(vehicle) {
        const name = document.getElementById('vehicleName');
        const description = document.getElementById('vehicleDescription');
        const tank = document.getElementById('tankCapacityL');
        const unit = document.getElementById('consumptionUnitDefault');
        if (name) name.value = vehicle?.name || '';
        if (description) description.value = vehicle?.description || '';
        if (tank) tank.value = String(toNumber(vehicle?.tank_capacity_l, 0));
        if (unit) unit.value = vehicle?.consumption_unit_default || 'L_PER_100KM';
    }

    function renderFuelRows() {
        const tbody = document.getElementById('fuelLogRows');
        if (!tbody) return;
        const rows = deriveFuelRows();
        tbody.innerHTML = '';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-secondary py-4">No refuel logs yet.</td></tr>';
            return;
        }

        rows.forEach((row) => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-fuel-log-id', row.id);
            tr.style.cursor = 'pointer';
            if (editingFuelLogId && row.id === editingFuelLogId) {
                tr.classList.add('table-active');
            }
            tr.innerHTML = `
                <td>${formatDateTime(row.fuelled_at)}</td>
                <td>${vehicleName(row.vehicle_id)}</td>
                <td class="text-end">${money.format(toNumber(row.fuel_litres, 0))}</td>
                <td class="text-end">${money.format(toNumber(row.total_amount, 0))}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderDriveRows() {
        const tbody = document.getElementById('commuteLogRows');
        if (!tbody) return;
        const rows = state.commuteLogs
            .map(deriveDriveRow)
            .sort((a, b) => new Date(b.driven_at).getTime() - new Date(a.driven_at).getTime());

        tbody.innerHTML = '';
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-4">No drive logs yet.</td></tr>';
            return;
        }

        rows.forEach((row) => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-commute-log-id', row.id);
            tr.style.cursor = 'pointer';
            if (editingCommuteLogId && row.id === editingCommuteLogId) {
                tr.classList.add('table-active');
            }
            tr.innerHTML = `
                <td>${formatDateTime(row.driven_at)}</td>
                <td>${vehicleName(row.vehicle_id)}</td>
                <td>
                    <div class="log-cell-main">${row.origin} - ${row.destination}</div>
                    <div class="log-cell-sub">${row.commute_type === 'work_commute' ? 'Work Commute' : 'Personal Drive'}</div>
                </td>
                <td class="text-end">${money.format(toNumber(row.distance_km, 0))} km</td>
                <td class="text-end">
                    <div class="log-cell-main">${row.estimated_fuel_litres === null ? '-' : money.format(row.estimated_fuel_litres)} L</div>
                    <div class="log-cell-sub">(${money.format(toNumber(row.mileageLPer100Km, 0))} L/100km)</div>
                </td>
                <td class="log-cell-cost">
                    <div class="log-cell-main">${row.estimated_fuel_cost === null ? '-' : `RM ${money.format(row.estimated_fuel_cost)}`}</div>
                    <div class="log-cell-sub">${row.estimated_cost_per_km === null ? '-' : `(RM ${money.format(row.estimated_cost_per_km)}/km)`}</div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderDashboard() {
        const summary = thisMonthSummary();
        const wrap = document.getElementById('fuelDashboardCards');
        if (!wrap) return;

        const items = [
            { label: "This Month's Fuel Spending", value: `RM ${money.format(summary.actualFuelSpending)}` },
            { label: "This Month's Estimated Drive Cost", value: `RM ${money.format(summary.estimatedCommuteCost)}` },
            { label: 'Total Fuel Litres Logged', value: `${money.format(summary.totalFuelLitres)} L` },
            { label: 'Average Mileage', value: `${summary.avgLPer100 === null ? '-' : money.format(summary.avgLPer100)} L/100KM` },
            { label: 'Commute Distance', value: `${money.format(summary.estimatedCommuteDistance)} KM` },
        ];

        wrap.innerHTML = '';
        items.forEach((item) => {
            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4';
            col.innerHTML = `
                <div class="dashboard-card">
                    <div class="k-label">${item.label}</div>
                    <div class="k-value">${item.value}</div>
                </div>
            `;
            wrap.appendChild(col);
        });
    }

    function renderAll() {
        renderVehicleListRows();
        renderVehicleSelects();
        renderFuelRows();
        renderDriveRows();
        renderDashboard();
        renderFuelFormMode();
        renderDriveFormMode();
    }

    function activateInputTab(tabButtonId) {
        const tabButton = document.getElementById(tabButtonId);
        if (!tabButton) return;

        if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance(tabButton).show();
            return;
        }

        tabButton.click();
    }

    function setFuelFormValues(log) {
        const dateTime = splitIsoDateTime(log?.fuelled_at || '');

        const vehicle = document.getElementById('fuelVehicleId');
        const odometer = document.getElementById('fuelOdometerKm');
        const litres = document.getElementById('fuelLitres');
        const mode = document.getElementById('fuelPriceMode');
        const price = document.getElementById('fuelPricePerLitre');
        const date = document.getElementById('fuelledAtDate');
        const time = document.getElementById('fuelledAtTime');
        const location = document.getElementById('fuelLocation');
        const notes = document.getElementById('fuelNote');

        if (vehicle) vehicle.value = log?.vehicle_id || (state.vehicles[0]?.id || '');
        if (odometer) odometer.value = String(toNumber(log?.odometer_km, 0));
        if (litres) litres.value = String(toNumber(log?.fuel_litres, 0));
        if (mode) mode.value = log?.fuel_price_mode || 'budi95';
        if (price) price.value = String(toNumber(log?.price_per_litre, PRICE_BUDI95));
        if (date) date.value = dateTime.date;
        if (time) time.value = dateTime.time;
        if (location) location.value = log?.location || '';
        if (notes) notes.value = log?.notes || '';

        if (date?._flatpickr) {
            date._flatpickr.setDate(dateTime.date, true, 'Y-m-d');
        }
        if (time?._flatpickr) {
            time._flatpickr.setDate(dateTime.time, true, 'H:i');
        }

        recomputeFuelTotal();
    }

    function clearFuelForm() {
        editingFuelLogId = null;
        setFuelFormValues(null);
    }

    function renderFuelFormMode() {
        const addWrap = document.getElementById('fuelAddButtonWrap');
        const editWrap = document.getElementById('fuelEditButtonWrap');
        const deleteWrap = document.getElementById('fuelDeleteButtonWrap');
        if (!addWrap || !editWrap || !deleteWrap) return;

        if (editingFuelLogId) {
            addWrap.classList.add('d-none');
            editWrap.classList.remove('d-none');
            deleteWrap.classList.remove('d-none');
            return;
        }

        addWrap.classList.remove('d-none');
        editWrap.classList.add('d-none');
        deleteWrap.classList.add('d-none');
    }

    function setDriveFormValues(log) {
        const dateTime = splitIsoDateTime(log?.driven_at || '');

        const vehicle = document.getElementById('commuteVehicleId');
        const type = document.getElementById('commuteType');
        const origin = document.getElementById('commuteOrigin');
        const destination = document.getElementById('commuteDestination');
        const distance = document.getElementById('commuteDistanceKm');
        const consumption = document.getElementById('commuteConsumptionValue');
        const date = document.getElementById('commuteDate');
        const time = document.getElementById('commuteTime');
        const note = document.getElementById('commuteNote');

        if (vehicle) vehicle.value = log?.vehicle_id || (state.vehicles[0]?.id || '');
        if (type) type.value = log?.commute_type || 'work_commute';
        if (origin) origin.value = log?.origin || 'Home';
        if (destination) destination.value = log?.destination || 'Work';
        if (distance) distance.value = String(toNumber(log?.distance_km, 0));
        if (consumption) consumption.value = String(toNumber(log?.consumption_value, 0));
        if (date) date.value = dateTime.date;
        if (time) time.value = dateTime.time;
        if (note) note.value = log?.notes || '';

        if (date?._flatpickr) {
            date._flatpickr.setDate(dateTime.date, true, 'Y-m-d');
        }
        if (time?._flatpickr) {
            time._flatpickr.setDate(dateTime.time, true, 'H:i');
        }
    }

    function clearDriveForm() {
        editingCommuteLogId = null;
        setDriveFormValues(null);
    }

    function renderDriveFormMode() {
        const addWrap = document.getElementById('driveAddButtonWrap');
        const editWrap = document.getElementById('driveEditButtonWrap');
        const deleteWrap = document.getElementById('driveDeleteButtonWrap');
        if (!addWrap || !editWrap || !deleteWrap) return;

        if (editingCommuteLogId) {
            addWrap.classList.add('d-none');
            editWrap.classList.remove('d-none');
            deleteWrap.classList.remove('d-none');
            return;
        }

        addWrap.classList.remove('d-none');
        editWrap.classList.add('d-none');
        deleteWrap.classList.add('d-none');
    }

    function defaultDateValue() {
        return new Date().toISOString().slice(0, 10);
    }

    function defaultTimeValue() {
        return new Date().toTimeString().slice(0, 5);
    }

    function wireVehicleSave() {
        const btn = document.getElementById('saveVehicleBtn');
        if (!btn) return;
        btn.addEventListener('click', async () => {
            const name = String(document.getElementById('vehicleName')?.value || '').trim();
            if (!name) {
                setStatus('Vehicle name is required.', true);
                return;
            }

            const vehiclePayload = {
                name,
                description: String(document.getElementById('vehicleDescription')?.value || '').trim(),
                tank_capacity_l: toNumber(document.getElementById('tankCapacityL')?.value, 0),
                consumption_unit_default: String(document.getElementById('consumptionUnitDefault')?.value || 'L_PER_100KM'),
            };

            try {
                if (editingVehicleId) {
                    const endpoint = `${config.vehiclesBaseUrl}/${encodeURIComponent(editingVehicleId)}`;
                    const snapshot = await apiRequest('PUT', endpoint, vehiclePayload);
                    applySnapshot(snapshot);
                    editingVehicleId = null;
                    setVehicleFormMode(false);
                    fillVehicleForm(null);
                    setStatus('Vehicle updated.');
                    return;
                }

                const snapshot = await apiRequest('POST', config.vehiclesEndpoint, vehiclePayload);
                applySnapshot(snapshot);
                fillVehicleForm(null);
                setStatus('Vehicle saved.');
            } catch (error) {
                setStatus(error.message || 'Failed to save vehicle.', true);
            }
        });
    }

    function wireVehicleCardActions() {
        const container = document.getElementById('vehicleListCards');
        if (!container) return;

        container.addEventListener('click', async (event) => {
            const button = event.target.closest('button[data-vehicle-action]');
            if (!button) return;

            const action = button.getAttribute('data-vehicle-action');
            const vehicleId = button.getAttribute('data-vehicle-id');
            if (!vehicleId) return;

            if (action === 'edit') {
                const vehicle = state.vehicles.find((item) => item.id === vehicleId);
                if (!vehicle) return;
                editingVehicleId = vehicleId;
                fillVehicleForm(vehicle);
                setVehicleFormMode(true);
                setStatus('Editing vehicle.');
                return;
            }

            if (action === 'delete') {
                try {
                    const endpoint = `${config.vehiclesBaseUrl}/${encodeURIComponent(vehicleId)}`;
                    const snapshot = await apiRequest('DELETE', endpoint);
                    applySnapshot(snapshot);
                    if (editingVehicleId === vehicleId) {
                        editingVehicleId = null;
                        setVehicleFormMode(false);
                        fillVehicleForm(null);
                    }
                    setStatus('Vehicle deleted.');
                } catch (error) {
                    setStatus(error.message || 'Failed to delete vehicle.', true);
                }
            }
        });
    }

    function wireFuelSave() {
        const btn = document.getElementById('addFuelLogBtn');
        if (!btn) return;
        btn.addEventListener('click', async () => {
            const vehicleId = String(document.getElementById('fuelVehicleId')?.value || '');
            if (!vehicleId) {
                setStatus('Add and select a vehicle first.', true);
                return;
            }

            const date = String(document.getElementById('fuelledAtDate')?.value || '');
            const time = String(document.getElementById('fuelledAtTime')?.value || '');
            const odometer = toNumber(document.getElementById('fuelOdometerKm')?.value, -1);
            const litres = toNumber(document.getElementById('fuelLitres')?.value, 0);
            const fuelType = String(document.getElementById('fuelPriceMode')?.value || 'budi95');
            const price = resolveFuelPriceByType(fuelType, toNumber(document.getElementById('fuelPricePerLitre')?.value, 0));
            const total = Number((litres * price).toFixed(2));

            if (!date || !time) {
                setStatus('Fuel date and time are required.', true);
                return;
            }
            if (odometer < 0 || litres <= 0 || price < 0) {
                setStatus('Please provide valid odometer/litres/price values.', true);
                return;
            }

            const payload = {
                vehicle_id: Number(vehicleId),
                odometer_km: odometer,
                fuel_litres: litres,
                fuel_price_mode: fuelType,
                price_per_litre: price,
                total_amount: total,
                fuelled_at: `${date}T${time}:00`,
                location: String(document.getElementById('fuelLocation')?.value || '').trim(),
                notes: String(document.getElementById('fuelNote')?.value || '').trim(),
            };

            try {
                const snapshot = await apiRequest('POST', config.fuelLogsEndpoint, payload);
                applySnapshot(snapshot);
                setStatus('Refuel log added.');
            } catch (error) {
                setStatus(error.message || 'Failed to add refuel log.', true);
            }
        });
    }

    function wireFuelRowSelection() {
        const tbody = document.getElementById('fuelLogRows');
        if (!tbody) return;

        tbody.addEventListener('click', (event) => {
            const row = event.target.closest('tr[data-fuel-log-id]');
            if (!row) return;

            const fuelLogId = row.getAttribute('data-fuel-log-id');
            const log = state.fuelLogs.find((item) => item.id === fuelLogId);
            if (!log) return;

            activateInputTab('tab-fuel-entry');
            editingFuelLogId = fuelLogId;
            setFuelFormValues(log);
            renderAll();
            setStatus('Editing refuel log.');
        });
    }

    function wireFuelEditActions() {
        const editBtn = document.getElementById('editFuelLogBtn');
        const deleteBtn = document.getElementById('deleteFuelLogBtn');
        if (!editBtn || !deleteBtn) return;

        editBtn.addEventListener('click', async () => {
            if (!editingFuelLogId) return;

            const vehicleId = String(document.getElementById('fuelVehicleId')?.value || '');
            const date = String(document.getElementById('fuelledAtDate')?.value || '');
            const time = String(document.getElementById('fuelledAtTime')?.value || '');
            const odometer = toNumber(document.getElementById('fuelOdometerKm')?.value, -1);
            const litres = toNumber(document.getElementById('fuelLitres')?.value, 0);
            const fuelType = String(document.getElementById('fuelPriceMode')?.value || 'budi95');
            const price = resolveFuelPriceByType(fuelType, toNumber(document.getElementById('fuelPricePerLitre')?.value, 0));
            const total = Number((litres * price).toFixed(2));

            if (!vehicleId || !date || !time || odometer < 0 || litres <= 0 || price < 0) {
                setStatus('Please provide valid refuel inputs.', true);
                return;
            }

            const payload = {
                vehicle_id: Number(vehicleId),
                odometer_km: odometer,
                fuel_litres: litres,
                fuel_price_mode: fuelType,
                price_per_litre: price,
                total_amount: total,
                fuelled_at: `${date}T${time}:00`,
                location: String(document.getElementById('fuelLocation')?.value || '').trim(),
                notes: String(document.getElementById('fuelNote')?.value || '').trim(),
            };

            try {
                const endpoint = `${config.fuelLogsBaseUrl}/${encodeURIComponent(editingFuelLogId)}`;
                const snapshot = await apiRequest('PUT', endpoint, payload);
                applySnapshot(snapshot);
                clearFuelForm();
                renderAll();
                setStatus('Refuel log updated.');
            } catch (error) {
                setStatus(error.message || 'Failed to update refuel log.', true);
            }
        });

        deleteBtn.addEventListener('click', async () => {
            if (!editingFuelLogId) return;

            try {
                const endpoint = `${config.fuelLogsBaseUrl}/${encodeURIComponent(editingFuelLogId)}`;
                const snapshot = await apiRequest('DELETE', endpoint);
                applySnapshot(snapshot);
                clearFuelForm();
                renderAll();
                setStatus('Refuel log deleted.');
            } catch (error) {
                setStatus(error.message || 'Failed to delete refuel log.', true);
            }
        });
    }

    function wireDriveSave() {
        const btn = document.getElementById('addCommuteLogBtn');
        if (!btn) return;
        btn.addEventListener('click', async () => {
            const vehicleId = String(document.getElementById('commuteVehicleId')?.value || '');
            if (!vehicleId) {
                setStatus('Add and select a vehicle first.', true);
                return;
            }

            const driveDate = String(document.getElementById('commuteDate')?.value || '');
            const driveTime = String(document.getElementById('commuteTime')?.value || '');
            const distance = toNumber(document.getElementById('commuteDistanceKm')?.value, 0);
            const mileage = toNumber(document.getElementById('commuteConsumptionValue')?.value, 0);

            if (!driveDate || !driveTime) {
                setStatus('Drive date and time are required.', true);
                return;
            }
            if (distance <= 0 || mileage <= 0) {
                setStatus('Please provide valid drive inputs.', true);
                return;
            }

            const payload = {
                vehicle_id: Number(vehicleId),
                commute_type: String(document.getElementById('commuteType')?.value || 'personal_drive'),
                origin: String(document.getElementById('commuteOrigin')?.value || '').trim() || 'Origin',
                destination: String(document.getElementById('commuteDestination')?.value || '').trim() || 'Destination',
                distance_km: distance,
                consumption_value: mileage,
                consumption_unit: 'L_PER_100KM',
                driven_at: `${driveDate}T${driveTime}:00`,
                notes: String(document.getElementById('commuteNote')?.value || '').trim(),
            };

            try {
                const snapshot = await apiRequest('POST', config.commuteLogsEndpoint, payload);
                applySnapshot(snapshot);
                setStatus('Drive log added.');
            } catch (error) {
                setStatus(error.message || 'Failed to add drive log.', true);
            }
        });
    }

    function wireDriveRowSelection() {
        const tbody = document.getElementById('commuteLogRows');
        if (!tbody) return;

        tbody.addEventListener('click', (event) => {
            const row = event.target.closest('tr[data-commute-log-id]');
            if (!row) return;

            const commuteLogId = row.getAttribute('data-commute-log-id');
            const log = state.commuteLogs.find((item) => item.id === commuteLogId);
            if (!log) return;

            activateInputTab('tab-drive-entry');
            editingCommuteLogId = commuteLogId;
            setDriveFormValues(log);
            renderAll();
            setStatus('Editing drive log.');
        });
    }

    function wireDriveEditActions() {
        const editBtn = document.getElementById('editCommuteLogBtn');
        const deleteBtn = document.getElementById('deleteCommuteLogBtn');
        if (!editBtn || !deleteBtn) return;

        editBtn.addEventListener('click', async () => {
            if (!editingCommuteLogId) return;

            const vehicleId = String(document.getElementById('commuteVehicleId')?.value || '');
            const driveDate = String(document.getElementById('commuteDate')?.value || '');
            const driveTime = String(document.getElementById('commuteTime')?.value || '');
            const distance = toNumber(document.getElementById('commuteDistanceKm')?.value, 0);
            const mileage = toNumber(document.getElementById('commuteConsumptionValue')?.value, 0);

            if (!vehicleId || !driveDate || !driveTime || distance <= 0 || mileage <= 0) {
                setStatus('Please provide valid drive inputs.', true);
                return;
            }

            const payload = {
                vehicle_id: Number(vehicleId),
                commute_type: String(document.getElementById('commuteType')?.value || 'personal_drive'),
                origin: String(document.getElementById('commuteOrigin')?.value || '').trim() || 'Origin',
                destination: String(document.getElementById('commuteDestination')?.value || '').trim() || 'Destination',
                distance_km: distance,
                consumption_value: mileage,
                consumption_unit: 'L_PER_100KM',
                driven_at: `${driveDate}T${driveTime}:00`,
                notes: String(document.getElementById('commuteNote')?.value || '').trim(),
            };

            try {
                const endpoint = `${config.commuteLogsBaseUrl}/${encodeURIComponent(editingCommuteLogId)}`;
                const snapshot = await apiRequest('PUT', endpoint, payload);
                applySnapshot(snapshot);
                clearDriveForm();
                renderAll();
                setStatus('Drive log updated.');
            } catch (error) {
                setStatus(error.message || 'Failed to update drive log.', true);
            }
        });

        deleteBtn.addEventListener('click', async () => {
            if (!editingCommuteLogId) return;

            try {
                const endpoint = `${config.commuteLogsBaseUrl}/${encodeURIComponent(editingCommuteLogId)}`;
                const snapshot = await apiRequest('DELETE', endpoint);
                applySnapshot(snapshot);
                clearDriveForm();
                renderAll();
                setStatus('Drive log deleted.');
            } catch (error) {
                setStatus(error.message || 'Failed to delete drive log.', true);
            }
        });
    }

    function wireFuelPriceModeHelpers() {
        const fuelMode = document.getElementById('fuelPriceMode');
        const fuelPrice = document.getElementById('fuelPricePerLitre');
        if (!fuelMode || !fuelPrice) return;

        fuelMode.addEventListener('change', () => {
            if (fuelMode.value === 'budi95') {
                fuelPrice.value = String(PRICE_BUDI95);
                return;
            }
            if (fuelMode.value === 'ron95' && toNumber(fuelPrice.value, 0) <= 0) {
                fuelPrice.value = String(PRICE_RON95);
            }
            recomputeFuelTotal();
        });
    }

    function recomputeFuelTotal() {
        const litresInput = document.getElementById('fuelLitres');
        const priceInput = document.getElementById('fuelPricePerLitre');
        const totalInput = document.getElementById('fuelTotalAmount');
        if (!litresInput || !priceInput || !totalInput) return;

        const litres = toNumber(litresInput.value, 0);
        const price = toNumber(priceInput.value, 0);
        const total = Number((litres * price).toFixed(2));
        totalInput.value = String(total);
    }

    function wireFuelTotalAutoCalculation() {
        const litresInput = document.getElementById('fuelLitres');
        const priceInput = document.getElementById('fuelPricePerLitre');
        if (litresInput) {
            litresInput.addEventListener('input', recomputeFuelTotal);
            litresInput.addEventListener('change', recomputeFuelTotal);
        }
        if (priceInput) {
            priceInput.addEventListener('input', recomputeFuelTotal);
            priceInput.addEventListener('change', recomputeFuelTotal);
        }
        recomputeFuelTotal();
    }

    function initDefaults() {
        const fuelDate = document.getElementById('fuelledAtDate');
        const fuelTime = document.getElementById('fuelledAtTime');
        const driveDate = document.getElementById('commuteDate');
        const driveTime = document.getElementById('commuteTime');
        const fuelMode = document.getElementById('fuelPriceMode');
        const fuelPrice = document.getElementById('fuelPricePerLitre');

        if (fuelDate && !fuelDate.value) fuelDate.value = defaultDateValue();
        if (fuelTime && !fuelTime.value) fuelTime.value = defaultTimeValue();
        if (driveDate && !driveDate.value) driveDate.value = defaultDateValue();
        if (driveTime && !driveTime.value) driveTime.value = defaultTimeValue();

        if (fuelTime) fuelTime.setAttribute('placeholder', 'HH:MM');
        if (driveTime) driveTime.setAttribute('placeholder', 'HH:MM');

        if (fuelMode && fuelPrice && fuelMode.value === 'budi95' && toNumber(fuelPrice.value, 0) <= 0) {
            fuelPrice.value = String(PRICE_BUDI95);
        }
        recomputeFuelTotal();
    }

    function initFuelInputTabUI() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('#fuelInputTabs [data-bs-toggle="tab"]').forEach((el) => {
                const existing = bootstrap.Tooltip.getInstance(el);
                if (!existing) {
                    new bootstrap.Tooltip(el, {
                        trigger: 'hover focus',
                    });
                }
            });
        }

        document.querySelectorAll('#fuelInputTabs [data-bs-toggle="tab"]').forEach((tabButton) => {
            tabButton.addEventListener('shown.bs.tab', () => {
                document.querySelectorAll('#fuelInputTabs .projection-input-tab').forEach((btn) => {
                    btn.classList.toggle('active', btn === tabButton);
                });
            });
        });
    }

    function initDatePickers() {
        if (typeof flatpickr === 'undefined') return;

        document.querySelectorAll('.date-picker').forEach((input) => {
            if (input._flatpickr) {
                input._flatpickr.destroy();
            }

            flatpickr(input, {
                altInput: true,
                altFormat: 'd/m/Y',
                dateFormat: 'Y-m-d',
                allowInput: false,
                defaultDate: input.value || null,
            });
        });

        ['fuelledAtTime', 'commuteTime'].forEach((id) => {
            const input = document.getElementById(id);
            if (!input) return;
            if (input._flatpickr) {
                input._flatpickr.destroy();
            }

            flatpickr(input, {
                enableTime: true,
                noCalendar: true,
                time_24hr: true,
                dateFormat: 'H:i',
                allowInput: false,
                defaultDate: input.value || null,
            });
        });
    }

    async function init() {
        wireVehicleSave();
        wireVehicleCardActions();
        wireFuelSave();
        wireFuelRowSelection();
        wireFuelEditActions();
        wireDriveSave();
        wireDriveRowSelection();
        wireDriveEditActions();
        wireFuelPriceModeHelpers();
        wireFuelTotalAutoCalculation();
        initDefaults();
        initDatePickers();
        initFuelInputTabUI();

        try {
            await refreshSnapshot();
        } catch (error) {
            renderAll();
            setStatus(error.message || 'Unable to load transportation data.', true);
        }
    }

    init();
})();
