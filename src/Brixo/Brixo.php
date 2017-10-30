<?php

namespace Kea\Brixo;

use Kea\Bluegiga\BGWrapper;

class Brixo
{
    private $deviceList = [];
    private $bgWrapper;

    public function __construct($port)
    {
        $this->bgWrapper = new BGWrapper($port);
    }

    public function scan($timeout)
    {
        $this->deviceList = $this->bgWrapper->scan($timeout);

        return $this->deviceList;
    }

    public function connect($mac):? BrixoDevice
    {
        $ble_dev = null;
        foreach ($this->deviceList as $device) {
            if ($device->mac_address() === $mac) {
                $ble_dev = $device;
                break;
            }
        }
        if ($ble_dev === null) {
            return null;
        }

        return new BrixoDevice($ble_dev);
    }
}
