<?php

namespace Kea\Bluegiga;

class PacketsBuffer
{
    private $bgapi_rx_buffer = '';
    private $bgapi_rx_expected_length;
    private $messages = [];

    public function getPacket():? Packet
    {
        return array_shift($this->messages);
    }

    /**
     * BGAPI packet structure:
     *            Byte 0:
     *                  [7] - 1 bit, Message Type (MT)         0 = Command/Response, 1 = Event
     *                [6:3] - 4 bits, Technology Type (TT)     0000 = Bluetooth 4.0 single mode, 0001 = Wi-Fi
     *                [2:0] - 3 bits, Length High (LH)         Payload length (high bits)
     *            Byte 1:     8 bits, Length Low (LL)          Payload length (low bits)
     *            Byte 2:     8 bits, Class ID (CID)           Command class ID
     *            Byte 3:     8 bits, Command ID (CMD)         Command ID
     *            Bytes 4-n:  0 - 2048 Bytes, Payload (PL)     Up to 2048 bytes of payload [*]
     * [*] Maximum actual payload length in protocol usage is 60 bytes, due to memory limitations on internal moduleCPUs.
     * The 11-bit <length> field theoretical maximum is 2048, but full packet length limit is 64 bytes,
     * leaving 4 for header and 60 for payload data
     * @param int $contentToAdd
     * @throws \Exception
     */
    private function addByteToBuffer(int $contentToAdd): void
    {
        $charToAdd = chr($contentToAdd);

        if (('' === $this->bgapi_rx_buffer) && (($contentToAdd === 0) || ($contentToAdd === 128) || ($contentToAdd === 8) || ($contentToAdd === 136))) {
            $this->bgapi_rx_buffer .= $charToAdd;
        } elseif (strlen($this->bgapi_rx_buffer) === 1) {
            $this->bgapi_rx_buffer .= $charToAdd;
            $this->bgapi_rx_expected_length = 4 + (ord($this->bgapi_rx_buffer[0]) & 7) + ord($this->bgapi_rx_buffer[1]);
        } elseif (strlen($this->bgapi_rx_buffer) > 1) {
            $this->bgapi_rx_buffer .= $charToAdd;
        } else {
            throw new \Exception('Something went wrong parsing message');
        }

        if (($this->bgapi_rx_expected_length > 0) &&
            (strlen($this->bgapi_rx_buffer) >= $this->bgapi_rx_expected_length)) {
            $message = substr($this->bgapi_rx_buffer, 0, $this->bgapi_rx_expected_length);
            $this->bgapi_rx_buffer = substr($this->bgapi_rx_buffer, $this->bgapi_rx_expected_length);

            $this->messages[] = Packet::fromBinary($message);
        }
    }

    public function addContent(string $contentToAdd): void
    {
        if ($contentToAdd === '') {
            return;
        }

        $chars = str_split($contentToAdd);
        foreach ($chars as $char) {
            $this->addByteToBuffer(ord($char));
        }
    }
}