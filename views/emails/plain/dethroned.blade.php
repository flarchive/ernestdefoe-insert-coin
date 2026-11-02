<x-mail::plain.notification :title="$translator->trans('ernestdefoe-insert-coin.email.dethroned.subject', ['game' => $blueprint->game->title, 'user' => $blueprint->champion->display_name])">
<x-slot:body>
{!! $translator->trans('ernestdefoe-insert-coin.email.dethroned.body', ['game' => $blueprint->game->title, 'user' => $blueprint->champion->display_name, 'score' => \ErnestDefoe\InsertCoin\Presenter::number($blueprint->score, $blueprint->game)]) !!}

{!! $url->to('forum')->route('insert-coin.game', ['slug' => $blueprint->game->slug]) !!}
</x-slot:body>
</x-mail::plain.notification>
