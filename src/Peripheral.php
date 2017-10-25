<?php

namespace Kea\Brixo;

class Peripheral
{
    private $sender;
    private $rssi;
    private $atype;
    private $conn_handle;
    private $chars;


    public function __construct($args)
    {
        /**
         * This is meant to be initialized from a ble_evt_gap_scan_response
         * :param args: args passed to ble_evt_gap_scan_response
         * :return:
         */

        $this->sender = $args['sender'];
        $this->rssi = $args['rssi'];
        $this->atype = $args['address_type'];
        $this->conn_handle = -1;
        $this->chars = [];
        $this->readString = [];
        $ad_services = [];
        $this_field = [];
        $bytes_left = 0;
        foreach ($args['data'] as $b) {
            if ($bytes_left == 0) {
                $bytes_left = \ord($b);
                $this_field = [];
            } else {
                $this_field[] = $b;
                $bytes_left--;
                if ($bytes_left === 0) {
                    if (($this_field[0] === 0x02) || ($this_field[0] === 0x03)) { # partial or complete list of 16-bit UUIDs
                        for ($i = 0; $i < (\strlen($this_field) - 1) / 2; $i++) {
                            $ad_services[] = UUID(\substr($this_field, $i * 2 + 1, 2));
                        }
                    }

                    if (($this_field[0] === 0x04) || ($this_field[0] === 0x05)) { # partial or complete list of 32-bit UUIDs
                        for ($i = 0; $i < (\strlen($this_field) - 1) / 4; $i++) {
                            $ad_services[] = UUID(\substr($this_field, $i * 4 + 1, 4));
                        }
                    }
                    if (($this_field[0] === 0x06) || ($this_field[0] === 0x07)) { # partial or complete list of 128-bit UUIDs
                        for ($i = 0; $i < (\strlen($this_field) - 1) / 16; $i++) {
                            $ad_services[] = UUID(\substr($this_field, $i * 16 + 1, 2));
                        }
                    }
                }
            }
            $this->ad_services = $ad_services;

            # Route the callbacks on notification
            $this->ble->ble_evt_attclient_attribute_value->add(
                function ($bglib_instance, $args) {
                    if ($args['connection'] !== $this->conn_handle) {
                        return;
                    }
                    if (!$this->chars->has_key($args['atthandle'])) {
                        return;
                    }
                    $this->chars[$args['atthandle']]->onNotify($args['value']);
                }
            );
        }
    }

    public function connect()
    {
        $this->conn_handle = connect();
    }

    public function disconnect()
    {
        disconnect($this->conn_handle);
    }

    private function discover()
    {
        groups = discoverServiceGroups(self.conn_handle);
        print "Service Groups:"
            for group in groups:
                print UUID(group['uuid'])
            for group in groups:
                new_group = discoverCharacteristics(self.conn_handle, group['start'], group['end'])
                for c in new_group:
                    # FIXME: For some reason the UUIDs are backwards
                    c['uuid'].reverse()
                    new_c = Characteristic(self, c['chrhandle'], UUID(c['uuid']))
                    self.chars[new_c.handle] = new_c
                    print new_c
        }
    private function mac_address()
    {
        return ':'.join(['%02X' % b for b in self.sender[::-1]])}

    private function findHandleForUUID(self, uuid):
        rval = []
        for c in self.chars.values():
            if c.uuid == uuid:
                rval.append(c.handle)
        if len(rval) != 1:
            raise Exception("Failed to get Handle")
        return rval[0]
    def readByHandle(self, char_handle):
        return read(self.conn_handle, char_handle)
    def writeByHandle(self, char_handle, payload):
        return write(self.conn_handle, char_handle, payload)
    def writecommandByHandle(self, char_handle, payload):
        return writecommand(self.conn_handle, char_handle, payload)
    def read(self, uuid):
        return self.readByHandle(self.findHandleForUUID(uuid))
    def write(self, uuid, payload):
        return self.writeByHandle(self.findHandleForUUID(uuid), payload)
    def writecommand(self, uuid, payload):
        return self.writecommandByHandle(self.findHandleForUUID(uuid), payload)
    def enableNotify(self, uuid, enable):
        # We need to find the characteristic configuration for the provided UUID
        # The characteristic configuration is a characteristic in its own right with UUID 0x2902
        # It will be located a few handles above the characteristic it controls in the table
        # To find it, we will simply iterate up the table a few slots looking for a characteristic with UUID 0x2902
        notify_uuid = UUID(0x2902)
        base_handle = self.findHandleForUUID(uuid)
        test_handle = base_handle + 1
        while true:
            if test_handle - base_handle > 3:
                # FIXME: I'm not sure what the error criteria should be, but if we are trying to enable
                # notifications for a characteristic that won't accept it we need to throw an error
                raise Exception("Trying to enable notification for a characteristic that doesn't allow it!")
            if self.chars[test_handle].uuid == notify_uuid:
                break
                test_handle += 1
        #test_handle now points at the characteristic config
        if (enable):
            payload = (1,0)
        else:
            payload = (0,0)
        return self.writeByHandle(test_handle, payload)

    def enableNotifyForUUID(self, uuid):
        self.chars[self.findHandleForUUID(uuid)].enableNotify(true, self.notifyHandler)

    def notifyHandler(self, handle, byte_value):
        #self.readString[handle] = "".join(["%02X:" % b for b in byte_value])
        self.readString[handle] = byte_value


    def readNotifyValue(self, uuid):
        handle = self.findHandleForUUID(uuid)
        idle()
        while not self.readString.has_key(handle) or self.readString[handle] == '':
            idle()
        return self.readString[handle]

    def replaceCharacteristic(self, new_char):
        """
        Provides a means to register subclasses of Characteristic with the Peripheral
        :param new_char: Instance of Characteristic or subclass with UUID set.  Handle does not need to be set
        :return:
        """
        handles_by_uuid = dict((c.uuid,c.handle) for c in self.chars.values())
            new_char.handle = handles_by_uuid[new_char.uuid]
        self.chars[new_char.handle] = new_char
    def __eq__(self, other):
        if isinstance(other, self.__class__):
            return self.sender == other.sender
        return false;

    public function __toString()
    {
        $s = ""
        l = ["%02X:" % self.sender[i] for i in range(6)]
        $s += "".join(l)
        $s += "\t%d" % self.rssi
        for service in self.ad_services:
            s += "\t"
            s += str(service)
        return s;

    }
}