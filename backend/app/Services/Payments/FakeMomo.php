<?php

namespace App\Services\Payments;

use App\Models\Domain\Payment;
use Illuminate\Support\Str;

/** Development/test gateway: phone numbers ending 0000 fail, 1111 stay pending, anything else succeeds on first poll. */
class FakeMomo implements MomoGateway
{
    public static array $statuses = [];

    public function requestToPay(Payment $p): array
    {
        $ref = (string) Str::uuid();
        $phone = (string) $p->payer_phone;
        self::$statuses[$ref] = str_ends_with($phone, '0000') ? 'failed' : (str_ends_with($phone, '1111') ? 'pending' : 'successful');

        return ['driver' => 'fake', 'reference' => $ref];
    }

    public function status(string $reference): string
    {
        return self::$statuses[$reference] ?? 'pending';
    }
}
