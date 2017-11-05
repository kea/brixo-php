<?php

namespace Kea\Bluegiga;

use Kea\Serial;
use Kea\UUID;

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


    public function ble_cmd_attclient_read_by_group_type($connection, $start, $end, UUID $uuid)
    {
        $uuidString = (string)$uuid;

        return "\x00".chr(6 + strlen($uuidString))."\x04\x01".pack(
                'CvvC',
                $connection,
                $start,
                $end,
                strlen($uuidString)
            ).$uuidString;
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

    public function ble_cmd_attclient_find_information($connection, $handleStart, $handleEnd)
    {
        return pack('C4Cvv', 0, 5, 4, 3, $connection, $handleStart, $handleEnd);
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

    private function handlePacketClass0(Packet $packet): void
    {
        throw new \Exception('Class 0 not implemented');
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

    private function handlePacketClass2(Packet $packet): void
    {
        throw new \Exception('Class 2 not implemented');
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

    private function handlePacketClass4(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_find_by_type_value', $params);
                break;
            case 1:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_read_by_group_type', $params);
                break;
            case 2:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_read_by_type', $params);
                break;
            case 3:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_find_information', $params);
                break;
            case 4:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_read_by_handle', $params);
                break;
            case 5:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_attribute_write', $params);
                break;
            case 6:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_write_command', $params);
                break;
            case 7:
                $params = unpack('vresult', substr($packet->getPayload(), 0, 2));
                $this->eventHandler->dispatch('ble_rsp_attclient_indicate_confirm', $params);
                break;
            case 8:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_read_long', $params);
                break;
            case 9:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_prepare_write', $params);
                break;
            case 10:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_execute_write', $params);
                break;
            case 11:
                $params = unpack('Cconnection/vresult', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_rsp_attclient_read_multiple', $params);
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
        throw new \Exception('Class 7 not implemented');
    }

    private function handlePacketClass8(Packet $packet): void
    {
        throw new \Exception('Class 8 not implemented');
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
                $params['address'] = new Address(substr($packet->getPayload(), 2, 6));
                $params += unpack(
                    'Caddress_type/vconn_interval/vtimeout/vlatency/Cbonding',
                    substr($packet->getPayload(), 8, 8)
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

    private function handleEventPacketClass4(Packet $packet): void
    {
        switch ($packet->getCommand()) {
            case 0:
                $params = unpack('Cconnection/vattrhandle', substr($packet->getPayload(), 0, 3));
                $this->eventHandler->dispatch('ble_evt_attclient_indicated', $params);
                break;
            case 1:
                $params = unpack('Cconnection/vresult/vchrhandle', substr($packet->getPayload(), 0, 5));
                $this->eventHandler->dispatch('ble_evt_attclient_procedure_completed', $params);
                break;
            case 2:
                $params = unpack('Cconnection/vstart/vend/Cuuid_len', substr($packet->getPayload(), 0, 6));
                $params['uuid'] = substr($packet->getPayload(), 6);
                $this->eventHandler->dispatch('ble_evt_attclient_group_found', $params);
                break;
            case 3:
                $params = unpack('Cconnection/vchrdecl/vvalue/Cproperties/Cuuid_len', substr($packet->getPayload(), 0, 7));
                $params['uuid'] = substr($packet->getPayload(), 7);
                $this->eventHandler->dispatch('ble_evt_attclient_attribute_found', $params);
                break;
            case 4:
                $params = unpack('Cconnection/vchrhandle/Cuuid_len', substr($packet->getPayload(), 0, 4));
                $params['uuid'] = substr($packet->getPayload(), 4);
                $this->eventHandler->dispatch('ble_evt_attclient_find_information_found', $params);
                break;
            case 5:
                $params = unpack('Cconnection/vatthandle/Ctype/Cvalue_len', substr($packet->getPayload(), 0, 5));
                $params['value'] = substr($packet->getPayload(), 5);
                $this->eventHandler->dispatch('ble_evt_attclient_attribute_value', $params);
                break;
            case 6:
                $params = unpack('Cconnection/Chandles_len', substr($packet->getPayload(), 0, 2));
                $params['handles'] = substr($packet->getPayload(), 2);
                $this->eventHandler->dispatch('ble_evt_attclient_read_multiple_response', $params);
        }
    }

    private function handleEventPacketClass5(Packet $packet): void
    {
        throw new \Exception('Class event 5 not implemented');
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
