<?php

namespace Kea\Bluegiga;

class Peripheral
{
    const UUID_LENGTH = ["\x02" => 2, "\x03" => 2, "\x04" => 4, "\x05" => 4, "\x06" => 16, "\x07" => 16];
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
    private $notifyHandlerBuffer;
    private $debug = false;
    private $connectionInterval;

    public function __construct($args, BGWrapper $BGWrapper)
    {
        $this->sender = $args['sender'];
        $this->rssi = $args['rssi'];
        $this->atype = $args['address_type'];
        $this->connection = -1;
        $adServices = [];
        $field = '';
        $bytesLeft = 0;
        $this->BGWrapper = $BGWrapper;

        for ($j = 0, $jMax = strlen($args['data']); $j < $jMax; $j++) {
            if ($bytesLeft === 0) {
                $bytesLeft = \ord($args['data'][$j]);
                $field = '';
            } else {
                $field .= $args['data'][$j];
                $bytesLeft--;
                if (($bytesLeft === 0) && (isset(self::UUID_LENGTH[$field[0]]))) {
                    $uuidLength = self::UUID_LENGTH[$field[0]];
                    $uuidCount = (\strlen($field) - 1) / $uuidLength;
                    for ($i = 0; $i < $uuidCount; $i++) {
                        $adServices[] = UUID::fromBinary(\substr($field, $i * $uuidLength + 1, $uuidLength));
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

    public function __toString()
    {
        $s = $this->macAddress();
        $s .= sprintf("\t%d", $this->rssi);
        if (!empty($this->adServices)) {
            $s .= "\n".implode("\t", $this->adServices);
        }

        return $s;
    }

    public function connect(): bool
    {
        return $this->BGWrapper->connect($this);
    }

    public function disconnect()
    {
        $this->BGWrapper->disconnect($this->connection);
    }

    public function discover()
    {
        $this->log("Service Groups: \n");
        $groups = $this->BGWrapper->discoverServiceGroups($this->connection);
        foreach ($groups as $group) {
            $this->log("SG: ".bin2hex(strrev($group['uuid']))."\n");
            $group = $this->BGWrapper->discoverCharacteristics($this->connection, $group['start'], $group['end']);
            foreach ($group as $i => $c) {
                $this->log("C $i H {$c['chrhandle']}:".bin2hex(strrev($c['uuid']))."\n");
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
            throw new \Exception("Failed to get Handle for ".bin2hex(strrev($uuid)));
        }

        return $rval[0];
    }

    public function readByHandle($charHandle)
    {
        return $this->BGWrapper->read($this->connection, $charHandle);
    }

    public function writeByHandle($charHandle, $payload)
    {
        $this->BGWrapper->write($this->connection, $charHandle, $payload);
    }

    private function writecommandByHandle($charHandle, $payload)
    {
        $this->BGWrapper->writeCommand($this->connection, $charHandle, $payload);
    }

    public function read(UUID $uuid)
    {
        return $this->readByHandle($this->findHandleForUUID($uuid));
    }

    private function write(UUID $uuid, $payload)
    {
        $this->writeByHandle($this->findHandleForUUID($uuid), $payload);
    }

    public function writeCommand(UUID $uuid, $payload)
    {
        $this->writecommandByHandle($this->findHandleForUUID($uuid), $payload);
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
        $notifyUuid = UUID::fromInt(0x2902);
        $baseHandle = $this->findHandleForUUID($uuid);
        $testHandle = $baseHandle + 1;
        while (true) {
            if ($testHandle - $baseHandle > 3) {
                throw new \RuntimeException(
                    "Trying to enable notification for a characteristic that doesn't allow it!"
                );
            }
            if ($this->characteristics[$testHandle]->getUUID() == $notifyUuid) {
                break;
            }
            $testHandle++;
        }
        $payload = ($enable ? "\x01" : "\x00") . "\x00";

        return $this->writeByHandle($testHandle, $payload);

    }

    public function enableNotifyForUUID($uuid)
    {
        $this->characteristics[$this->findHandleForUUID($uuid)]->enableNotify(true, [$this, 'notifyHandler']);
    }

    public function notifyHandler($handle, $byteValue)
    {
        $this->notifyHandlerBuffer[$handle] = $byteValue;
    }

    public function readNotifyValue($uuid)
    {
        $handle = $this->findHandleForUUID($uuid);
        $this->BGWrapper->idle();
        while (empty($this->notifyHandlerBuffer[$handle])) {
            $this->BGWrapper->idle();
        }

        return $this->notifyHandlerBuffer[$handle];
    }

    private function log(string $message)
    {
        if ($this->debug) {
            echo $message;
        }
    }

    public function getSender()
    {
        return $this->sender;
    }

    public function getMac()
    {
        return implode(':', str_split(bin2hex(strrev($this->sender)), 2));
    }

    public function getRssi()
    {
        return $this->rssi;
    }

    public function getAType()
    {
        return $this->atype;
    }

    public function getName()
    {
        return $this->readByHandle($this->findHandleForUUID(UUID::fromInt(0x2A00)));
    }

    public function getConnectionInterval()
    {
        return $this->connectionInterval;
    }

    public function setRssi($rssi)
    {
        $this->rssi = $rssi;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    public function setInterval(int $connectionInterval)
    {
        $this->connectionInterval = $connectionInterval;
    }

}
