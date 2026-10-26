<?php

namespace ErnestDefoe\InsertCoin\Listener;

use ErnestDefoe\InsertCoin\Arcade;
use ErnestDefoe\InsertCoin\Event\CrownTaken;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;

/**
 * FoF Gamification points for taking a crown, when the forum gives any.
 *
 * 🚨 fof/gamification is optional and resolved by NAME: this listener is only
 * registered while it is enabled, and pays only where its point-grants seam
 * exists. Each grant is keyed by the crown, so it is never paid twice.
 */
class GrantCrownPoints
{
    public const POINT_GRANTS = 'FoF\Gamification\PointGrants';

    public function __construct(protected SettingsRepositoryInterface $settings, protected Container $container)
    {
    }

    public function handle(CrownTaken $event): void
    {
        $points = (int) $this->settings->get(Arcade::PREFIX.'crown_points');

        if ($points !== 0 && class_exists(self::POINT_GRANTS)) {
            $this->container->make(self::POINT_GRANTS)->grant($event->champion, 'insert-coin', 'crown:'.$event->crown->id, $points);
        }
    }
}
