<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin\Client;

use InvalidArgumentException;

final class Git implements GitInterface
{
    public function getDiffBetweenTags(string $fromTag, string $toTag): string
    {
        $url = 'https://github.com/Sylius/Sylius/compare/' . $fromTag . '..' . $toTag . '.diff';
        $diff = @file_get_contents($url);
        if ($diff === false) {
            throw new InvalidArgumentException('Error: one or both given versions does not exists. Did you forget to add the "v" prefix in the tags?');
        }

        return $diff;
    }
}
