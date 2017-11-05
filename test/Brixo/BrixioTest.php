<?php

namespace Test\Kea\Brixo;

use Kea\Bluegiga\Peripheral;
use Kea\Brixo\Brixo;
use Kea\Serial;
use PHPUnit\Framework\TestCase;

class BrixioTest extends TestCase
{
    /**
     * @test
     */
    public function searchDevice()
    {
        $serial = $this->createMock(Serial::class);
        $serial->expects($this->exactly(5))
            ->method('write')
            ->withConsecutive(
                [$this->equalTo("\x00\x01\x03\x00\x00")],
                [$this->equalTo("\x00\x00\x06\x04")],
                [$this->equalTo("\x00\x05\x06\x07\xc8\x00\xc8\x00\x01")],
                [$this->equalTo("\x00\x01\x06\x02\x01")],
                [$this->equalTo("\x00\x00\x06\x04")]
            );
        $serial->expects($this->exactly(5))
            ->method('blockingRead')
            ->willReturnOnConsecutiveCalls(
                "\x80\x10\x03\x00\x00\x09\x2f\x39\x00\x50\xa0\xbb\x00\x12\x00\xc8\x00\x00\x00\xff",
                "\x00\x02\x06\x04\x81\x01",
                "",
                "\x00\x02\x06\x07\x00\x00".
                "\x00\x02\x06\x02\x00\x00".
                "\x80\x16\x06\x00\xcb\x00\xee\x28\x5d\xd6\x29\x74\x01\xff\x0b\x02\x01\x06\x07\xff\x4c\x00\x10\x02\x0b\x00".
                "\x80\x0b\x06\x00\xcb\x04\xee\x28\x5d\xd6\x29\x74\x01\xff\x00".
                "\x80\x1f\x06\x00\xb7\x00\x2f\x39\x00\x50\xa0\xbb\x00\xff\x14\x02\x01\x06\x0b\x09\x42\x52\x49\x58\x4f\x5f\x33\x39\x32\x46\x04\xff\x00\x00\x00".
                "\x80\x11\x06\x00\xb7\x04\x2f\x39\x00\x50\xa0\xbb\x00\xff\x06\x05\xff\x31\x01\x3b\x04".
                "\x80\x16\x06\x00\xc9\x00\xee\x28\x5d\xd6\x29\x74\x01\xff\x0b\x02\x01\x06\x07\xff\x4c\x00\x10\x02\x0b\x00",
                "\x00\x02\x06\x04\x00\x00"
            );

        $brixo = new Brixo($serial);
        $list = $brixo->scan(3);
        $this->assertCount(2, $list);
        $this->assertInstanceOf(Peripheral::class, $list[0]);
        $this->assertEquals('74:29:d6:5d:28:ee', $list[0]->getMac());
        $this->assertEquals('bb:a0:50:00:39:2f', $list[1]->getMac());
    }

