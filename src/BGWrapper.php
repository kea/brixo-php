<?php

namespace Kea\Brixo;

class BGWrapper
{
    private $ser;
    private $ble;

    const uuid_service = [0x28, 0x00];  # 0x2800

    /**
     * BGWrapper constructor.
     * @param string $port es. Windows "\.\com4", Mac "/dev/tty.xyk", Linux "/dev/ttySxx"
     */
    public function __construct(string $port)
    {
        $this->ser = fopen($port, 'r+b');
        fflush($this->ser);
        $this->ble = new BGLib();
        $this->ble->packet_mode = false;
        $this->ble->debug = false;

        $this->disconnect(0);
        $this->stopScan();
    }

    private function idle()
    {
        $this->ble->check_activity($this->ser);
    }

    private function startScan()
    {
        # set scan parameters
        $this->ble->send_command($this->ser, $this->ble->ble_cmd_gap_set_scan_parameters(0xC8, 0xC8, 1));
        $this->ble->check_activity($this->ser);
        # start scanning now
        $this->ble->send_command($this->ser, $this->ble->ble_cmd_gap_discover(1));
        $this->ble->check_activity($this->ser);
    }

    private function stopScan()
    {
        $this->ble->send_command($this->ser, $this->ble->ble_cmd_gap_end_procedure());
        $this->ble->check_activity($this->ser);
    }

    public function scan(int $duration, int $stopAfter = 0)
    {
        $results = [];

        $this->ble->ble_evt_gap_scan_response->add(
            function ($bglib_instance, $args) use (&$results) {
                $found = false;
                foreach ($results as $resp) {
                    if ($args['sender'] === $resp->sender) {
                        $resp->rssi = $args['rssi'];
                        $found = true;
                    }
                }
                if (!$found) {
                    $results[] = Peripheral($args);
                }
            }
        );
        $start_time = \time();

        $this->startScan();
        while ((time() - $start_time < $duration) && (($stopAfter !== 0) || (count($results) < $stopAfter))) {
            $this->ble->check_activity($this->ser);
        }
        $this->stopScan();
        $this->ble->ble_evt_gap_scan_response -= $this->scan_response_handler;

        return results;
    }

    private function connect($scan_result)
    {
        #Connects and returns connection handle
        $sr = $scan_result;
        # Connection intervals have units of 1.25ms
        $this->ble->send_command(
            $this->ser,
            $this->ble->ble_cmd_gap_connect_direct($sr->sender, $sr->atype, 30, 60, 0x100, 0)
        );
        # Check for the command response
        $this->ble->check_activity($this->ser);
        $result = [];

        $this->ble->ble_evt_connection_status->add(
            function ($bglib_instance, $args) use (&$result) {
                if (($args['flags'] & 0x05) === 0x05) {
                    echo 'Connected to :'.$args['address'];
                    echo 'Interval: '.($args['conn_interval'] / 1.25).'ms';
                    $result[] = $args['connection'];
                }
            }
        );

        while (count($result) === 0) {
            $this->ble->check_activity($this->ser);
        }
        $this->ble->ble_evt_connection_status->removeLastCallback();

        return $result[0];
    }

