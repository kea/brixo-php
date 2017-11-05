<?php

namespace Test\Kea\Bluegiga;

use Kea\Bluegiga\Address;
use PHPUnit\Framework\TestCase;

class AddressTest extends TestCase
{
    public function getAddresses()
    {
        return [
            ['0123456', '36:35:34:33:32:31:30'],
            ['ABCD', '44:43:42:41'],
            ["\x00\x01\x02", '02:01:00'],
        ];
    }

    /**
     * @test
     * @dataProvider getAddresses
     */
    public function readable($binary, $readable)
    {
        $address = new Address($binary);
        $this->assertEquals($readable, $address->getReadable());
    }
}
