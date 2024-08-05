<?php
declare(strict_types=1);

namespace Zim\SymfonyEventDispatcherTracingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Zim\SymfonyEventDispatcherTracingBundle\Instrumentation\EventDispatcher\InstrumentedEventDispatcher;

class ReplaceEventDispatcherPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $decoratedServices = $container->getParameter('tracing.event_dispatcher.decorated_services');

        foreach ($decoratedServices as $params) {
            $instrumented = (new Definition(InstrumentedEventDispatcher::class));

            $instrumented->setArguments([
                '$tracer' => new Reference('tracing.tracer.event_dispatcher'),
                '$rootContextProvider' => new Reference('tracing.root_context_provider'),
            ]);
            $instrumented->setPublic(true);

            $container->setDefinition($params['service'], $instrumented);

            $container->setAlias('event_dispatcher', $params['service'])->setPublic(true);
        }

        $container->getParameterBag()->remove('tracing.event_dispatcher.decorated_services');
    }
}
