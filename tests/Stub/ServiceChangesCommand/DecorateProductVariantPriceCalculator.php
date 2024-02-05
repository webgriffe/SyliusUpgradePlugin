<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand;

use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class DecorateProductVariantPriceCalculator implements ProductVariantPricesCalculatorInterface
{
    public function calculate(ProductVariantInterface $productVariant, array $context): int
    {
        return 0;
    }

    public function calculateOriginal(ProductVariantInterface $productVariant, array $context): int
    {
        return 0;
    }
}
