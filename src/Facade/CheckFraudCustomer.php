<?php

namespace CheckFraudCustomer\CourierFraudChecker\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array check($phoneNumber)
 */
class CheckFraudCustomer extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'check_fraud_customer';
    }
}