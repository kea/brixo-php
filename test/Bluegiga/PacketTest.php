<?php

namespace Test\Kea\Bluegiga;

use Kea\Bluegiga\Packet;
use PHPUnit\Framework\TestCase;

class PacketTest extends TestCase
{
    public function getPackets()
    {
        return [
            ["\x00\x02\x06\x02\x00\x00"],
            ["\x80\x03\x03\x04\x00\x16\x02"],
            ["\x80\x16\x06\x00\xCB\x00\xEE\x28\x5D\xD6\x29\x74\x01\xFF\x0B\x02\x01\x06\x07\xFF\x4C\x00\x10\x02\x0B\x00"],
        ];
    }

    /**
     * @test
     * @dataProvider getPackets
     */
    public function fromBinaryAndBack($binaryPacket)
    {
        $fromBinary = Packet::fromBinary($binaryPacket);

        $packet = new Packet(
            $fromBinary->getType(),
            $fromBinary->getPayloadLength(),
            $fromBinary->getClass(),
            $fromBinary->getCommand(),
            $fromBinary->getPayload()
        );

        $this->assertEquals($binaryPacket, (string)$packet);
    }
}