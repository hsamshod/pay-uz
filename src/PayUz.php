<?php

namespace Goodoneuz\PayUz;

use Goodoneuz\PayUz\Http\Classes\GatewayInterface;
use Goodoneuz\PayUz\Http\Classes\Octo\Octo;
use Goodoneuz\PayUz\Models\Transaction;
use Goodoneuz\PayUz\Models\PaymentSystem;
use Goodoneuz\PayUz\Http\Classes\Payme\Payme;
use Goodoneuz\PayUz\Http\Classes\Click\Click;
use Goodoneuz\PayUz\Http\Classes\Paynet\Paynet;
use Goodoneuz\PayUz\Http\Classes\Stripe\Stripe;
use Goodoneuz\PayUz\Http\Classes\PaymentException;
use Goodoneuz\PayUz\Services\PaymentService;

class PayUz
{

    protected GatewayInterface|null $driverClass = null;

    /**
     * PayUz constructor.
     */
    public function __construct()
    {
    }


    /**
     * Select payment driver
     * @param null $driver
     * @return $this
     */
    public function driver(?string $driver = null): self
    {
        $this->driverClass = match ($driver) {
            PaymentSystem::PAYME => new Payme(),
            PaymentSystem::OCTO => new Octo(),
            PaymentSystem::CLICK => new Click,
            PaymentSystem::PAYNET => new Paynet,
            PaymentSystem::STRIPE => new Stripe,
        };

        return $this;
    }

    public function redirectUrl($key, $amount, $itemAmount = 0, $currency_code = Transaction::CURRENCY_CODE_UZS): string
    {
        $model = PaymentService::convertKeyToModel($key);

        return $this->driverClass->getRedirectUrl($model, $amount, $currency_code, $itemAmount);
    }

    /**
     * Redirect to payment system
     * @param $model
     * @param $amount
     * @param int $currency_code
     * @return PayUz
     * @throws \Exception
     */
    public function redirect($model, $amount, $currency_code = Transaction::CURRENCY_CODE_UZS, $url = null)
    {
        $driver = $this->driverClass;
        $params = $driver->getRedirectParams($model, $amount, $currency_code, $url);
        $view = 'pay-uz::merchant.index';
        if (!empty($driver::CUSTOM_FORM))
            $view = $driver::CUSTOM_FORM;
        echo view($view, compact('params'));
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function handle()
    {
        try {
            return $this->driverClass->run();
        } catch (PaymentException $e) {
            return $e->response();
        }

        return $this;
    }

    /**
     * @param $model
     * @param $amount
     * @param $currency_code
     * @throws \Exception
     */
    public function validateModel($model, $amount, $currency_code)
    {
        if (is_null($model))
            throw new \Exception('Modal can\'t be null');
        if (is_null($amount) || $amount == 0)
            throw new \Exception('Amount can\'t be null or 0');
        if (is_null($currency_code))
            throw new \Exception('Currency code can\'t be null');
    }

    public function setDescription($hasDescription)
    {
        $this->driverClass->setDescription($hasDescription);
        return $this;
    }
}