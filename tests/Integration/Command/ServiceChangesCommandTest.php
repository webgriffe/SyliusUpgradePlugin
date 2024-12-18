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

    public function test_it_detects_with_inner_substitution_strategy_those_decorated_services_that_changed(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->name() . '/git.diff');

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

Found 2 services that changed and were decorated ([decorated service] -> [decorating service]):
"Sylius\Bundle\AdminBundle\EmailManager\OrderEmailManager" -> "Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\\test_it_detects_with_inner_substitution_strategy_those_decorated_services_that_changed\DecorateOrderEmailManagerInterface"
"Sylius\Component\Core\Cart\Context\ShopBasedCartContext" -> "Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\\test_it_detects_with_inner_substitution_strategy_those_decorated_services_that_changed\DecorateNewShopBased"

Found 1 services that must be checked manually because the related alias referes to a Sylius service. Actually it's impossible to detect if the original class changed between versions. Here is the list ([decorated service] -> [decorating service]):
"sylius.calculator.product_variant_price" -> "Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\DecorateProductVariantPriceCalculator"

TXT;

        self::assertEquals($expectedOutput, $output);
    }

    public function test_it_detects_with_decorated_definition_strategy_those_decorated_services_that_changed(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->name() . '/git.diff');

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

Found 2 services that changed and were decorated ([decorated service] -> [decorating service]):
"Sylius\Component\Addressing\Provider\ProvinceNamingProvider" -> "webgriffe_sylius_upgrade.service_changes_command.test_it_detects_with_decorated_definition_strategy_those_decorated_services_that_changed.decorate_province_naming_provider"
"Sylius\Component\Core\OrderProcessing\OrderPaymentProcessor" -> "webgriffe_sylius_upgrade.service_changes_command.test_it_detects_with_decorated_definition_strategy_those_decorated_services_that_changed.decorate_order_payment_processor"

Found 1 services that must be checked manually because the related alias referes to a Sylius service. Actually it's impossible to detect if the original class changed between versions. Here is the list ([decorated service] -> [decorating service]):
"sylius.calculator.product_variant_price" -> "Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\DecorateProductVariantPriceCalculator"

TXT;

        self::assertEquals($expectedOutput, $output);
    }

    public function test_it_detects_with_alias_strategy_those_decorated_services_that_changed(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->name() . '/git.diff');

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

Found 1 services that changed and were decorated ([decorated service] -> [decorating service]):
"Sylius\Bundle\ApiBundle\CommandHandler\Checkout\SendOrderConfirmationHandler" -> "Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\\test_it_detects_with_alias_strategy_those_decorated_services_that_changed\DecorateSendOrderConfirmationHandler"

Found 1 services that must be checked manually because the related alias referes to a Sylius service. Actually it's impossible to detect if the original class changed between versions. Here is the list ([decorated service] -> [decorating service]):
"sylius.calculator.product_variant_price" -> "Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\DecorateProductVariantPriceCalculator"

TXT;

        self::assertEquals($expectedOutput, $output);
    }

    public function test_it_ignores_those_decorated_services_that_changed_but_the_decoration_services_are_not_within_the_given_namespace(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->name() . '/git.diff');

        $result = $this->commandTester->execute(
            [
                ServiceChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.11.0',
                ServiceChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.12.0',
                '--' . ServiceChangesCommand::NAMESPACE_PREFIX_OPTION_NAME => 'OtherVendorTests',
                '--' . ServiceChangesCommand::ALIAS_PREFIX_OPTION_NAME => 'other_vendor',
            ],
        );

        self::assertEquals(0, $result);

        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing modified services between 1.11.0 and 1.12.0

Found 0 services that changed and was decorated.
No changes detected

TXT;

        self::assertEquals($expectedOutput, $output);
    }
}
