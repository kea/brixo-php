<?php

namespace Kea\Bluegiga;

use Kea\Serial;
use Kea\UUID;

class BGWrapper
{
    private $serial;
    private $ble;

    const uuid_service = [0x28, 0x00];

    /**
     * BGWrapper constructor.
     */
    public function __construct(Serial $serial)
    {
        $this->serial = $serial;
        $this->ble = new BGLib();

        $this->disconnect(0);
        $this->stopScan();
    }

    public function idle(): void
    {
        $this->ble->checkActivity($this->serial);
    }

    private function startScan(): void
    {
        $this->ble->sendCommand($this->serial, $this->ble->ble_cmd_gap_set_scan_parameters(0xC8, 0xC8, 1));
        $this->ble->sendCommand($this->serial, $this->ble->ble_cmd_gap_discover(1));
        $this->ble->checkActivity($this->serial);
    }

    private function stopScan(): void
    {
        $this->ble->sendCommand($this->serial, $this->ble->ble_cmd_gap_end_procedure());
        $this->ble->checkActivity($this->serial);
    }

    /**
     * @param int $duration
     * @param int $stopAfter
     * @return Peripheral[]
     */
    public function scan(int $duration, int $stopAfter = 0)
    {
        $results = [];

        $this->ble->getEventHandler()->add(
            'ble_evt_gap_scan_response',
            function ($args) use (&$results) {
                $found = false;
                foreach ($results as $resp) {
                    if ($args['sender'] === $resp->getSender()) {
                        $resp->setRssi($args['rssi']);
                        $found = true;
                    }
                }
                if (!$found) {
                    $results[] = new Peripheral($args, $this);
                }
            }
        );
        echo "Scanning...\n";
        $this->startScan();
        $this->ble->checkActivity($this->serial, $duration * 1000000);
        $this->stopScan();
        $this->ble->getEventHandler()->remove('ble_evt_gap_scan_response');

        return $results;
    }

    public function connect(Peripheral $peripheral)
    {
        # Connection intervals have units of 1.25ms
        $this->ble->sendCommand(
            $this->serial,
            $this->ble->ble_cmd_gap_connect_direct($peripheral->getSender(), $peripheral->getAType(), 30, 60, 0x100, 0)
        );

        $result = [];

        $this->ble->getEventHandler()->add(
            'ble_evt_connection_status',
            function ($args) use (&$result) {
                if (($args['flags'] & 0x05) === 0x05) {
                    echo 'Connected to: '.$args['address']->getReadable()."\n";
                    echo 'Interval: '.($args['conn_interval'] / 1.25)."ms\n";
                    $result[] = $args['connection'];
                }
            }
        );

        while (count($result) === 0) {
            $this->ble->checkActivity($this->serial);
        }
        $this->ble->getEventHandler()->remove('ble_evt_connection_status');

        return $result[0];
    }

    public function discoverServiceGroups($connection)
    {
        $serviceGroups = [];
        $discoverDone = false;
        $this->ble->sendCommand(
            $this->serial,
            $this->ble->ble_cmd_attclient_read_by_group_type(
                $connection,
                0x0001,
                0xFFFF,
                UUID::fromArray(self::uuid_service)
            )
        );

        $this->ble->getEventHandler()->add(
            'ble_evt_attclient_group_found',
            function ($args) use ($connection, &$serviceGroups) {
                if ($args['connection'] === $connection) {
                    $serviceGroups[] = $args;
                }
            }
        );
        $this->ble->getEventHandler()->add(
            'ble_evt_attclient_procedure_completed',
            function ($args) use ($connection, &$discoverDone) {
                if ($args['connection'] === $connection) {
                    $discoverDone = true;
                }
            }
        );

        while (!$discoverDone) {
            $this->ble->checkActivity($this->serial);
        }

        $this->ble->getEventHandler()->remove('ble_evt_attclient_group_found');
        $this->ble->getEventHandler()->remove('ble_evt_attclient_procedure_completed');

        return $serviceGroups;
    }

