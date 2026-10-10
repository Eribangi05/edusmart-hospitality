<?php

namespace App\Services\Payments;

use App\Models\Domain\Payment;

interface MomoGateway
{
    /** Start a request-to-pay. Returns provider data incl. 'reference'. */
    public function requestToPay(Payment $p): array;

    /** @return string pending|successful|failed */
    public function status(string $reference): string;
}
