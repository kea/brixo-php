<?php

namespace Kea\Brixo;

use Kea\Bluegiga\Peripheral;
use Kea\UUID;

class BrixoDevice
{
    private $bleDevice;
    private $channel = 0;
    private $power = 100;

    public function __construct(Peripheral $bleDevice)
    {
        $this->bleDevice = $bleDevice;
        $bleDevice->connect();
        $bleDevice->discover();
        try {
            $bleDevice->enableNotifyForUUID(UUID::fromInt(0x2B10));
        } catch (\Exception $e) {
            echo "It seems that the device '".$bleDevice->getName()."' is not a Brixo battery\n";
            exit(1);
        }
        $status = $this->getStatus();
        if ($status->getCW()) {
            $this->channel = 1;
        } elseif ($status->getCCW()) {
            $this->channel = 2;
        } elseif ($status->getStandby()) {
            $this->channel = 0;
        }
    }

    public function disconnect(): void
    {
        $this->bleDevice->disconnect();
    }

    public function setDirection(int $channel, bool $beep = true): void
    {
        $this->channel = $channel;
        $command = "\x61\x70\x70";
        $command .= $beep ? "\x63" : "\xE3";
        $command .= chr($this->channel);
        $command .= chr($this->power);
        $command .= "\r\n";
        $this->writeCommand($command);
    }

    public function standby(bool $beep = true): void
    {
        $command = "\x61\x70\x70";
        $command .= $beep ? "\x63" : "\xE3";
        $command .= "\x00\x00\r\n";
        $this->writeCommand($command);
    }

    public function setPower(int $pwmRatio, bool $beep = false): void
    {
        $command = "\x61\x70\x70";
        $command .= $beep ? "\x63" : "\xE3";
        $command .= chr($this->channel);
        $this->power = $pwmRatio;
        if ($pwmRatio < 0) {
            $this->power = 0;
        } elseif ($pwmRatio > 100) {
            $this->power = 100;
        }
        $command .= chr($this->power);
        $command .= "\r\n";
        $this->writeCommand($command);
    }

    public function shutdownBattery(bool $beep = true): void
    {
        $command = "\x61\x70\x70";
        $command .= $beep ? "\x6F" : "\xEF";
        $command .= "\x66\x66\r\n";
        $this->writeCommand($command);
    }

    public function setTimer(int $cutOutTime, bool $beep = true): void
    {
        $command = "\x61\x70\x70";
        $command .= $beep ? "\x78" : "\xF8";
        $command .= chr(floor($cutOutTime / 256));
        $command .= chr($cutOutTime % 256);
        $command .= "\r\n";
        $this->writeCommand($command);
    }

    public function getStatus(): BrixoStatus
    {
        return BrixoStatus::fromChar($this->readBatteryInfo()[1]);
    }

    public function getOutputCurrent(): int
    {
        $readdata = $this->readBatteryInfo();

        return \ord($readdata[6]) * 256 + \ord($readdata[7]);
    }

    public function getOutputVoltage(): int
    {
        $readdata = $this->readBatteryInfo();

        return \ord($readdata[8]) * 256 + \ord($readdata[9]);
    }

    public function getTimeLeft(): int
    {
        $readdata = $this->readBatteryInfo();

        return \ord($readdata[12]) * 256 + \ord($readdata[13]);
    }

    private function readBatteryInfo(): string
    {
        return $this->bleDevice->readNotifyValue(UUID::fromInt(0x2B10));
    }

    public function getName(): string
    {
        return $this->bleDevice->getName();
    }

    private function writeCommand(string $command): void
    {
        $this->bleDevice->writeCommand(UUID::fromInt(0x2B11), $command);
    }

    public function printInfo()
    {
        $status = $this->getStatus();

        echo "Standby  : ".$status->getStandby()."\n";
        echo "CW       : ".$status->getCW()."\n";
        echo "CCW      : ".$status->getCCW()."\n";
        echo "OC       : ".$status->getOC()."\n";
        echo "Warning  : ".$status->getWarning()."\n";
        echo "Overload : ".$status->getOverload()."\n";
        echo "USBSource: ".$status->getUSB()."\n";
        echo "Streaming: ".$status->getStreaming()."\n\n";
        echo "Output Current: ".$this->getOutputCurrent()." mA\n";
        echo "Output Voltage: ".($this->getOutputVoltage() * 10)." mV\n";
        echo "Time Left     : ".$this->getTimeLeft()." s\n";
    }
}
