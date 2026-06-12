<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\TransportationCommuteLog;
use App\Models\TransportationFuelLog;
use App\Models\TransportationParkingLog;
use App\Models\TransportationVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransportationLogController extends Controller
{
    public function index(): View
    {
        return view('transportation-log', [
            'theme' => Setting::getValue('theme', 'light'),
        ]);
    }

    public function snapshot(): JsonResponse
    {
        return response()->json($this->snapshotData());
    }

    public function storeVehicle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'tank_capacity_l' => ['required', 'numeric', 'min:0'],
            'consumption_unit_default' => ['required', Rule::in(['L_PER_100KM', 'KM_PER_L'])],
        ]);

        TransportationVehicle::query()->create($validated);

        return response()->json($this->snapshotData());
    }

    public function updateVehicle(Request $request, TransportationVehicle $vehicle): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'tank_capacity_l' => ['required', 'numeric', 'min:0'],
            'consumption_unit_default' => ['required', Rule::in(['L_PER_100KM', 'KM_PER_L'])],
        ]);

        $vehicle->update($validated);

        return response()->json($this->snapshotData());
    }

    public function destroyVehicle(TransportationVehicle $vehicle): JsonResponse
    {
        $vehicle->delete();

        return response()->json($this->snapshotData());
    }

    public function storeFuelLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'exists:transportation_vehicles,id'],
            'odometer_km' => ['required', 'numeric', 'min:0'],
            'fuel_litres' => ['required', 'numeric', 'min:0.001'],
            'fuel_price_mode' => ['required', Rule::in(['budi95', 'ron95'])],
            'price_per_litre' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'fuelled_at' => ['required', 'date_format:Y-m-d\TH:i:s'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        TransportationFuelLog::query()->create($validated);

        return response()->json($this->snapshotData());
    }

    public function updateFuelLog(Request $request, TransportationFuelLog $fuelLog): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'exists:transportation_vehicles,id'],
            'odometer_km' => ['required', 'numeric', 'min:0'],
            'fuel_litres' => ['required', 'numeric', 'min:0.001'],
            'fuel_price_mode' => ['required', Rule::in(['budi95', 'ron95'])],
            'price_per_litre' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'fuelled_at' => ['required', 'date_format:Y-m-d\TH:i:s'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $fuelLog->update($validated);

        return response()->json($this->snapshotData());
    }

    public function destroyFuelLog(TransportationFuelLog $fuelLog): JsonResponse
    {
        $fuelLog->delete();

        return response()->json($this->snapshotData());
    }

    public function storeCommuteLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'exists:transportation_vehicles,id'],
            'commute_type' => ['required', Rule::in(['work_commute', 'personal_drive'])],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'distance_km' => ['required', 'numeric', 'min:0.01'],
            'consumption_value' => ['required', 'numeric', 'min:0.0001'],
            'consumption_unit' => ['required', Rule::in(['L_PER_100KM', 'KM_PER_L'])],
            'driven_at' => ['required', 'date_format:Y-m-d\TH:i:s'],
            'ended_at' => ['required', 'date_format:Y-m-d\TH:i:s', 'after:driven_at'],
            'average_speed_kmh' => ['required', 'numeric', 'min:0'],
            'top_speed_kmh' => ['required', 'numeric', 'min:0', 'gte:average_speed_kmh'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['drive_time_minutes'] = $this->driveTimeMinutes($validated['driven_at'], $validated['ended_at']);

        TransportationCommuteLog::query()->create($validated);

        return response()->json($this->snapshotData());
    }

    public function updateCommuteLog(Request $request, TransportationCommuteLog $commuteLog): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'exists:transportation_vehicles,id'],
            'commute_type' => ['required', Rule::in(['work_commute', 'personal_drive'])],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'distance_km' => ['required', 'numeric', 'min:0.01'],
            'consumption_value' => ['required', 'numeric', 'min:0.0001'],
            'consumption_unit' => ['required', Rule::in(['L_PER_100KM', 'KM_PER_L'])],
            'driven_at' => ['required', 'date_format:Y-m-d\TH:i:s'],
            'ended_at' => ['required', 'date_format:Y-m-d\TH:i:s', 'after:driven_at'],
            'average_speed_kmh' => ['required', 'numeric', 'min:0'],
            'top_speed_kmh' => ['required', 'numeric', 'min:0', 'gte:average_speed_kmh'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['drive_time_minutes'] = $this->driveTimeMinutes($validated['driven_at'], $validated['ended_at']);

        $commuteLog->update($validated);

        return response()->json($this->snapshotData());
    }

    public function destroyCommuteLog(TransportationCommuteLog $commuteLog): JsonResponse
    {
        $commuteLog->delete();

        return response()->json($this->snapshotData());
    }

    public function storeParkingLog(Request $request): JsonResponse
    {
        $validated = $this->validateParkingLog($request);

        TransportationParkingLog::query()->create($validated);

        return response()->json($this->snapshotData());
    }

    public function updateParkingLog(Request $request, TransportationParkingLog $parkingLog): JsonResponse
    {
        $validated = $this->validateParkingLog($request);

        $parkingLog->update($validated);

        return response()->json($this->snapshotData());
    }

    public function destroyParkingLog(TransportationParkingLog $parkingLog): JsonResponse
    {
        $parkingLog->delete();

        return response()->json($this->snapshotData());
    }

    private function snapshotData(): array
    {
        $vehicles = TransportationVehicle::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $fuelLogs = TransportationFuelLog::query()
            ->latest('fuelled_at')
            ->latest('id')
            ->get();

        $commuteLogs = TransportationCommuteLog::query()
            ->latest('driven_at')
            ->latest('id')
            ->get();

        $parkingLogs = TransportationParkingLog::query()
            ->latest('parking_date')
            ->latest('id')
            ->get();

        return [
            'vehicles' => $vehicles,
            'fuelLogs' => $fuelLogs,
            'commuteLogs' => $commuteLogs,
            'parkingLogs' => $parkingLogs,
        ];
    }

    private function validateParkingLog(Request $request): array
    {
        $validated = $request->validate([
            'parking_type' => ['required', Rule::in(['casual', 'monthly_pass'])],
            'location' => ['required', 'string', 'max:255'],
            'parking_date' => ['required', 'date_format:Y-m-d'],
            'billing_month' => ['nullable', 'date_format:Y-m-d'],
            'start_hour' => ['nullable', 'integer', 'min:0', 'max:23', 'required_if:parking_type,casual'],
            'end_hour' => ['nullable', 'integer', 'min:1', 'max:24', 'required_if:parking_type,casual'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validated['parking_type'] === 'casual' && (int) $validated['end_hour'] <= (int) $validated['start_hour']) {
            throw ValidationException::withMessages([
                'end_hour' => 'Parking end hour must be after start hour.',
            ]);
        }

        if ($validated['parking_type'] === 'monthly_pass' && empty($validated['billing_month'])) {
            $validated['billing_month'] = date('Y-m-01', strtotime($validated['parking_date']));
        }

        if ($validated['parking_type'] === 'casual') {
            $validated['billing_month'] = null;
        } else {
            $validated['start_hour'] = null;
            $validated['end_hour'] = null;
        }

        return $validated;
    }

    private function driveTimeMinutes(string $startedAt, string $endedAt): int
    {
        $seconds = strtotime($endedAt) - strtotime($startedAt);

        return max(1, (int) round($seconds / 60));
    }
}
