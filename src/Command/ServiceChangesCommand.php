<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin\Command;

use Symfony\Bundle\FrameworkBundle\Command\BuildDebugContainerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass as BaseDecoratorServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tests\Webgriffe\SyliusUpgradePlugin\Application\Kernel;
use Webgriffe\SyliusUpgradePlugin\Client\GitInterface;
use Webgriffe\SyliusUpgradePlugin\DependencyInjection\Compiler\DecoratorServicePass;

final class ServiceChangesCommand extends Command
{
    public const FROM_VERSION_ARGUMENT_NAME = 'from';

    public const TO_VERSION_ARGUMENT_NAME = 'to';

    public const NAMESPACE_PREFIX_OPTION_NAME = 'namespace-prefix';

    public const ALIAS_PREFIX_OPTION_NAME = 'alias-prefix';

    use BuildDebugContainerTrait;

    protected static $defaultName = 'webgriffe:upgrade:service-changes';

    private OutputInterface $output;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private string $toVersion;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private string $fromVersion;

    private string $namespacePrefix;

    private string $aliasPrefix;

    public function __construct(private GitInterface $gitClient, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription(
                'Print the list of your services that decorates or replaces a service that changed between two given Sylius versions.',
            )
            ->addArgument(
                self::FROM_VERSION_ARGUMENT_NAME,
                InputArgument::REQUIRED,
                'Starting Sylius version to use for changes computation.',
            )
            ->addArgument(
                self::TO_VERSION_ARGUMENT_NAME,
                InputArgument::REQUIRED,
                'Target Sylius version to use for changes computation.',
            )
            ->addOption(
                self::NAMESPACE_PREFIX_OPTION_NAME,
                'np',
                InputArgument::OPTIONAL,
                'The first part of the namespace of your app services, like "App" in "App\Calculator\PriceCalculator". Default: "App".',
                'App',
            )
            ->addOption(
                self::ALIAS_PREFIX_OPTION_NAME,
                'ap',
                InputArgument::OPTIONAL,
                'The first part of the alias of your app services, like "app" in "app.calculator.price". Default: "app".',
                'App',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->loadInputs($input);

        /** @var Kernel $rawKernel */
        $rawKernel = $this->getApplication()->getKernel();
        $buildContainer = \Closure::bind(function () {
            $this->initializeBundles();

            return $this->buildContainer();
        }, $rawKernel, \get_class($rawKernel));
        /** @var ContainerBuilder $rawContainerBuilder */
        $rawContainerBuilder = $buildContainer();

        $decoratorServiceDefinitionsPass = new DecoratorServicePass();
        $this->compile($rawContainerBuilder, $decoratorServiceDefinitionsPass);
        $rawKernel->boot();

        $decoratedServicesAssociation = [];
        $syliusServicesWithAppClass = [];
        $decoratedDefintions = $decoratorServiceDefinitionsPass::$decoratedServices;

        $this->outputVerbose("\n\n### DEBUG: Computing decorated services");

        /** @var array<string, Definition> $rawDefinitions */
        $rawDefinitions = $rawContainerBuilder->getDefinitions();
        foreach ($rawDefinitions as $alias => $definition) {
            $decoratedDefintion = $decoratedDefintions[$alias] ?? null;
            if ($this->applyDecoratedDefinitionsStrategy($decoratedServicesAssociation, $alias, $decoratedDefintion)) {
                continue;
            }

            // otherwise, the replaced service must be a "Sylius" service
            if (!(str_starts_with($alias, 'sylius.') ||
                str_starts_with($alias, 'Sylius\\') ||
                str_starts_with($alias, '\\Sylius\\'))
            ) {
                continue;
            }

            $aliasNormalized = strtolower($alias);
            // ignore repositories and controllers 'cause they are magically registered
            if (str_contains($aliasNormalized, 'repository') || str_contains($aliasNormalized, 'controller')) {
                // todo: do not know if it's possible to handle this case
                continue;
            }

            $definitionClass = $definition->getClass();
            if (!is_string($definitionClass)) {
                continue;
            }

            $isAppClass = str_starts_with($definitionClass, sprintf('%s\\', $this->namespacePrefix));
            if (!$isAppClass) {
                $this->applyDecoratedDefinitionsNonAppClassStrategy($decoratedServicesAssociation, $alias, $definitionClass);

                continue;
            }

            if ($this->applyAliasStrategy($decoratedServicesAssociation, $alias, $definitionClass)) {
                continue;
            }

            if ($this->applyInnerStrategy($decoratedServicesAssociation, $definition, $decoratedDefintions, $definitionClass)) {
                continue;
            }

            if (class_exists($definitionClass)) {
                $syliusServicesWithAppClass[$alias] = $definitionClass;
            }
        }


        $this->outputVerbose("\n\n### Computing changed services");
        $this->output->writeln(sprintf('Computing modified services between %s and %s', $this->fromVersion, $this->toVersion));
        $filesChanged = $this->getFilesChangedBetweenTwoVersions();
        $atLeastOneChanged = false;
        foreach ($filesChanged as $fileChanged) {
            foreach ($decoratedServicesAssociation as $newService => $oldService) {
                $pathFromNamespace = str_replace('\\', \DIRECTORY_SEPARATOR, $oldService);
                if (!str_contains($fileChanged, $pathFromNamespace)) {
                    continue;
                }
                $atLeastOneChanged = true;
                $output->writeln(
                    sprintf(
                        'Service "%s" must be checked because the service that it decorates "%s" has changed between given versions',
                        $newService,
                        $oldService,
                    ),
                );
            }
        }

        if (!$atLeastOneChanged) {
            $this->output->writeln('No changes detected');
        }

        foreach ($syliusServicesWithAppClass as $alias => $class) {
            $output->writeln(
                sprintf(
                    'Service with class "%s" must be checked manually because the related alias "%s" referes to a' .
                    ' Sylius service. Actually it\'s impossible to detects if the original class chnaged between versions.',
                    $class,
                    $alias,
                ),
            );
        }

        return 0;
    }

    /**
     * @return string[]
     */
    private function getFilesChangedBetweenTwoVersions(): array
    {
        $diff = $this->gitClient->getDiffBetweenTags($this->fromVersion, $this->toVersion);
        $versionChangedFiles = [];
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
            $versionChangedFiles[] = $changedFileName;
        }

        return $versionChangedFiles;
    }

