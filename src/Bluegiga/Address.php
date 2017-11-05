<?php

namespace Kea\Bluegiga;

class Address
{
    private $address;

    public function __construct(string $address)
    {
        $this->address = $address;
    }

    public function getReadable()
    {
        return implode(':', str_split(bin2hex(strrev($this->address)), 2));
    }
}