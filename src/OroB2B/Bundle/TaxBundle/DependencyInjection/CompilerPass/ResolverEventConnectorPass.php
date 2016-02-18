<?php

namespace OroB2B\Bundle\TaxBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ResolverEventConnectorPass implements CompilerPassInterface
{
    const TAG_NAME = 'orob2b_tax.resolver';
    const CONNECTOR_CLASS = 'orob2b_tax.event.resolver_event_connector.class';
    const CONNECTOR_SERVICE_NAME = 'orob2b_tax.event.resolver_event_connector';

    /** {@inheritdoc} */
    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds(self::TAG_NAME);

        foreach ($taggedServices as $id => $tags) {
            if (!$tags) {
                continue;
            }

            $alias = $this->getAlias($id);
            $definition = new Definition($container->getParameter(self::CONNECTOR_CLASS), [new Reference($id)]);
            foreach ($tags as $tag) {
                if (empty($tag['event'])) {
                    throw new \InvalidArgumentException(sprintf('Wrong tags configuration "%s"', json_encode($tags)));
                }

                $attributes = ['event' => $tag['event'], 'method' => 'onResolve'];
                if (!empty($tag['priority'])) {
                    $attributes['priority'] = $tag['priority'];
                }
                $definition->addTag('kernel.event_listener', $attributes);
            }
            $container->setDefinition(sprintf('%s.%s', self::CONNECTOR_SERVICE_NAME, $alias), $definition);
        }
    }

    /**
     * @param string $id
     * @return string
     */
    protected function getAlias($id)
    {
        $parts = explode('.', (string)$id);

        return (string)end($parts);
    }
}
