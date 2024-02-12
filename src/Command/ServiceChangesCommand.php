<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin\Command;

use Symfony\Bundle\FrameworkBundle\Command\BuildDebugContainerTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
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
use Webmozart\Assert\Assert;

final class ServiceChangesCommand extends Command
{
    public const FROM_VERSION_ARGUMENT_NAME = 'from';

    public const TO_VERSION_ARGUMENT_NAME = 'to';

    public const NAMESPACE_PREFIX_OPTION_NAME = 'namespace-prefix';

    public const ALIAS_PREFIX_OPTION_NAME = 'alias-prefix';

    use BuildDebugContainerTrait;

    protected static $defaultName = 'webgriffe:upgrade:service-changes';

    private ?OutputInterface $output = null;

    private ?string $toVersion = null;

    private ?string $fromVersion = null;

    private ?string $namespacePrefix = null;

    private ?string $aliasPrefix = null;

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

        /** @var Application|null $application */
        $application = $this->getApplication();
        /** @var Kernel|null $rawKernel */
        $rawKernel = $application?->getKernel();
        Assert::isInstanceOf($rawKernel, Kernel::class);

        /** @var \Closure $buildContainer */
        $buildContainer = \Closure::bind(function (): ContainerBuilder {
            /** @psalm-suppress UndefinedMethod */
            $this->initializeBundles();
            /**
             * @var ContainerBuilder $containerBuilder
             * @psalm-suppress UndefinedMethod
             */
            $containerBuilder = $this->buildContainer();

            return $containerBuilder;
        }, $rawKernel, \get_class($rawKernel));
        /** @var ContainerBuilder $rawContainerBuilder */
        $rawContainerBuilder = $buildContainer();

        $decoratorServiceDefinitionsPass = new DecoratorServicePass();
        $this->compile($rawContainerBuilder, $decoratorServiceDefinitionsPass);
        $rawKernel->boot();

        /** @var array<string, string> $decoratedServicesAssociation */
        $decoratedServicesAssociation = [];
        $syliusServicesWithAppClass = [];
        $decoratedDefintions = $decoratorServiceDefinitionsPass::$decoratedServices;

        $this->outputVerbose("\n\n### DEBUG: Computing decorated services");

