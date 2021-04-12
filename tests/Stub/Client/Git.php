<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\Client;

use Webgriffe\SyliusUpgradePlugin\Client\GitInterface;

final class Git implements GitInterface
{
    /** @var string */
    public static $diffToReturn = '';

    public function getDiffBetweenTags(string $fromTag, string $toTag): string
    {
        return self::$diffToReturn;
    }
}
