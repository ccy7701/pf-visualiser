<?php

namespace App\Services\Projection;

use InvalidArgumentException;

class MonthHelper
{
    public static function normalize(string $value): string
    {
        $value = trim($value);

        if (! preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            throw new InvalidArgumentException("Invalid month format [{$value}]. Expected YYYY-MM.");
        }

        $month = (int) $matches[2];

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid month value [{$value}]. Month must be 01 to 12.");
        }

        return sprintf('%04d-%02d', (int) $matches[1], $month);
    }

    public static function toIndex(string $month): int
    {
        $month = self::normalize($month);
        [$year, $monthNum] = array_map('intval', explode('-', $month));

        return ($year * 12) + ($monthNum - 1);
    }

    public static function fromIndex(int $index): string
    {
        $year = intdiv($index, 12);
        $month = ($index % 12) + 1;

        return sprintf('%04d-%02d', $year, $month);
    }

    public static function isSameOrAfter(string $candidateMonth, string $thresholdMonth): bool
    {
        return self::toIndex($candidateMonth) >= self::toIndex($thresholdMonth);
    }

    public static function sequence(string $startMonth, string $endMonth): array
    {
        $startIndex = self::toIndex($startMonth);
        $endIndex = self::toIndex($endMonth);

        if ($startIndex > $endIndex) {
            throw new InvalidArgumentException('Start month must be before or equal to end month.');
        }

        $months = [];

        for ($index = $startIndex; $index <= $endIndex; $index++) {
            $months[] = self::fromIndex($index);
        }

        return $months;
    }
}
