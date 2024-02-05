<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\test_it_detects_with_inner_substitution_strategy_those_decorated_services_that_changed;

use Sylius\Component\Core\Customer\CustomerAddressAdderInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;

final class DecorateCustomerUniqueAddressAdder implements CustomerAddressAdderInterface
{
    public function add(CustomerInterface $customer, AddressInterface $address): void
    {
        // TODO: Implement add() method.
    }
}
