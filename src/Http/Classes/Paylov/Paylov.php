<?php

namespace Goodoneuz\PayUz\Http\Classes\Paylov;

use Exception;
use Goodoneuz\PayUz\Http\Classes\BaseGateway;
use Goodoneuz\PayUz\Http\Classes\DataFormat;
use Goodoneuz\PayUz\Http\Classes\GatewayInterface;
use Goodoneuz\PayUz\Models\PaymentSystem;
use Goodoneuz\PayUz\Models\Transaction;
use Goodoneuz\PayUz\Services\PaymentService;
use Goodoneuz\PayUz\Services\PaymentSystemService;
use Illuminate\Database\Eloquent\Model;

class Paylov extends BaseGateway implements GatewayInterface
{
    private const CHECKOUT_URL = 'https://my.paylov.uz/checkout/create';
    private const CHECK_METHOD = 'transaction.check';
    private const PERFORM_METHOD = 'transaction.perform';
    private $config;
    private $merchant;
    private $request;
    private $response;

    public function __construct()
    {
        $this->config = PaymentSystemService::getPaymentSystemParamsCollect(PaymentSystem::PAYLOV);
        $this->request = request();
        $this->response = new Response();
        $this->merchant = new Merchant($this->response);
    }


    public function run(): void
    {
        $data = $this->request->json();
        switch ($data['method'] ?? '') {
            case self::CHECK_METHOD:
                $this->check($data);
                break;
            case self::PERFORM_METHOD:
                $this->perform($data);
                break;
            default:
                $this->response->setResult(Response::SMTH_WENT_WRONG, 'Invalid Method');
        }

        $this->response->send();
    }

    private function check(array $data = []): void
    {
        $id = $data['params']['account']['id'] ?? null;

        $model = PaymentService::convertKeyToModel($id);

        if (!$model) {
            $this->response->setResult(Response::SMTH_WENT_WRONG, 'Invalid account.id');
            return;
        }

        PaymentService::payListener($model, 1 * ($data['params']['amount']), 'before-pay');

        if (!PaymentService::isProperModelAndAmount($model, $data['params']['amount'])) {
            $this->response->setResult(Response::INVALID_AMOUNT, 'Amount check failed');
        }

        $transaction = (object)['amount' => $data['params']['amount']];
        PaymentService::payListener($model, $transaction, 'paying');
    }

    private function perform(array $data = [])
    {
        $this->check($data);
        $id = $data['params']['account']['id'] ?? null;
        $model = PaymentService::convertKeyToModel($id);
        $create_time = DataFormat::timestamp(true);
        $transaction = Transaction::create([
            'payment_system' => PaymentSystem::PAYLOV,
            'system_transaction_id' => $data['params']['transaction_id'],
            'amount' => $data['params']['amount'],
            'currency_code' => Transaction::CURRENCY_CODE_UZS,
            'state' => Transaction::STATE_COMPLETED,
            'updated_time' => 1 * $create_time,
            'comment' => '',
            'detail' => $data,
            'transactionable_type' => get_class($model),
            'transactionable_id' => $model->id,
        ]);

        PaymentService::payListener(null, $transaction, 'after-pay');

        PaymentService::beforeResponse("Paylov@Complete", $data['params'], []);
    }

    public function getRedirectParams($model, $amount, $currency, $url)
    {
        return [];
    }

    public function getRedirectUrl(
        Model $model,
        float|int $amount,
        int $itemAmount,
        int $currency_code,
    ): string {
        $params = 'merchant_id=' . $this->config['merchant_id'] .
            '&amount=' . $amount .
            '&account.id=' . $model->id .
            '&return_url=' . config('payuz')['return_url'];

        return self::CHECKOUT_URL . '/' . base64_encode($params);
    }
}
