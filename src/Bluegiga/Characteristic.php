<?php

namespace Kea\Bluegiga;

class Characteristic
{
    private $parent;
    private $handle;
    private $uuid;
    private $byte_value = [];
    private $notify_cb;

    /**
     * @param $parent
     * @param $handle ??? args returned by ble_evt_attclient_find_information_found
     * @param $uuid
     */
    public function __construct(Peripheral $parent, $handle, $uuid)
    {
        $this->parent = $parent;
        $this->handle = $handle;
        $this->uuid = $uuid;
    }

    /**
     * Subclasses should override this to serialize any instance members
     * that need to go in to $this->byte_value
     */
    public function pack()
    {
        $this->parent->pack();
    }

    /**
     * Subclasses should override this to unserialize any instance members
     * from $this->byte_value
     */
    public function unpack()
    {
        return $this->parent->unpack();
    }

    public function write()
    {
        $this->pack();
        $this->parent->writeByHandle($this->handle, $this->byte_value);
    }

    public function read()
    {
        $this->byte_value = $this->parent->readByHandle($this->handle);
        $this->unpack();
    }

    public function onNotify($new_value)
    {
        $this->byte_value = $new_value;
        $this->unpack();
        if ($this->notify_cb) {
            $this->notify_cb($this->handle, $this->byte_value);
        }
    }

    public function enableNotify($enable, $cb)
    {
        $this->parent->enableNotify($this->uuid, $enable);
        $this->notify_cb = $cb;
    }

    public function __toString()
    {
        return $this->handle.":\t".$this->uuid;
    }
}
