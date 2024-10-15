<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->import('vendor/sylius-labs/coding-standard/ecs.php');

    $ecsConfig->paths([
        'src',
        'tests/Behat',
        'tests/Integration',
        'tests/Stub',
    ]);
    $ecsConfig->skip(['src/Kernel.php']);

    $ecsConfig->skip([
        VisibilityRequiredFixer::class => ['*Spec.php'],
    ]);
};
