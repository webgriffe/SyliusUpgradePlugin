<?php

$consoleColorClass = class_exists('Symplify\ConsoleColorDiff\ConsoleColorDiffBundle') ?
    'Symplify\ConsoleColorDiff\ConsoleColorDiffBundle' :
    'Symplify\ConsoleColorDiff\Bundle\ConsoleColorDiffBundle';
return [
    BabDev\PagerfantaBundle\BabDevPagerfantaBundle::class => ['all' => true],
    SyliusLabs\Polyfill\Symfony\Security\Bundle\SyliusLabsPolyfillSymfonySecurityBundle::class => ['all' => true],
    $consoleColorClass => ['dev' => true, 'test' => true],
];
