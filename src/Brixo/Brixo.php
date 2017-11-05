<?php

namespace Kea\Brixo;

use Kea\Bluegiga\BGWrapper;
use Kea\Bluegiga\Peripheral;
use Kea\Serial;

class Brixo
{
    /** @var Peripheral[] */
    private $deviceList = [];
    private $bgWrapper;

    public function __construct(Serial $serial)
    {
        $this->bgWrapper = new BGWrapper($serial);
    }

    /**
     * @param string $port es. Windows "\.\com4", Mac "/dev/tty.xyk", Linux "/dev/ttySxx"
     * @return Brixo
     */
    public static function fromPortName(string $port): Brixo
    {
        $serial = new Serial($port);

        return new self($serial);
    }

    /**
     * @param $timeout
     * @return Peripheral[]
     */
    public function scan($timeout)
    {
        $this->deviceList = $this->bgWrapper->scan($timeout);

        return $this->deviceList;
    }

    public function connect(Peripheral $peripheral):? BrixoDevice
    {
        return new BrixoDevice($peripheral);
    }
}