    public function selectDevice()
    {
        /*
        [0] BB:A0:50:00:39:2F
        Select device:0
        SEND =>[ 00 0F 06 03 2F 39 00 50 A0 BB 00 1E 00 3C 00 00 01 00 00 ]
        RECV <=[ 00 02 06 04 00 00 ]
        RECV <=[ 00 03 06 03 00 00 00 ]
        RECV <=[ 80 10 03 00 00 05 2F 39 00 50 A0 BB 00 1E 00 00 01 00 00 FF ]
        Connected to BB:A0:50:00:39:2F
        Interval: 24ms
        SEND =>[ 00 08 04 01 00 01 00 FF FF 02 00 28 ]
        RECV <=[ 00 03 04 01 00 00 00 ]
        RECV <=[ 80 08 04 02 00 01 00 07 00 02 00 18 ]
        RECV <=[ 80 08 04 02 00 08 00 0B 00 02 01 18 ]
        RECV <=[ 80 08 04 02 00 0C 00 16 00 02 00 2B ]
        RECV <=[ 80 05 04 01 00 00 00 20 00 ]
        Service Groups:
        0018
        0118
        002B
        SEND =>[ 00 05 04 03 00 01 00 07 00 ]
        RECV <=[ 00 03 04 03 00 00 00 ]
        RECV <=[ 80 06 04 04 00 01 00 02 00 28 ]
        RECV <=[ 80 06 04 04 00 02 00 02 03 28 ]
        RECV <=[ 80 06 04 04 00 03 00 02 00 2A ]
        RECV <=[ 80 06 04 04 00 04 00 02 03 28 ]
        RECV <=[ 80 06 04 04 00 05 00 02 01 2A ]
        RECV <=[ 80 06 04 04 00 06 00 02 03 28 ]
        RECV <=[ 80 06 04 04 00 07 00 02 04 2A ]
        RECV <=[ 80 05 04 01 00 00 00 08 00 ]
        1:	2800
        2:	2803
        3:	2A00
        4:	2803
        5:	2A01
        6:	2803
        7:	2A04
        SEND =>[ 00 05 04 03 00 08 00 0B 00 ]
        RECV <=[ 00 03 04 03 00 00 00 ]
        RECV <=[ 80 06 04 04 00 08 00 02 00 28 ]
        RECV <=[ 80 06 04 04 00 09 00 02 03 28 ]
        RECV <=[ 80 06 04 04 00 0A 00 02 05 2A ]
        RECV <=[ 80 06 04 04 00 0B 00 02 02 29 ]
        RECV <=[ 80 05 04 01 00 00 00 0C 00 ]
        8:	2800
        9:	2803
        10:	2A05
        11:	2902
        SEND =>[ 00 05 04 03 00 0C 00 16 00 ]
        RECV <=[ 00 03 04 03 00 00 00 ]
        RECV <=[ 80 06 04 04 00 0C 00 02 00 28 ]
        RECV <=[ 80 06 04 04 00 0D 00 02 03 28 ]
        RECV <=[ 80 06 04 04 00 0E 00 02 10 2B ]
        RECV <=[ 80 06 04 04 00 0F 00 02 02 29 ]
        RECV <=[ 80 06 04 04 00 10 00 02 03 28 ]
        RECV <=[ 80 06 04 04 00 11 00 02 11 2B ]
        RECV <=[ 80 06 04 04 00 12 00 02 03 28 ]
        RECV <=[ 80 06 04 04 00 13 00 02 12 2B ]
        RECV <=[ 80 06 04 04 00 14 00 02 03 28 ]
        RECV <=[ 80 06 04 04 00 15 00 02 13 2B ]
        RECV <=[ 80 06 04 04 00 16 00 02 02 29 ]
        RECV <=[ 80 05 04 01 00 00 00 17 00 ]
        12:	2800
        13:	2803
        14:	2B10
        15:	2902
        16:	2803
        17:	2B11
        18:	2803
        19:	2B12
        20:	2803
        21:	2B13
        22:	2902
        SEND =>[ 00 06 04 05 00 0F 00 02 01 00 ]
        RECV <=[ 00 03 04 05 00 00 00 ]
        RECV <=[ 80 05 04 01 00 00 00 0F 00 ]
        RECV <=[ 80 13 04 05 00 0E 00 01 0E 53 82 64 64 03 13 00 01 03 13 00 5B 01 FD ]

        ------

        Service Groups:
        SEND =>[ 00080401000100ffff020028 ]
        RECV <= [00030401000000]
        RECV <= [800804020001000700020018]
        RECV <= [800804020008000b00020118]
        RECV <= [80080402000c00160002002b]
        RECV <= [800504010000002000]
        '0018'
        SEND =>[ 000504030001000700 ]
        RECV <= [00030403000000]
        RECV <= [80060404000100020028]
        RECV <= [80060404000200020328]
        RECV <= [8006040400030002002a]
        RECV <= [80060404000400020328]
        RECV <= [8006040400050002012a]
        RECV <= [80060404000600020328]
        RECV <= [8006040400070002042a]
        RECV <= [800504010000000800]
        C $1:0028
        C $1:0328
        C $1:002a
        C $1:0328
        C $1:012a
        C $1:0328
        C $1:042a
        '0118'
        SEND =>[ 000504030008000b00 ]
        RECV <= [00030403000000]
        RECV <= [80060404000800020028]
        RECV <= [80060404000900020328]
        RECV <= [80060404000a0002052a]
        RECV <= [80060404000b00020229]
        RECV <= [800504010000000c00]
        C $1:0028
        C $1:0328
        C $1:052a
        C $1:0229
        '002b'
        SEND =>[ 00050403000c001600 ]
        RECV <= [00030403000000]
        RECV <= [80060404000c00020028]
        RECV <= [80060404000d00020328]
        RECV <= [80060404000e0002102b]
        RECV <= [80060404000f00020229]
        RECV <= [80060404001000020328]
        RECV <= [8006040400110002112b]
        RECV <= [80060404001200020328]
        RECV <= [8006040400130002122b]
        RECV <= [80060404001400020328]
        RECV <= [8006040400150002132b]
        RECV <= [80060404001600020229]
        RECV <= [800504010000001700]
        C $1:0028
        C $1:0328
        C $1:102b
        C $1:0229
        C $1:0328
        C $1:112b
        C $1:0328
        C $1:122b
        C $1:0328
        C $1:132b
        C $1:0229



        */
    }
}
