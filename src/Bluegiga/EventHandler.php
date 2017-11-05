<?php

namespace Kea\Bluegiga;

class EventHandler
{
    private $events;

    public function __construct()
    {
        $this->events = new \ArrayObject();
    }

    public function dispatch(string $eventName, ...$args): void
    {
        if ($this->events->offsetExists($eventName)) {
            foreach ($this->events[$eventName] as $callback)
                $callback(...$args);
        }
    }

    public function add(string $eventName, callable $callable): void
    {
        if (!$this->events->offsetExists($eventName)) {
            $this->events[$eventName] = [];
        }
        $this->events[$eventName][] = $callable;
    }

    public function remove(string $eventName): void
    {
        if (count($this->events[$eventName]) === 1) {
            $this->events->offsetUnset($eventName);

            return;
        }

        array_pop($this->events[$eventName]);
    }
}
