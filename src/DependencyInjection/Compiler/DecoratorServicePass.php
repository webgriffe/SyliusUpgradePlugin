<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\AbstractRecursivePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class DecoratorServicePass extends AbstractRecursivePass
{
    /** @var array[] */
    public static array $decoratedServices = [];

    public function process(ContainerBuilder $container)
    {
        $definitions = new \SplPriorityQueue();
        $order = \PHP_INT_MAX;

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$decorated = $definition->getDecoratedService()) {
                continue;
            }
            $definitions->insert([$id, $definition], [$decorated[2], --$order]);
        }
        $decoratingDefinitions = [];

        $tagsToKeep = $container->hasParameter('container.behavior_describing_tags')
            ? $container->getParameter('container.behavior_describing_tags')
            : ['proxy', 'container.do_not_inline', 'container.service_locator', 'container.service_subscriber', 'container.service_subscriber.locator'];

        foreach ($definitions as [$id, $definition]) {
            $decoratedService = $definition->getDecoratedService();
            [$inner, $renamedId] = $decoratedService;
            $invalidBehavior = $decoratedService[3] ?? ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;

            $definition->setDecoratedService(null);

            if (!$renamedId) {
                $renamedId = $id . '.inner';
            }

            $this->currentId = $renamedId;
            $this->processValue($definition);

            $definition->innerServiceId = $renamedId;
            $definition->decorationOnInvalid = $invalidBehavior;

            // we create a new alias/service for the service we are replacing
            // to be able to reference it in the new one
            if ($container->hasAlias($inner)) {
                $alias = $container->getAlias($inner);
                $public = $alias->isPublic();
                $container->setAlias($renamedId, new Alias((string) $alias, false));
                $decoratedDefinition = $container->findDefinition((string) $alias);
            } elseif ($container->hasDefinition($inner)) {
                $decoratedDefinition = $container->getDefinition($inner);
                $public = $decoratedDefinition->isPublic();
                $decoratedDefinition->setPublic(false);
                $container->setDefinition($renamedId, $decoratedDefinition);
                $decoratingDefinitions[$inner] = $decoratedDefinition;
            } elseif (ContainerInterface::IGNORE_ON_INVALID_REFERENCE === $invalidBehavior) {
                $container->removeDefinition($id);

                continue;
            } elseif (ContainerInterface::NULL_ON_INVALID_REFERENCE === $invalidBehavior) {
                $public = $definition->isPublic();
                $decoratedDefinition = null;
            } else {
                throw new ServiceNotFoundException($inner, $id);
            }

            if ($decoratedDefinition?->isSynthetic()) {
                throw new InvalidArgumentException(sprintf('A synthetic service cannot be decorated: service "%s" cannot decorate "%s".', $id, $inner));
            }

            if (isset($decoratingDefinitions[$inner])) {
                $decoratingDefinition = $decoratingDefinitions[$inner];

                $decoratingTags = $decoratingDefinition->getTags();
                $resetTags = [];

                // Behavior-describing tags must not be transferred out to decorators
                foreach ($tagsToKeep as $containerTag) {
                    if (isset($decoratingTags[$containerTag])) {
                        $resetTags[$containerTag] = $decoratingTags[$containerTag];
                        unset($decoratingTags[$containerTag]);
                    }
                }

                $definition->setTags(array_merge($decoratingTags, $definition->getTags()));
                $decoratingDefinition->setTags($resetTags);
                $decoratingDefinitions[$inner] = $definition;
            }
            self::$decoratedServices[$id] = [
                'id' => $inner,
                'definition' => $decoratedDefinition,
            ];

            $container->setAlias($inner, $id)->setPublic($public);
        }
    }

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if ($value instanceof Reference && '.inner' === (string) $value) {
            return new Reference($this->currentId, $value->getInvalidBehavior());
        }

        return parent::processValue($value, $isRoot);
    }
}
