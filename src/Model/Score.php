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
 * @property int|null $session_id
 * @property float $score
 * @property int $duration
 * @property string $status approved|flagged|pending
 * @property string|null $reason outlier|crown
 * @property Carbon $created_at
 * @property User|null $user
 * @property Game|null $game
 */
class Score extends AbstractModel
{
    public const APPROVED = 'approved';
    public const FLAGGED = 'flagged';
    public const PENDING = 'pending';

    protected $table = 'insertcoin_scores';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'game_id' => 'integer',
        'user_id' => 'integer',
        'session_id' => 'integer',
        'score' => 'float',
        'duration' => 'integer',
        'created_at' => 'datetime',
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
