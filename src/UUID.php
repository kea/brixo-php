<?php

namespace Kea;

class UUID
{
    private $bytes;

    private function __construct($initializer)
    {
        if (is_string($initializer)) {
            $this->bytes = $this->stringToBytes($initializer);
        } elseif (is_int($initializer)) {
            $this->bytes = [($initializer >> 8) & 0xFF, ($initializer >> 0) & 0xFF];
        } elseif (is_array($initializer) && (count($initializer) === 2)) {
            $this->bytes = $initializer;
        } else {
            throw new \InvalidArgumentException('UUID should be init, string or array');
        }
    }

    private function stringToBytes($arg)
    {
        $arg = strtoupper($arg);
        $arg = strtr($arg, ['-' => '']);

        return \unpack('H*', $arg);
    }

    private function bytesToString($bytes)
    {
        throw new \Exception('Not implemented');
        /*
        $l = ["%02X" % $bytes[i] for i in range(len(bytes))]
        if (count($bytes) == 16){
            l = ["%02X" % bytes[i] for i in range(16)]
            l.insert(4, '-')
            l.insert(7, '-')
            l.insert(10, '-')
            l.insert(13, '-')
        }
        return ''.join(l)
        */
    }

    private function asString()
    {
        return $this->bytesToString($this->bytes);
    }

    public function __toString()
    {
        return $this->asString();
    }
}