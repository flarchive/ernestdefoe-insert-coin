<?php

namespace ErnestDefoe\InsertCoin;

use ErnestDefoe\InsertCoin\Source\BundledRenderer;
use ErnestDefoe\InsertCoin\Source\PackReader;
use ErnestDefoe\InsertCoin\Source\PackRenderer;
use ErnestDefoe\InsertCoin\Source\Renderer;
use Illuminate\Contracts\Container\Container;

/**
 * The kinds of game the arcade can frame, and the pack formats it can read.
 * Add-ons extend both with Extend\GameSource.
 */
class Sources
{
    /** @var array<string, class-string<Renderer>> */
    protected static array $renderers = ['bundled' => BundledRenderer::class, 'pack' => PackRenderer::class];

    /** @var list<class-string<PackReader>> */
    protected static array $readers = [];

    public function __construct(protected Container $container)
    {
    }

    /** @param class-string<Renderer> $renderer */
    public static function addRenderer(string $source, string $renderer): void
    {
        static::$renderers[$source] = $renderer;
    }

    /** @param class-string<PackReader> $reader */
    public static function addReader(string $reader): void
    {
        static::$readers[] = $reader;
    }

    public static function hasRenderer(string $source): bool
    {
        return isset(static::$renderers[$source]);
    }

    public function renderer(string $source): ?Renderer
    {
        return isset(static::$renderers[$source]) ? $this->container->make(static::$renderers[$source]) : null;
    }

    /** @return list<PackReader> */
    public function readers(): array
    {
        return array_map(fn ($class) => $this->container->make($class), static::$readers);
    }
}
