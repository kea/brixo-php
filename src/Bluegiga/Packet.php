<?php

namespace Kea\Bluegiga;

class Packet
{
    private $type;
    private $payloadLength;
    private $class;
    private $command;
    private $payload;

    /**
     * Message constructor.
     * @param $type
     * @param $payloadLength
     * @param $class
     * @param $command
     * @param $payload
     */
    public function __construct($type, $payloadLength, $class, $command, $payload)
    {
        $this->type = $type;
        $this->payloadLength = $payloadLength;
        $this->class = $class;
        $this->command = $command;
        $this->payload = $payload;
    }

    public static function fromBinary(string $message)
    {
        $type = ord($message[0]);
        $payloadLength = ord($message[1]);
        $class = ord($message[2]);
        $command = ord($message[3]);
        $payload = substr($message, 4);

        return new self($type, $payloadLength, $class, $command, $payload);
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getPayloadLength(): int
    {
        return $this->payloadLength;
    }

    public function getClass(): int
    {
        return $this->class;
    }

    public function getCommand(): int
    {
        return $this->command;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function isCommandOrResponse()
    {
        return ($this->type & 136) === 0;
    }

    public function isEvent()
    {
        return ($this->type & 136) === 128;
    }

    public function __toString()
    {
        return chr($this->type).chr($this->payloadLength).chr($this->class).chr($this->command).$this->payload;
    }
}
