<?php

namespace Kea;

class Serial
{
    private $handle;

    /**
     * Serial constructor.
     * @param string $device es. Windows "\.\com4", Mac "/dev/tty.xyk", Linux "/dev/ttySxx"
     * @throws \RuntimeException
     */
    public function __construct(string $device)
    {
        $this->handle = \fopen($device, 'r+nb');
        if (!\is_resource($this->handle)) {
            throw new \RuntimeException("Can't open device \"$device\"");
        }
        $this->setSpeed($device);
    }

    public function write(string $content): void
    {
        \fflush($this->handle);
        \fwrite($this->handle, $content);
    }

    private function read()
    {
        usleep(500000);
        $read = \fread($this->handle, 1500);
        echo 'Read: '.bin2hex($read)."\n";

        return $read;
    }

    /**
     * @param $timeout int microseconds
     * @return bool|string
     * @throws \RuntimeException
     */
    public function blockingRead(int $timeout)
    {
        fflush($this->handle);
        $null = [];
        $read = [$this->handle];
        if (false === ($ready = \stream_select($read, $null, $null, 0, $timeout))) {
            throw new \RuntimeException('something went wrong reading stream');
        }
        if ($ready > 0) {
            return $this->read();
        }

        return false;
    }

    /**
     * @param string $device
     * @throws \RuntimeException
     */
    private function setSpeed(string $device): void
    {
        $speed = 115200;
        $flags = 'clocal';
        if (stripos(PHP_OS, 'win') === 0) {
            throw new \RuntimeException('Windows not yes supporter');
        } elseif (stripos(PHP_OS, 'darwin') === 0) {
            `stty -f $device speed $speed $flags`;
        } elseif (stripos(PHP_OS, 'linux') === 0) {
            `stty -F $device speed $speed $flags`;
        }
    }
}
