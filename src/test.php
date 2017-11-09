<?php

ini_set("display_errors", "1");
error_reporting(E_ALL);

include_once __DIR__.'/../vendor/autoload.php';
include_once __DIR__.'/Console.php';

function printMenu()
{
    Console::print("0: ", 'green'); Console::println("Set direction", 'white');
    Console::print("1: ", 'green'); Console::println("Standby", 'white');
    Console::print("2: ", 'green'); Console::println("Set power", 'white');
    Console::print("3: ", 'green'); Console::println("Shutdown battery", 'white');
    Console::print("4: ", 'green'); Console::println("Set timer", 'white');
    Console::print("5: ", 'green'); Console::println("Status information", 'white');
    Console::print("q: ", 'green'); Console::println("Quit", 'white');
}

$deviceName = '/dev/tty.usbmodem1';
$brixo = \Kea\Brixo\Brixo::fromPortName($deviceName);

Console::println("Scanning...", 'cyan');
$results = $brixo->scan(3);
if (count($results) === 0) {
    echo "No devices found\n";
    exit;
}

Console::println("\nFound ".count($results)." device(s):", 'cyan');
foreach ($results as $index => $result) {
    Console::print('['.$index.'] ', 'green');
    Console::println($result->getMac(), 'white');
}

Console::print('Choose device: ', 'yellow');
$choice = (int)trim(fgets(STDIN));

$device = $brixo->connect($results[$choice]);
Console::print('Device name: ', 'cyan');
Console::println($device->getName(), 'red');
$beep = 1;

while ($choice !== 'q') {
    printMenu();
    Console::print('Input Command: ', 'yellow');
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
            $status = $device->getStatus();
            Console::printStatus("Standby  : ", $status->getStandby());
            Console::printStatus("CW       : ", $status->getCW());
            Console::printStatus("CCW      : ", $status->getCCW());
            Console::printStatus("OC       : ", $status->getOC());
            Console::printStatus("Warning  : ", $status->getWarning());
            Console::printStatus("Overload : ", $status->getOverload());
            Console::printStatus("USBSource: ", $status->getUSB());
            Console::printStatus("Streaming: ", $status->getStreaming());
            Console::printInfo("Output Current: ", $device->getOutputCurrent()." mA");
            Console::printInfo("Output Voltage: ", ($device->getOutputVoltage() * 10)." mV");
            Console::printInfo("Time Left     : ", $device->getTimeLeft()." s");


    }
}