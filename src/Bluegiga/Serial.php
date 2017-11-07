<?php

namespace Kea\Bluegiga;

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
        return \fread($this->handle, 1500);
    }

    /**
     * @param $timeout int microseconds
     * @return bool|string
     * @throws \RuntimeException
     */
    public function blockingRead(int $timeout)
    {
        $start = microtime(true);
        fflush($this->handle);
        usleep(10000);
        $null = [];
        $readStreams = [$this->handle];
        $read = '';
        $timeLeft = $timeout;
        do {
            if (false === ($ready = \stream_select($readStreams, $null, $null, 0, $timeLeft))) {
                throw new \RuntimeException('something went wrong reading stream');
            }
            if ($ready > 0) {
                $read .= $this->read();
            }
            $timeLeft = (int)($timeout - ((microtime(true) - $start) * 10**6));
        } while ($timeLeft > 0);

        return $read === '' ? false : $read;
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
