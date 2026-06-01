(function () {
    const STORAGE_KEY = 'fuel_log_state_v1';
    const PRICE_BUDI95 = 1.99;
    const PRICE_RON95 = 2.05;

    const money = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let statusTimer = null;

    const state = loadState();

    function loadState() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return { vehicles: [], fuelLogs: [], commuteLogs: [], monthlyTransportBudget: 250 };
            }
            const parsed = JSON.parse(raw);
            return {
                vehicles: Array.isArray(parsed.vehicles) ? parsed.vehicles : [],
                fuelLogs: Array.isArray(parsed.fuelLogs) ? parsed.fuelLogs : [],
                commuteLogs: Array.isArray(parsed.commuteLogs) ? parsed.commuteLogs : [],
                monthlyTransportBudget: Number(parsed.monthlyTransportBudget) || 250,
            };
        } catch (_e) {
            return { vehicles: [], fuelLogs: [], commuteLogs: [], monthlyTransportBudget: 250 };
        }
    }

    function saveState() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    }

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

    function uid() {
        return `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
    }

    function monthKeyFromDate(dateLike) {
        const d = new Date(dateLike);
        if (Number.isNaN(d.getTime())) return '';
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    }

    function vehicleName(vehicleId) {
        const vehicle = state.vehicles.find((v) => v.id === vehicleId);
        return vehicle ? vehicle.name : 'Unknown';
    }

    function formatDateTime(dateLike) {
        const d = new Date(dateLike);
        if (Number.isNaN(d.getTime())) return '-';
        return d.toLocaleString('en-MY', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
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
            .filter((row) => row.vehicle_id === vehicleId)
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
        if (row.commute_date) return `${row.commute_date}T00:00:00`;
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
                    reliability_status: current.is_full_tank ? 'more_reliable_full_tank_based' : 'estimated',
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
        const now = new Date();
        const elapsedDays = now.getDate();
        const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();

        const fuelRowsMonth = fuelRows.filter((row) => monthKeyFromDate(row.fuelled_at) === month);
        const driveRowsMonth = driveRows.filter((row) => monthKeyFromDate(row.driven_at) === month);

        const actualFuelSpending = fuelRowsMonth.reduce((carry, row) => carry + toNumber(row.total_amount, 0), 0);
        const totalFuelLitres = fuelRowsMonth.reduce((carry, row) => carry + toNumber(row.fuel_litres, 0), 0);
        const avgLPer100 = (() => {
            const eligible = fuelRowsMonth.filter((row) => row.estimated_l_per_100km !== null);
            if (!eligible.length) return null;
            const sum = eligible.reduce((carry, row) => carry + toNumber(row.estimated_l_per_100km, 0), 0);
            return sum / eligible.length;
        })();
        const estimatedCommuteCost = driveRowsMonth.reduce((carry, row) => carry + toNumber(row.estimated_fuel_cost, 0), 0);
        const estimatedCommuteDistance = driveRowsMonth.reduce((carry, row) => carry + toNumber(row.distance_km, 0), 0);
        const budgetRemaining = toNumber(state.monthlyTransportBudget, 0) - actualFuelSpending;
        const projectedMonthEndFuelCost = elapsedDays > 0 ? (actualFuelSpending / elapsedDays) * daysInMonth : 0;

        return {
            actualFuelSpending,
            estimatedCommuteCost,
            totalFuelLitres,
            avgLPer100,
            estimatedCommuteDistance,
            budgetRemaining,
            projectedMonthEndFuelCost,
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

    function renderFuelRows() {
        const tbody = document.getElementById('fuelLogRows');
        if (!tbody) return;
        const rows = deriveFuelRows();
        tbody.innerHTML = '';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">No fuel logs yet.</td></tr>';
            return;
        }

        rows.forEach((row) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${formatDateTime(row.fuelled_at)}</td>
                <td>${vehicleName(row.vehicle_id)}</td>
                <td class="text-end">${money.format(toNumber(row.fuel_litres, 0))}</td>
                <td class="text-end">${money.format(toNumber(row.total_amount, 0))}</td>
                <td class="text-end">${row.distance_km === null ? '-' : money.format(row.distance_km)}</td>
                <td class="text-end">${row.estimated_l_per_100km === null ? '-' : money.format(row.estimated_l_per_100km)}</td>
                <td class="text-end">${row.estimated_km_per_l === null ? '-' : money.format(row.estimated_km_per_l)}</td>
                <td class="text-end">${row.cost_per_km === null ? '-' : money.format(row.cost_per_km)}</td>
                <td><span class="status-pill">${row.reliability_status === 'estimated' ? 'Estimated' : 'Full-tank based'}</span></td>
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
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-secondary py-4">No drive logs yet.</td></tr>';
            return;
        }

        rows.forEach((row) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${formatDateTime(row.driven_at)}</td>
                <td>${vehicleName(row.vehicle_id)}</td>
                <td>${row.origin} → ${row.destination}</td>
                <td class="text-end">${money.format(toNumber(row.distance_km, 0))}</td>
                <td class="text-end">${row.estimated_fuel_litres === null ? '-' : money.format(row.estimated_fuel_litres)}</td>
                <td class="text-end">${row.estimated_fuel_cost === null ? '-' : money.format(row.estimated_fuel_cost)}</td>
                <td class="text-end">${row.estimated_cost_per_km === null ? '-' : money.format(row.estimated_cost_per_km)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderDashboard() {
        const summary = thisMonthSummary();
        const wrap = document.getElementById('fuelDashboardCards');
        const actualFuelSpendingValue = document.getElementById('actualFuelSpendingValue');
        const estimatedCommuteCostValue = document.getElementById('estimatedCommuteCostValue');
        if (!wrap || !actualFuelSpendingValue || !estimatedCommuteCostValue) return;

        actualFuelSpendingValue.textContent = `RM ${money.format(summary.actualFuelSpending)}`;
        estimatedCommuteCostValue.textContent = `RM ${money.format(summary.estimatedCommuteCost)}`;

        const items = [
            { label: "This Month's Fuel Spending", value: `RM ${money.format(summary.actualFuelSpending)}` },
            { label: "This Month's Estimated Drive Cost", value: `RM ${money.format(summary.estimatedCommuteCost)}` },
            { label: 'Total Fuel Litres Logged', value: money.format(summary.totalFuelLitres) },
            { label: 'Average L/100km', value: summary.avgLPer100 === null ? '-' : money.format(summary.avgLPer100) },
            { label: 'Estimated Commute Distance (km)', value: money.format(summary.estimatedCommuteDistance) },
            { label: 'Fuel Budget Remaining', value: `RM ${money.format(summary.budgetRemaining)}` },
            { label: 'Projected Month-End Fuel Cost', value: `RM ${money.format(summary.projectedMonthEndFuelCost)}` },
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
        renderVehicleSelects();
        renderFuelRows();
        renderDriveRows();
        renderDashboard();
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
        btn.addEventListener('click', () => {
            const name = String(document.getElementById('vehicleName')?.value || '').trim();
            if (!name) {
                setStatus('Vehicle name is required.', true);
                return;
            }
            const vehicle = {
                id: uid(),
                name,
                description: String(document.getElementById('vehicleDescription')?.value || '').trim(),
                tank_capacity_l: toNumber(document.getElementById('tankCapacityL')?.value, 0),
                consumption_unit_default: String(document.getElementById('consumptionUnitDefault')?.value || 'L_PER_100KM'),
            };
            state.vehicles.push(vehicle);
            saveState();
            renderAll();
            setStatus('Vehicle saved.');
        });
    }

    function wireFuelSave() {
        const btn = document.getElementById('addFuelLogBtn');
        if (!btn) return;
        btn.addEventListener('click', () => {
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
            let total = toNumber(document.getElementById('fuelTotalAmount')?.value, 0);

            if (!date || !time) {
                setStatus('Fuel date and time are required.', true);
                return;
            }
            if (odometer < 0 || litres <= 0 || price < 0) {
                setStatus('Please provide valid odometer/litres/price values.', true);
                return;
            }

            if (total <= 0) {
                total = litres * price;
            }

            state.fuelLogs.push({
                id: uid(),
                vehicle_id: vehicleId,
                odometer_km: odometer,
                fuel_litres: litres,
                fuel_price_mode: fuelType,
                price_per_litre: price,
                total_amount: total,
                is_full_tank: Boolean(document.getElementById('fuelIsFullTank')?.checked),
                fuelled_at: `${date}T${time}:00`,
                location: String(document.getElementById('fuelLocation')?.value || '').trim(),
                notes: String(document.getElementById('fuelNote')?.value || '').trim(),
            });

            saveState();
            renderAll();
            setStatus('Fuel log added.');
        });
    }

    function wireDriveSave() {
        const btn = document.getElementById('addCommuteLogBtn');
        if (!btn) return;
        btn.addEventListener('click', () => {
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

            state.commuteLogs.push({
                id: uid(),
                vehicle_id: vehicleId,
                commute_type: String(document.getElementById('commuteType')?.value || 'personal_drive'),
                origin: String(document.getElementById('commuteOrigin')?.value || '').trim() || 'Origin',
                destination: String(document.getElementById('commuteDestination')?.value || '').trim() || 'Destination',
                distance_km: distance,
                consumption_value: mileage,
                consumption_unit: 'L_PER_100KM',
                driven_at: `${driveDate}T${driveTime}:00`,
                notes: String(document.getElementById('commuteNote')?.value || '').trim(),
            });

            saveState();
            renderAll();
            setStatus('Drive log added.');
        });
    }

    function wireBudgetSave() {
        const input = document.getElementById('monthlyTransportBudget');
        if (!input) return;
        input.value = money.format(toNumber(state.monthlyTransportBudget, 250));
        input.addEventListener('focus', () => {
            input.value = String(toNumber(state.monthlyTransportBudget, 250));
        });
        input.addEventListener('blur', () => {
            state.monthlyTransportBudget = toNumber(input.value, 0);
            input.value = money.format(state.monthlyTransportBudget);
            saveState();
            renderDashboard();
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
        });
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

        if (fuelMode && fuelPrice && fuelMode.value === 'budi95' && toNumber(fuelPrice.value, 0) <= 0) {
            fuelPrice.value = String(PRICE_BUDI95);
        }
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

    wireVehicleSave();
    wireFuelSave();
    wireDriveSave();
    wireBudgetSave();
    wireFuelPriceModeHelpers();
    initDefaults();
    initFuelInputTabUI();
    renderAll();
})();
