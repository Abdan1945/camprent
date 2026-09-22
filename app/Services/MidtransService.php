<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function configure(): void
    {
        $serverKey = config('services.midtrans.server_key');

        if (empty($serverKey)) {
            throw new \RuntimeException('MIDTRANS_SERVER_KEY belum diatur di file .env atau config/services.php');
        }

        Config::$serverKey = $serverKey;
        Config::$clientKey = config('services.midtrans.client_key');
        Config::$isProduction = filter_var(config('services.midtrans.is_production', false), FILTER_VALIDATE_BOOLEAN);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function buildSnapPayload(string $orderId, float $grossAmount, array $customerDetails = [], array $items = []): array
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) round($grossAmount),
            ],
            'customer_details' => [
                'first_name' => $customerDetails['first_name'] ?? 'Customer',
                'email' => $customerDetails['email'] ?? 'customer@example.com',
                'phone' => $customerDetails['phone'] ?? null,
            ],
            'credit_card' => [
                'secure' => true,
            ],
        ];

        if (!empty($items)) {
            $payload['item_details'] = $items;
        }

        $payload['customer_details'] = array_filter($payload['customer_details'], function ($value) {
            return $value !== null && $value !== '';
        });

        return $payload;
    }

    public function createSnapToken(string $orderId, float $grossAmount, array $customerDetails = [], array $items = []): string
    {
        $this->configure();

        return Snap::getSnapToken(
            $this->buildSnapPayload($orderId, $grossAmount, $customerDetails, $items)
        );
    }
}
