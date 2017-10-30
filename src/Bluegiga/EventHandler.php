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
            $this->events[$eventName](...$args);
        }
    }

    public function add(string $eventName, callable $callable): void
    {
        if ($this->events->offsetExists($eventName)) {
            throw new \InvalidArgumentException('Event "'.$eventName.'" already set');
        }
        $this->events[$eventName] = $callable;
    }

    public function addOrReplace(string $eventName, callable $callable): void
    {
        $this->events[$eventName] = $callable;
    }

    public function remove(string $eventName): void
    {
        $this->events->offsetUnset($eventName);
    }
}
