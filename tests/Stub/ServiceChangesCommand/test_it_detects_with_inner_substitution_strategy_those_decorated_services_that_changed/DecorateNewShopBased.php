<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\test_it_detects_with_inner_substitution_strategy_those_decorated_services_that_changed;

use Sylius\Component\Core\Model\Order;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Contracts\Service\ResetInterface;

final class DecorateNewShopBased implements CartContextInterface, ResetInterface
{
    public function getCart(): OrderInterface
    {
        return new Order();
    }

    public function reset()
    {
        // TODO: Implement reset() method.
    }
}
