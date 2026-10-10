<?php

namespace App\Services;

use App\Models\TaxRate;

/**
 * Tax / service-charge / levy engine. Amounts are whole RWF.
 * Inclusive rates are extracted from the listed price; exclusive rates are added on top of the net.
 */
class Taxes
{
    /** @param  iterable<TaxRate>  $rates */
    public static function compute(int $listed, iterable $rates): array
    {
        $rates = collect($rates);
        $inclBp = (int) $rates->where('inclusive', true)->sum('rate_bp');
        $net = $inclBp > 0 ? (int) round($listed * 10000 / (10000 + $inclBp)) : $listed;
        $lines = [];
        $incl = $listed - $net;
        $inclRates = $rates->where('inclusive', true);
        $allocated = 0;
        foreach ($inclRates->values() as $i => $r) {
            $amt = $i === $inclRates->count() - 1 ? $incl - $allocated : (int) round($incl * $r->rate_bp / max($inclBp, 1));
            $allocated += $amt;
            $lines[] = ['key' => $r->key, 'name' => $r->name, 'kind' => $r->kind, 'rate_bp' => $r->rate_bp, 'amount' => $amt, 'inclusive' => true];
        }
        $add = 0;
        foreach ($rates->where('inclusive', false) as $r) {
            $amt = (int) round($net * $r->rate_bp / 10000);
            $add += $amt;
            $lines[] = ['key' => $r->key, 'name' => $r->name, 'kind' => $r->kind, 'rate_bp' => $r->rate_bp, 'amount' => $amt, 'inclusive' => false];
        }

        return ['net' => $net, 'taxes' => $lines, 'tax_total' => $incl + $add, 'total' => $net + $incl + $add];
    }

    public static function activeRates(?array $ids = null)
    {
        $q = TaxRate::where('is_active', true)->where('rate_bp', '>', 0);
        if ($ids !== null) {
            return $q->whereIn('id', $ids)->get();
        }

        return $q->where('kind', 'tax')->get();
    }
}
