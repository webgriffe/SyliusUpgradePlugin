<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\test_it_detects_with_decorated_definition_strategy_those_decorated_services_that_changed;

use Sylius\Component\Core\Customer\OrderAddressesSaverInterface;

final class DecorateCustomerOrderAddressesSaver
{
    public function __construct(private OrderAddressesSaverInterface $decoratedCustomerOrderAddressesSaver)
    {
    }
}
