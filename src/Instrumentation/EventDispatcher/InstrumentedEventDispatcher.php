<?php
declare(strict_types=1);

namespace Zim\SymfonyEventDispatcherTracingBundle\Instrumentation\EventDispatcher;

use OpenTelemetry\API\Trace\TracerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Zim\SymfonyTracingCoreBundle\RootContextProvider;
use Symfony\Contracts\Service\ResetInterface;

class InstrumentedEventDispatcher implements EventDispatcherInterface, ResetInterface
{
    /**
     * @var InstrumentedEventListener[]
     */
    private array $addedListeners = [];

    public function __construct(
        private readonly EventDispatcherInterface $inner,
        private readonly TracerInterface $tracer,
        private readonly RootContextProvider $rootContextProvider,
    )
    {
    }

    public function dispatch(object $event, string $eventName = null): object
    {
        return $this->inner->dispatch($event, $eventName);
    }

    public function addListener(string $eventName, callable|array $listener, int $priority = 0): void
    {
        $listener = new InstrumentedEventListener(
            $listener,
            $this->tracer,
            fn() => $this->rootContextProvider->get(),
        );

        $this->inner->addListener($eventName, $listener, $priority);
        $this->addedListeners[$eventName][] = $listener;
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->inner->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        foreach ($this->addedListeners[$eventName] ?? [] as $addedListener) {
            $inner = $addedListener->getInner();

            if ($inner === $listener) {
                $this->inner->removeListener($eventName, $addedListener);
                return;
            }
        }

        throw new \Exception('Can not remove listener');
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->inner->removeSubscriber($subscriber);
    }

    public function getListeners(?string $eventName = null): array
    {
        return $this->inner->getListeners();
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return $this->inner->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->inner->hasListeners($eventName);
    }

    public function reset()
    {
        $this->addedListeners = [];
    }
}
