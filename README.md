# Check Fraud Customer

A Laravel package to check potential fraud customers by integrating with various courier services like Steadfast, Pathao, and Redx. This package allows you to retrieve delivery statistics for a given phone number from these services, helping to identify suspicious activity.

## Features

-   Integrates with Steadfast, Pathao, and Redx courier services.
-   Retrieves customer delivery statistics based on phone number.
-   Includes helper functions for phone number validation and environment/configuration checks.

## Installation

You can install the package via composer:

```bash
composer require check-fraud-customer/check-fraud-customer
```

The package will automatically register its service provider.

### Configuration

Publish the configuration file using the following command:

```bash
php artisan vendor:publish --provider="CheckFraudCustomer\CourierFraudChecker\CheckFraudCustomerServiceProvider" --tag="config"
```

This will publish `check_fraud_customer.php` to your `config` directory. You will need to set up the API credentials for each courier service in this file or in your `.env` file.

Example `.env` variables:

```
STEADFAST_USER=your_steadfast_username
STEADFAST_PASSWORD=your_steadfast_password

PATHAO_USER=your_pathao_username
PATHAO_PASSWORD=your_pathao_password

REDX_PHONE=your_redx_phone_number
REDX_PASSWORD=your_redx_password
```

## Usage

You can use the `CheckFraudCustomer` facade to check a phone number:

```php
use CheckFraudCustomer\CourierFraudChecker\Facade\CheckFraudCustomer;

try {
    $phoneNumber = '01798765432'; // Example Bangladeshi phone number
    $fraudData = CheckFraudCustomer::check($phoneNumber);

    foreach ($fraudData as $service => $data) {
        echo "Service: " . ucfirst($service) . "\n";
        echo "Delivery Stats: " . json_encode($data) . "\n\n";
    }
} catch (\InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "An unexpected error occurred: " . $e->getMessage() . "\n";
}
```

### Helper Functions

The package also provides helper functions for validation:

```php
use CheckFraudCustomer\CourierFraudChecker\Helpers\CheckFraudCustomerHelper;

try {
    CheckFraudCustomerHelper::validatePhoneNumber('01798765432');
    echo "Phone number is valid.\n";
} catch (\InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

try {
    CheckFraudCustomerHelper::checkRequiredEnv(['STEADFAST_API_KEY', 'PATHAO_CLIENT_ID']);
    echo "Required environment variables are set.\n";
} catch (\InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
