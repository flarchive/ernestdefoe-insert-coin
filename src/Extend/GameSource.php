<?php

namespace ErnestDefoe\InsertCoin\Extend;

use ErnestDefoe\InsertCoin\Source\PackReader;
use ErnestDefoe\InsertCoin\Source\Renderer;
use ErnestDefoe\InsertCoin\Sources;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;

/**
 * Teach the arcade a new kind of game:
 *
 *     (new GameSource('flash'))->renderer(RufflePage::class)->packReader(IbProPack::class)
 */
class GameSource implements ExtenderInterface
{
    /** @var class-string<Renderer>|null */
    protected ?string $renderer = null;

    /** @var list<class-string<PackReader>> */
    protected array $readers = [];

    public function __construct(protected string $source)
    {
    }

    /** @param class-string<Renderer> $renderer */
    public function renderer(string $renderer): self
    {
        $this->renderer = $renderer;

        return $this;
    }

    /** @param class-string<PackReader> $reader */
    public function packReader(string $reader): self
    {
        $this->readers[] = $reader;

        return $this;
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        if ($this->renderer) {
            Sources::addRenderer($this->source, $this->renderer);
        }

        foreach ($this->readers as $reader) {
            Sources::addReader($reader);
        }
    }
}
