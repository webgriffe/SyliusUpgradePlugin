<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class TemplateChangesCommandTest extends KernelTestCase
{
    /** @var CommandTester */
    private $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

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
}
