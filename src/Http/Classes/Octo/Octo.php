<?php

declare(strict_types=1);

namespace Goodoneuz\PayUz\Http\Classes\Octo;

use Exception;
use Goodoneuz\PayUz\Http\Classes\BaseGateway;
use Goodoneuz\PayUz\Http\Classes\DataFormat;
use Goodoneuz\PayUz\Http\Classes\GatewayInterface;
use Goodoneuz\PayUz\Http\Classes\PaymentException;
use Goodoneuz\PayUz\Models\PaymentSystem;
use Goodoneuz\PayUz\Models\Transaction;
use Goodoneuz\PayUz\Services\PaymentService;
use Goodoneuz\PayUz\Services\PaymentSystemService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Octo extends BaseGateway implements GatewayInterface
{
    const CHECKOUT_URL = 'https://secure.octo.uz/prepare_payment';

    private $config;
    private $merchant;
    private $request;
    private $response;

    public function __construct()
    {
        $this->config = PaymentSystemService::getPaymentSystemParamsCollect(PaymentSystem::OCTO);
        $this->request = request();
        $this->response = new Response();
        $this->merchant = new Merchant($this->response);
    }


    public function run(): void
    {
        Log::info('Octo notification', $this->request->all());

        $this->merchant->validateRequest($this->request->all());
        if ($this->request->status === 'succeeded') {
            $transaction = Transaction::where('system_transaction_id', $this->request->shop_transaction_id)->first();
            if ($transaction) {
                $transaction->state = Transaction::STATE_COMPLETED;
                $transaction->update();

                PaymentService::payListener(null, $transaction, 'after-pay');
            }
        }
    }

    public function getRedirectParams($model, $amount, $currency, $transactionId)
    {
        return [
            'octo_shop_id' => $this->config['shop_id'],
            'octo_secret' => $this->config['secret'],
            'shop_transaction_id' => $transactionId,
            'auto_capture' => true,
            'test' => !app()->isProduction(),
            'total_sum' => $amount,
            'currency' => $currency,
            'description' => 'Account top-up for #' . $model->id,
            'return_url' => config('payuz')['return_url'],
        ];
    }

    public function getRedirectUrl(
        Model $model,
        float|int $amount,
        int $currency_code,
        int $itemAmount,
        ?string $returnUrl = ''
    ): string {
        try {
            $transaction = Transaction::create([
                'payment_system' => PaymentSystem::OCTO,
                'amount' => $amount,
                'currency_code' => $currency_code,
                'state' => Transaction::STATE_CREATED,
                'updated_time' => DataFormat::timestamp(true),
                'detail' => compact('amount', 'currency_code', 'itemAmount', 'returnUrl'),
                'transactionable_type' => get_class($model),
                'transactionable_id' => $model->id
            ]);

            $params = $this->getRedirectParams($model, $amount, $currency_code === Transaction::CURRENCY_CODE_USD ? 'USD' : 'UZS', $transaction->id);

            $response = Http::asJson()->timeout(5)->post(self::CHECKOUT_URL, $params);
            $response->throw();
            $response = $response->json();

            if ($response['error']) {
                throw new \RuntimeException('Cant generate payment url' . print_r($response, true));
            }

            $transaction->system_transaction_id = $response['octo_payment_UUID'];
            $transaction->save();

            return $response['octo_pay_url'];
        } catch (Exception $e) {
            Log::error('Octo payment creation error', [
                $e->getMessage(),
                $e->getTraceAsString()
            ]);
        }

        return '';
    }
}
