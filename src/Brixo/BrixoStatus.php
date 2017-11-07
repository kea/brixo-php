<?php

namespace Kea\Brixo;

class BrixoStatus
{
    private $status;

    public function __construct(int $status)
    {
        $this->status = $status;
    }

    public static function fromChar(string $status): BrixoStatus
    {
        if ('' === $status) {
            throw new \InvalidArgumentException('Status should be one character');
        }

        return new self(ord($status[0]));
    }

    public function getStandby(): bool
    {
        return ($this->status & 0x01) !== 0;
    }

    public function getCW(): bool
    {
        return ($this->status & 0x02) !== 0;
    }

    public function getCCW(): bool
    {
        return ($this->status & 0x04) !== 0;
    }

    public function getOC(): bool
    {
        return ($this->status & 0x08) !== 0;
    }

    public function getWarning(): bool
    {
        return ($this->status & 0x10) !== 0;
    }

    public function getOverload(): bool
    {
        return ($this->status & 0x20) !== 0;
    }

    public function getUSB(): bool
    {
        return ($this->status & 0x40) !== 0;
    }

    public function getStreaming(): bool
    {
        return ($this->status & 0x80) !== 0;
    }
}
