<?php

namespace Kea\Brixo;

class Characteristic
{

    /**
     * @param parent : a Peripheral instance
     * @param args : args returned by ble_evt_attclient_find_information_found
     * @return
     */
    public function __construct($parent, $handle, $uuid)
    {
        $this->p = parent
        $this->handle = handle
        $this->uuid = uuid
        $this->byte_value = []
        $this->notify_cb = None
   }

    public function pack()
    {
        """
        Subclasses should override this to serialize any instance members
        that need to go in to $this->byte_value
        :return:
        """
        pass
   }

    public function unpack()
    {
        """
        Subclasses should override this to unserialize any instance members
        from $this->byte_value
        :return:
        """
        pass
   }

    public function write()
    {
        $this->pack()
        $this->p.writeByHandle(.handle,$this->byte_value)
   }

    public function read()
    {
        $this->byte_value = $this->p.readByHandle(.handle)
        $this->unpack()
   }

    public function onNotify(
        $new_value
    ) {
        $this->byte_value = $new_value
        $this->unpack()
        if ($this->notify_cb) {
            $this->notify_cb(.handle, $this->byte_value);}
   }

    public function enableNotify(
        $enable,
        $cb
    ) {
        $this->p.enableNotify(.uuid, enable);
        $this->notify_cb = cb;
   }

    public function __hash__()
    {
        return $this->handle;
    }

    public function __str__()
    {
        return str(.handle) +":\t" + str(.uuid);
}
}