<?php

namespace Kea\Bluegiga;

class Characteristic
{
    private $parent;
    private $handle;
    private $uuid;
    private $byteValue = [];
    private $notifyCallable;

    /**
     * @param $parent
     * @param $handle int args returned by ble_evt_attclient_find_information_found
     * @param $uuid
     */
    public function __construct(Peripheral $parent, $handle, UUID $uuid)
    {
        $this->parent = $parent;
        $this->handle = $handle;
        $this->uuid = $uuid;
    }

    public function write()
    {
        $this->parent->writeByHandle($this->handle, $this->byteValue);
    }

    public function read()
    {
        $this->byteValue = $this->parent->readByHandle($this->handle);
    }

    public function onNotify($newValue)
    {
        $this->byteValue = $newValue;
        if (is_callable($this->notifyCallable)) {
            call_user_func($this->notifyCallable, $this->handle, $this->byteValue);
        }
    }

    public function enableNotify(bool $enable, callable $callable)
    {
        $this->parent->enableNotify($this->uuid, $enable);
        $this->notifyCallable = $callable;
    }

    public function getHandle()
    {
        return $this->handle;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function setHandle($handle)
    {
        $this->handle = $handle;
    }

    public function __toString()
    {
        return $this->handle.":\t".$this->uuid;
    }
}
