<?php

class Console
{
    const FG_COLORS = [
        'black' => ['on' => 30, 'off' => 39],
        'red' => ['on' => 31, 'off' => 39],
        'green' => ['on' => 32, 'off' => 39],
        'yellow' => ['on' => 33, 'off' => 39],
        'blue' => ['on' => 34, 'off' => 39],
        'magenta' => ['on' => 35, 'off' => 39],
        'cyan' => ['on' => 36, 'off' => 39],
        'white' => ['on' => 37, 'off' => 39],
        'default' => ['on' => 39, 'off' => 39],
    ];
    const BG_COLORS = [
        'black' => ['on' => 40, 'off' => 49],
        'red' => ['on' => 41, 'off' => 49],
        'green' => ['on' => 42, 'off' => 49],
        'yellow' => ['on' => 43, 'off' => 49],
        'blue' => ['on' => 44, 'off' => 49],
        'magenta' => ['on' => 45, 'off' => 49],
        'cyan' => ['on' => 46, 'off' => 49],
        'white' => ['on' => 47, 'off' => 49],
        'default' => ['on' => 49, 'off' => 49],
    ];

    public static function print($message, $fg, $bg = null)
    {
        $on = self::FG_COLORS[$fg]['on'].($bg ? ';'.self::BG_COLORS[$bg]['on']: '');
        $off = self::FG_COLORS[$fg]['off'].($bg ? ';'.self::BG_COLORS[$bg]['off']: '');

        printf("\033[%sm%s\033[%sm", $on, $message, $off);
    }

    public static function println($message, $fg, $bg = null)
    {
        self::print($message, $fg, $bg);
        echo "\n";
    }

    public static function printInfo($label, $info)
    {
        self::print($label, 'cyan');
        self::println($info, 'white');
    }

    public static function printStatus($label, bool $statusFlag)
    {
        self::print($label, 'cyan');
        self::println($statusFlag ? '√' : 'x', $statusFlag ? 'white' : 'default');
    }
}