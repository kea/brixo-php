<?php

namespace Kea\Bluegiga;

class Peripheral
{
    private $sender;
    private $rssi;
    private $atype;
    private $conn_handle;
    private $chars = [];
    /**
     * @var BGWrapper
     */
    private $BGWrapper;

    public function __construct($args, BGWrapper $BGWrapper)
    {
        $this->sender = $args['sender'];
        $this->rssi = $args['rssi'];
        $this->atype = $args['address_type'];
        $this->conn_handle = -1;
        $this->readString = [];
        $ad_services = [];
        $this_field = [];
        $bytes_left = 0;
        $this->BGWrapper = $BGWrapper;

        for ($j = 0, $jMax = strlen($args['data']); $j < $jMax; $j++) {
            if ($bytes_left === 0) {
                $bytes_left = \ord($args['data'][$j]);
                $this_field = [];
            } else {
                $this_field[] = $args['data'][$j];
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
        }
        $this->ad_services = $ad_services;

        /*
        $this->ble->getEventManager()->add(
            'ble_evt_attclient_attribute_value',
            function ($args) {
                if ($args['connection'] !== $this->conn_handle) {
                    return;
                }
                if (!$this->chars->has_key($args['atthandle'])) {
                    return;
                }
                $this->chars[$args['atthandle']]->onNotify($args['value']);
            }
        );
        */
    }

    public function connect($scan_result)
    {
        $this->conn_handle = $this->BGWrapper->connect($scan_result);
    }

    public function disconnect()
    {
        $this->BGWrapper->disconnect($this->conn_handle);
    }

    public function discover()
    {
        $groups = $this->BGWrapper->discoverServiceGroups($this->conn_handle);
        print "Service Groups:";
        foreach ($groups as $group) {
            print UUID($group['uuid']);
        }
        foreach ($groups as $group) {
            $new_group = $this->BGWrapper->discoverCharacteristics($this->conn_handle, $group['start'], $group['end']);
            foreach ($new_group as $c) {
                $c['uuid'];
                $new_c = new Characteristic($c['chrhandle'], UUID($c['uuid']));
                $this->chars[$new_c->handle] = $new_c;
                print new_c;
            }
        }
    }

    private function mac_address()
    {
        return ':'.array_reduce(
                unpack('C*', $this->sender),
                function ($result, $c) {
                    return $result.sprintf('%02X', $c);
                }
            );
    }

    private function findHandleForUUID($uuid)
    {
        $rval = [];
        foreach ($this->chars as $c) {
            if ($c->uuid == $uuid) {
                $rval[] = $c->handle;
            }
        }
        if (count($rval) !== 1) {
            throw new \Exception("Failed to get Handle");
        }

        return $rval[0];
    }

    private function readByHandle($char_handle)
    {
        return $this->BGWrapper->read($this->conn_handle, $char_handle);
    }

    private function writeByHandle($char_handle, $payload)
    {
        return $this->BGWrapper->write($this->conn_handle, $char_handle, $payload);
    }

    private function writecommandByHandle($char_handle, $payload)
    {
        return $this->BGWrapper->writeCommand($this->conn_handle, $char_handle, $payload);
    }

    private function read($uuid)
    {
        return $this->readByHandle($this->findHandleForUUID($uuid));
    }

    private function write($uuid, $payload)
    {
        return $this->writeByHandle($this->findHandleForUUID($uuid), $payload);
    }

    private function writecommand($uuid, $payload)
    {
        return $this->writecommandByHandle($this->findHandleForUUID($uuid), $payload);
    }

    /**
     * We need to find the characteristic configuration for the provided UUID
     * The characteristic configuration is a characteristic in its own right with UUID 0x2902
     * It will be located a few handles above the characteristic it controls in the table
     * To find it, we will simply iterate up the table a few slots looking for a characteristic with UUID 0x2902
     *
     * @param $uuid
     * @param $enable
     * @return mixed
     * @throws \Exception
     */
    public function enableNotify($uuid, $enable)
    {
        $notify_uuid = UUID(0x2902);
        $base_handle = $this->findHandleForUUID($uuid);
        $test_handle = $base_handle + 1;
        while (true) {
            if ($test_handle - $base_handle > 3) {
                throw new \RuntimeException(
                    "Trying to enable notification for a characteristic that doesn't allow it!"
                );
            }
            if ($this->chars[$test_handle]->uuid == $notify_uuid) {
                break;
            }
            $test_handle++;
        }
        $payload = [($enable ? 1 : 0), 0];

        return $this->writeByHandle($test_handle, $payload);

    }

    private function enableNotifyForUUID($uuid)
    {
        $this->chars[$this->findHandleForUUID($uuid)]->enableNotify(true, $this->notifyHandler);
    }

    private function notifyHandler($handle, $byte_value)
    {
        $this->readString[$handle] = $byte_value;
    }

    private function readNotifyValue($uuid)
    {
        $handle = $this->findHandleForUUID($uuid);
        $this->BGWrapper->idle();
        while (!empty($this->readString[$handle])) {
            $this->BGWrapper->idle();
        }

        return $this->readString[$handle];
    }

    /**
     * Provides a means to register subclasses of Characteristic with the Peripheral
     * @param $new_char Characteristic instance of Characteristic or subclass with UUID set.  Handle does not need to be set
     */
    private function replaceCharacteristic(Characteristic $new_char)
    {
        $new_char->handle = $this->findHandleForUUID($new_char->uuid);
        $this->chars[$new_char->handle] = $new_char;
    }

    public function getSender()
    {
        return $this->sender;
    }

    public function setRssi($rssi)
    {
        $this->rssi = $rssi;
    }

    public function getRssi()
    {
        return $this->rssi;
    }

    public function getAType()
    {
        return $this->atype;
    }

    public function __toString()
    {
        $s = $this->mac_address();
        $s .= sprintf("\t%d", $this->rssi);
        if (!empty($this->ad_services)) {
            $s .= "\n".implode("\t", $this->ad_services);
        }

        return $s;
    }
}