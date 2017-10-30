<?php

namespace Kea\Bluegiga;

// pack
// H => v
// h => s
// B => C
// b => c
// I => I

use Kea\Serial;

class BGLib
{
    private $debug = true;
    private $busy = false;

    private $eventHandler;
    private $packetsBuffer;

    public function __construct()
    {
        $this->eventHandler = new EventHandler();
        $this->packetsBuffer = new PacketsBuffer();
    }

    /**
     * @return EventHandler
     */
    public function getEventHandler(): EventHandler
    {
        return $this->eventHandler;
    }

    public function sendCommand(Serial $serial, $packet): void
    {
        if ($this->debug) {
            echo 'SEND =>[ '.\bin2hex($packet)." ]\n";
        }
        $this->busy = true;
        $serial->write($packet);
    }

    /**
     * @param Serial $serial
     * @param int $timeout microseconds
     * @return bool
     * @throws \RuntimeException
     */
    public function checkActivity(Serial $serial, int $timeout = 0)
    {
        $data = $serial->blockingRead($timeout);
        $this->packetsBuffer->addContent((string)$data);
        while ($packet = $this->packetsBuffer->getPacket()) {
            if ($this->debug) {
                echo 'RECV <= ['.bin2hex((string)$packet)."]\n";
            }
            $this->handle($packet);
        }
    }

    public function ble_cmd_gap_end_procedure()
    {
        return pack('C4', 0, 0, 6, 4);
    }

    public function ble_cmd_attclient_read_by_handle($conn, $handle)
    {
        throw new \RuntimeException('Not implemented');
    }

    public function ble_cmd_gap_set_scan_parameters($scan_interval, $scan_window, $active)
    {
        return pack('C4vvC', 0, 5, 6, 7, $scan_interval, $scan_window, $active);
    }

    public function ble_cmd_gap_discover($mode)
    {
        return pack('C4C', 0, 1, 6, 2, $mode);
    }

    public function ble_cmd_connection_disconnect($connection)
    {
        return pack('C4C', 0, 1, 3, 0, $connection);
    }

    public function ble_cmd_gap_connect_direct(
        $address,
        $addr_type,
        $conn_interval_min,
        $conn_interval_max,
        $timeout,
        $latency
    ) {
        return "\x00\x0f\x06\x03".$address.pack(
                'Cvvvv',
                $addr_type,
                $conn_interval_min,
                $conn_interval_max,
                $timeout,
                $latency
            );
    }

    /**
     * @param Packet $packet
     */
    private function handle(Packet $packet)
    {
        if ($packet->isCommandOrResponse()) {
            $method = 'handlePacketClass'.$packet->getClass();
            $this->$method($packet);
            $this->busy = false;
            $this->eventHandler->dispatch('on_idle');
        } elseif ($packet->isEvent()) {
            $method = 'handleEventPacketClass'.$packet->getClass();
            $this->$method($packet);
        }
    }

