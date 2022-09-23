<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin\Command;

use const DIRECTORY_SEPARATOR;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webgriffe\SyliusUpgradePlugin\Client\GitInterface;
use Webmozart\Glob\Glob;

final class TemplateChangesCommand extends Command
{
    public const FROM_VERSION_ARGUMENT_NAME = 'from';

    public const TO_VERSION_ARGUMENT_NAME = 'to';

    public const THEME_OPTION_NAME = 'theme';

    public const LEGACY_MODE_OPTION_NAME = 'legacy';

    private const TEMPLATES_BUNDLES_SUBDIR = 'templates' . DIRECTORY_SEPARATOR . 'bundles' . DIRECTORY_SEPARATOR;

    protected static $defaultName = 'webgriffe:upgrade:template-changes';

    /** @var string */
    private $rootPath;

    /** @var OutputInterface|null */
    private $output;

    /** @var GitInterface */
    private $gitClient;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private string $toVersion;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private string $fromVersion;

    public function __construct(GitInterface $gitClient, string $rootPath, string $name = null)
    {
        parent::__construct($name);

        $this->gitClient = $gitClient;
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(
                'Print a list of template files (with extension .html.twig) that changed between two given Sylius versions ' .
                'and that has been overridden in the project (in "templates" dir or in a theme).',
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
                self::THEME_OPTION_NAME,
                't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Name of the theme for which check the templates that changed.',
            )
            ->addOption(
                self::LEGACY_MODE_OPTION_NAME,
                'l',
                InputOption::VALUE_NONE,
                'Use legacy mode for theme bundle paths: from version 2.0 of the SyliusThemeBundle the theme structure has changed. More info here: https://github.com/Sylius/SyliusThemeBundle/blob/master/UPGRADE.md#upgrade-from-1x-to-20',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

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

        $versionChangedFiles = $this->getFilesChangedBetweenTwoVersions();
        $this->computeTemplateFilesChangedAndOverridden($versionChangedFiles);

        /** @var mixed $themesNames */
        $themesNames = $input->getOption(self::THEME_OPTION_NAME);
        if (is_string($themesNames)) {
            $themesNames = [$themesNames];
        }
        if (is_array($themesNames)) {
            foreach ($themesNames as $themeName) {
                if (!is_string($themeName) || $themeName === '') {
                    continue;
                }
                $legacyMode = (bool) $input->getOption(self::LEGACY_MODE_OPTION_NAME);
                $this->computeThemeTemplateFilesChangedAndOverridden($versionChangedFiles, $themeName, $legacyMode);
            }
        }

        return 0;
    }

    /**
     * @return string[]
     */
    private function getFilesChangedBetweenTwoVersions(): array
    {
        $this->writeLine(sprintf('Computing differences between %s and %s', $this->fromVersion, $this->toVersion));
        $diff = $this->gitClient->getDiffBetweenTags($this->fromVersion, $this->toVersion);
        $versionChangedFiles = [];
        $diffLines = explode(\PHP_EOL, $diff);
        foreach ($diffLines as $diffLine) {
            if (strpos($diffLine, 'diff --git') !== 0) {
                continue;
            }
            $diffLineParts = explode(' ', $diffLine);
            $changedFileName = substr($diffLineParts[2], 2);
            if (strpos($changedFileName, 'Resources' . DIRECTORY_SEPARATOR . 'views') === false) {
                continue;
            }
            $versionChangedFiles[] = $changedFileName;
        }

        // src/Sylius/Bundle/AdminBundle/Resources/views/PaymentMethod/_form.html.twig -> SyliusAdminBundle/PaymentMethod/_form.html.twig
        return array_map(
            static function (string $versionChangedFile): string {
                return str_replace(
                    [
                        'src' . DIRECTORY_SEPARATOR . 'Sylius' . DIRECTORY_SEPARATOR . 'Bundle' . DIRECTORY_SEPARATOR,
                        DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'views',
                    ],
                    [
                        'Sylius',
                        '',
                    ],
                    $versionChangedFile,
                );
            },
            $versionChangedFiles,
        );
    }

    private function computeTemplateFilesChangedAndOverridden(array $versionChangedFiles): void
    {
        $targetDir = $this->rootPath . self::TEMPLATES_BUNDLES_SUBDIR;
        $this->writeLine('');
        $this->writeLine(sprintf('Searching "%s" for overridden files that changed between the two versions.', $targetDir));
        $templateFilenames = $this->getProjectTemplatesFiles($targetDir);
        /** @var string[] $overriddenTemplateFiles */
        $overriddenTemplateFiles = array_intersect($versionChangedFiles, $templateFilenames);
        if (count($overriddenTemplateFiles) === 0) {
            $this->writeLine('Found 0 files that changed and was overridden.');

            return;
        }

        $this->writeLine(sprintf('Found %s files that changed and was overridden:', count($overriddenTemplateFiles)));
        foreach ($overriddenTemplateFiles as $file) {
            $checkFilesHistoryUrl = $this->getCheckFilesHistoryUrlFromBundleFilePath($file);
            $this->writeLine("\t" . sprintf(
                '%s [<href=%s>Check file\'s history</>]',
                $file,
                $checkFilesHistoryUrl,
            ));
        }
    }

    /**
     * @return string[]
     */
    private function getProjectTemplatesFiles(string $targetDir): array
    {
        $files = Glob::glob($targetDir . 'Sylius*Bundle' . DIRECTORY_SEPARATOR . '**' . DIRECTORY_SEPARATOR . '*.html.twig');

        // from /path_to_project/templates/bundles/SyliusAdminBundle/PaymentMethod/_form.html.twig
        // to SyliusAdminBundle/PaymentMethod/_form.html.twig
        return array_map(
            static function (string $file) use ($targetDir): string {
                return str_replace($targetDir, '', $file);
            },
            $files,
        );
    }

    private function computeThemeTemplateFilesChangedAndOverridden(array $versionChangedFiles, string $themeName, bool $legacyMode): void
    {
        $targetDir = $this->rootPath . rtrim($themeName, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!$legacyMode) {
            $targetDir .= self::TEMPLATES_BUNDLES_SUBDIR;
        }

        $this->writeLine('');
        if (!is_dir($targetDir)) {
            throw new \RuntimeException(sprintf('Cannot search "%s" cause it does not exists.', $targetDir));
        }

        $this->writeLine(sprintf('Searching "%s" for overridden files that changed between the two versions.', $targetDir));
        $templateFilenames = $legacyMode ? $this->getProjectLegacyThemeFiles($targetDir) : $this->getProjectTemplatesFiles($targetDir);

        /** @var string[] $overriddenTemplateFiles */
        $overriddenTemplateFiles = array_intersect($versionChangedFiles, $templateFilenames);
        if (count($overriddenTemplateFiles) === 0) {
            $this->writeLine('Found 0 files that changed and was overridden.');

            return;
        }

        $this->writeLine(sprintf('Found %s files that changed and was overridden:', count($overriddenTemplateFiles)));
        foreach ($overriddenTemplateFiles as $file) {
            $checkFilesHistoryUrl = $this->getCheckFilesHistoryUrlFromBundleFilePath($file);
            $this->writeLine("\t" . sprintf(
                '%s [<href=%s>Check file\'s history</>]',
                $file,
                $checkFilesHistoryUrl,
            ));
        }
    }

    /**
     * @return string[]
     */
    private function getProjectLegacyThemeFiles(string $targetDir): array
    {
        $files = Glob::glob($targetDir . 'Sylius*Bundle' . DIRECTORY_SEPARATOR . '**' . DIRECTORY_SEPARATOR . '*.html.twig');

        // from /path_to_project/themes/my-theme/SyliusAdminBundle/views/PaymentMethod/_form.html.twig
        // to SyliusAdminBundle/PaymentMethod/_form.html.twig
        return array_map(
            static function (string $file) use ($targetDir): string {
                return str_replace([$targetDir, DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR], ['', DIRECTORY_SEPARATOR], $file);
            },
            $files,
        );
    }

    private function writeLine(string $message): void
    {
        if ($this->output === null) {
            return;
        }
        $this->output->writeln($message);
    }

    private function getFilePathWithBundle(string $file): string
    {
        if (false === $pos = strpos($file, '/')) {
            return $file;
        }
        $bundle = substr($file, 0, $pos);
        $file = substr($file, $pos + 1);
        $bundle = str_replace('Sylius', '', $bundle);

        return $bundle . '/Resources/views/' . $file;
    }

    private function getCheckFilesHistoryUrlFromBundleFilePath(string $file): string
    {
        return 'https://github.com/Sylius/Sylius/commits/' . $this->toVersion . '/src/Sylius/Bundle/' . $this->getFilePathWithBundle($file);
    }
}
