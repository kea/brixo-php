<?php

ini_set("display_errors", "1");
error_reporting(E_ALL);

include_once __DIR__.'/../vendor/autoload.php';

function printMenu()
{
    echo "0: Set direction\n";
    echo "1: Standby\n";
    echo "2: Set power\n";
    echo "3: Shutdown battery\n";
    echo "4: Set timer\n";
    echo "5: Status information\n";
    echo "q: Quit\n";
}

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

$device = $brixo->connect($results[$choice]);
echo 'Device name: '.$device->getName()."\n";
$beep = 1;

while ($choice != 'q') {
    printMenu();
    echo 'Input Command: ';
    $choice = strtolower(trim(fgets(STDIN)));
    switch ($choice) {
        case 'q':
            $device->disconnect();
            break;
        case '0':
            echo "Input Channel (1:CW, 2:CCW): ";
            $channel = trim(fgets(STDIN));
            $device->setDirection((int)$channel, $beep);
            break;
        case '1':
            $device->standby($beep);
            break;
        case '2':
            echo "Input Power (0-100): ";
            $power = trim(fgets(STDIN));
            $device->setPower((int)$power, $beep);
            break;
        case '3':
            $device->shutdownBattery($beep);
            break;
        case '4':
            echo "Input Cutout Time (0-65535): ";
            $timerValue = trim(fgets(STDIN));
            $device->setTimer((int)$timerValue);
            break;
        case '5':
            $device->printInfo();
    }
}