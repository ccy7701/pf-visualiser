<?php

namespace App\Services;

use App\Models\HistoryMonth;
use App\Models\PromptTemplate;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PromptComposerService
{
    public function compose(PromptTemplate $template, array $input): array
    {
        $start = CarbonImmutable::parse($input['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($input['end_date'])->endOfDay();
        $transactions = Transaction::query()
            ->with(['category:id,name', 'subcategory:id,category_id,name'])
            ->whereBetween('datetime', [$start, $end])
            ->orderBy('datetime')
            ->orderBy('id')
            ->get();
        $expenses = $transactions->where('type', 'expense')->values();
        $incomes = $transactions->where('type', 'income')->values();
        $positions = $this->resolvePositions($end, $input);
        $previousPositions = $this->previousMonthPositions($end);
        $period = $this->periodLabel($start, $end, $template->period_type);
        $periodStatus = $this->resolvePeriodStatus($start, $end, $input['period_status'] ?? 'automatic');

        $placeholders = [
            'period' => $period,
            'period_intro' => $this->periodIntro($template->period_type, $period, $periodStatus),
            'start_date' => $start->format('j/n/Y'),
            'end_date' => $end->format('j/n/Y'),
            'month' => $end->format('F Y'),
            'positions' => $this->formatPositions($positions),
            'positions_comparison' => $this->formatPositionsComparison($positions, $previousPositions, $end),
            'expense_total' => $this->money($expenses->sum('amount')),
            'expense_breakdown' => $this->formatBreakdown($expenses, '-'),
            'income_total' => $this->money($incomes->sum('amount')),
            'income_breakdown' => $this->formatBreakdown($incomes, '+'),
            'additional_context' => trim((string) ($input['additional_context'] ?? '')),
            'questions' => trim((string) ($input['questions'] ?? '')),
        ];

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $matches): string => array_key_exists($matches[1], $placeholders)
                ? $placeholders[$matches[1]]
                : $matches[0],
            $template->body
        );

        return [
            'prompt' => trim((string) preg_replace("/\n{3,}/", "\n\n", (string) $rendered)),
            'period' => [
                'label' => $period,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => $periodStatus,
            ],
            'totals' => [
                'expenses' => round((float) $expenses->sum('amount'), 2),
                'incomes' => round((float) $incomes->sum('amount'), 2),
            ],
        ];
    }

    private function resolvePositions(CarbonImmutable $end, array $input): array
    {
        $history = HistoryMonth::query()
            ->where('month', '<=', $end->format('Y-m'))
            ->latest('month')
            ->first();
        $coh = $this->positionOverride($input, 'closing_coh')
            ?? ($history?->closing_coh !== null ? (float) $history->closing_coh : null);
        $elr = $this->positionOverride($input, 'closing_elr')
            ?? ($history?->closing_elr !== null ? (float) $history->closing_elr : null);
        $epf = $this->positionOverride($input, 'closing_epf')
            ?? ($history?->closing_epf !== null ? (float) $history->closing_epf : null);

        return $this->positionTotals($coh, $elr, $epf);
    }

    private function previousMonthPositions(CarbonImmutable $end): ?array
    {
        $history = HistoryMonth::query()
            ->where('month', $end->startOfMonth()->subMonth()->format('Y-m'))
            ->first();

        if (! $history) {
            return null;
        }

        return $this->positionTotals(
            (float) $history->closing_coh,
            $history->closing_elr !== null ? (float) $history->closing_elr : null,
            $history->closing_epf !== null ? (float) $history->closing_epf : null,
        );
    }

    private function positionOverride(array $input, string $key): ?float
    {
        if (! array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            return null;
        }

        return round((float) $input[$key], 2);
    }

    private function positionTotals(?float $coh, ?float $elr, ?float $epf): array
    {
        $lfp = $coh !== null && $elr !== null ? $coh + $elr : null;
        $tfp = $lfp !== null && $epf !== null ? $lfp + $epf : null;

        return compact('coh', 'elr', 'epf', 'lfp', 'tfp');
    }

    private function formatPositions(array $positions): string
    {
        return collect([
            'COH' => $positions['coh'],
            'ELR' => $positions['elr'],
            'EPF' => $positions['epf'],
            'LFP' => $positions['lfp'],
            'TFP' => $positions['tfp'],
        ])->map(fn (?float $value, string $label): string => $value === null
            ? "{$label} not available"
            : "{$label} at RM{$this->money($value)}")
            ->implode("\n");
    }

    private function formatPositionsComparison(array $current, ?array $previous, CarbonImmutable $end): string
    {
        $heading = 'EOTM '.strtoupper($end->format('F Y')).' POSITIONS';
        if (! $previous) {
            return $heading.":\n".$this->formatPositions($current)."\nNo prior month-end comparison is available.";
        }

        $previousLabel = strtoupper($end->startOfMonth()->subMonth()->format('F Y'));
        $lines = collect([
            'TFP' => 'tfp',
            'LFP' => 'lfp',
            'COH' => 'coh',
            'ELR' => 'elr',
            'EPF' => 'epf',
        ])->map(function (string $key, string $label) use ($current, $previous): string {
            if ($current[$key] === null) {
                return "{$label} not available";
            }
            if ($previous[$key] === null) {
                return "{$label} RM{$this->money($current[$key])}";
            }

            $direction = $current[$key] >= $previous[$key] ? 'up' : 'down';

            return "{$label} RM{$this->money($current[$key])} ({$direction} from RM{$this->money($previous[$key])})";
        })->implode("\n");

        return "{$heading}, COMPARED WITH {$previousLabel}:\n{$lines}";
    }

    private function formatBreakdown(Collection $transactions, string $sign): string
    {
        if ($transactions->isEmpty()) {
            return 'None recorded.';
        }

        return $transactions
            ->groupBy(fn (Transaction $transaction): string => (string) $transaction->category_id)
            ->map(function (Collection $entries): array {
                $first = $entries->first();

                return [
                    'name' => $first?->category?->name ?? 'Uncategorised',
                    'total' => (float) $entries->sum('amount'),
                    'entries' => $entries,
                ];
            })
            ->sortByDesc('total')
            ->map(function (array $group) use ($sign): string {
                $entries = $group['entries'];
                $hasSubcategories = $entries->contains(fn (Transaction $entry): bool => $entry->subcategory !== null);
                if ($hasSubcategories) {
                    $subcategories = $entries
                        ->groupBy(fn (Transaction $entry): string => $entry->subcategory_id !== null
                            ? (string) $entry->subcategory_id
                            : 'none')
                        ->map(function (Collection $subcategoryEntries): array {
                            return [
                                'name' => $subcategoryEntries->first()?->subcategory?->name ?? 'No subcategory',
                                'total' => (float) $subcategoryEntries->sum('amount'),
                            ];
                        })
                        ->sortByDesc('total')
                        ->map(fn (array $subcategory): string => "\t{$sign}RM{$this->money($subcategory['total'])} from {$subcategory['name']}")
                        ->implode("\n");

                    return "{$sign}RM{$this->money($group['total'])} from {$group['name']}, of which\n{$subcategories}";
                }

                return "{$sign}RM{$this->money($group['total'])} from {$group['name']}";
            })
            ->implode("\n");
    }

    private function periodLabel(CarbonImmutable $start, CarbonImmutable $end, string $periodType): string
    {
        if ($periodType === 'monthly') {
            return $end->format('F Y');
        }

        if ($periodType === 'weekly' && $start->year === $end->year) {
            return $start->format('j/n').'–'.$end->format('j/n');
        }

        return $start->format('j/n/Y').'–'.$end->format('j/n/Y');
    }

    private function resolvePeriodStatus(CarbonImmutable $start, CarbonImmutable $end, string $requestedStatus): string
    {
        if (in_array($requestedStatus, ['ongoing', 'complete'], true)) {
            return $requestedStatus;
        }

        $today = CarbonImmutable::now('Asia/Kuala_Lumpur')->startOfDay();

        if ($today->greaterThan($end)) {
            return 'complete';
        }
        if ($today->lessThan($start)) {
            return 'not_started';
        }

        return 'ongoing';
    }

    private function periodIntro(string $periodType, string $period, string $periodStatus): string
    {
        $noun = $periodType === 'monthly' ? 'month' : ($periodType === 'weekly' ? 'week' : 'period');
        $label = $periodType === 'monthly' ? $period : "of {$period}";

        if ($periodStatus === 'complete') {
            return "The {$noun} {$label} is over. Here is the final breakdown:";
        }
        if ($periodStatus === 'not_started') {
            return "The {$noun} {$label} has not started yet.";
        }

        return "The {$noun} {$label} is still ongoing. Breakdown so far:";
    }

    private function money(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
