<?php

namespace ErnestDefoe\InsertCoin;

use ErnestDefoe\InsertCoin\Model\Game;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\User\User;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;

/** The JSON the forum reads: games, members and scores. Never a query. */
class Presenter
{
    protected Cloud $assets;

    public function __construct(protected UrlGenerator $url, protected SlugManager $slugs, Factory $filesystems)
    {
        /** @var Cloud $assets Flarum's disks are cloud disks: they have URLs. */
        $assets = $filesystems->disk('flarum-assets');
        $this->assets = $assets;
    }

    /** @return array<string, mixed>|null */
    public function person(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'username' => $user->username,
            'displayName' => $user->display_name,
            'avatarUrl' => $user->avatar_url,
            'slug' => $this->slugs->forResource(User::class)->toSlug($user),
            'crowns' => (int) $user->getAttribute('insertcoin_crowns'),
        ];
    }

    /** @return array<string, mixed> */
    public function game(Game $game, bool $admin = false): array
    {
        $data = [
            'id' => $game->id,
            'slug' => $game->slug,
            'title' => $game->title,
            'description' => $game->description,
            'categoryId' => $game->category_id,
            'thumbnailUrl' => $this->thumbnail($game),
            'width' => $game->width,
            'height' => $game->height,
            'mobile' => $game->mobile,
            'scoreOrder' => $game->score_order,
            'scoreLabel' => $game->score_label,
            'plays' => $game->plays_count,
            'enabled' => $game->enabled,
            'crownApproval' => $game->crown_approval,
            'source' => $game->source,
            'frameUrl' => in_array($game->source, Game::SOURCES_WITH_FRAME, true) || Sources::hasRenderer($game->source)
                ? $this->url->to('forum')->route('insert-coin.frame', ['id' => $game->id]).'?v='.$game->updated_at?->getTimestamp()
                : null,
            'externalUrl' => $game->source === 'url' ? $game->url : null,
            'champion' => $game->champion_user_id ? [
                'user' => $this->person($game->champion),
                'score' => $game->champion_score,
                'since' => $game->champion_since?->toIso8601String(),
            ] : null,
        ];

        if ($admin) {
            $data += [
                'position' => $game->position,
                'scoreCeiling' => $game->score_ceiling,
                'minSeconds' => $game->min_seconds,
                'maxSeconds' => $game->max_seconds,
                'path' => $game->path,
                'url' => $game->url,
            ];
        }

        return $data;
    }

    public function thumbnail(Game $game): ?string
    {
        if (! $game->thumbnail) {
            return null;
        }

        return preg_match('~^https?://~i', $game->thumbnail) ? $game->thumbnail : $this->assets->url($game->thumbnail);
    }

    /** A score as people read it: whole numbers without decimals, then the game's label. */
    public static function number(float $score, Game $game): string
    {
        $text = floor($score) === $score ? number_format($score, 0, '.', ',') : rtrim(rtrim(number_format($score, 3, '.', ','), '0'), '.');

        return trim($text.' '.($game->score_label ?? ''));
    }
}
