<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\test_it_detects_with_decorated_definition_strategy_those_decorated_services_that_changed;

use Sylius\Component\Addressing\Model\AddressInterface;
use Sylius\Component\Addressing\Provider\ProvinceNamingProvider as BaseProvinceNamingProvider;
use Sylius\Component\Addressing\Provider\ProvinceNamingProviderInterface;

final class DecorateProvinceNamingProvider implements ProvinceNamingProviderInterface
{
    public function __construct(private BaseProvinceNamingProvider $baseProvinceNamingProvider)
    {
    }

    public function getName(AddressInterface $address): string
    {
        return $this->baseProvinceNamingProvider->getName($address);
    }

    public function getAbbreviation(AddressInterface $address): string
    {
        return $this->baseProvinceNamingProvider->getAbbreviation($address);
    }
}
