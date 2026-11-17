<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Integration;

use Override;
use Phil\LtiBundle\Infrastructure\Console\SchemaCommand;
use Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens;
use Phil\LtiBundle\Infrastructure\Dbal\DbalDeepLinkingReturns;
use Phil\LtiBundle\Infrastructure\Dbal\DbalKeyStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLineItemStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates;
use Phil\LtiBundle\Infrastructure\Dbal\DbalNonces;
use Phil\LtiBundle\Infrastructure\Dbal\DbalPendingScores;
use Phil\LtiBundle\Infrastructure\Dbal\DbalScoreStore;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Phil\LtiBundle\Infrastructure\Dbal\Row;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DbalNonces::class)]
#[CoversClass(DbalLoginStates::class)]
#[CoversClass(DbalAccessTokens::class)]
#[CoversClass(DbalLineItemStore::class)]
#[CoversClass(DbalScoreStore::class)]
#[CoversClass(DbalPendingScores::class)]
#[CoversClass(DbalDeepLinkingReturns::class)]
#[CoversClass(SchemaCommand::class)]
#[CoversClass(DbalKeyStore::class)]
#[CoversClass(LtiTables::class)]
#[CoversClass(Row::class)]
#[Group('mysql')]
final class MySqlStoresTest extends ServerStoresTestCase
{
    #[Override]
    protected function variables(): array
    {
        return ['PHIL_LTI_TEST_MYSQL_URL', 'PHIL_TEST_MYSQL_URL'];
    }
}
