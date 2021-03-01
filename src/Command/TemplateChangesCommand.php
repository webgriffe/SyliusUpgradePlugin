<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TemplateChangesCommand extends Command
{
    protected static $defaultName = 'webgriffe:upgrade:template-changes';

    protected function configure(): void
    {
        $this->setDescription(
            'Print a list of template files (with extension .html.twig) that changed between two given Sylius versions ' .
            'and that has been overridden in the project (in "templates" dir or in a theme).'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}
