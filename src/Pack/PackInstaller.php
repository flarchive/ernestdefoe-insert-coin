<?php

namespace ErnestDefoe\InsertCoin\Pack;

use ErnestDefoe\InsertCoin\Model\Category;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Sources;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Installs a game pack: a .zip with an insertcoin.json manifest.
 *
 * 🚨 A pack is a stranger's files, so nothing in it is trusted:
 * - every path is checked before anything is written: no absolute paths,
 *   drive letters, backslashes, `..`, empty segments or symlinks;
 * - zip bombs are refused (file count, total size, compression ratio);
 * - only an allow-list of inert file types is written to public assets. No
 *   PHP, no HTML, no SVG or XML (both can run script when opened directly),
 *   no dotfiles;
 * - the one HTML page, the entry, is kept in the database and only ever
 *   served by the frame, which is sandboxed.
 */
class PackInstaller
{
    public const MANIFEST = 'insertcoin.json';
    public const MAX_FILES = 3000;
    public const MAX_TOTAL = 268435456; // 256 MB unpacked
    public const MAX_ENTRY = 2097152; // 2 MB
    public const MAX_RATIO = 200;

    /** Inert files a game may carry: code the browser runs only from the page, data, media, fonts. */
    public const ALLOWED = [
        'js', 'mjs', 'css', 'json', 'map', 'txt', 'md', 'csv', 'wasm',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp', 'ico',
        'mp3', 'ogg', 'oga', 'wav', 'm4a', 'aac', 'flac', 'opus', 'weba', 'webm', 'mp4',
        'woff', 'woff2', 'ttf', 'otf',
        'glsl', 'vert', 'frag', 'fnt', 'atlas', 'bin', 'dat', 'data', 'pck',
    ];

