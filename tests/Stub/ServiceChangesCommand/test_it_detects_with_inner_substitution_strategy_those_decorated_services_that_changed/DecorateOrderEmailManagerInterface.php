<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\test_it_detects_with_inner_substitution_strategy_those_decorated_services_that_changed;

use Sylius\Bundle\AdminBundle\EmailManager\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class DecorateOrderEmailManagerInterface implements OrderEmailManagerInterface
{
    public function sendConfirmationEmail(OrderInterface $order): void
    {
        // TODO: Implement sendConfirmationEmail() method.
    }
}
