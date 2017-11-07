<?php

namespace Kea\Bluegiga;

class UUID
{
    private $bytes;

    private function __construct()
    {
    }

    public static function fromArray(array $bytes): UUID
    {
        if (count($bytes) !== 2) {
            throw new \InvalidArgumentException('UUID should be init, string or array');
        }
        $uuid = new self();
        $uuid->bytes = $bytes;

        return $uuid;
    }

    public static function fromInt(int $int): UUID
    {
        $uuid = new self();
        $uuid->bytes = [($int >> 8) & 0xFF, $int & 0xFF];

        return $uuid;
    }

    public static function fromHexString(string $string): UUID
    {
        $uuid = new self();
        $uuid->bytes = self::hexStringToBytes($string);

        return $uuid;
    }

    public static function fromBinary(string $string): UUID
    {
        $uuid = new self();
        $uuid->bytes = [ord($string[1]), ord($string[0])];

        return $uuid;
    }

    private static function hexStringToBytes(string $arg)
    {
        $arg = \strtoupper($arg);
        $arg = \strtr($arg, ['-' => '']);

        return \array_shift(\unpack('H*', $arg));
    }

    public function __toString()
    {
        return chr($this->bytes[1]).chr($this->bytes[0]);
    }
}