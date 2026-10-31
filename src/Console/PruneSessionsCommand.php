<?php

namespace ErnestDefoe\InsertCoin\Console;

use Carbon\Carbon;
use ErnestDefoe\InsertCoin\Model\PlaySession;
use Flarum\Console\AbstractCommand;

/**
 * Daily: forget play sessions nobody can submit any more (older than a day
 * and never finished) and, after 90 days, the rest ("recently played" only
 * looks back that far).
 */
class PruneSessionsCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setName('insert-coin:prune')->setDescription('Delete old Insert Coin play sessions');
    }

    protected function fire(): int
    {
        $deleted = PlaySession::query()->whereNull('submitted_at')->where('started_at', '<', Carbon::now()->subDays(2))->delete()
            + PlaySession::query()->where('started_at', '<', Carbon::now()->subDays(90))->delete();

        $this->info("Deleted $deleted play sessions.");

        return 0;
    }
}
