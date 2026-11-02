<?php

namespace ErnestDefoe\InsertCoin\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property int $game_id
 * @property int $user_id
 * @property Carbon $started_at
 * @property Carbon|null $submitted_at
 */
class PlaySession extends AbstractModel
{
    protected $table = 'insertcoin_sessions';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'game_id' => 'integer',
        'user_id' => 'integer',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];
}
