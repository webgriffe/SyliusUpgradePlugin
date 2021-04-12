<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin\Client;

interface GitInterface
{
    public function getDiffBetweenTags(string $fromTag, string $toTag): string;
}
