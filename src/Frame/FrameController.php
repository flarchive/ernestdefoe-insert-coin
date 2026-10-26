<?php

namespace ErnestDefoe\InsertCoin\Frame;

use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Sources;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /arcade/frame/{id} — the page inside the cabinet's iframe.
 *
 * 🚨 The response carries `Content-Security-Policy: sandbox`, so the game runs
 * in an opaque origin even when someone opens this URL directly: it can't read
 * the forum's cookies or call its API as the player. The cabinet's iframe is
 * sandboxed the same way; either alone would do, both is belt and braces.
 */
class FrameController implements RequestHandlerInterface
{
    public function __construct(protected Sources $sources)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');

        /** @var Game|null $game */
        $game = Game::query()->visibleTo($actor)->find($id);
        $renderer = $game ? $this->sources->renderer($game->source) : null;
        $html = $renderer?->html($game);

        if (! $game || $html === null) {
            throw new ModelNotFoundException();
        }

        $base = $renderer->baseUrl($game);
        $inject = ($base ? '<base href="'.htmlspecialchars($base, ENT_QUOTES).'">' : '')
            .'<script>'.file_get_contents(dirname(__DIR__, 2).'/assets/sdk/insertcoin.js').'</script>';

        // Straight after <head>, else <html>, else the doctype: never before
        // the doctype, which would drop the page into quirks mode.
        $html = $this->inject($html, $inject);

        return new HtmlResponse((string) $html, 200, [
            'Content-Security-Policy' => "sandbox allow-scripts allow-pointer-lock; frame-ancestors 'self'",
            // The URL carries the game's version, so a browser may keep it.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    protected function inject(string $html, string $inject): string
    {
        foreach (['/<head\b[^>]*>/i', '/<html\b[^>]*>/i', '/<!doctype[^>]*>/i'] as $pattern) {
            if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE)) {
                $at = $match[0][1] + strlen($match[0][0]);

                return substr($html, 0, $at).$inject.substr($html, $at);
            }
        }

        return $inject.$html;
    }
}