    public function discoverCharacteristics($connection, $handle_start, $handle_end)
    {
        $characteristics = [];
        $discoverDone = false;
        $this->ble->sendCommand(
            $this->serial,
            $this->ble->ble_cmd_attclient_find_information($connection, $handle_start, $handle_end)
        );
        $this->ble->checkActivity($this->serial);

        $this->ble->getEventHandler()->add(
            'ble_evt_attclient_find_information_found',
            function ($args) use ($connection, &$characteristics) {
                if ($args['connection'] === $connection) {
                    $characteristics[] = $args;
                }
            }
        );
        $this->ble->getEventHandler()->add(
            'ble_evt_attclient_procedure_completed',
            function ($args) use ($connection, &$discoverDone) {
                if ($args['connection'] === $connection) {
                    $discoverDone = true;
                }
            }
        );

        while (!$discoverDone) {
            $this->ble->checkActivity($this->serial);
        }

        $this->ble->getEventHandler()->remove('ble_evt_attclient_find_information_found');
        $this->ble->getEventHandler()->remove('ble_evt_attclient_procedure_completed');

        return $characteristics;
    }

    public function disconnect($conn): void
    {
        $this->ble->sendCommand($this->serial, $this->ble->ble_cmd_connection_disconnect($conn));
        $this->ble->checkActivity($this->serial);
    }

    public function read($conn, $handle)
    {
        $this->ble->sendCommand($this->serial, $this->ble->ble_cmd_attclient_read_by_handle($conn, $handle));
        $result = [];
        $payload = [];
        $fail = [];

        $this->ble->ble_rsp_attclient_read_by_handle->add(
            function ($args) use (&$result, $conn) {
                if ($args['connection'] == $conn) {
                    $result[] = $args['result'];
                }
            }
        );
        $this->ble->ble_evt_attclient_attribute_value->add(
            function ($args) use (&$payload, $conn) {
                if ($args['connection'] == $conn) {
                    $payload[] = $args['value'];
                }
            }
        );
        $this->ble->ble_evt_attclient_procedure_completed->add(
            function ($args) use (&$fail, $conn) {
                if ($args['connection'] == $conn) {
                    $fail[] = 0;
                }
            }
        );
        while (true) {
            $this->ble->checkActivity($this->serial);
            if (count($result) && $result[0]) {
                #There was a read error
                break;
            }
            if (count($fail)) {
                #Command was processed correctly but we still failed
                break;
            }
            if (count($payload)) {
                break;
            }
        }
        $this->ble->ble_rsp_attclient_read_by_handle->removeLastCallback();
        $this->ble->ble_evt_attclient_attribute_value->removeLastCallback();
        $this->ble->ble_evt_attclient_procedure_completed->removeLastCallback();

        if ($result[0] || count($fail)) {
            return [];
        }

        return $payload[0];
    }

    public function write($connection, $handle, $value): void
    {
        if ($handle == 0) {
            print 'Invalid handle! Did you forget a call to Peripheral.replaceCharacteristic(c)?';
        }
        $this->ble->sendCommand($this->serial, $this->ble->ble_cmd_attclient_attribute_write($connection, $handle, $value));
        $result = [];

        $this->ble->getEventHandler()->add(
            'ble_rsp_attclient_attribute_write',
            function ($args) use (&$result, $connection) {
                if ($args['connection'] == $connection) {
                    $result[] = null;
                }
            }
        );
        $this->ble->getEventHandler()->add(
            'ble_evt_attclient_procedure_completed',
            function ($args) use (&$result, $connection) {
                if ($args['connection'] == $connection) {
                    $result[] = null;
                }
            }
        );

        while (count($result) < 2) {
            $this->idle();
        }
        $this->ble->getEventHandler()->remove('ble_rsp_attclient_attribute_write');
        $this->ble->getEventHandler()->remove('ble_evt_attclient_procedure_completed');
    }

    public function writeCommand($conn, $handle, $value): void
    {
        if ($handle === 0) {
            print 'Invalid handle! Did you forget a call to Peripheral.replaceCharacteristic(c) ?';
        }
        $this->ble->sendCommand($this->serial, $this->ble->ble_cmd_attclient_write_command($conn, $handle, $value));
        $this->ble->checkActivity($this->serial);
    }

    public function addEventHandler($eventName, $callback)
    {
        $this->ble->getEventHandler()->add(
            $eventName,
            $callback
        );
    }
}
