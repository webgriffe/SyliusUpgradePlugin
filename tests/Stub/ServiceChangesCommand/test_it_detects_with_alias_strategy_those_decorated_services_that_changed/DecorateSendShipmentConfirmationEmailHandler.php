<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Stub\ServiceChangesCommand\test_it_detects_with_alias_strategy_those_decorated_services_that_changed;

use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class DecorateSendShipmentConfirmationEmailHandler implements MessageHandlerInterface
{
}
