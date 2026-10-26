<?php

// The part of flarum/realtime that Insert Coin uses, for PHPStan only.

namespace Flarum\Realtime\Extend {
    use Flarum\Extend\ExtenderInterface;
    use Flarum\Extension\Extension;
    use Illuminate\Contracts\Container\Container;

    class Realtime implements ExtenderInterface
    {
        /** @param string|string[] $events */
        public function broadcastModelEvent(string|array $events, callable $getModel, ?callable $getActor = null, ?string $eventName = null): self
        {
            return $this;
        }

        public function registerModelEndpoint(string $modelClass, string $endpoint): self
        {
            return $this;
        }

        public function extend(Container $container, ?Extension $extension = null): void
        {
        }
    }
}
