<?php
declare(strict_types=1);

namespace Zim\SymfonyEventDispatcherTracingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Zim\SymfonyEventDispatcherTracingBundle\Instrumentation\EventDispatcher\InstrumentedEventDispatcher;

class DecorateEventDispatcherPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $decoratedServices = $container->getParameter('tracing.event_dispatcher.decorated_services');

        foreach ($decoratedServices as $idx => $params) {
            $decorated = (new Definition(InstrumentedEventDispatcher::class))
                ->setDecoratedService($params['service'])
            ;

            $decorated->setArguments([
                '$inner' => new Reference('.inner'),
                '$tracer' => new Reference('tracing.tracer.event_dispatcher'),
                '$rootContextProvider' => new Reference('tracing.root_context_provider'),
            ]);

            $container->setDefinition("tracing.instrumented_event_dispatcher.$idx", $decorated);
        }

        $container->getParameterBag()->remove('tracing.event_dispatcher.decorated_services');
    }
}
