<?php

ini_set("display_errors", "1");
error_reporting(E_ALL);

include_once __DIR__.'/../vendor/autoload.php';

$brixo = \Kea\Brixo\Brixo::fromPortName('/dev/tty.usbmodem1');

$results = $brixo->scan(3);
if (count($results) === 0) {
    echo "No devices found\n";
    exit;
}

echo "Found ".count($results)." device(s):\n";
foreach ($results as $index => $result) {
    echo '['.$index.'] '.$result->getMac()."\n";
}

echo 'Choose device: ';
$choice = (int)trim(fgets(STDIN));

$brixo->connect($results[$choice]);