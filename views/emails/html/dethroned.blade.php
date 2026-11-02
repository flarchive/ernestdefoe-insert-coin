<x-mail::html.notification :title="$translator->trans('ernestdefoe-insert-coin.email.dethroned.subject', ['game' => $blueprint->game->title, 'user' => $blueprint->champion->display_name])">
    <x-slot:body>
        <p>{{ $translator->trans('ernestdefoe-insert-coin.email.dethroned.body', ['game' => $blueprint->game->title, 'user' => $blueprint->champion->display_name, 'score' => \ErnestDefoe\InsertCoin\Presenter::number($blueprint->score, $blueprint->game)]) }}</p>
        <p><a href="{{ $url->to('forum')->route('insert-coin.game', ['slug' => $blueprint->game->slug]) }}">{{ $translator->trans('ernestdefoe-insert-coin.email.dethroned.link') }}</a></p>
    </x-slot:body>
</x-mail::html.notification>
