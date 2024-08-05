<?php
declare(strict_types=1);

namespace Zim\SymfonyEventDispatcherTracingBundle\Instrumentation\EventDispatcher;

use Closure;
use OpenTelemetry\API\Trace\TracerInterface;
use ReflectionFunction;
use Symfony\Component\EventDispatcher\Debug\WrappedListener;

class InstrumentedEventListener
{
    private mixed $inner;
    private TracerInterface $tracer;
    private Closure $rootContext;
    private string $pretty;

    public function __construct(
        mixed $inner,
        TracerInterface $tracer,
        Closure $rootContext,
    )
    {
        if ($inner instanceof WrappedListener) {
            $inner = $inner->getWrappedListener();
        }

        $this->parseListenerName($inner);

        if (is_array($inner) && isset($inner[0]) && $inner[0] instanceof Closure && 2 >= count($inner)) {
            $inner[0] = $inner[0]();
            $inner[1] ??= '__invoke';
        }

        $this->inner = $inner;
        $this->tracer = $tracer;
        $this->rootContext = $rootContext;
    }

    public function __invoke(...$args)
    {
        $rootContext = ($this->rootContext)();

        if ($rootContext === null) {
            return ($this->inner)(...$args);
        }

        $spanName = sprintf('Event listener: %s', $this->pretty);

        $span = $this->tracer
            ->spanBuilder($spanName)
            ->setParent(($this->rootContext)())
            ->startSpan()
        ;

        try {
            return ($this->inner)(...$args);
        } finally {
            $span->end();
        }
    }

    public function getInner(): mixed
    {
        return $this->inner;
    }

    private function parseListenerName(mixed $listener): void
    {
        if (is_array($listener)) {
            $this->pretty = $this->parseArrayListener($listener) .'::'.$listener[1];
        } elseif ($listener instanceof \Closure) {
            $r = new ReflectionFunction($listener);
            if ($r->isAnonymous()) {
                $this->pretty = 'closure';
            } elseif ($class = $r->getClosureCalledClass()) {
                $name = $class->name;
                $this->pretty = $name .'::'.$r->name;
            } else {
                $this->pretty = $r->name;
            }
        } elseif (is_string($listener)) {
            $this->pretty = $listener;
        } else {
            $name = get_debug_type($listener);
            $this->pretty = $name .'::__invoke';
        }
    }

    private function parseArrayListener(array $listener): string
    {
        if ($listener[0] instanceof Closure) {
            foreach ((new ReflectionFunction($listener[0]))->getAttributes(Closure::class) as $attribute) {
                if ($name = $attribute->getArguments()['name'] ?? false) {
                    return $name;
                }
            }
        }

        if (is_object($listener[0])) {
            return get_debug_type($listener[0]);
        }

        return $listener[0];
    }
}
