<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Pack\PackInstaller;
use ErnestDefoe\InsertCoin\Presenter;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Admin uploads:
 *   POST /insert-coin/manage/packs                 a game pack (.zip); `game` replaces that game's files
 *   POST /insert-coin/manage/games/{id}/thumbnail  a cover image
 */
class UploadController implements RequestHandlerInterface
{
    use Concerns;

    public const MAX_THUMBNAIL = 5242880;

    public function __construct(protected PackInstaller $installer, protected Presenter $presenter, protected TranslatorInterface $translator, protected Factory $filesystems)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        if ($request->getAttribute('routeName') === 'insert-coin.manage.packs') {
            $file = $this->file($request, 'pack');
            $replace = ($id = (int) Arr::get((array) $request->getParsedBody(), 'game', 0)) ? Game::query()->findOrFail($id) : null;
            $tmp = tempnam(sys_get_temp_dir(), 'insertcoin');

            try {
                $file->moveTo($tmp);
                $game = $this->installer->install($tmp, $replace);
            } finally {
                @unlink($tmp);
            }

            return new JsonResponse(['data' => $this->presenter->game($game, true)], $replace ? 200 : 201);
        }

        /** @var Game $game */
        $game = Game::query()->findOrFail($this->routeId($request));
        $file = $this->file($request, 'thumbnail');
        $contents = (string) $file->getStream();
        $info = $file->getSize() <= self::MAX_THUMBNAIL ? @getimagesizefromstring($contents) : false;
        $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];

        if (! $info || ! isset($types[$info[2]])) {
            throw new ValidationException(['thumbnail' => $this->translator->trans('ernestdefoe-insert-coin.api.thumbnail')]);
        }

        $assets = $this->filesystems->disk('flarum-assets');
        $path = 'insert-coin/thumbs/'.$game->id.'-'.strtolower(Str::random(10)).'.'.$types[$info[2]];
        $assets->put($path, $contents);

        if ($game->thumbnail && str_starts_with($game->thumbnail, 'insert-coin/thumbs/')) {
            $assets->delete($game->thumbnail);
        }

        $game->thumbnail = $path;
        $game->save();

        return new JsonResponse(['data' => $this->presenter->game($game->load('champion'), true)]);
    }

    protected function file(ServerRequestInterface $request, string $name): UploadedFileInterface
    {
        $file = Arr::get($request->getUploadedFiles(), $name);

        if (! $file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException([$name => $this->translator->trans('ernestdefoe-insert-coin.api.upload_failed')]);
        }

        return $file;
    }
}
