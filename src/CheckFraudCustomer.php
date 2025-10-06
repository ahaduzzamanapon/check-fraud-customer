<?php

namespace CheckFraudCustomer\CourierFraudChecker;

use CheckFraudCustomer\CourierFraudChecker\Services\SteadfastService;
use CheckFraudCustomer\CourierFraudChecker\Services\PathaoService;
use CheckFraudCustomer\CourierFraudChecker\Services\RedxService;

class CheckFraudCustomer
{
    protected $steadfastService;
    protected $pathaoService;
    protected $redxService;

    public function __construct(SteadfastService $steadfastService, PathaoService $pathaoService, RedxService $redxService)
    {
        $this->steadfastService = $steadfastService;
        $this->pathaoService = $pathaoService;
        $this->redxService = $redxService;
    }

    public function check($phoneNumber)
    {
        return [
            'steadfast' => $this->steadfastService->getCustomerDeliveryStats($phoneNumber),
            'pathao' => $this->pathaoService->getCustomerDeliveryStats($phoneNumber),
            'redx' => $this->redxService->getCustomerDeliveryStats($phoneNumber),
        ];
    }
}