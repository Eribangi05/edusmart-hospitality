<?php

namespace App\Services\Rooms;

use App\Exceptions\ApiError;
use App\Models\Domain\Folio;
use App\Models\Domain\FolioEntry;
use App\Services\Audit;

/** Folio = append-only ledger. Charges are positive, payments negative; entries are voided, never deleted. */
class FolioService
{
    public function balance(Folio $folio): int
    {
        return (int) FolioEntry::where('folio_id', $folio->id)->whereNull('voided_at')->sum('amount');
    }

    public function post(Folio $folio, string $type, string $description, int $unitPrice, float $qty = 1, array $extra = []): FolioEntry
    {
        if ($folio->status !== 'open') {
            throw new ApiError('folio_closed', 'This folio is closed.', 409);
        }
        if (! empty($extra['idempotency_key']) && ($e = FolioEntry::where('idempotency_key', $extra['idempotency_key'])->first())) {
            return $e;
        }
        $amount = (int) round($unitPrice * $qty);
        $signed = in_array($type, ['payment', 'discount'], true) ? -abs($amount) : $amount;
        $entry = new FolioEntry;
        $entry->forceFill([
            'property_id' => $folio->property_id, 'folio_id' => $folio->id, 'type' => $type, 'description' => $description,
            'quantity' => $qty, 'unit_price' => $unitPrice, 'amount' => $signed,
            'business_date' => $extra['business_date'] ?? now()->toDateString(), 'idempotency_key' => $extra['idempotency_key'] ?? null,
            'source_type' => $extra['source_type'] ?? null, 'source_id' => $extra['source_id'] ?? null,
            'posted_by' => request()?->user()?->id, 'meta' => $extra['meta'] ?? null,
        ])->save();
        Audit::log('folio.entry_posted', 'folio_entry', $entry->id, null, ['folio' => $folio->id, 'type' => $type, 'amount' => $signed]);

        return $entry;
    }

    public function void(FolioEntry $entry, string $reason): FolioEntry
    {
        if ($entry->voided_at) {
            throw new ApiError('invalid_state', 'Entry already voided.', 409);
        }
        $entry->forceFill(['voided_at' => now(), 'void_reason' => $reason])->save();
        Audit::log('folio.entry_voided', 'folio_entry', $entry->id, null, ['reason' => $reason, 'amount' => $entry->amount]);

        return $entry;
    }
}
