<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('webgriffe_sylius_upgrade_plugin');
        $rootNode = $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