    public const IMAGES = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];

    protected Filesystem $assets;

    public function __construct(protected Sources $sources, protected TranslatorInterface $translator, Factory $filesystems)
    {
        $this->assets = $filesystems->disk('flarum-assets');
    }

    public function install(string $zipPath, ?Game $replace = null): Game
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            $this->refuse('bad_zip');
        }

        try {
            return $this->installFrom($zip, $replace);
        } finally {
            $zip->close();
        }
    }

    protected function installFrom(ZipArchive $zip, ?Game $replace): Game
    {
        $files = $this->entries($zip);
        [$prefix, $manifest] = $this->manifest($zip, $files);

        $source = (string) ($manifest['source'] ?? 'pack');
        $entry = $this->path((string) ($manifest['entry'] ?? 'index.html'));
        $allowed = self::ALLOWED;

        foreach ($source === 'pack' ? [] : $this->sources->readers() as $reader) {
            $allowed = array_merge($allowed, $reader->extensions());
        }

        // Paths inside the pack, relative to its root, and what to do with each.
        $write = [];
        $rejected = [];

        foreach ($files as $name => $index) {
            if ($prefix !== '' && ! str_starts_with($name, $prefix)) {
                continue;
            }

            $relative = substr($name, strlen($prefix));
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

            if ($relative === self::MANIFEST || ($source === 'pack' && $relative === $entry)) {
                continue;
            }

            if ($extension === '' || ! in_array($extension, $allowed, true) || str_starts_with(basename($relative), '.')) {
                $rejected[] = $relative;
                continue;
            }

            $write[$relative] = $index;
        }

        if ($rejected) {
            $this->refuse('bad_files', ['files' => implode(', ', array_slice($rejected, 0, 5)).(count($rejected) > 5 ? ' …' : '')]);
        }

        $entryHtml = null;

        if ($source === 'pack') {
            if (! isset($files[$prefix.$entry]) || ! in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), ['html', 'htm'], true)) {
                $this->refuse('no_entry', ['entry' => $entry]);
            }

            $entryHtml = (string) $zip->getFromIndex($files[$prefix.$entry]);

            if (strlen($entryHtml) > self::MAX_ENTRY) {
                $this->refuse('entry_too_big');
            }

            if (! mb_check_encoding($entryHtml, 'UTF-8')) {
                $entryHtml = mb_convert_encoding($entryHtml, 'UTF-8', 'Windows-1252');
            }
        } elseif (! isset($write[$entry])) {
            $this->refuse('no_entry', ['entry' => $entry]);
        }

        $game = $replace ?? new Game();
        $slug = $replace ? $replace->slug : $this->uniqueSlug(Str::slug((string) ($manifest['slug'] ?? '')) ?: Str::slug($manifest['title']) ?: 'game');
        $dir = $slug.'-'.strtolower(Str::random(8));
        $base = 'insert-coin/packs/'.$dir.'/';
        $oldPath = $replace?->path;
        $oldThumbnail = $replace?->thumbnail;

        try {
            // Streamed, so a large sound or video file never sits in memory whole.
            foreach ($write as $relative => $index) {
                $stream = $zip->getStreamIndex($index);

                if ($stream === false) {
                    $this->refuse('bad_zip');
                }

                $this->assets->writeStream($base.$relative, $stream);
                fclose($stream);
            }

            if (! $this->assets->exists('insert-coin/packs/.htaccess')) {
                // Apache only; lets games fetch their own files from the sandbox.
                $this->assets->put('insert-coin/packs/.htaccess', "<IfModule mod_headers.c>\n    Header set Access-Control-Allow-Origin \"*\"\n    Header set X-Content-Type-Options \"nosniff\"\n</IfModule>\n");
            }

            $thumbnail = isset($manifest['thumbnail']) ? $this->path((string) $manifest['thumbnail']) : null;

            $game->slug = $slug;
            $game->title = Str::limit(trim($manifest['title']), 150, '');
            $game->description = isset($manifest['description']) ? Str::limit(trim((string) $manifest['description']), 2000, '') : $game->description;
            $game->source = $source;
            $game->path = $dir;
            $game->entry = $entry;
            $game->entry_html = $entryHtml;
            $game->url = null;
            $game->width = $this->int($manifest['width'] ?? 4, 1, 4000);
            $game->height = $this->int($manifest['height'] ?? 3, 1, 4000);
            $game->mobile = (bool) ($manifest['mobile'] ?? false);
            $game->score_order = ($manifest['scoreOrder'] ?? 'high') === 'low' ? 'low' : 'high';
            $game->score_label = isset($manifest['scoreLabel']) ? Str::limit((string) $manifest['scoreLabel'], 30, '') : null;
            $game->score_ceiling = isset($manifest['ceiling']) && is_numeric($manifest['ceiling']) ? (float) $manifest['ceiling'] : null;
            $game->min_seconds = $this->int($manifest['minSeconds'] ?? 3, 0, 3600);
            $game->max_seconds = $this->int($manifest['maxSeconds'] ?? 10800, 10, 86400);

            if ($thumbnail && isset($write[$thumbnail]) && in_array(strtolower(pathinfo($thumbnail, PATHINFO_EXTENSION)), self::IMAGES, true)) {
                $game->thumbnail = $base.$thumbnail;
            } elseif ($oldThumbnail && str_starts_with($oldThumbnail, 'insert-coin/packs/'.$oldPath.'/')) {
                $game->thumbnail = null;
            }

            if (! $replace) {
                $game->enabled = false;
                $game->position = (int) Game::query()->max('position') + 1;
                $game->category_id = $this->category($manifest['category'] ?? null);
            }

            $game->save();
        } catch (Throwable $e) {
            $this->assets->deleteDirectory(rtrim($base, '/'));

            throw $e;
        }

        if ($oldPath && $oldPath !== $dir && preg_match('/^[a-z0-9-]+$/', $oldPath)) {
            $this->assets->deleteDirectory('insert-coin/packs/'.$oldPath);
        }

        return $game;
    }

    /**
     * Every file in the zip by name, after the safety checks.
     *
     * @return array<string, int>
     */
    protected function entries(ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_FILES) {
            $this->refuse('too_many', ['max' => self::MAX_FILES]);
        }

        $files = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat ? (string) $stat['name'] : '';

            if (str_ends_with($name, '/')) {
                continue;
            }

            $this->path($name);

            $segments = explode('/', $name);
            $base = end($segments);

            // macOS and Windows leave these in archives (they broke a forum once: ._ files are not PHP-safe).
            if (in_array('__MACOSX', $segments, true) || str_starts_with($base, '._') || in_array($base, ['.DS_Store', 'Thumbs.db', 'desktop.ini'], true)) {
                continue;
            }

            if ($zip->getExternalAttributesIndex($i, $system, $attributes) && $system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                $this->refuse('unsafe_path', ['path' => $name]);
            }

            $size = (int) $stat['size'];
            $total += $size;

            if ($total > self::MAX_TOTAL || ($size > 1048576 && $size > max(1, (int) $stat['comp_size']) * self::MAX_RATIO)) {
                $this->refuse('too_big');
            }

            $files[$name] = $i;
        }

        return $files;
    }

    /**
     * The manifest, and the folder the pack lives in when it was zipped with one.
     *
     * @param array<string, int> $files
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function manifest(ZipArchive $zip, array $files): array
    {
        $prefix = null;

        if (isset($files[self::MANIFEST])) {
            $prefix = '';
        } else {
            $roots = array_unique(array_map(fn ($name) => strstr($name, '/', true) ?: '', array_keys($files)));

            if (count($roots) === 1 && $roots[0] !== '' && isset($files[$roots[0].'/'.self::MANIFEST])) {
                $prefix = $roots[0].'/';
            }
        }

        if ($prefix !== null) {
            $manifest = json_decode((string) $zip->getFromIndex($files[$prefix.self::MANIFEST], 65536), true);

            if (! is_array($manifest) || ! is_string($manifest['title'] ?? null) || trim($manifest['title']) === '') {
                $this->refuse('bad_manifest');
            }

            unset($manifest['source']);

            return [$prefix, $manifest];
        }

        foreach ($this->sources->readers() as $reader) {
            $manifest = $reader->read($zip, array_keys($files));

            if (is_array($manifest) && is_string($manifest['title'] ?? null) && trim($manifest['title']) !== '') {
                return [(string) ($manifest['prefix'] ?? ''), $manifest];
            }
        }

        $this->refuse('no_manifest');
    }

    /** A relative path inside the pack, or a refusal. */
    protected function path(string $path): string
    {
        $segments = explode('/', $path);

        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/')
            || preg_match('/^[a-z]:/i', $path) || array_intersect($segments, ['', '.', '..'])) {
            $this->refuse('unsafe_path', ['path' => Str::limit($path, 80)]);
        }

        return $path;
    }

    protected function uniqueSlug(string $slug): string
    {
        $slug = Str::limit($slug, 80, '');
        $try = $slug;

        for ($n = 2; in_array($try, Game::RESERVED, true) || ctype_digit($try) || Game::query()->where('slug', $try)->exists(); $n++) {
            $try = $slug.'-'.$n;
        }

        return $try;
    }

    protected function category(mixed $name): ?int
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return Category::query()->where('slug', Str::slug($name))->orWhere('name', trim($name))->value('id');
    }

    protected function int(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    /**
     * @param array<string, mixed> $params
     * @return never
     */
    protected function refuse(string $reason, array $params = []): void
    {
        throw new ValidationException(['pack' => $this->translator->trans('ernestdefoe-insert-coin.api.pack.'.$reason, $params)]);
    }
}
