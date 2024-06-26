<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\test_it_detects_with_decorated_definition_strategy_those_decorated_services_that_changed;

use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class DecorateOrderPaymentProcessor implements OrderProcessorInterface
{
    public function __construct(private OrderProcessorInterface $decoratedAfterCheckoutOrderPaymentProcessor)
    {
    }

    public function process(OrderInterface $order): void
    {
        $this->decoratedAfterCheckoutOrderPaymentProcessor->process($order);
    }
}