    private function compile(
        ContainerBuilder $rawContainerBuilder,
        DecoratorServicePass $decoratorServiceDefinitionsPass
    ): void {
        $compiler = $rawContainerBuilder->getCompiler();

        foreach ($rawContainerBuilder->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof BaseDecoratorServicePass) {
                $decoratorServiceDefinitionsPass->process($rawContainerBuilder);

                continue;
            }
            $pass->process($rawContainerBuilder);
        }

        $compiler->getServiceReferenceGraph()->clear();
    }

    private function outputVerbose(string $message): void
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->output->writeln($message);
        }
    }

    private function loadInputs(InputInterface $input): void
    {
        $fromVersion = $input->getArgument(self::FROM_VERSION_ARGUMENT_NAME);
        if (!is_string($fromVersion) || trim($fromVersion) === '') {
            throw new \RuntimeException(sprintf('Argument "%s" is not a valid non-empty string', self::FROM_VERSION_ARGUMENT_NAME));
        }
        $this->fromVersion = $fromVersion;

        $toVersion = $input->getArgument(self::TO_VERSION_ARGUMENT_NAME);
        if (!is_string($toVersion) || trim($toVersion) === '') {
            throw new \RuntimeException(sprintf('Argument "%s" is not a valid non-empty string', self::TO_VERSION_ARGUMENT_NAME));
        }
        $this->toVersion = $toVersion;

        $namespacePrefix = $input->getOption(self::NAMESPACE_PREFIX_OPTION_NAME);
        if (!is_string($namespacePrefix) || trim($namespacePrefix) === '') {
            throw new \RuntimeException(sprintf('Option "%s" is not a valid non-empty string', self::NAMESPACE_PREFIX_OPTION_NAME));
        }
        $this->namespacePrefix = $namespacePrefix;

        $aliasPrefix = $input->getOption(self::ALIAS_PREFIX_OPTION_NAME);
        if (!is_string($aliasPrefix) || trim($aliasPrefix) === '') {
            throw new \RuntimeException(sprintf('Option "%s" is not a valid non-empty string', self::NAMESPACE_PREFIX_OPTION_NAME));
        }
        $this->aliasPrefix = $aliasPrefix;
    }

    /**
     * If the service is an "App" service, seek for the original Sylius service in the decorated definitions
     */
    private function applyDecoratedDefinitionsStrategy(
        array &$decoratedServicesAssociation,
        string $alias,
        ?array $decoratedDef = null
    ): bool {
        if ($decoratedDef &&
            (str_starts_with($alias, sprintf('%s\\', $this->namespacePrefix)) ||
                str_starts_with($alias, sprintf('%s.', $this->aliasPrefix)))
        ) {
            $decoratedServiceId = $decoratedDef['id'];
            if (str_starts_with($decoratedServiceId, 'sylius.') ||
                str_starts_with($decoratedServiceId, 'Sylius\\') ||
                str_starts_with($decoratedServiceId, '\\Sylius\\')) {
                $class = $decoratedDef['definition']?->getClass();
                if ($class !== null && class_exists($class)) {
                    $decoratedServicesAssociation[$alias] = $class;
                    if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $this->outputVerbose(sprintf('Sylius service "%s" has been replaced with "%s"', $decoratedServiceId, $alias));
                        $this->outputVerbose(sprintf("\tFound classpath by 'decorated definitions' strategy: %s", $class));
                    }

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * It could happen that the definition class of the decorating service is an "App" class,
     * but it still was defined with original service class and alias..
     */
    private function applyDecoratedDefinitionsNonAppClassStrategy(array &$decoratedServicesAssociation, string $alias, string $definitionClass,): bool
    {
        // todo: i cannot find a way to test these cases
        $decoratedDef = $decoratedDefintions[$definitionClass]['definition'] ?? null;
        if (!$decoratedDef) {
            return true;
        }

        $decoratedDefClass = $decoratedDef->getClass();
        if (!str_starts_with($decoratedDefClass, sprintf('%s\\', $this->namespacePrefix)) || !class_exists($decoratedDefClass)) {
            return true;
        }

        $decoratedServicesAssociation[$definitionClass] = $decoratedDefClass;
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->outputVerbose(sprintf('Sylius service "%s" has been replaced with "%s"', $alias, $definitionClass));
            $this->outputVerbose(sprintf("\tFound classpath by 'decorated definitions' strategy: %s", $decoratedDefClass));
        }

        return true;
    }

    private function applyAliasStrategy(array &$decoratedServicesAssociation, string $alias, string $definitionClass,): bool
    {
        if (!class_exists($alias)) {
            return false;
        }

        $decoratedServicesAssociation[$definitionClass] = $alias;
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->outputVerbose(sprintf('Sylius service "%s" has been replaced with "%s"', $alias, $definitionClass));
            $this->outputVerbose(sprintf("\tFound classpath by 'alias' strategy: %s", $alias));
        }

        return true;
    }

    private function applyInnerStrategy(array &$decoratedServicesAssociation, Definition $definition, array $decoratedDefintions, string $definitionClass): bool
    {
        $innerServiceId = $definition->innerServiceId;
        if ($innerServiceId !== null && str_contains($innerServiceId, '.inner')) {
            $originalServiceId = str_replace('.inner', '', $innerServiceId);
            $decoratedDefintion = $decoratedDefintions[$originalServiceId] ?? null;
            if ($decoratedDefintion) {
                $class = $decoratedDefintion['definition']?->getClass();
                if ($class !== null && class_exists($class)) {
                    $decoratedServicesAssociation[$definitionClass] = $class;
                    if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $this->outputVerbose(sprintf('Sylius service "%s" has been replaced with "%s"', $class, $definitionClass));
                        $this->outputVerbose(sprintf("\tFound classpath by '.inner substitution' strategy: %s", $class));
                    }

                    return true;
                }
            }
        }

        return false;
    }
}
