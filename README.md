Brixo API PHP implementation
============================

This project allows you to communicate with the BRIXO battery, by using a BLED112 dongle.

Install
-------

Download and install Composer by following the [official instructions](https://getcomposer.org/download/).
```bash
$ php composer.phar install
```

Configure
---------

Edit `src/test.php` changing the device name at line 19 to match device dongle

```php
$deviceName = '/dev/tty.usbmodem1';
```

Es. Windows `"\.\com4"`, Mac `"/dev/tty.xyk"`, Linux `"/dev/ttySxx"`

Play
----

```bash
$ php src/test.php
```

[![asciicast](https://asciinema.org/a/A59Wr3EE85boFHpDZAg6LWrO2.png)](https://asciinema.org/a/A59Wr3EE85boFHpDZAg6LWrO2)