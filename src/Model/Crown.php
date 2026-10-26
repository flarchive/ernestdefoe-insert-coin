<?php

namespace ErnestDefoe\InsertCoin\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_id
 * @property int $user_id
 * @property int|null $previous_user_id
 * @property float $score
 * @property Carbon $created_at
 * @property User|null $user
 * @property User|null $previous
 * @property Game|null $game
 */
class Crown extends AbstractModel
{
    protected $table = 'insertcoin_crowns';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'game_id' => 'integer',
        'user_id' => 'integer',
        'previous_user_id' => 'integer',
        'score' => 'float',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_user_id');
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
