<?php

declare(strict_types=1);

namespace Goodoneuz\PayUz\Http\Classes\Octo;

use Goodoneuz\PayUz\Models\PaymentSystem;
use Goodoneuz\PayUz\Http\Classes\PaymentException;
use Goodoneuz\PayUz\Services\PaymentSystemService;

class Response{
    
    const SUCCESS                       = 0;

    public $result = [];

    /**
     * @param null $status
     * @param null $params
     * @throws PaymentException
     */
    public function setResult($status = null, $params = null)
    {
        $this->result['error'] = $status;
        switch ($status) {
            case self::SUCCESS:
                $this->result['error_note'] = 'Success';
                break;
        }
        if (is_array($params)){
            foreach ($params as $key => $param ){
                $this->result[$key] = $param;
            }
        }
        throw new PaymentException($this);
    }

    /**
     *
     */
    public function send(){
        $params = PaymentSystemService::getPaymentSystemParamsCollect(PaymentSystem::CLICK);
        $timestamp = time();
        $digest = sha1($timestamp .  $params['secret_key']);
        
        if(env('APP_ENV') != 'testing')
            header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($this->result);
    }

    public static function fromCreateUrl(): self
    {

    }
}
