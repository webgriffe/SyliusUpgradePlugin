<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class WebgriffeSyliusUpgradePlugin extends Bundle
{
    use SyliusPluginTrait;
}
