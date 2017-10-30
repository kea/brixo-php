<?php

ini_set("display_errors", "1");
error_reporting(E_ALL);

include __DIR__.'/Brixo/Brixo.php';
include __DIR__.'/Brixo/BrixoDevice.php';
include __DIR__.'/Brixo/BrixoStatus.php';
include __DIR__.'/Bluegiga/BGWrapper.php';
include __DIR__.'/Bluegiga/Characteristic.php';
include __DIR__.'/Bluegiga/Peripheral.php';
include __DIR__.'/Bluegiga/EventHandler.php';
include __DIR__.'/Bluegiga/BGLib.php';
include __DIR__.'/Bluegiga/PacketsBuffer.php';
include __DIR__.'/Bluegiga/Packet.php';
include __DIR__.'/UUID.php';
include __DIR__.'/Serial.php';

$b = new \Kea\Brixo\Brixo('/dev/tty.usbmodem1');

$results = $b->scan(3);
if (count($results) === 0) {
    print "No devices found\n";
    exit;
}

$closest = $results[0];
foreach ($results as $result) {
    if ($result->getRssi() > $closest->getRssi()) {
        $closest = $result;
    }
}
$closest->connect($closest);
$closest->discover();