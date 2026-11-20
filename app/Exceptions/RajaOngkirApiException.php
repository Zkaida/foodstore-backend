<?php

namespace App\Exceptions;

use RuntimeException;

class RajaOngkirApiException extends RuntimeException
{
    public function __construct(private int $providerStatus)
    {
        parent::__construct('RajaOngkir destination search failed.');
    }

    public function providerStatus(): int
    {
        return $this->providerStatus;
    }
}
