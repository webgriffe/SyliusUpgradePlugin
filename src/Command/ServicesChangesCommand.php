<?php

declare(strict_types=1);

namespace App\Command;

use App\DependencyInjection\Compiler\DecoratorServicePass;
use App\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Command\BuildDebugContainerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass as BaseDecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass as BaseRemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ServicesChangesCommand extends Command
{
    use BuildDebugContainerTrait;

    protected static $defaultName = 'app:process:services-changes';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Kernel $rawKernel */
        $rawKernel = $this->getApplication()->getKernel();
        $buildContainer = \Closure::bind(function () {
            $this->initializeBundles();

            return $this->buildContainer();
        }, $rawKernel, \get_class($rawKernel));
        /** @var ContainerBuilder $rawContainerBuilder */
        $rawContainerBuilder = $buildContainer();
        $removeUnusedDefinitionsPass = new RemoveUnusedDefinitionsPass();
        $decoratorServiceDefinitionsPass = new DecoratorServicePass();
        $this->compile($rawContainerBuilder, $removeUnusedDefinitionsPass, $decoratorServiceDefinitionsPass);
        $rawKernel->boot();

        $decoratedServicesAssociation = [];
        $decoratedDefintions = $decoratorServiceDefinitionsPass::$decoratedServices;

        $output->writeln("\n\n### DEBUG: Computing decorated services");

        $rawDefinitions = $rawContainerBuilder->getDefinitions();
        foreach ($rawDefinitions as $alias => $definition) {
            $decoratedDef = $decoratedDefintions[$alias] ?? null;

            // if the service is an "App" service, seek for the original Sylius service in the decorated definitions
            if ($decoratedDef && (str_starts_with($alias, 'App\\') || str_starts_with($alias, 'app.'))) {
                $decoratedServiceId = $decoratedDef['id'];
                if (str_starts_with($decoratedServiceId, 'sylius.') ||
                    str_starts_with($decoratedServiceId, 'Sylius\\') ||
                    str_starts_with($decoratedServiceId, '\\Sylius\\')) {
                    $class = $decoratedDef['definition']?->getClass();
                    if ($class !== null && class_exists($class)) {
                        $decoratedServicesAssociation[$alias] = $class;
                        $output->writeln(sprintf('Sylius service "%s" has been replaced with "%s"', $decoratedServiceId, $alias));
                        $output->writeln(sprintf("\tFound classpath by 'decorated definitions' %s", $class));

                        continue;
                    }
                }
            }


            // otherwise, the replaced service must be a "Sylius" service
            if (!(str_starts_with($alias, 'sylius.') ||
                str_starts_with($alias, 'Sylius\\') ||
                str_starts_with($alias, '\\Sylius\\'))
            ) {
                continue;
            }

            $definitionClass = $definition->getClass();
            if (!is_string($definitionClass)) {
                continue;
            }

            $aliasNormalized = strtolower($alias);
            // ignore repositories and controllers 'cause they are magically registered
            if (str_contains($aliasNormalized, 'repository') || str_contains($aliasNormalized, 'controller')) {
                continue;
            }

            // the new service must be an "App" service
            if (!str_starts_with($definitionClass, 'App\\')) {
                // this internal service class could have been replaced with an App class even though the original service alias is still untouched

                // todo: search in $decoratedDefintions by alias?
                $decoratedDef = $decoratedDefintions[$definitionClass]['definition'] ?? null;
                if (!$decoratedDef) {
                    continue;
                }

                $decoratedDefClass = $decoratedDef->getClass();
                if (!str_starts_with($decoratedDefClass, 'App\\') || !class_exists($decoratedDefClass)) {
                    continue;
                }

                $decoratedServicesAssociation[$definitionClass] = $decoratedDefClass;
                $output->writeln(sprintf('Sylius service "%s" has been replaced with "%s"', $alias, $definitionClass));
                $output->writeln(sprintf("\tFound classpath by 'decorated definitions' %s", $decoratedDefClass));

                continue;
            }

            $output->writeln(sprintf('Sylius service "%s" has been replaced with "%s"', $alias, $definitionClass));
            if (class_exists($alias)) {
                $decoratedServicesAssociation[$definitionClass] = $alias;
                $output->writeln(sprintf("\tFound classpath by alias %s", $alias));

                continue;
            }

            $decoratedDefintion = $decoratedDefintions[$alias] ?? null;
            if ($decoratedDefintion) {
                $class = $decoratedDefintion['definition']?->getClass();
                if ($class !== null && class_exists($class)) {
                    $decoratedServicesAssociation[$definitionClass] = $class;
                    $output->writeln(sprintf("\tFound classpath by 'decorated definitions' %s", $class));

                    continue;
                }
            }

            if (str_ends_with($alias, 'Interface')) {
                $class = str_replace('Interface', '', $alias);
                if (class_exists($class)) {
                    $decoratedServicesAssociation[$definitionClass] = $class;
                    $output->writeln(sprintf("\tFound classpath with 'Interface substitution' %s", $class));

                    continue;
                }
            }

            $innerServiceId = $definition->innerServiceId;
            if ($innerServiceId !== null && str_contains($innerServiceId, '.inner')) {
                $originalServiceId = str_replace('.inner', '', $innerServiceId);
                $decoratedDefintion = $decoratedDefintions[$originalServiceId] ?? null;
                if ($decoratedDefintion) {
                    $class = $decoratedDefintion['definition']?->getClass();
                    if ($class !== null && class_exists($class)) {
                        $decoratedServicesAssociation[$definitionClass] = $class;
                        $output->writeln(sprintf("\tFound classpath with '.inner substitution' %s", $class));

                        continue;
                    }
                }
            }

            $output->writeln(sprintf("\tNot found classpath for alias %s", $alias));
        }


        $output->writeln("\n\n### Computing changed services");
        $diff = @file_get_contents('https://github.com/Sylius/Sylius/compare/v1.11.0..v1.12.0.diff');
        file_put_contents('diff.txt', $diff);
        $diffLines = explode(\PHP_EOL, $diff);
        foreach ($diffLines as $diffLine) {
            if (strpos($diffLine, 'diff --git') !== 0) {
                continue;
            }
            $diffLineParts = explode(' ', $diffLine);
            $changedFileName = substr($diffLineParts[2], 2);

            if (!str_starts_with($changedFileName, 'src/')) {
                continue;
            }

            foreach ($decoratedServicesAssociation as $newService => $oldService) {
                $pathFromNamespace = str_replace('\\', \DIRECTORY_SEPARATOR, $oldService);
                if (!str_contains($changedFileName, $pathFromNamespace)) {
                    continue;
                }
                $output->writeln(
                    sprintf(
//                        'Service "%s" must be checked because the service that it decorates "%s" has changed in these versions',
                        "\t%s | %s",
                        $newService,
                        $oldService,
                    ),
                );
            }
        }

        return 0;
    }

    private function compile(ContainerBuilder $rawContainerBuilder, RemoveUnusedDefinitionsPass $removeUnusedDefinitionsPass, DecoratorServicePass $decoratorServiceDefinitionsPass): void
    {
        $compiler = $rawContainerBuilder->getCompiler();

        foreach ($rawContainerBuilder->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof BaseRemoveUnusedDefinitionsPass) {
                $removeUnusedDefinitionsPass->process($rawContainerBuilder);

                continue;
            }
            if ($pass instanceof BaseDecoratorServicePass) {
                $decoratorServiceDefinitionsPass->process($rawContainerBuilder);

                continue;
            }
            $pass->process($rawContainerBuilder);
        }

        $compiler->getServiceReferenceGraph()->clear();
    }
}
