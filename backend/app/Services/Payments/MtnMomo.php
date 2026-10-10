<?php

namespace App\Services\Payments;

use App\Exceptions\ApiError;
use App\Models\Domain\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** MTN MoMo Collections API (request-to-pay). Credentials come from env; nothing is stored in code. */
class MtnMomo implements MomoGateway
{
    private function http()
    {
        $c = config('momo');

        return Http::baseUrl($c['base_url'])->withHeaders([
            'Ocp-Apim-Subscription-Key' => $c['subscription_key'], 'X-Target-Environment' => $c['target_environment'],
        ])->timeout(20);
    }

    private function token(): string
    {
        return Cache::remember('momo.token', 3000, function () {
            $c = config('momo');
            $res = $this->http()->withBasicAuth($c['api_user'], $c['api_key'])->post('/collection/token/');
            if (! $res->ok()) {
                throw new ApiError('payment_provider_error', 'Could not authenticate with MTN MoMo.', 502);
            }

            return $res->json('access_token');
        });
    }

    public function requestToPay(Payment $p): array
    {
        $ref = (string) Str::uuid();
        $c = config('momo');
        $msisdn = preg_replace('/\D+/', '', (string) $p->payer_phone);
        $res = $this->http()->withToken($this->token())->withHeaders(array_filter(['X-Reference-Id' => $ref, 'X-Callback-Url' => $c['callback_url']]))
            ->post('/collection/v1_0/requesttopay', [
                'amount' => (string) $p->amount, 'currency' => $c['currency'], 'externalId' => $p->id,
                'payer' => ['partyIdType' => 'MSISDN', 'partyId' => $msisdn],
                'payerMessage' => 'Payment', 'payeeNote' => 'Edusmart Hospitality',
            ]);
        if ($res->status() !== 202) {
            throw new ApiError('payment_provider_error', 'MTN MoMo rejected the payment request.', 502, ['status' => $res->status()]);
        }

        return ['driver' => 'mtn', 'reference' => $ref];
    }

    public function status(string $reference): string
    {
        $res = $this->http()->withToken($this->token())->get("/collection/v1_0/requesttopay/$reference");
        if (! $res->ok()) {
            return 'pending';
        }

        return match ($res->json('status')) {
            'SUCCESSFUL' => 'successful',
            'FAILED', 'REJECTED', 'TIMEOUT' => 'failed',
            default => 'pending',
        };
    }
}
