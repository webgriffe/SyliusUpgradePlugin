<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Integration\Command;

use org\bovigo\vfs\vfsStream;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Webgriffe\SyliusUpgradePlugin\Stub\Client\Git;
use Webgriffe\SyliusUpgradePlugin\Command\TemplateChangesCommand;

final class TemplateChangesCommandTest extends KernelTestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../DataFixtures/Command/TemplateChangesCommandTest/';

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        vfsStream::setup();

        $application = new Application(self::$kernel);
        $command = $application->find('webgriffe:upgrade:template-changes');
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @test
     */
    public function it_is_executable_with_mandatory_parameters(): void
    {
        $return = $this->commandTester->execute(
            [
                TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4',
                TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.8.8',
            ],
        );

        self::assertEquals(0, $return);
    }

    /**
     * @test
     */
    public function it_throws_when_from_version_argument_is_not_valid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Argument "from" is not a valid non-empty string');

        $this->commandTester->execute([TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '', TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '']);
    }

    /**
     * @test
     */
    public function it_throws_when_to_version_argument_is_not_valid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Argument "to" is not a valid non-empty string');

        $this->commandTester->execute([TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4', TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '']);
    }

    /**
     * @test
     */
    public function it_outputs_filepaths_of_overridden_template_files_in_templates_dir_that_changed_between_two_given_versions(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->name() . '/git.diff');
        vfsStream::copyFromFileSystem(self::FIXTURE_DIR . $this->name() . '/vfs');

        $return = $this->commandTester->execute(
            [
                TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4',
                TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.8.8',
            ],
        );

        self::assertEquals(0, $return);
        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing differences between 1.8.4 and 1.8.8

Searching "vfs://root/templates/bundles/" for overridden files that changed between the two versions.
Found 2 files that changed and was overridden:
	SyliusShopBundle/Checkout/Address/_form.html.twig [Check file's history]
	SyliusUiBundle/Form/theme.html.twig [Check file's history]

TXT;

        self::assertEquals($expectedOutput, $output);
    }

    /**
     * @test
     */
    public function it_outputs_filepaths_of_overridden_template_files_in_theme_dir_that_changed_between_two_given_versions(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->name() . '/git.diff');
        vfsStream::copyFromFileSystem(self::FIXTURE_DIR . $this->name() . '/vfs');

        $return = $this->commandTester->execute(
            [
                TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4',
                TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.8.8',
                '--' . TemplateChangesCommand::THEME_OPTION_NAME => 'themes/my-theme',
                '--' . TemplateChangesCommand::LEGACY_MODE_OPTION_NAME => true,
            ],
        );

        self::assertEquals(0, $return);
        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing differences between 1.8.4 and 1.8.8

Searching "vfs://root/templates/bundles/" for overridden files that changed between the two versions.
Found 0 files that changed and was overridden.

Searching "vfs://root/themes/my-theme/" for overridden files that changed between the two versions.
Found 3 files that changed and was overridden:
	SyliusShopBundle/Checkout/_header.html.twig [Check file's history]
	SyliusUiBundle/Form/theme.html.twig [Check file's history]
	SyliusAdminBundle/Product/_mainImage.html.twig [Check file's history]

TXT;

        self::assertEquals($expectedOutput, $output);
    }

    /**
     * @test
     */
    public function it_outputs_error_message_when_given_theme_name_directory_does_not_exists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot search "vfs://root/themes/my-theme/" cause it does not exists');

        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->name() . '/git.diff');
        vfsStream::copyFromFileSystem(self::FIXTURE_DIR . $this->name() . '/vfs');

        $this->commandTester->execute(
            [
                TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4',
                TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.8.8',
                '--' . TemplateChangesCommand::THEME_OPTION_NAME => 'themes/my-theme',
                '--' . TemplateChangesCommand::LEGACY_MODE_OPTION_NAME => true,
            ],
        );
    }

    /**
     * @test
     */
    public function it_outputs_proper_messages_when_there_arent_changed_files_in_both_templates_and_theme_dirs(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->name() . '/git.diff');
        vfsStream::copyFromFileSystem(self::FIXTURE_DIR . $this->name() . '/vfs');

        $return = $this->commandTester->execute(
            [
                TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4',
                TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.8.8',
                '--' . TemplateChangesCommand::THEME_OPTION_NAME => 'themes/my-theme',
                '--' . TemplateChangesCommand::LEGACY_MODE_OPTION_NAME => true,
            ],
        );

        self::assertEquals(0, $return);
        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing differences between 1.8.4 and 1.8.8

Searching "vfs://root/templates/bundles/" for overridden files that changed between the two versions.
Found 0 files that changed and was overridden.

Searching "vfs://root/themes/my-theme/" for overridden files that changed between the two versions.
Found 0 files that changed and was overridden.

TXT;

        self::assertEquals($expectedOutput, $output);
    }

    /**
     * @test
     */
    public function it_outputs_filepaths_of_overridden_template_files_in_theme_dir_that_changed_between_two_given_versions_with_new_theme_bundles_path_location(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->name() . '/git.diff');
        vfsStream::copyFromFileSystem(self::FIXTURE_DIR . $this->name() . '/vfs');

        $return = $this->commandTester->execute(
            [
                TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4',
                TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.8.8',
                '--' . TemplateChangesCommand::THEME_OPTION_NAME => 'themes/my-theme',
            ],
        );

        self::assertEquals(0, $return);
        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing differences between 1.8.4 and 1.8.8

Searching "vfs://root/templates/bundles/" for overridden files that changed between the two versions.
Found 0 files that changed and was overridden.

Searching "vfs://root/themes/my-theme/templates/bundles/" for overridden files that changed between the two versions.
Found 2 files that changed and was overridden:
	SyliusShopBundle/Checkout/_header.html.twig [Check file's history]
	SyliusUiBundle/Form/theme.html.twig [Check file's history]

TXT;

        self::assertEquals($expectedOutput, $output);
    }

    /**
     * @test
     */
    public function it_outputs_filepaths_of_overridden_template_files_for_multiple_theme_folders(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->name() . '/git.diff');
        vfsStream::copyFromFileSystem(self::FIXTURE_DIR . $this->name() . '/vfs');

        $return = $this->commandTester->execute(
            [
                TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4',
                TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.8.8',
                '--' . TemplateChangesCommand::THEME_OPTION_NAME => ['themes/my-theme', 'themes/my-other-theme'],
            ],
        );

        self::assertEquals(0, $return);
        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing differences between 1.8.4 and 1.8.8

Searching "vfs://root/templates/bundles/" for overridden files that changed between the two versions.
Found 0 files that changed and was overridden.

Searching "vfs://root/themes/my-theme/templates/bundles/" for overridden files that changed between the two versions.
Found 2 files that changed and was overridden:
	SyliusShopBundle/Checkout/_header.html.twig [Check file's history]
	SyliusUiBundle/Form/theme.html.twig [Check file's history]

Searching "vfs://root/themes/my-other-theme/templates/bundles/" for overridden files that changed between the two versions.
Found 1 files that changed and was overridden:
	SyliusUiBundle/Form/theme.html.twig [Check file's history]

TXT;

        self::assertEquals($expectedOutput, $output);
    }
}
