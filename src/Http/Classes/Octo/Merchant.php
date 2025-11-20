<?php

declare(strict_types=1);

namespace Goodoneuz\PayUz\Http\Classes\Octo;

use Goodoneuz\PayUz\Models\PaymentSystem;
use Goodoneuz\PayUz\Services\PaymentSystemService;

class Merchant
{
    private $config;
    private $response;


    public function __construct($response)
    {
        $this->config = PaymentSystemService::getPaymentSystemParamsCollect(PaymentSystem::CLICK);
        $this->response = $response;
    }

    public function validateRequest($request)
    {
        $sign = sha1($this->config['secret'] . $request['octo_payment_UUID'] . $request['status']);
        return $request['signature'] === $sign;
    }
}
