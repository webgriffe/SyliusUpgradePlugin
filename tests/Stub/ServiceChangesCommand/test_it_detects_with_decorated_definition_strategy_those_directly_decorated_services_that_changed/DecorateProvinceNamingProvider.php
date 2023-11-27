<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\test_it_detects_with_decorated_definition_strategy_those_directly_decorated_services_that_changed;

use Sylius\Component\Addressing\Provider\ProvinceNamingProvider as BaseProvinceNamingProvider;

final class DecorateProvinceNamingProvider
{
    public function __construct(private BaseProvinceNamingProvider $baseProvinceNamingProvider)
    {
    }
}
