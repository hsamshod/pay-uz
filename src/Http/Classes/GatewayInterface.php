<?php

declare(strict_types=1);

namespace Goodoneuz\PayUz\Http\Classes;

interface GatewayInterface
{
    public function run(): void;
}