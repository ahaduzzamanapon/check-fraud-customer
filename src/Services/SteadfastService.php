<?php

namespace CheckFraudCustomer\CourierFraudChecker\Services;

use CheckFraudCustomer\CourierFraudChecker\Helpers\CheckFraudCustomerHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SteadfastService
{
    protected string $email;

    protected string $password;

    protected string $cacheKey = 'steadfast_auth_data';

    protected int $cacheMinutes = 50;

    public function __construct()
    {
        CheckFraudCustomerHelper::checkRequiredConfig([
            'check_fraud_customer.steadfast.user',
            'check_fraud_customer.steadfast.password',
        ]);

        $this->email = config('check_fraud_customer.steadfast.user');
        $this->password = config('check_fraud_customer.steadfast.password');
    }

   

    public function getCustomerDeliveryStats($phoneNumber)
    {
        $baseUrl = 'https://www.steadfast.com.bd';

        // Step 1: Get login page (CSRF + initial cookies)
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'text/html,application/xhtml+xml',
        ])->get("$baseUrl/login");

        preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $response->body(), $matches);
        $token = $matches[1] ?? null;

        if (! $token) {
            return ['error' => 'CSRF token not found'];
        }

        $cookiesArray = [];
        foreach ($response->cookies()->toArray() as $cookie) {
            $cookiesArray[$cookie['Name']] = $cookie['Value'];
        }

        // dd($this->email, $this->password, $token, $cookiesArray, $baseUrl, $phoneNumber);

        // Step 2: Submit login (allow redirects!)
        $loginResponse = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'text/html,application/xhtml+xml',
        ])
            ->withCookies($cookiesArray, 'www.steadfast.com.bd')
            ->asForm()
            ->withOptions(['allow_redirects' => true]) // follow 302 to dashboard
            ->post("$baseUrl/login", [
                '_token' => $token,
                'email' => $this->email,
                'password' => $this->password,
            ]);

        // Merge cookies again
        foreach ($loginResponse->cookies()->toArray() as $cookie) {
            $cookiesArray[$cookie['Name']] = $cookie['Value'];
        }

        // Step 3: Try fraud check with full cookies
        $authResponse = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'application/json',
        ])
            ->withCookies($cookiesArray, 'www.steadfast.com.bd')
            ->get("$baseUrl/user/frauds/check/{$phoneNumber}");

        if ($authResponse->redirect() || str_contains($authResponse->body(), 'login')) {
            return ['error' => 'Login failed (still redirected to login)'];
        }

        if (! $authResponse->successful()) {
            return ['error' => 'Fraud check failed'];
        }

        $object = $authResponse->json();

        if (empty($object) || isset($object['error'])) {
            return ['error' =>' Invalid response from Steadfast']; ;
        }

        return [
            'success' => $object['total_delivered'] ?? 0,
            'cancel' => $object['total_cancelled'] ?? 0,
            'total' => ($object['total_delivered'] ?? 0) + ($object['total_cancelled'] ?? 0),
        ];
    }
}
