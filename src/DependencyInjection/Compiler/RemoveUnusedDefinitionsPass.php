<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\AbstractRecursivePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RemoveUnusedDefinitionsPass extends AbstractRecursivePass
{
    private array $connectedIds = [];

    /** @var Definition[] */
    public static array $removedDefinitions = [];

    /**
     * Processes the ContainerBuilder to remove unused definitions.
     */
    public function process(ContainerBuilder $container)
    {
        try {
            $this->enableExpressionProcessing();
            $this->container = $container;
            $connectedIds = [];
            $aliases = $container->getAliases();

            foreach ($aliases as $id => $alias) {
                if ($alias->isPublic()) {
                    $this->connectedIds[] = (string) $aliases[$id];
                }
            }

            foreach ($container->getDefinitions() as $id => $definition) {
                if ($definition->isPublic()) {
                    $connectedIds[$id] = true;
                    $this->processValue($definition);
                }
            }

            while ($this->connectedIds) {
                $ids = $this->connectedIds;
                $this->connectedIds = [];
                foreach ($ids as $id) {
                    if (!isset($connectedIds[$id]) && $container->hasDefinition($id)) {
                        $connectedIds[$id] = true;
                        $this->processValue($container->getDefinition($id));
                    }
                }
            }

            foreach ($container->getDefinitions() as $id => $definition) {
                if (!isset($connectedIds[$id])) {
                    self::$removedDefinitions[$id] = $definition;
                    $container->removeDefinition($id);
                    $container->resolveEnvPlaceholders(!$definition->hasErrors() ? serialize($definition) : $definition);
                    $container->log($this, sprintf('Removed service "%s"; reason: unused.', $id));
                }
            }
        } finally {
            $this->container = null;
            $this->connectedIds = [];
        }
    }

    /**
     * @inheritdoc
     */
    protected function processValue($value, bool $isRoot = false)
    {
        if (!$value instanceof Reference) {
            return parent::processValue($value, $isRoot);
        }

        if (ContainerBuilder::IGNORE_ON_UNINITIALIZED_REFERENCE !== $value->getInvalidBehavior()) {
            $this->connectedIds[] = (string) $value;
        }

        return $value;
    }
}
