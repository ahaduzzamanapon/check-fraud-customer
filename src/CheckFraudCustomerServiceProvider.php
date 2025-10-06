<?php

namespace CheckFraudCustomer\CourierFraudChecker;

use Illuminate\Support\ServiceProvider;
use CheckFraudCustomer\CourierFraudChecker\Services\SteadfastService;
use CheckFraudCustomer\CourierFraudChecker\Services\PathaoService;
use CheckFraudCustomer\CourierFraudChecker\Services\RedxService;

class CheckFraudCustomerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish the config file on vendor:publish
        $this->publishes([
            __DIR__ . '/../config/check_fraud_customer.php' => config_path('check_fraud_customer.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/check_fraud_customer.php', 'check_fraud_customer'
        );

        $this->app->singleton('check_fraud_customer', function ($app) {
            return new CheckFraudCustomer(
                $app->make(SteadfastService::class),
                $app->make(PathaoService::class),
                $app->make(RedxService::class)
            );
        });
    }
}