<?php

namespace App\Services\Projection;

class StatutoryDeductionResolver
{
    private const SOCSO_PATH = 'data/contribution-brackets/socso_act4_brackets.json';
    private const EIS_PATH = 'data/contribution-brackets/eis_act800_brackets.json';

    public function resolve(float $grossSalary): array
    {
        if ($grossSalary <= 0) {
            return [
                'employer_socso' => 0.0,
                'socso' => 0.0,
                'socso_l24' => 0.0,
                'eis' => 0.0,
            ];
        }

        $socsoBracket = $this->findBracket(self::SOCSO_PATH, $grossSalary);
        $eisBracket = $this->findBracket(self::EIS_PATH, $grossSalary);

        return [
            'employer_socso' => $this->amount($socsoBracket['employer_share'] ?? 0),
            'socso' => $this->amount($socsoBracket['employee_INV'] ?? 0),
            'socso_l24' => $this->amount($socsoBracket['employee_NEI'] ?? 0),
            'eis' => $this->amount($eisBracket['employee'] ?? 0),
        ];
    }

    private function findBracket(string $path, float $grossSalary): array
    {
        $fullPath = base_path($path);

        if (! is_file($fullPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($fullPath), true);

        if (! is_array($decoded) || ! is_array($decoded['brackets'] ?? null)) {
            return [];
        }

        foreach ($decoded['brackets'] as $bracket) {
            if (! is_array($bracket)) {
                continue;
            }

            $min = isset($bracket['min']) ? (float) $bracket['min'] : 0.0;
            $max = array_key_exists('max', $bracket) && $bracket['max'] !== null ? (float) $bracket['max'] : null;

            if ($grossSalary >= $min && ($max === null || $grossSalary <= $max)) {
                return $bracket;
            }
        }

        return [];
    }

    private function amount(float|int|string $value): float
    {
        return (float) $value;
    }
}