    /**
     * @todo TBD
     * @param Packet $packet
     */
    private function handlePacketClass0(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $this->ble_rsp_system_reset([]);
                $this->busy = false;
                $this->on_idle();
                break;
            case 1:
                $this->ble_rsp_system_hello([]);
                break;
            case 2:
                $address = unpack(' < 6s', array_slice($packet->getPayload(), 0, 6))[0];
                $this->ble_rsp_system_address_get(array('address' => $address));

                break;
            case 3:
                $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, 2)), 0);
                $this->ble_rsp_system_reg_write(array('result' => $result));

                break;
            case 4:
                list($address, $value) = unpack(' < HB', array_slice($packet->getPayload(), 0, 3));
                $this->ble_rsp_system_reg_read(array('address' => $address, 'value' => $value));

                break;
            case 5:
                list($txok, $txretry, $rxok, $rxfail, $mbuf) = unpack(
                    ' < BBBBB',
                    substr($packet->getPayload(), 5)
                );
                $this->ble_rsp_system_get_counters(
                    array(
                        'txok' => $txok,
                        'txretry' => $txretry,
                        'rxok' => $rxok,
                        'rxfail' => $rxfail,
                        'mbuf' => $mbuf,
                    )
                );

                break;
            case 6:
                $maxconn = $packet->getPayload()[0];
                $this->ble_rsp_system_get_connections(array('maxconn' => $maxconn));

                break;
            case 7:
                list($address, $data_len) = unpack(' < IB', array_slice($packet->getPayload(), 0, 5));
                $data_data = substr($packet->getPayload(), 5);
                $this->ble_rsp_system_read_memory(array('address' => $address, 'data' => $data_data));

                break;
            case 8:
                list($major, $minor, $patch, $build, $ll_version, $protocol_version, $hw) = unpack(
                    ' < HHHHHBB',
                    array_slice($packet->getPayload(), 0, 12)
                );
                $this->ble_rsp_system_get_info(
                    array(
                        'major' => $major,
                        'minor' => $minor,
                        'patch' => $patch,
                        'build' => $build,
                        'll_version' => $ll_version,
                        'protocol_version' => $protocol_version,
                        'hw' => $hw,
                    )
                );

                break;
            case 9:
                $result = unpack(' < H', substr($packet->getPayload(), 0, 2))[1];
                $this->ble_rsp_system_endpoint_tx(array('result' => $result));

                break;
            case 10:
                $result = unpack(' < H', substr($packet->getPayload(), 0, 2))[1];
                $this->ble_rsp_system_whitelist_append(array('result' => $result));

                break;
            case 11:
                $result = unpack(' < H', substr($packet->getPayload(), 0, 2))[1];
                $this->ble_rsp_system_whitelist_remove(array('result' => $result));

                break;
            case 12:
                $this->ble_rsp_system_whitelist_clear([]);

                break;
            case 13:
                list($result, $data_len) = unpack('vC', substr($packet->getPayload(), 0, 3));
                $data_data = substr($packet->getPayload(), 3);
                $this->ble_rsp_system_endpoint_rx(array('result' => $result, 'data' => $data_data));

                break;
            case 14:
                $result = unpack('v', substr($packet->getPayload(), 0, 2))[1];
                $this->ble_rsp_system_endpoint_set_watermarks(array('result' => $result));
        }
    }

    private function handlePacketClass1(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $this->eventHandler->dispatch('ble_rsp_flash_ps_defrag', []);
                break;
            case 1:
                $this->eventHandler->dispatch('ble_rsp_flash_ps_dump', []);
                break;
            case 2:
                $this->eventHandler->dispatch('ble_rsp_flash_ps_erase_all', []);
                break;
            case 3:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_flash_ps_save', $params);
                break;
            case 4:
                $params = unpack('vresult/Cvalue_len', substr($packet->getPayload(), 0, 3));
                $params['value'] = substr($packet->getPayload(), 3);
                $this->eventHandler->dispatch('ble_rsp_flash_ps_load', $params);
                break;
            case 5:
                $this->eventHandler->dispatch('ble_rsp_flash_ps_erase', []);
                break;
            case 6:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_flash_erase_page', $params);
                break;
            case 7:
                $this->eventHandler->dispatch('ble_rsp_flash_write_words', []);
                break;
        }
    }

    /**
     * @todo TBD
     * @param Packet $packet
     */
    private function handlePacketClass2(Packet $packet): void
    {
        if (($packet_command == 0)) {
            [, $result] = unpack(' < H', substr($packet->getPayload(), 0, 2));
            $this->ble_rsp_attributes_write(array('result' => $result));
        } elseif (($packet_command == 1)) {
            [, $handle, $offset, $result, $value_len] = unpack('HHHB', substr($packet->getPayload(), 0, 7));
            $value_data = array_map(
                function ($b) {
                    return ord($b);
                },
                substr($packet->getPayload(), 7)
            );
            $this->ble_rsp_attributes_read(
                array('handle' => $handle, 'offset' => $offset, 'result' => $result, 'value' => $value_data)
            );
        } elseif (($packet_command == 2)) {
            list($handle, $result, $value_len) = unpack(
                ' < HHB',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (5 - 0)
                )
            );
            $value_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    5
                )
            );
            $this->ble_rsp_attributes_read_type(
                array('handle' => $handle, 'result' => $result, 'value' => $value_data)
            );
        } elseif (($packet_command == 3)) {
            $this->ble_rsp_attributes_user_read_response([]);
        } elseif (($packet_command == 4)) {
            $this->ble_rsp_attributes_user_write_response([]);
        }
    }

    private function handlePacketClass3(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_connection_disconnect', $params);
                break;
            case 1:
                $params = unpack('Cconnection/crssi', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_connection_get_rssi', $params);
                break;
            case 2:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_connection_update', $params);
                break;
            case 3:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_connection_version_update', $params);
                break;
            case 4:
                $params = unpack('Cconnection/Cmaplen/Cmap*', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_connection_channel_map_get', $params);
                break;
            case 5:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_connection_channel_map_set', $params);
                break;
            case 6:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_connection_features_get', $params);
                break;
            case 7:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 1));
                $this->eventHandler->dispatch('ble_rsp_connection_get_status', $params);
                break;
            case 8:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 1));
                $this->eventHandler->dispatch('ble_rsp_connection_raw_tx', $params);
                break;
        }
    }

    private function handlePacletClass4(Packet $packet): void
    {
        if (($packet_command == 0)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_find_by_type_value(
                array('connection' => $connection, 'result' => $result)
            );
        } elseif (($packet_command == 1)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_read_by_group_type(
                array('connection' => $connection, 'result' => $result)
            );
        } elseif (($packet_command == 2)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_read_by_type(array('connection' => $connection, 'result' => $result));
        } elseif (($packet_command == 3)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_find_information(
                array('connection' => $connection, 'result' => $result)
            );
        } elseif (($packet_command == 4)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_read_by_handle(
                array('connection' => $connection, 'result' => $result)
            );
        } elseif (($packet_command == 5)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_attribute_write(
                array('connection' => $connection, 'result' => $result)
            );
        } elseif (($packet_command == 6)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_write_command(array('connection' => $connection, 'result' => $result));
        } elseif (($packet_command == 7)) {
            $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_attclient_indicate_confirm(array('result' => $result));
        } elseif (($packet_command == 8)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_read_long(array('connection' => $connection, 'result' => $result));
        } elseif (($packet_command == 9)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_prepare_write(array('connection' => $connection, 'result' => $result));
        } elseif (($packet_command == 10)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_execute_write(array('connection' => $connection, 'result' => $result));
        } elseif (($packet_command == 11)) {
            list($connection, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_rsp_attclient_read_multiple(array('connection' => $connection, 'result' => $result));
        }
    }

    private function handlePacketClass5(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $params = unpack('Chandle/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_sm_encrypt_start', $params);
                break;
            case 1:
                $this->eventHandler->dispatch('ble_rsp_sm_set_bondable_mode', []);
                break;
            case 2:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_sm_delete_bonding', $params);
                break;
            case 3:
                $this->eventHandler->dispatch('ble_rsp_sm_set_parameters', []);
                break;
            case 4:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_sm_passkey_entry', $params);
                break;
            case 5:
                $params = unpack('Cbonds', substr($packet->getPayload(), 0, 1));
                $this->eventHandler->dispatch('ble_rsp_sm_get_bonds', $params);
                break;
            case 6:
                $this->eventHandler->dispatch('ble_rsp_sm_set_oob_data', []);
                break;
        }
    }

    private function handlePacketClass6(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $this->eventHandler->dispatch('ble_rsp_gap_set_privacy_flags', []);
                break;
            case 1:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_gap_set_mode', $params);
                break;
            case 2:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_gap_discover', $params);
                break;
            case 3:
                $params = unpack('vresult/Cconnection_handle', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_gap_connect_direct', $params);
                break;
            case 4:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_gap_end_procedure', $params);
                break;
            case 5:
                $params = unpack('vresult/Cconnection_handle', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_gap_connect_selective', $params);
                break;
            case 6:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_gap_set_filtering', $params);
                break;
            case 7:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_gap_set_scan_parameters', $params);
                break;
            case 8:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_gap_set_adv_parameters', $params);
                break;
            case 9:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_gap_set_adv_data', $params);
                break;
            case 10:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_gap_set_directed_connectable_mode', $params);
        }
    }

    private function handlePacketClass7(Packet $packet): void
    {
        if (($packet_command == 0)) {
            $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_hardware_io_port_config_irq(array('result' => $result));
        } elseif (($packet_command == 1)) {
            $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_hardware_set_soft_timer(array('result' => $result));
        } elseif (($packet_command == 2)) {
            $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_hardware_adc_read(array('result' => $result));
        } elseif (($packet_command == 3)) {
            $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_hardware_io_port_config_direction(array('result' => $result));
        } elseif (($packet_command == 4)) {
            $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_hardware_io_port_config_function(array('result' => $result));
        } elseif (($packet_command == 5)) {
            $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_hardware_io_port_config_pull(array('result' => $result));
        } elseif (($packet_command == 6)) {
            $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_hardware_io_port_write(array('result' => $result));
        } elseif (($packet_command == 7)) {
            list($result, $port, $data) = unpack(' < HBB', array_slice($packet->getPayload(), 0, (4 - 0)));
            $this->ble_rsp_hardware_io_port_read(
                array('result' => $result, 'port' => $port, 'data' => $data)
            );
        } elseif (($packet_command == 8)) {
            $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_hardware_spi_config(array('result' => $result));
        } elseif (($packet_command == 9)) {
            list($result, $channel, $data_len) = unpack(
                ' < HBB',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (4 - 0)
                )
            );
            $data_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    4
                )
            );
            $this->ble_rsp_hardware_spi_transfer(
                array('result' => $result, 'channel' => $channel, 'data' => $data_data)
            );
        } elseif (($packet_command == 10)) {
            list($result, $data_len) = unpack(' < HB', array_slice($packet->getPayload(), 0, (3 - 0)));
            $data_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    3
                )
            );
            $this->ble_rsp_hardware_i2c_read(array('result' => $result, 'data' => $data_data));
        } elseif (($packet_command == 11)) {
            $written = pyphp_subscript(unpack(' < B', array_slice($packet->getPayload(), 0, (1 - 0))), 0);
            $this->ble_rsp_hardware_i2c_write(array('written' => $written));
        } elseif (($packet_command == 12)) {
            $this->ble_rsp_hardware_set_txpower([]);
        } elseif (($packet_command == 13)) {
            $result = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_hardware_timer_comparator(array('result' => $result));
        }
    }

    /**
     * @todo TBD
     * @param Packet $packet
     */
    private function handlePacketClass8(Packet $packet): void
    {
        if (($packet_command == 0)) {
            $this->ble_rsp_test_phy_tx([]);
        } elseif (($packet_command == 1)) {
            $this->ble_rsp_test_phy_rx([]);
        } elseif (($packet_command == 2)) {
            $counter = pyphp_subscript(unpack(' < H', array_slice($packet->getPayload(), 0, (2 - 0))), 0);
            $this->ble_rsp_test_phy_end(array('counter' => $counter));
        } elseif (($packet_command == 3)) {
            $this->ble_rsp_test_phy_reset([]);
        } elseif (($packet_command == 4)) {
            $channel_map_len = pyphp_subscript(
                unpack(
                    ' < B',
                    array_slice($packet->getPayload(), 0, (1 - 0))
                ),
                0
            );
            $channel_map_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    1
                )
            );
            $this->ble_rsp_test_get_channel_map(array('channel_map' => $channel_map_data));
        } elseif (($packet_command == 5)) {
            $output_len = pyphp_subscript(
                unpack(
                    ' < B',
                    array_slice($packet->getPayload(), 0, (1 - 0))
                ),
                0
            );
            $output_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    1
                )
            );
            $this->ble_rsp_test_debug(array('output' => $output_data));
        }
    }

    private function handleEventPacketClass0(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $params = unpack(
                    'vmajor/vminor/vpatch/vbuild/vll_version/Cprotocol_version/Chw',
                    substr($packet->getPayload(), 0, 12)
                );
                $this->eventHandler->dispatch('ble_evt_system_boot', $params);
                $this->busy = false;
                $this->eventHandler->dispatch('on_idle');
                break;
            case 1:
                $data_len = unpack('Cdata_len', substr($packet->getPayload(), 0, 1));
                $data_data = substr($packet->getPayload(), 1);
                $this->eventHandler->dispatch('ble_evt_system_debug', ['data' => $data_data]);
                break;
            case 2:
                $params = unpack('Cendpoint/Cdata', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_evt_system_endpoint_watermark_rx', $params);
                break;
            case 3:
                $params = unpack('Cendpoint/Cdata', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_evt_system_endpoint_watermark_tx', $params);
                break;
            case 4:
                $params = unpack('vaddress/vreason', substr($packet->getPayload(), 0, 4));
                $this->eventHandler->dispatch('ble_evt_system_script_failure', $params);
                break;
            case 5:
                $this->eventHandler->dispatch('ble_evt_system_no_license_key', []);
        }
    }

    private function handleEventPacketClass1(Packet $packet): void
    {
        if ($packet->getCommand() === 0) {
            $params = unpack('vkey/Cvalue', substr($packet->getPayload(), 0, 3));
            $params['value_data'] = substr($packet->getPayload(), 3);
            $this->eventHandler->dispatch('ble_evt_flash_ps_key', $params);
        }
    }

    private function handleEventPacketClass2(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $params = unpack('Cconnection/Creason/vhandle/voffset/Cvalue_len', substr($packet->getPayload(), 0, 7));
                $params['value'] = substr($packet->getPayload(), 7);
                $this->eventHandler->dispatch('ble_evt_attributes_value', $params);
                break;
            case 1:
                $params = unpack('Cconnection/vhandle/voffset/Cmaxsize', substr($packet->getPayload(), 0, 6));
                $this->eventHandler->dispatch('ble_evt_attributes_user_read_request', $params);
                break;
            case 2:
                $params = unpack('vhandle/Cflags', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_evt_attributes_status', $params);
                break;
        }
    }

    private function handleEventPacketClass3(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $params = unpack('Cconnection/Cflags', substr($packet->getPayload(), 0, 2));
                $params['address'] = substr($packet->getPayload(), 2, 6);
                $params += unpack(
                    'Caddress_type/vconn_interval/vtimeout/vlatency/Cbonding',
                    substr($packet->getPayload(), 0, 16)
                );
                $this->eventHandler->dispatch('ble_evt_connection_status', $params);
                break;
            case 1:
                $params = unpack('Cconnection/Cvers_nr/vcomp_id/vsub_vers_nr', substr($packet->getPayload(), 0, 6));
                $this->eventHandler->dispatch('ble_evt_connection_version_ind', $params);
                break;
            case 2:
                $params = unpack('Cconnection/Cfeatures_len', substr($packet->getPayload(), 0, 2));
                $params['features'] = substr($packet->getPayload(), 2);
                $this->eventHandler->dispatch('ble_evt_connection_feature_ind', $params);
                break;
            case 3:
                $params = unpack('Cconnection/Cdata_len', substr($packet->getPayload(), 0, 2));
                $params['data'] = substr($packet->getPayload(), 2);
                $this->eventHandler->dispatch('ble_evt_connection_raw_rx', $params);
                break;
            case 4:
                $params = unpack('Cconnection/vreason', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_evt_connection_disconnected', $params);
        }
    }

    /**
     * @todo TBD
     * @param Packet $packet
     */
    private function handleEventPacketClass4(Packet $packet): void
    {
        if (($packet_command == 0)) {
            list($connection, $attrhandle) = unpack(
                ' < BH',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (3 - 0)
                )
            );
            $this->ble_evt_attclient_indicated(
                array('connection' => $connection, 'attrhandle' => $attrhandle)
            );
        } elseif (($packet_command == 1)) {
            list($connection, $result, $chrhandle) = unpack(
                ' < BHH',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (5 - 0)
                )
            );
            $this->ble_evt_attclient_procedure_completed(
                array('connection' => $connection, 'result' => $result, 'chrhandle' => $chrhandle)
            );
        } elseif (($packet_command == 2)) {
            list($connection, $start, $end, $uuid_len) = unpack(
                ' < BHHB',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (6 - 0)
                )
            );
            $uuid_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    6
                )
            );
            $this->ble_evt_attclient_group_found(
                array('connection' => $connection, 'start' => $start, 'end' => $end, 'uuid' => $uuid_data)
            );
        } elseif (($packet_command == 3)) {
            list($connection, $chrdecl, $value, $properties, $uuid_len) = unpack(
                ' < BHHBB',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (7 - 0)
                )
            );
            $uuid_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    7
                )
            );
            $this->ble_evt_attclient_attribute_found(
                array(
                    'connection' => $connection,
                    'chrdecl' => $chrdecl,
                    'value' => $value,
                    'properties' => $properties,
                    'uuid' => $uuid_data,
                )
            );
        } elseif (($packet_command == 4)) {
            list($connection, $chrhandle, $uuid_len) = unpack(
                ' < BHB',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (4 - 0)
                )
            );
            $uuid_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    4
                )
            );
            $this->ble_evt_attclient_find_information_found(
                array('connection' => $connection, 'chrhandle' => $chrhandle, 'uuid' => $uuid_data)
            );
        } elseif (($packet_command == 5)) {
            list($connection, $atthandle, $type, $value_len) = unpack(
                ' < BHBB',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (5 - 0)
                )
            );
            $value_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    5
                )
            );
            $this->ble_evt_attclient_attribute_value(
                array(
                    'connection' => $connection,
                    'atthandle' => $atthandle,
                    'type' => $type,
                    'value' => $value_data,
                )
            );
        } elseif (($packet_command == 6)) {
            list($connection, $handles_len) = unpack(
                ' < BB',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (2 - 0)
                )
            );
            $handles_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    2
                )
            );
            $this->ble_evt_attclient_read_multiple_response(
                array('connection' => $connection, 'handles' => $handles_data)
            );
        }
    }

    /**
     * @todo TBD
     * @param Packet $packet
     */
    private function handleEventPacketClass5(Packet $packet): void
    {
        if (($packet_command == 0)) {
            list($handle, $packet, $data_len) = unpack(
                ' < BBB',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (3 - 0)
                )
            );
            $data_data = array_map(
                function ($b) {
                    return ord($b);
                },
                array_slice(
                    $packet->getPayload(),
                    3
                )
            );
            $this->ble_evt_sm_smp_data(array('handle' => $handle, 'packet' => $packet, 'data' => $data_data));
        } elseif (($packet_command == 1)) {
            list($handle, $result) = unpack(' < BH', array_slice($packet->getPayload(), 0, (3 - 0)));
            $this->ble_evt_sm_bonding_fail(array('handle' => $handle, 'result' => $result));
        } elseif (($packet_command == 2)) {
            list($handle, $passkey) = unpack(' < BI', array_slice($packet->getPayload(), 0, (5 - 0)));
            $this->ble_evt_sm_passkey_display(array('handle' => $handle, 'passkey' => $passkey));
        } elseif (($packet_command == 3)) {
            $handle = pyphp_subscript(unpack(' < B', array_slice($packet->getPayload(), 0, (1 - 0))), 0);
            $this->ble_evt_sm_passkey_request(array('handle' => $handle));
        } elseif (($packet_command == 4)) {
            list($bond, $keysize, $mitm, $keys) = unpack(
                ' < BBBB',
                array_slice(
                    $packet->getPayload(),
                    0,
                    (4 - 0)
                )
            );
            $this->ble_evt_sm_bond_status(
                array('bond' => $bond, 'keysize' => $keysize, 'mitm' => $mitm, 'keys' => $keys)
            );
        }
    }

    private function handleEventPacketClass6(Packet $packet): void
    {
        if ($packet->getCommand() === 0) {
            $params = unpack('crssi/Cpacket_type', substr($packet->getPayload(), 0, 2));
            $params['sender'] = substr($packet->getPayload(), 2, 6);
            $params += unpack('Caddress_type/Cbond/Cdata_len', substr($packet->getPayload(), 8, 3));
            $params['data'] = substr($packet->getPayload(), 11);
            $this->eventHandler->dispatch('ble_evt_gap_scan_response', $params);
        } elseif ($packet->getCommand() === 1) {
            $params = unpack('Cdiscover/Cconnect', substr($packet->getPayload(), 0, 2));
            $this->eventHandler->dispatch('ble_evt_gap_mode_changed', $params);
        }
    }

    private function handleEventPacketClass7(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $params = unpack('Itimestamp/Cport/Cirq/Cstate', substr($packet->getPayload(), 0, 7));
                $this->eventHandler->dispatch('ble_evt_hardware_io_port_status', $params);
                break;
            case 1:
                $params = unpack('Chandle', substr($packet->getPayload(), 0, 1));
                $this->eventHandler->dispatch('ble_evt_hardware_soft_timer', $params);
                break;
            case 2:
                $params = unpack('Cinput/svalue', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_evt_hardware_adc_result', $params);
                break;
        }
    }
}
