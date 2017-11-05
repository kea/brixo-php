<?php

namespace Kea\Bluegiga;

use Kea\UUID;

class Peripheral
{
    private $sender;
    private $rssi;
    private $atype;
    private $connection;
    private $characteristics = [];
    /**
     * @var BGWrapper
     */
    private $BGWrapper;
    private $adServices;

    public function __construct($args, BGWrapper $BGWrapper)
    {
        $this->sender = $args['sender'];
        $this->rssi = $args['rssi'];
        $this->atype = $args['address_type'];
        $this->connection = -1;
        $adServices = [];
        $field = [];
        $bytesLeft = 0;
        $this->BGWrapper = $BGWrapper;

        for ($j = 0, $jMax = strlen($args['data']); $j < $jMax; $j++) {
            if ($bytesLeft === 0) {
                $bytesLeft = \ord($args['data'][$j]);
                $field = [];
            } else {
                $field[] = $args['data'][$j];
                $bytesLeft--;
                if ($bytesLeft === 0) {
                    if (($field[0] === 0x02) || ($field[0] === 0x03)) {
                        for ($i = 0; $i < (\strlen($field) - 1) / 2; $i++) {
                            $adServices[] = UUID::fromBinary(\substr($field, $i * 2 + 1, 2));
                        }
                    }
                    if (($field[0] === 0x04) || ($field[0] === 0x05)) {
                        for ($i = 0; $i < (\strlen($field) - 1) / 4; $i++) {
                            $adServices[] = UUID::fromBinary(\substr($field, $i * 4 + 1, 4));
                        }
                    }
                    if (($field[0] === 0x06) || ($field[0] === 0x07)) {
                        for ($i = 0; $i < (\strlen($field) - 1) / 16; $i++) {
                            $adServices[] = UUID::fromBinary(\substr($field, $i * 16 + 1, 2));
                        }
                    }
                }
            }
        }
        $this->adServices = $adServices;
        $peripheral = $this;
        $this->BGWrapper->addEventHandler(
            'ble_evt_attclient_attribute_value',
            function ($args) use ($peripheral) {
                if ($args['connection'] !== $peripheral->connection) {
                    return;
                }
                if (!isset($peripheral->characteristics[$args['atthandle']])) {
                    return;
                }
                $this->characteristics[$args['atthandle']]->onNotify($args['value']);
            }
        );
    }

    public function connect()
    {
        $this->connection = $this->BGWrapper->connect($this);
    }

    public function disconnect()
    {
        $this->BGWrapper->disconnect($this->connection);
    }

    public function discover()
    {
        echo "Service Groups: \n";
        $groups = $this->BGWrapper->discoverServiceGroups($this->connection);
        foreach ($groups as $group) {
            echo "SG: ".bin2hex($group['uuid'])."\n";
            $group = $this->BGWrapper->discoverCharacteristics($this->connection, $group['start'], $group['end']);
            foreach ($group as $i => $c) {
                echo "C $i:".bin2hex($c['uuid'])."\n";
                $characteristic = new Characteristic($this, $c['chrhandle'], UUID::fromBinary($c['uuid']));
                $this->characteristics[$characteristic->getHandle()] = $characteristic;
            }
        }
    }

    public function macAddress()
    {
        return $this->sender->getReadable();
    }

    private function findHandleForUUID(UUID $uuid)
    {
        $rval = [];
        foreach ($this->characteristics as $c) {
            if ($c->getUUID() == $uuid) {
                $rval[] = $c->getHandle();
            }
        }
        if (count($rval) !== 1) {
            throw new \Exception("Failed to get Handle");
        }

        return $rval[0];
    }

    private function readByHandle($char_handle)
    {
        return $this->BGWrapper->read($this->connection, $char_handle);
    }

    private function writeByHandle($char_handle, $payload)
    {
        return $this->BGWrapper->write($this->connection, $char_handle, $payload);
    }

    private function writecommandByHandle($char_handle, $payload)
    {
        return $this->BGWrapper->writeCommand($this->connection, $char_handle, $payload);
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
        $notify_uuid = UUID::fromInt(0x2902);
        $base_handle = $this->findHandleForUUID($uuid);
        $test_handle = $base_handle + 1;
        while (true) {
            if ($test_handle - $base_handle > 3) {
                throw new \RuntimeException(
                    "Trying to enable notification for a characteristic that doesn't allow it!"
                );
            }
            if ($this->characteristics[$test_handle]->getUUID() == $notify_uuid) {
                break;
            }
            $test_handle++;
        }
        $payload = [$enable ? 1 : 0, 0];

        return $this->writeByHandle($test_handle, $payload);

    }

    public function enableNotifyForUUID($uuid)
    {
        $this->characteristics[$this->findHandleForUUID($uuid)]->enableNotify(true, $this->notifyHandler);
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
     * @param $newChar Characteristic instance of Characteristic or subclass with UUID set.  Handle does not need to be set
     */
    private function replaceCharacteristic(Characteristic $newChar)
    {
        $newChar->setHandle($this->findHandleForUUID($newChar->getUuid()));
        $this->characteristics[$newChar->getHandle()] = $newChar;
    }

    public function getSender()
    {
        return $this->sender;
    }

    public function getMac()
    {
        return implode(':', str_split(bin2hex(strrev($this->sender)), 2));
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
        $s = $this->macAddress();
        $s .= sprintf("\t%d", $this->rssi);
        if (!empty($this->adServices)) {
            $s .= "\n".implode("\t", $this->adServices);
        }

        return $s;
    }
}