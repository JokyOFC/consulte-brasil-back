<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Src\Shared\Application\Contracts\EventBus;

final class LaravelEventBus implements EventBus
{
    public function __construct(private Dispatcher $events) {}

    public function publish(object $event): void
    {
        $this->events->dispatch($event);
    }
}
