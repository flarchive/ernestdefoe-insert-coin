<?php

/*
 * Insert Coin — an arcade for Flarum.
 *
 * The shape of it: games run in a sandboxed iframe (the "cabinet") and report
 * scores through a tiny postMessage SDK. Pressing Start asks the server for a
 * signed play session; the score for that session is accepted once, within
 * the game's time limits and under its ceiling. Each member's best per game is
 * kept in one row, the champion on the game itself, and a crown changing
 * hands notifies the member who lost it.
 */

use ErnestDefoe\InsertCoin\Access;
use ErnestDefoe\InsertCoin\Api;
use ErnestDefoe\InsertCoin\Console\DailySchedule;
use ErnestDefoe\InsertCoin\Console\PruneSessionsCommand;
use ErnestDefoe\InsertCoin\Content\GameDocument;
use ErnestDefoe\InsertCoin\Event\CrownTaken;
use ErnestDefoe\InsertCoin\Event\ScoreAccepted;
use ErnestDefoe\InsertCoin\Frame\FrameController;
use ErnestDefoe\InsertCoin\Listener\GrantCrownPoints;
use ErnestDefoe\InsertCoin\Listener\KeepCrownsWhenUsersGo;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Notification\DethronedBlueprint;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // The arcade's pages are their own chunks; other pages load none of it.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->route('/arcade', 'insert-coin.home')
        ->route('/arcade/moderate', 'insert-coin.moderate')
        ->route('/arcade/{slug:[a-z0-9-]+}', 'insert-coin.game', GameDocument::class)
        ->route('/u/{username}/arcade', 'user.arcade'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\View())
        ->namespace('ernestdefoe-insert-coin', __DIR__.'/views'),

    // The page inside the cabinet: a forum route with no file extension, so
    // nginx's static rules never answer it in PHP's place.
    (new Extend\Routes('forum'))
        ->get('/arcade/frame/{id:\d+}', 'insert-coin.frame', FrameController::class),

    (new Extend\Routes('api'))
        ->get('/insert-coin', 'insert-coin.home', Api\HomeController::class)
        ->get('/insert-coin/games/{slug:[a-z0-9-]+}', 'insert-coin.games.show', Api\GameController::class)
        ->get('/insert-coin/games/{id:\d+}/scores', 'insert-coin.games.scores', Api\BoardController::class)
        ->post('/insert-coin/games/{id:\d+}/sessions', 'insert-coin.sessions.start', Api\SessionController::class)
        ->post('/insert-coin/sessions/{id:\d+}/score', 'insert-coin.sessions.score', Api\ScoreController::class)
        ->get('/insert-coin/users/{id:\d+}', 'insert-coin.users.show', Api\ProfileController::class)
        ->get('/insert-coin/crowns', 'insert-coin.crowns', Api\CrownsController::class)
        // Moderators
        ->get('/insert-coin/queue', 'insert-coin.queue', Api\ModerationController::class)
        ->post('/insert-coin/scores/{id:\d+}/approve', 'insert-coin.scores.approve', Api\ModerationController::class)
        ->delete('/insert-coin/scores/{id:\d+}', 'insert-coin.scores.delete', Api\ModerationController::class)
        ->delete('/insert-coin/games/{id:\d+}/scores', 'insert-coin.games.wipe', Api\ModerationController::class)
        ->delete('/insert-coin/users/{id:\d+}/scores', 'insert-coin.users.wipe', Api\ModerationController::class)
        ->post('/insert-coin/users/{id:\d+}/ban', 'insert-coin.users.ban', Api\ModerationController::class)
        ->delete('/insert-coin/users/{id:\d+}/ban', 'insert-coin.users.unban', Api\ModerationController::class)
        // Admins
        ->get('/insert-coin/manage', 'insert-coin.manage', Api\ManageController::class)
        ->post('/insert-coin/manage/games', 'insert-coin.manage.games.create', Api\ManageController::class)
        ->post('/insert-coin/manage/games/order', 'insert-coin.manage.games.order', Api\ManageController::class)
        ->patch('/insert-coin/manage/games/{id:\d+}', 'insert-coin.manage.games.update', Api\ManageController::class)
        ->delete('/insert-coin/manage/games/{id:\d+}', 'insert-coin.manage.games.delete', Api\ManageController::class)
        ->post('/insert-coin/manage/games/{id:\d+}/thumbnail', 'insert-coin.manage.thumbnail', Api\UploadController::class)
        ->post('/insert-coin/manage/packs', 'insert-coin.manage.packs', Api\UploadController::class)
        ->post('/insert-coin/manage/categories', 'insert-coin.manage.categories.create', Api\ManageController::class)
        ->post('/insert-coin/manage/categories/order', 'insert-coin.manage.categories.order', Api\ManageController::class)
        ->patch('/insert-coin/manage/categories/{id:\d+}', 'insert-coin.manage.categories.update', Api\ManageController::class)
        ->delete('/insert-coin/manage/categories/{id:\d+}', 'insert-coin.manage.categories.delete', Api\ManageController::class)
        ->post('/insert-coin/manage/starters', 'insert-coin.manage.starters', Api\ManageController::class),

    new Extend\ApiResource(Api\GameResource::class),

    (new Extend\Notification())
        ->type(DethronedBlueprint::class, ['alert', 'email']),

    (new Extend\Event())
        ->subscribe(KeepCrownsWhenUsersGo::class),

    (new Extend\Console())
        ->command(PruneSessionsCommand::class)
        ->schedule(PruneSessionsCommand::class, DailySchedule::class),

    (new Extend\Settings())
        ->default('ernestdefoe-insert-coin.show_nav', true)
        ->default('ernestdefoe-insert-coin.crown_points', 0)
        ->default('ernestdefoe-insert-coin.outlier_factor', 3)
        ->default('ernestdefoe-insert-coin.outlier_players', 5)
        ->serializeToForum('insertCoinShowNav', 'ernestdefoe-insert-coin.show_nav', 'boolval'),

    // Permissions the actor's groups were already loaded for: no query.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canPlayArcade')->get(fn ($forum, Context $context) => Access::canPlay($context->getActor())),
            Schema\Boolean::make('canSubmitArcade')->get(fn ($forum, Context $context) => Access::canSubmit($context->getActor())),
            Schema\Boolean::make('canModerateArcade')->get(fn ($forum, Context $context) => Access::canModerate($context->getActor())),
        ]),

    // Crowns are counted on the member, so any user card can show them for free.
    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Schema\Integer::make('arcadeCrowns')->get(fn (User $user) => (int) $user->getAttribute('insertcoin_crowns')),
            Schema\Boolean::make('arcadeBanned')
                ->visible(fn (User $user, Context $context) => Access::canModerate($context->getActor()))
                ->get(fn (User $user) => Access::banned($user)),
        ]),

    (new Extend\Conditional())
        ->whenExtensionEnabled('fof-gamification', fn () => [
            (new Extend\Event())->listen(CrownTaken::class, GrantCrownPoints::class),
        ])
        ->whenExtensionEnabled('flarum-realtime', fn () => [
            (new \Flarum\Realtime\Extend\Realtime())
                ->broadcastModelEvent(ScoreAccepted::class, fn (ScoreAccepted $event) => $event->game, null, 'insertCoinScore')
                ->registerModelEndpoint(Game::class, 'insert-coin-games'),
        ]),
];
