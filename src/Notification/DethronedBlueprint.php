<?php

namespace ErnestDefoe\InsertCoin\Notification;

use ErnestDefoe\InsertCoin\Model\Game;
use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/** "You've been dethroned on {game} by {user}." */
class DethronedBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(public Game $game, public User $champion, public float $score)
    {
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->game;
    }

    public function getFromUser(): ?User
    {
        return $this->champion;
    }

    public function getData(): mixed
    {
        return ['score' => $this->score];
    }

    public static function getType(): string
    {
        return 'insertCoinDethroned';
    }

    public static function getSubjectModel(): string
    {
        return Game::class;
    }

    public function getEmailViews(): array
    {
        return ['text' => 'ernestdefoe-insert-coin::emails.plain.dethroned', 'html' => 'ernestdefoe-insert-coin::emails.html.dethroned'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('ernestdefoe-insert-coin.email.dethroned.subject', [
            'game' => $this->game->title,
            'user' => $this->champion->display_name,
        ]);
    }
}
