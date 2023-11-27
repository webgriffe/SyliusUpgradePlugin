<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Webgriffe\SyliusUpgradePlugin\Stub\Client\Git;
use Webgriffe\SyliusUpgradePlugin\Command\ServiceChangesCommand;

final class ServiceChangesCommandTest extends KernelTestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../DataFixtures/Command/ServiceChangesCommandTest/';

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        // todo: we could pass a custom "env" value, something like "test_services_changes"
        self::bootKernel();

        $application = new Application(self::$kernel);
        $this->commandTester = new CommandTester($application->find('webgriffe:upgrade:service-changes'));
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } catch (\Throwable) { // the kernel was already shut down, so we catch the error here to avoid a red test

        }
    }

    public function test_it_detects_directly_decorated_services_that_changed(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->getName() . '/git.diff');

        $result = $this->commandTester->execute(
            [
                ServiceChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.11.0',
                ServiceChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.12.0',
                '--' . ServiceChangesCommand::NAMESPACE_PREFIX_OPTION_NAME => 'Tests',
                '--' . ServiceChangesCommand::ALIAS_PREFIX_OPTION_NAME => 'webgriffe_sylius_upgrade',
            ],
        );

        self::assertEquals(0, $result);

        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing modified services between 1.11.0 and 1.12.0
Service "Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\\test_it_detects_directly_decorated_services_that_changed\DecorateOrderEmailManagerInterface" must be checked because the service that it decorates "Sylius\Bundle\AdminBundle\EmailManager\OrderEmailManager" has changed between given versions
Service "Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\\test_it_detects_directly_decorated_services_that_changed\DecorateNewShopBased" must be checked because the service that it decorates "Sylius\Component\Core\Cart\Context\ShopBasedCartContext" has changed between given versions

TXT;

        self::assertEquals($expectedOutput, $output);
    }

    public function test_it_detects_with_decorated_definition_strategy_those_directly_decorated_services_that_changed(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->getName() . '/git.diff');

        $result = $this->commandTester->execute(
            [
                ServiceChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.11.0',
                ServiceChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.12.0',
                '--' . ServiceChangesCommand::NAMESPACE_PREFIX_OPTION_NAME => 'Tests',
                '--' . ServiceChangesCommand::ALIAS_PREFIX_OPTION_NAME => 'webgriffe_sylius_upgrade',
            ],
        );

        self::assertEquals(0, $result);

        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing modified services between 1.11.0 and 1.12.0
Service "webgriffe_sylius_upgrade.service_changes_command.test_it_detects_with_decorated_definition_strategy_those_directly_decorated_services_that_changed.decorate_province_naming_provider" must be checked because the service that it decorates "Sylius\Component\Addressing\Provider\ProvinceNamingProvider" has changed between given versions
Service "webgriffe_sylius_upgrade.service_changes_command.test_it_detects_with_decorated_definition_strategy_those_directly_decorated_services_that_changed.decorate_order_payment_processor" must be checked because the service that it decorates "Sylius\Component\Core\OrderProcessing\OrderPaymentProcessor" has changed between given versions

TXT;

        self::assertEquals($expectedOutput, $output);
    }

    public function test_it_detects_with_alias_strategy_those_directly_decorated_services_that_changed(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->getName() . '/git.diff');

        $result = $this->commandTester->execute(
            [
                ServiceChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.11.0',
                ServiceChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.12.0',
                '--' . ServiceChangesCommand::NAMESPACE_PREFIX_OPTION_NAME => 'Tests',
                '--' . ServiceChangesCommand::ALIAS_PREFIX_OPTION_NAME => 'webgriffe_sylius_upgrade',
            ],
        );

        self::assertEquals(0, $result);

        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing modified services between 1.11.0 and 1.12.0
Service "Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\\test_it_detects_with_alias_strategy_those_directly_decorated_services_that_changed\DecorateSendOrderConfirmationHandler" must be checked because the service that it decorates "Sylius\Bundle\ApiBundle\CommandHandler\Checkout\SendOrderConfirmationHandler" has changed between given versions

TXT;

        self::assertEquals($expectedOutput, $output);
    }
}
