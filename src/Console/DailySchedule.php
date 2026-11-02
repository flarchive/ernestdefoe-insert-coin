<?php

namespace ErnestDefoe\InsertCoin\Console;

use Illuminate\Console\Scheduling\Event;

class DailySchedule
{
    public function __invoke(Event $event): void
    {
        $event->daily()->withoutOverlapping();
    }
}
