<?php

namespace Test\Kea\Brixo;

use Kea\Bluegiga\Peripheral;
use Kea\Brixo\Brixo;
use Kea\Brixo\BrixoDevice;
use Kea\Brixo\BrixoStatus;
use Kea\Serial;
use Kea\UUID;
use PHPUnit\Framework\TestCase;

class BrixoDeviceTest extends TestCase
{
    private $peripheral;

    public function setUp()
    {
        $this->peripheral = $this->createMock(Peripheral::class);
        $this->peripheral->expects($this->exactly(1))
            ->method('readNotifyValue')
            ->with(UUID::fromInt(0x2B10))
            ->willReturn("\x80\x13\x04\x05\x00\x0e\x00\x01\x0e\x53\xc2\x64\x32\x03\x91\x00\x01\x01\xc9\x00\x5c\x00\x05");
    }

    /**
     * @test
     */
    public function searchDevice()
    {
        $this->peripheral->expects($this->exactly(1))
            ->method('connect');
        $this->peripheral->expects($this->exactly(1))
            ->method('discover');
        $this->peripheral->expects($this->exactly(1))
            ->method('enableNotifyForUUID');

        $brixoDevice = new BrixoDevice($this->peripheral);
    }

    /**
     * @test
     */
    public function disconnect(): void
    {
        $this->peripheral->expects($this->exactly(1))
            ->method('disconnect');

        $brixoDevice = new BrixoDevice($this->peripheral);
        $brixoDevice->disconnect();
    }

    /**
     * @test
     */
    public function setDirection()
    {
        $this->peripheral->expects($this->exactly(1))
            ->method('writeCommand')
            ->with(UUID::fromInt(0x2B11), "\x61\x70\x70\x63\x01\x64\x0d\x0a");

        $brixoDevice = new BrixoDevice($this->peripheral);
        $brixoDevice->setDirection(1);
    }

    /**
     * @test
     */
    public function standby()
    {
        $this->peripheral->expects($this->exactly(1))
            ->method('writeCommand')
            ->with(UUID::fromInt(0x2B11), "\x61\x70\x70\x63\x00\x00\x0d\x0a");

        $brixoDevice = new BrixoDevice($this->peripheral);
        $brixoDevice->standby();
    }

    public function getPowerLevels()
    {
        return [
            [10, 10],
            [-10, 0],
            [0, 0],
            [101, 100],
            [100000, 100]
        ];
    }

    /**
     * @test
     * @dataProvider getPowerLevels
     */
    public function setPower($powerLevel, $expeted)
    {
        $this->peripheral->expects($this->exactly(1))
            ->method('writeCommand')
            ->with(UUID::fromInt(0x2B11), "\x61\x70\x70\xe3\x01".chr($expeted)."\x0d\x0a");

        $brixoDevice = new BrixoDevice($this->peripheral);
        $brixoDevice->setPower($powerLevel);
    }

    /**
     * @test
     */
    public function shutdownBattery()
    {
        $this->peripheral->expects($this->exactly(1))
            ->method('writeCommand')
            ->with(UUID::fromInt(0x2B11), "appoff\r\n");

        $brixoDevice = new BrixoDevice($this->peripheral);
        $brixoDevice->shutdownBattery();
    }
}
