<?php

namespace CheckFraudCustomer\CourierFraudChecker\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use CheckFraudCustomer\CourierFraudChecker\Helpers\CheckFraudCustomerHelper;

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

    protected function getAuthData()
    {
        // Use cached auth data if available
        $authData = Cache::get($this->cacheKey);
        if ($authData) {
            return $authData;
        }

        // Step 1: Fetch login page
        $response = Http::get('https://steadfast.com.bd/login');

        // Extract CSRF token
        preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $response->body(), $matches);
        $token = $matches[1] ?? null;

        if (!$token) {
            return null;
        }

        // Convert CookieJar to array
        $rawCookies = $response->cookies();
        $cookiesArray = [];
        foreach ($rawCookies->toArray() as $cookie) {
            $cookiesArray[$cookie['Name']] = $cookie['Value'];
        }

        // Step 2: Log in
        $loginResponse = Http::withCookies($cookiesArray, 'steadfast.com.bd')
            ->asForm()
            ->post('https://steadfast.com.bd/login', [
                '_token' => $token,
                'email' => $this->email,
                'password' => $this->password,
            ]);

        if (!($loginResponse->successful() || $loginResponse->redirect())) {
            return null;
        }

        // Rebuild cookies after login
        $loginCookiesArray = [];
        foreach ($loginResponse->cookies()->toArray() as $cookie) {
            $loginCookiesArray[$cookie['Name']] = $cookie['Value'];
        }

        $authData = [
            'cookies' => $loginCookiesArray,
            'token' => $token, // This token might not be needed after login, but caching it for completeness
        ];

        Cache::put($this->cacheKey, $authData, now()->addMinutes($this->cacheMinutes));

        return $authData;
    }

    public function getCustomerDeliveryStats($phoneNumber)
    {
        CheckFraudCustomerHelper::validatePhoneNumber($phoneNumber);

        $authData = $this->getAuthData();

        if (!$authData) {
            return ['error' => 'Login to Steadfast failed or unable to get auth data'];
        }

        // Step 3: Access fraud data
        $authResponse = Http::withCookies($authData['cookies'], 'steadfast.com.bd')
            ->get("https://steadfast.com.bd/user/frauds/check/{$phoneNumber}");

        if (!$authResponse->successful()) {
            // If token is invalid, clear cache and try again once
            if ($authResponse->status() === 401) {
                Cache::forget($this->cacheKey);
                $authData = $this->getAuthData();
                if ($authData) {
                    $authResponse = Http::withCookies($authData['cookies'], 'steadfast.com.bd')
                        ->get("https://steadfast.com.bd/user/frauds/check/{$phoneNumber}");
                }
            }

            if (!$authResponse->successful()) {
                return ['error' => 'Failed to fetch fraud data from Steadfast'];
            }
        }

        $object = $authResponse->collect()->toArray();

        $result = [
            'success' => $object['total_delivered'] ?? 0,
            'cancel' => $object['total_cancelled'] ?? 0,
            'total'  => ($object['total_delivered'] ?? 0) + ($object['total_cancelled'] ?? 0),
        ];

        // Step 4: Logout (optional, as session is cached)
        // The logout logic is removed here as the session is managed by cache.
        // If explicit logout is required, it should be handled outside this method.

        return $result;
    }
}
