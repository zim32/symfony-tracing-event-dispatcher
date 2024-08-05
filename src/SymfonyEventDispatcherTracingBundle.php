<?php
declare(strict_types=1);

namespace Zim\SymfonyEventDispatcherTracingBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Zim\SymfonyEventDispatcherTracingBundle\DependencyInjection\DecorateEventDispatcherPass;
use Zim\SymfonyEventDispatcherTracingBundle\DependencyInjection\ReplaceEventDispatcherPass;

class SymfonyEventDispatcherTracingBundle extends AbstractBundle
{
    protected string $extensionAlias = 'event_dispatcher_tracing';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('decorated_services')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('service')
                                ->isRequired()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('tracing.event_dispatcher.decorated_services', $config['decorated_services']);
    }

    public function build(ContainerBuilder $container)
    {
//        $container->addCompilerPass(new ReplaceEventDispatcherPass());
        $container->addCompilerPass(new DecorateEventDispatcherPass());
    }
}
