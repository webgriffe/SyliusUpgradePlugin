<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Integration\Command;

use org\bovigo\vfs\vfsStream;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Webgriffe\SyliusUpgradePlugin\Command\TemplateChangesCommand;

final class TemplateChangesCommandTest extends KernelTestCase
{

    /** @var CommandTester */
    private $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        vfsStream::setup();

        $application = new Application(static::$kernel);
        $command = $application->find('webgriffe:upgrade:template-changes');
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @test
     */
    public function it_is_executable(): void
    {
        $return = $this->commandTester->execute([]);
        self::assertEquals(0, $return);
    }

    /**
     * @test
     */
    public function it_outputs_filepaths_of_overridden_template_files_that_changed_between_two_given_versions(): void
    {
        vfsStream::copyFromFileSystem(__DIR__ . '/../DataFixtures/Command/TemplateChangesCommandTest/it_outputs_filepaths_of_overridden_template_files_that_changed_between_two_given_versions/');

        $return = $this->commandTester->execute([
            TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4',
            TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.8.8',
        ]);

        self::assertEquals(0, $return);
        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing differences between 1.8.4 and 1.8.8: https://github.com/Sylius/Sylius/compare/v1.8.4...v1.8.8.diff

Searching "vfs://root/templates/bundles/" for overridden files that changed between the two versions.
Found 2 files that changed and was overridden:
	SyliusShopBundle/Checkout/Address/_form.html.twig
	SyliusUiBundle/Form/theme.html.twig

TXT;

        self::assertEquals($expectedOutput, $output);
    }
}
