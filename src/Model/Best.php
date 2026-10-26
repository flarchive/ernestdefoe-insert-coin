<?php

namespace ErnestDefoe\InsertCoin\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $game_id
 * @property int $user_id
 * @property int $score_id
 * @property float $score
 * @property Carbon $achieved_at
 * @property User|null $user
 * @property Game|null $game
 */
class Best extends AbstractModel
{
    protected $table = 'insertcoin_bests';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $casts = [
        'game_id' => 'integer',
        'user_id' => 'integer',
        'score_id' => 'integer',
        'score' => 'float',
        'achieved_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
