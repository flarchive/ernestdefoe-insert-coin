<?php

namespace ErnestDefoe\InsertCoin\Listener;

use ErnestDefoe\InsertCoin\Arcade;
use ErnestDefoe\InsertCoin\Model\Best;
use ErnestDefoe\InsertCoin\Model\Game;
use Flarum\User\Event\Deleted;
use Flarum\User\Event\Deleting;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * When a champion's account is deleted, their bests go with it (a foreign
 * key), so the crown passes to whoever is next on each board.
 */
class KeepCrownsWhenUsersGo
{
    /** @var array<int, list<int>> games each departing user held, by user id */
    protected array $held = [];

    public function __construct(protected Arcade $arcade)
    {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Deleting::class, function (Deleting $event) {
            $this->held[$event->user->id] = Game::query()->where('champion_user_id', $event->user->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        });

        $events->listen(Deleted::class, function (Deleted $event) {
            // The foreign key has done this already where one is enforced.
            Best::query()->where('user_id', $event->user->id)->delete();

            foreach ($this->held[$event->user->id] ?? [] as $gameId) {
                $this->arcade->recrown($gameId);
            }

            unset($this->held[$event->user->id]);
        });
    }
}
