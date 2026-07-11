<?php

namespace App\Services;

use App\Models\TransportationCommuteLog;
use App\Models\TransportationFuelLog;
use App\Models\TransportationParkingLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TransportationExportPayloadBuilder
{
    private const DEFAULT_FUEL_PRICE_PER_LITRE = 1.99;

    public function build(array $selection): array
    {
        $scope = $this->resolveScope($selection);
        $allFuelLogs = TransportationFuelLog::query()
            ->with('vehicle')
            ->orderBy('fuelled_at')
            ->orderBy('id')
            ->get();

        $fuelLogs = $allFuelLogs
            ->filter(fn (TransportationFuelLog $log) => $this->inScope(CarbonImmutable::parse($log->fuelled_at), $scope))
            ->sortByDesc('fuelled_at')
            ->values();

        $commuteLogs = TransportationCommuteLog::query()
            ->with('vehicle')
            ->orderByDesc('driven_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (TransportationCommuteLog $log) => $this->inScope(CarbonImmutable::parse($log->driven_at), $scope))
            ->values();

        $parkingLogs = TransportationParkingLog::query()
            ->orderByDesc('parking_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (TransportationParkingLog $log) => $this->inScope($this->parkingScopeDate($log, $scope['type']), $scope))
            ->values();

        $derivedDriveLogs = $commuteLogs->map(fn (TransportationCommuteLog $log) => $this->deriveDriveLog($log, $allFuelLogs));

        $totalDistance = $derivedDriveLogs
            ->filter(fn (array $log) => $log['mileage'] !== null && $log['distance'] > 0)
            ->sum('distance');
        $weightedMileage = $derivedDriveLogs
            ->filter(fn (array $log) => $log['mileage'] !== null && $log['distance'] > 0)
            ->sum(fn (array $log) => $log['mileage'] * $log['distance']);

        return [
            'period' => [
                'type' => $scope['type'],
                'starts_at' => $scope['start']->format('Y-m-d H:i:s'),
                'ends_before' => $scope['end']?->format('Y-m-d H:i:s'),
            ],
            'headline_stats' => [
                'fuel_spending' => round((float) $fuelLogs->sum('total_amount'), 2),
                'estimated_drive_cost' => round((float) $derivedDriveLogs->sum(fn (array $log) => $log['route_cost'] ?? 0), 2),
                'fuel_litres_refuelled' => round((float) $fuelLogs->sum('fuel_litres'), 3),
                'average_mileage_l_per_100km' => $totalDistance > 0 ? round($weightedMileage / $totalDistance, 4) : 'N/A',
                'commute_distance_km' => round((float) $derivedDriveLogs->sum('distance'), 2),
                'parking_cost' => round((float) $parkingLogs->sum('total_amount'), 2),
            ],
            'refuel_logs' => $fuelLogs->map(fn (TransportationFuelLog $log) => [
                'datetime' => CarbonImmutable::parse($log->fuelled_at)->format('Y-m-d H:i:s'),
                'vehicle' => $this->textOrNotAvailable($log->vehicle?->name),
                'odometer_at_pump_km' => round((float) $log->odometer_km, 2),
                'fuel_price_type' => $this->fuelPriceType($log->fuel_price_mode),
                'location' => $this->textOrNotAvailable($log->location),
                'litres' => round((float) $log->fuel_litres, 3),
                'total_cost' => round((float) $log->total_amount, 2),
            ])->all(),
            'drive_logs' => $derivedDriveLogs->map(fn (array $log) => [
                'date' => CarbonImmutable::parse($log['model']->driven_at)->format('Y-m-d'),
                'start_time' => CarbonImmutable::parse($log['model']->driven_at)->format('H:i'),
                'end_time' => $log['model']->ended_at
                    ? CarbonImmutable::parse($log['model']->ended_at)->format('H:i')
                    : 'N/A',
                'vehicle' => $this->textOrNotAvailable($log['model']->vehicle?->name),
                'route' => $this->routeOrNotAvailable($log['model']),
                'drive_type' => $this->driveType($log['model']->commute_type),
                'distance_km' => round($log['distance'], 2),
                'odometer_reading_km' => $log['model']->final_odometer_km === null
                    ? 'N/A'
                    : round((float) $log['model']->final_odometer_km, 2),
                'mileage_l_per_100km' => $log['mileage'] === null ? 'N/A' : round($log['mileage'], 4),
                'route_cost' => $log['route_cost'] === null ? 'N/A' : round($log['route_cost'], 2),
                'notes' => $this->textOrNotAvailable($log['model']->notes),
            ])->all(),
            'parking_logs' => $parkingLogs->map(fn (TransportationParkingLog $log) => [
                'date_or_datetime' => $this->parkingDateTime($log),
                'type' => $log->parking_type === 'monthly_pass' ? 'Monthly Pass' : 'Casual Parking',
                'location' => $this->textOrNotAvailable($log->location),
                'cost' => round((float) $log->total_amount, 2),
            ])->all(),
        ];
    }

    private function resolveScope(array $selection): array
    {
        $type = $selection['period'] ?? 'monthly';
        $referenceDate = CarbonImmutable::parse($selection['reference_date'] ?? now()->toDateString());

        if ($type === 'weekly') {
            $start = $referenceDate->startOfWeek();

            return compact('type', 'start') + ['end' => $start->addWeek()];
        }

        if ($type === 'custom') {
            $start = $this->dateOrFail($selection['custom_start_date'] ?? null, 'A start date is required for a custom period.');
            $endDate = $this->dateOrFail($selection['custom_end_date'] ?? null, 'An end date is required for a custom period.');
            if ($endDate->lessThan($start)) {
                throw new InvalidArgumentException('The custom period end date must not be before its start date.');
            }

            return compact('type', 'start') + ['end' => $endDate->addDay()];
        }

        if ($type === 'since_refuel') {
            $fuelLogs = TransportationFuelLog::query()
                ->orderByDesc('fuelled_at')
                ->orderByDesc('id')
                ->get();
            $offset = min(max((int) ($selection['refuel_offset'] ?? 0), 0), max($fuelLogs->count() - 1, 0));
            $selectedRefuel = $fuelLogs->get($offset);
            if (!$selectedRefuel) {
                return ['type' => $type, 'start' => CarbonImmutable::createFromTimestamp(0), 'end' => CarbonImmutable::createFromTimestamp(0)];
            }

            return [
                'type' => $type,
                'start' => CarbonImmutable::parse($selectedRefuel->fuelled_at),
                'end' => ($fuelLogs->get($offset - 1)) ? CarbonImmutable::parse($fuelLogs->get($offset - 1)->fuelled_at) : null,
            ];
        }

        $type = 'monthly';
        $start = $referenceDate->startOfMonth();

        return compact('type', 'start') + ['end' => $start->addMonth()];
    }

    private function inScope(CarbonImmutable $date, array $scope): bool
    {
        return $date->greaterThanOrEqualTo($scope['start'])
            && ($scope['end'] === null || $date->lessThan($scope['end']));
    }

    private function parkingScopeDate(TransportationParkingLog $log, string $period): CarbonImmutable
    {
        $date = $period === 'monthly' && $log->parking_type === 'monthly_pass' && $log->billing_month
            ? $log->billing_month
            : $log->parking_date;

        return CarbonImmutable::parse($date);
    }

    private function deriveDriveLog(TransportationCommuteLog $log, Collection $fuelLogs): array
    {
        $distance = (float) $log->distance_km;
        $mileage = (float) $log->consumption_value;
        $fuelUsed = $distance > 0 && $mileage > 0 ? ($mileage / 100) * $distance : null;
        $routeCost = $fuelUsed === null ? null : $fuelUsed * $this->pricePerLitreForDrive($log, $fuelLogs);

        return compact('distance', 'mileage', 'fuelUsed', 'routeCost') + [
            'fuel_used' => $fuelUsed,
            'route_cost' => $routeCost,
            'model' => $log,
        ];
    }

    private function pricePerLitreForDrive(TransportationCommuteLog $drive, Collection $fuelLogs): float
    {
        $vehicleFuelLogs = $fuelLogs
            ->filter(fn (TransportationFuelLog $fuelLog) => $fuelLog->vehicle_id === $drive->vehicle_id)
            ->sortBy('fuelled_at');
        $historicalFuelLog = $vehicleFuelLogs
            ->filter(fn (TransportationFuelLog $fuelLog) => CarbonImmutable::parse($fuelLog->fuelled_at)->lessThanOrEqualTo(CarbonImmutable::parse($drive->driven_at)))
            ->last();
        $fuelLog = $historicalFuelLog ?? $vehicleFuelLogs->last();

        return $fuelLog ? (float) $fuelLog->price_per_litre : self::DEFAULT_FUEL_PRICE_PER_LITRE;
    }

    private function dateOrFail(?string $value, string $message): CarbonImmutable
    {
        if (!$value) {
            throw new InvalidArgumentException($message);
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
    }

    private function textOrNotAvailable(?string $value): string
    {
        return filled($value) ? $value : 'N/A';
    }

    private function routeOrNotAvailable(TransportationCommuteLog $log): string
    {
        if (!filled($log->origin) || !filled($log->destination)) {
            return 'N/A';
        }

        return "{$log->origin} - {$log->destination}";
    }

    private function driveType(?string $type): string
    {
        return match ($type) {
            'work_commute' => 'Work Commute',
            'personal_drive' => 'Personal Drive',
            default => 'N/A',
        };
    }

    private function fuelPriceType(?string $type): string
    {
        return match ($type) {
            'budi95' => 'BUDI95',
            'ron95' => 'RON95',
            default => 'N/A',
        };
    }

    private function parkingDateTime(TransportationParkingLog $log): string
    {
        if ($log->parking_type !== 'casual' || $log->start_hour === null || $log->end_hour === null) {
            return $log->parking_date ?: 'N/A';
        }

        return sprintf('%s %02d:00 - %02d:00', $log->parking_date, $log->start_hour, $log->end_hour);
    }
}
