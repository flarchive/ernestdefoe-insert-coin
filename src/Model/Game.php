<?php

namespace ErnestDefoe\InsertCoin\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $category_id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string $source bundled|pack|url, or one an add-on registers
 * @property string|null $path
 * @property string|null $url
 * @property string|null $entry the entry file's path inside a pack
 * @property string|null $entry_html
 * @property string|null $thumbnail
 * @property int $width
 * @property int $height
 * @property bool $mobile
 * @property string $score_order high|low
 * @property string|null $score_label
 * @property float|null $score_ceiling
 * @property int $min_seconds
 * @property int $max_seconds
 * @property bool $crown_approval
 * @property bool $enabled
 * @property int $position
 * @property int $plays_count
 * @property int|null $champion_user_id
 * @property float|null $champion_score
 * @property Carbon|null $champion_since
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User|null $champion
 * @property Category|null $category
 */
class Game extends AbstractModel
{
    public const SOURCES_WITH_FRAME = ['bundled', 'pack'];

    /** Slugs the arcade's own pages use under /arcade. */
    public const RESERVED = ['moderate', 'frame'];

    protected $table = 'insertcoin_games';

    public $timestamps = true;

    protected $hidden = ['entry_html'];

    protected $casts = [
        'id' => 'integer',
        'category_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'mobile' => 'boolean',
        'score_ceiling' => 'float',
        'min_seconds' => 'integer',
        'max_seconds' => 'integer',
        'crown_approval' => 'boolean',
        'enabled' => 'boolean',
        'position' => 'integer',
        'plays_count' => 'integer',
        'champion_user_id' => 'integer',
        'champion_score' => 'float',
        'champion_since' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function champion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'champion_user_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function lowWins(): bool
    {
        return $this->score_order === 'low';
    }

    /** Whether score $a beats score $b on this game. A tie never wins. */
    public function beats(float $a, ?float $b): bool
    {
        return $b === null || ($this->lowWins() ? $a < $b : $a > $b);
    }

    public function direction(): string
    {
        return $this->lowWins() ? 'asc' : 'desc';
    }

    /**
     * Games the actor may play: enabled ones, and every one for admins.
     *
     * @param Builder<Game> $query
     */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if (! $actor->hasPermission('insertCoin.play')) {
            $query->whereRaw('1 = 0');
        } elseif (! $actor->isAdmin()) {
            $query->where('enabled', true);
        }
    }
}
