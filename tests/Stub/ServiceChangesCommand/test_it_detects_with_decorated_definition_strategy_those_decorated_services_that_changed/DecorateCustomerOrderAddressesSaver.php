<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\test_it_detects_with_decorated_definition_strategy_those_decorated_services_that_changed;

use Sylius\Component\Core\Customer\OrderAddressesSaverInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class DecorateCustomerOrderAddressesSaver implements OrderAddressesSaverInterface
{
    public function __construct(private OrderAddressesSaverInterface $decoratedCustomerOrderAddressesSaver)
    {
    }

    public function saveAddresses(OrderInterface $order): void
    {
        $this->decoratedCustomerOrderAddressesSaver->saveAddresses($order);
    }
}
