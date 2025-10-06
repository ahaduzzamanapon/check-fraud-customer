<?php

require_once __DIR__ . '/vendor/autoload.php';

use CheckFraudCustomer\CourierFraudChecker\CheckFraudCustomer;
use CheckFraudCustomer\CourierFraudChecker\Services\PathaoService;
use CheckFraudCustomer\CourierFraudChecker\Services\RedxService;
use CheckFraudCustomer\CourierFraudChecker\Services\SteadfastService;
use CheckFraudCustomer\CourierFraudChecker\Helpers\CheckFraudCustomerHelper;

// --- Mocking Laravel's env() and config() for manual testing ---
// In a real Laravel app, these would be provided by Laravel itself.
if (!function_exists('env')) {
    function env($key, $default = null) {
        $envVars = [
            'STEADFAST_USER' => 'mock_steadfast_user',
            'STEADFAST_PASSWORD' => 'mock_steadfast_password',
            'PATHAO_USER' => 'mock_pathao_user',
            'PATHAO_PASSWORD' => 'mock_pathao_password',
            'REDX_PHONE' => '01700000000', // Mock phone number for Redx
            'REDX_PASSWORD' => 'mock_redx_password',
        ];
        return $envVars[$key] ?? $default;
    }
}

if (!function_exists('config')) {
    function config($key, $default = null) {
        $config = [
            'check_fraud_customer.steadfast.user' => env('STEADFAST_USER'),
            'check_fraud_customer.steadfast.password' => env('STEADFAST_PASSWORD'),
            'check_fraud_customer.pathao.user' => env('PATHAO_USER'),
            'check_fraud_customer.pathao.password' => env('PATHAO_PASSWORD'),
            'check_fraud_customer.redx.phone' => env('REDX_PHONE'),
            'check_fraud_customer.redx.password' => env('REDX_PASSWORD'),
        ];
        return $config[$key] ?? $default;
    }
}

// Mock Validator facade for CheckFraudCustomerHelper
// This mock is more robust to mimic Laravel's Validator facade
class MockValidationErrors
{
    protected $messages;

    public function __construct(array $messages)
    {
        $this->messages = $messages;
    }

    public function first($key = null)
    {
        if ($key && isset($this->messages[$key][0])) {
            return $this->messages[$key][0];
        }
        return reset($this->messages)[0] ?? null;
    }
}

class MockValidator
{
    public static function make($data, $rules, $messages = [])
    {
        $errors = [];

        // Simplified mock: only checks for required and basic regex for phone
        if (isset($rules['phone'])) {
            foreach ($rules['phone'] as $rule) {
                if ($rule === 'required' && empty($data['phone'])) {
                    $errors['phone'][] = $messages['phone.required'] ?? 'The phone field is required.';
                }
                if (str_contains($rule, 'regex:/^01[3-9][0-9]{8}$/')) {
                    if (!empty($data['phone']) && !preg_match('/^01[3-9][0-9]{8}$/', $data['phone'])) {
                        $errors['phone'][] = $messages['phone.regex'] ?? 'Invalid Bangladeshi phone number.';
                    }
                }
            }
        }

        return new class($errors) {
            private $errors;

            public function __construct($errors) {
                $this->errors = $errors;
            }

            public function fails(): bool
            {
                return !empty($this->errors);
            }

            public function errors(): MockValidationErrors
            {
                return new MockValidationErrors($this->errors);
            }
        };
    }
}

// Replace the real Validator facade with our mock for this script
if (!class_exists('Illuminate\Support\Facades\Validator')) {
    class_alias('MockValidator', 'Illuminate\Support\Facades\Validator');
}


// --- Mock Service Implementations for manual testing ---
// These will return dummy data instead of making actual API calls.
class MockSteadfastService extends SteadfastService
{
    public function __construct()
    {
        // Parent constructor expects config, so we pass dummy values
        parent::__construct(); // No need to pass config here, it uses global config()
    }

    public function getCustomerDeliveryStats($phoneNumber): array
    {
        return [
            'status' => 'success',
            'data' => [
                'total_deliveries' => rand(10, 100),
                'successful_deliveries' => rand(5, 90),
                'failed_deliveries' => rand(0, 10),
                'last_delivery_date' => '2025-09-30',
                'is_fraud' => (rand(0, 1) == 1)
            ]
        ];
    }
}

class MockPathaoService extends PathaoService
{
    public function __construct()
    {
        // Parent constructor expects config, so we pass dummy values
        parent::__construct(); // No need to pass config here, it uses global config()
    }

    public function getCustomerDeliveryStats($phoneNumber): array
    {
        return [
            'status' => 'success',
            'data' => [
                'total_orders' => rand(15, 150),
                'completed_orders' => rand(10, 140),
                'cancelled_orders' => rand(0, 15),
                'average_rating' => rand(30, 50) / 10, // 3.0 to 5.0
                'is_high_risk' => (rand(0, 1) == 1)
            ]
        ];
    }
}

class MockRedxService extends RedxService
{
    public function __construct()
    {
        // Parent constructor expects config, so we pass dummy values
        parent::__construct(); // No need to pass config here, it uses global config()
    }

    public function getCustomerDeliveryStats($phoneNumber): array
    {
        return [
            'status' => 'success',
            'data' => [
                'delivery_count' => rand(5, 80),
                'return_count' => rand(0, 8),
                'last_order_status' => ['delivered', 'returned'][rand(0, 1)],
                'fraud_score' => rand(1, 100),
            ]
        ];
    }
}

// --- Manual Test Execution ---
echo "--- Starting Manual Package Check ---

";

try {
    // Instantiate the CheckFraudCustomer with mock services
    $fraudChecker = new CheckFraudCustomer(
        new MockSteadfastService(),
        new MockPathaoService(),
        new MockRedxService()
    );

    $phoneNumber = '01712345678'; // A valid Bangladeshi phone number for testing

    echo "Checking phone number: {$phoneNumber}

";

    // Use the helper to validate the phone number first
    CheckFraudCustomerHelper::validatePhoneNumber($phoneNumber);
    echo "Phone number validated successfully.

";

    $results = $fraudChecker->check($phoneNumber);

    echo "Fraud check results:
";
    print_r($results);

    echo "
--- Manual Package Check Complete ---
";

} catch (InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage() . "
";
} catch (Exception $e) {
    echo "An unexpected error occurred: " . $e->getMessage() . "
";
}

echo "
--- Testing CheckFraudCustomerHelper ---
";
try {
    echo "Testing checkRequiredEnv (should pass with mocks):
";
    CheckFraudCustomerHelper::checkRequiredEnv(['STEADFAST_USER', 'PATHAO_USER', 'REDX_PHONE']);
    echo "checkRequiredEnv passed.

";
} catch (InvalidArgumentException $e) {
    echo "checkRequiredEnv failed: " . $e->getMessage() . "

";
}

try {
    echo "Testing validatePhoneNumber with invalid number:
";
    CheckFraudCustomerHelper::validatePhoneNumber('123'); // Invalid number
} catch (InvalidArgumentException $e) {
    echo "validatePhoneNumber caught expected error: " . $e->getMessage() . "

";
}

try {
    echo "Testing checkRequiredConfig (should pass with mocks):
";
    CheckFraudCustomerHelper::checkRequiredConfig(['check_fraud_customer.steadfast.user', 'check_fraud_customer.pathao.user', 'check_fraud_customer.redx.phone']);
    echo "checkRequiredConfig passed.

";
} catch (InvalidArgumentException $e) {
    echo "checkRequiredConfig failed: " . $e->getMessage() . "

";
}