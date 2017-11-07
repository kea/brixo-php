<?php

namespace Test\Kea\Brixo;

use Kea\Bluegiga\Peripheral;
use Kea\Brixo\Brixo;
use Kea\Brixo\BrixoDevice;
use Kea\Brixo\BrixoStatus;
use Kea\Serial;
use Kea\UUID;
use PHPUnit\Framework\TestCase;

class BrixoStatusTest extends TestCase
{
    /**
     * @test
     */
    public function empty()
    {
        $status = new BrixoStatus(0);
        $this->assertFalse($status->getStandby());
        $this->assertFalse($status->getCW());
        $this->assertFalse($status->getCCW());
        $this->assertFalse($status->getOC());
        $this->assertFalse($status->getWarning());
        $this->assertFalse($status->getOverload());
        $this->assertFalse($status->getUSB());
        $this->assertFalse($status->getStreaming());
    }

    /**
     * @test
     */
    public function standBy()
    {
        $status = new BrixoStatus(1);
        $this->assertTrue($status->getStandby());
    }

    /**
     * @test
     */
    public function getCW()
    {
        $status = new BrixoStatus(2);
        $this->assertTrue($status->getCW());
    }

    /**
     * @test
     */
    public function getCCW()
    {
        $status = new BrixoStatus(4);
        $this->assertTrue($status->getCCW());
    }

    /**
     * @test
     */
    public function getOC()
    {
        $status = new BrixoStatus(8);
        $this->assertTrue($status->getOC());
    }

    /**
     * @test
     */
    public function getWarning()
    {
        $status = new BrixoStatus(16);
        $this->assertTrue($status->getWarning());
    }

    /**
     * @test
     */
    public function getOverload()
    {
        $status = new BrixoStatus(32);
        $this->assertTrue($status->getOverload());
    }

    /**
     * @test
     */
    public function getUSB()
    {
        $status = new BrixoStatus(64);
        $this->assertTrue($status->getUSB());
    }

    /**
     * @test
     */
    public function getStreaming()
    {
        $status = new BrixoStatus(128);
        $this->assertTrue($status->getStreaming());
    }
}