        $rawDefinitions = $rawContainerBuilder->getDefinitions();
        foreach ($rawDefinitions as $alias => $definition) {
            $decoratedDefintion = $decoratedDefintions[$alias] ?? null;
            if ($this->applyDecoratedDefinitionsStrategy($decoratedServicesAssociation, $alias, $decoratedDefintion)) {
                continue;
            }

            // otherwise, the replaced service must be a "Sylius" service
            if (!($this->isSyliusService($alias))) {
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

            $isAppClass = str_starts_with($definitionClass, sprintf('%s\\', $this->getNamespacePrefix()));
            if (!$isAppClass) {
                $this->applyDecoratedDefinitionsNonAppClassStrategy($decoratedServicesAssociation, $decoratedDefintions, $alias, $definitionClass);

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

        if (!($this->computeServicesThatChanged($decoratedServicesAssociation))) {
            $this->writeLine('No changes detected');
        }

        if (count($syliusServicesWithAppClass) > 0) {
            $this->writeLine('');
            $this->writeLine(
                sprintf(
                    'Found %s services that must be checked manually because the related alias referes to a Sylius' .
                    ' service. Actually it\'s impossible to detect if the original class changed between versions.' .
                    ' Here is the list ([decorated service] -> [decorating service]):',
                    count($syliusServicesWithAppClass)
                )
            );
            foreach ($syliusServicesWithAppClass as $alias => $class) {
                $this->writeLine(sprintf('"%s" -> "%s"', $alias, $class));
            }
        }

        return 0;
    }

    /**
     * @param array<string, string> $decoratedServicesAssociation
     */
    private function computeServicesThatChanged(array $decoratedServicesAssociation): bool
    {
        $this->writeLine(
            sprintf('Computing modified services between %s and %s', $this->getFromVersion(), $this->getToVersion())
        );
        $this->writeLine('');

        $decoratedServices = [];
        $filesChanged = $this->getFilesChangedBetweenTwoVersions();
        foreach ($filesChanged as $fileChanged) {
            foreach ($decoratedServicesAssociation as $newService => $oldService) {
                $pathFromNamespace = str_replace('\\', \DIRECTORY_SEPARATOR, $oldService);
                if (!str_contains($fileChanged, $pathFromNamespace)) {
                    continue;
                }
                $decoratedServices[$newService] = $oldService;
            }
        }
        if (count($decoratedServices) === 0) {
            $this->writeLine('Found 0 services that changed and was decorated.');

            return false;
        }

        $this->writeLine(sprintf(
            'Found %s services that changed and were decorated ([decorated service] -> [decorating service]):',
            count($decoratedServices)
        ));
        foreach ($decoratedServices as $newService => $oldService) {
            $this->writeLine(sprintf('"%s" -> "%s"', $oldService, $newService));
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function getFilesChangedBetweenTwoVersions(): array
    {
        $diff = $this->gitClient->getDiffBetweenTags($this->getFromVersion(), $this->getToVersion());
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
        if ($this->output === null) {
            return;
        }
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->writeLine($message);
        }
    }

    private function writeLine(string $message): void
    {
        if ($this->output === null) {
            return;
        }
        $this->output->writeln($message);
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

    private function isSyliusService(string $decoratedServiceId): bool
    {
        return str_starts_with($decoratedServiceId, 'sylius.') ||
            str_starts_with($decoratedServiceId, 'Sylius\\') ||
            str_starts_with($decoratedServiceId, '\\Sylius\\');
    }

    /**
     * If the service is an "App" service, seek for the original Sylius service in the decorated definitions
     *
     * @param array<string, string> $decoratedServicesAssociation
     */
    private function applyDecoratedDefinitionsStrategy(
        array &$decoratedServicesAssociation,
        string $alias,
        ?array $decoratedDef = null
    ): bool {
        if ($decoratedDef !== null &&
            (str_starts_with($alias, sprintf('%s\\', $this->getNamespacePrefix())) ||
                str_starts_with($alias, sprintf('%s.', $this->getAliasPrefix())))
        ) {
            /** @var string $decoratedServiceId */
            $decoratedServiceId = $decoratedDef['id'];
            if ($this->isSyliusService($decoratedServiceId)) {
                /** @var Definition|null $definition */
                $definition = $decoratedDef['definition'] ?? null;
                $class = $definition?->getClass();
                if ($class !== null && class_exists($class)) {
                    $decoratedServicesAssociation[$alias] = $class;
                    $this->outputVerbose(sprintf('Sylius service "%s" has been replaced with "%s"', $decoratedServiceId, $alias));
                    $this->outputVerbose(sprintf("\tFound classpath by 'decorated definitions' strategy: %s", $class));

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * It could happen that the definition class of the decorating service is an "App" class,
     * but it still was defined with original service class and alias..
     *
     * @param array<string, string> $decoratedServicesAssociation
     */
    private function applyDecoratedDefinitionsNonAppClassStrategy(
        array &$decoratedServicesAssociation,
        array $decoratedDefintions,
        string $alias,
        string $definitionClass,
    ): bool {
        // todo: i cannot find a way to test these cases
        /** @var Definition|null $decoratedDef */
        $decoratedDef = $decoratedDefintions[$definitionClass]['definition'] ?? null;
        if ($decoratedDef === null) {
            return false;
        }

        $decoratedDefClass = $decoratedDef->getClass();
        if ($decoratedDefClass === null) {
            return false;
        }

        if (!str_starts_with($decoratedDefClass, sprintf('%s\\', $this->getNamespacePrefix())) ||
            !class_exists($decoratedDefClass)) {
            return false;
        }

        $decoratedServicesAssociation[$definitionClass] = $decoratedDefClass;
        $this->outputVerbose(sprintf('Sylius service "%s" has been replaced with "%s"', $alias, $definitionClass));
        $this->outputVerbose(sprintf("\tFound classpath by 'decorated definitions' strategy: %s", $decoratedDefClass));

        return true;
    }

    /**
     * @param array<string, string> $decoratedServicesAssociation
     */
    private function applyAliasStrategy(array &$decoratedServicesAssociation, string $alias, string $definitionClass,): bool
    {
        if (!class_exists($alias)) {
            return false;
        }

        $decoratedServicesAssociation[$definitionClass] = $alias;
        $this->outputVerbose(sprintf('Sylius service "%s" has been replaced with "%s"', $alias, $definitionClass));
        $this->outputVerbose(sprintf("\tFound classpath by 'alias' strategy: %s", $alias));

        return true;
    }

    /**
     * @param array<string, string> $decoratedServicesAssociation
     * @param array[] $decoratedDefintions
     */
    private function applyInnerStrategy(
        array &$decoratedServicesAssociation,
        Definition $definition,
        array $decoratedDefintions,
        string $definitionClass
    ): bool {
        /**
         * @var string|null $innerServiceId
         * @psalm-suppress InternalProperty
         */
        $innerServiceId = $definition->innerServiceId;
        if ($innerServiceId !== null && str_contains($innerServiceId, '.inner')) {
            $originalServiceId = str_replace('.inner', '', $innerServiceId);
            $decoratedDefintion = $decoratedDefintions[$originalServiceId] ?? null;
            if ($decoratedDefintion !== null) {
                /** @var Definition|null $definition2 */
                $definition2 = $decoratedDefintion['definition'];
                $class = $definition2?->getClass();
                if ($class !== null && class_exists($class)) {
                    $decoratedServicesAssociation[$definitionClass] = $class;
                    $this->outputVerbose(sprintf('Sylius service "%s" has been replaced with "%s"', $class, $definitionClass));
                    $this->outputVerbose(sprintf("\tFound classpath by '.inner substitution' strategy: %s", $class));

                    return true;
                }
            }
        }

        return false;
    }

    private function getFromVersion(): string
    {
        $value = $this->fromVersion;
        Assert::notNull($value);

        return $value;
    }

    private function getToVersion(): string
    {
        $value = $this->toVersion;
        Assert::notNull($value);

        return $value;
    }

    private function getNamespacePrefix(): string
    {
        $value = $this->namespacePrefix;
        Assert::notNull($value);

        return $value;
    }

    private function getAliasPrefix(): string
    {
        $value = $this->aliasPrefix;
        Assert::notNull($value);

        return $value;
    }
}
