<?php

declare(strict_types=1);

namespace Goodoneuz\PayUz\Http\Classes;

use Illuminate\Database\Eloquent\Model;

interface GatewayInterface
{
    public function run(): void;

    public function getRedirectUrl(Model $model, float|int $amount, int $itemAmount, int $currency_code): string;
}