    /*
        private function discoverServiceGroups($conn)
        {
            $this->ble->send_command(
                $this->ser,
                $this->ble->ble_cmd_attclient_read_by_group_type($conn, 0x0001, 0xFFFF, list(reversed($uuid_service)))
            );
            # Get command response
            $this->ble->check_activity($this->ser);
            service_groups = [];
            finish = [];
        }

        private function found_cb(bglib_instance, args) {
            if args['connection'] == conn:
                service_groups.append(args);
            }
    private function finished_cb(bglib_instance, args) {
        if args['connection'] == conn:
            finish.append(0)
            $this->ble->ble_evt_attclient_group_found += found_cb
            $this->ble->ble_evt_attclient_procedure_completed += finished_cb
            while not len(finish):
                $this->ble->check_activity($this->ser)
            $this->ble->ble_evt_attclient_group_found -= found_cb
            $this->ble->ble_evt_attclient_procedure_completed -= finished_cb
            return service_groups;
    }
    private function discoverCharacteristics(conn, handle_start, handle_end) {
        $this->ble->send_command(
            $this->ser,
            $this->ble->ble_cmd_attclient_find_information(conn, handle_start, handle_end)
        )
            # Get command response
            $this->ble->check_activity($this->ser)
            chars = []
            finish = [];
    }
    private function found_cb(bglib_instance, args) {
        if args['connection'] == conn:
            chars.append(args);
        }
    private function finished_cb(bglib_instance, args) {
        if args['connection'] == conn:
            finish.append(0)
            $this->ble->ble_evt_attclient_find_information_found += found_cb
            $this->ble->ble_evt_attclient_procedure_completed += finished_cb
            while not len(finish):
                $this->ble->check_activity($this->ser)
            $this->ble->ble_evt_attclient_find_information_found -= found_cb
            $this->ble->ble_evt_attclient_procedure_completed -= finished_cb
            return chars;
    }
    */

    private function disconnect($conn)
    {
        $this->ble->send_command($this->ser, $this->ble->ble_cmd_connection_disconnect($conn));
        $this->ble->check_activity($this->ser);
    }

    private function read($conn, $handle)
    {
        $this->ble->send_command($this->ser, $this->ble->ble_cmd_attclient_read_by_handle($conn, $handle));
        $result = [];
        $payload = [];
        $fail = [];

        $this->ble->ble_rsp_attclient_read_by_handle->add(
            function ($bglib_instance, $args) use (&$result, $conn) {
                if ($args['connection'] == $conn) {
                    $result[] = $args['result'];
                }
            }
        );
        $this->ble->ble_evt_attclient_attribute_value->add(
            function ($bglib_instance, $args) use (&$payload, $conn) {
                if ($args['connection'] == $conn) {
                    $payload[] = $args['value'];
                }
            }
        );
        $this->ble->ble_evt_attclient_procedure_completed->add(
            function ($bglib_instance, $args) use (&$fail, $conn) {
                if ($args['connection'] == $conn) {
                    $fail[] = 0;
                }
            }
        );
        while (true) {
            $this->ble->check_activity($this->ser);
            if (count($result) && $result[0]) {
                #There was a read error
                break;
            } elseif (count($fail)) {
                #Command was processed correctly but we still failed
                break;
            } elseif (count($payload)) {
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

    private function write($conn, $handle, $value)
    {
        if ($handle == 0) {
            print 'Invalid handle! Did you forget a call to Peripheral.replaceCharacteristic(c)?';
        }
        $this->ble->send_command($this->ser, $this->ble->ble_cmd_attclient_attribute_write($conn, $handle, $value));
        $this->ble->check_activity($this->ser);
        $result = [];

        $this->ble->ble_rsp_attclient_attribute_write->add(
            function ($bglib_instance, $args) use (&$result, $conn) {
                if ($args['connection'] == $conn) {
                    $result[] = null;
                }
            }
        );
        $this->ble->ble_evt_attclient_procedure_completed->add(
            function ($bglib_instance, $args) use (&$result, $conn) {
                if ($args['connection'] == $conn) {
                    $result[] = null;
                }
            }
        );

        while (count($result) < 2) {
            $this->idle();
        }
        $this->ble->ble_rsp_attclient_attribute_write->removeLastCallback();
        $this->ble->ble_evt_attclient_procedure_completed->removeLastCallback();
    }

    private function writecommand($conn, $handle, $value)
    {
        if ($handle == 0) {
            print 'Invalid handle!Did you forget a call to Peripheral.replaceCharacteristic(c) ?';
        }
        $this->ble->send_command($this->ser, $this->ble->ble_cmd_attclient_write_command($conn, $handle, $value));
        $this->ble->check_activity($this->ser);
    }
}
