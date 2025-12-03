<?php

namespace Goodoneuz\PayUz\Http\Classes\Paylov;

class Response
{

    const SUCCESS = 0;
    const INVALID_AMOUNT = 5;
    const SMTH_WENT_WRONG = 303;

    public int $status = 0;
    public string $statusText = 'OK';

    public function setResult(int $status = self::SUCCESS, string $statusText = 'OK'): void
    {
        $this->status = $status;
        $this->statusText = $statusText;
    }

    public function send()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $response = [
            'jsonrpc' => '2.0',
            'id' => random_int(100_000, 900_000),
            'result' => [
                'status' => $this->status,
                'statusText' => $this->message,
            ],
        ];

        echo json_encode($response);
        exit();
    }
}
