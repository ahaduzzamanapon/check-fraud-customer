<?php

namespace CheckFraudCustomer\CourierFraudChecker\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use CheckFraudCustomer\CourierFraudChecker\Helpers\CheckFraudCustomerHelper;

class PathaoService
{
    protected string $username;
    protected string $password;
    protected string $cacheKey = 'pathao_access_token';
    protected int $cacheMinutes = 50;

    public function __construct()
    {
        CheckFraudCustomerHelper::checkRequiredConfig([
            'check_fraud_customer.pathao.user',
            'check_fraud_customer.pathao.password',
        ]);

        $this->username = config('check_fraud_customer.pathao.user');
        $this->password = config('check_fraud_customer.pathao.password');
    }

    protected function getAccessToken()
    {
        // Use cached token if available
        $token = Cache::get($this->cacheKey);
        if ($token) {
            return $token;
        }

        $response = Http::post('https://merchant.pathao.com/api/v1/login', [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        $accessToken = trim($data['access_token'] ?? '');

        if ($accessToken) {
            Cache::put($this->cacheKey, $accessToken, now()->addMinutes($this->cacheMinutes));
        }

        return $accessToken;
    }

    public function getCustomerDeliveryStats($phoneNumber)
    {
        CheckFraudCustomerHelper::validatePhoneNumber($phoneNumber);

        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return ['error' => 'Failed to authenticate with Pathao or retrieve access token'];
        }

        $responseAuth = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ])->post('https://merchant.pathao.com/api/v1/user/success', [
            'phone' => $phoneNumber,
        ]);

        if (!$responseAuth->successful()) {
            // If token is invalid, clear cache and try again once
            if ($responseAuth->status() === 401) {
                Cache::forget($this->cacheKey);
                $accessToken = $this->getAccessToken();
                if ($accessToken) {
                    $responseAuth = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $accessToken,
                    ])->post('https://merchant.pathao.com/api/v1/user/success', [
                        'phone' => $phoneNumber,
                    ]);
                }
            }

            if (!$responseAuth->successful()) {
                return ['error' => 'Failed to retrieve customer data', 'status' => $responseAuth->status()];
            }
        }

        $object = $responseAuth->json();

        return [
            'success' => $object['data']['customer']['successful_delivery'] ?? 0,
            'cancel' => ($object['data']['customer']['total_delivery'] ?? 0) - ($object['data']['customer']['successful_delivery'] ?? 0),
            'total' => $object['data']['customer']['total_delivery'] ?? 0,
        ];
    }
}
