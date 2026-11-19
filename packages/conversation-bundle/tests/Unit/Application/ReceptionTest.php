<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Application;

use Phil\ConversationBundle\Application\NoContacts;
use Phil\ConversationBundle\Application\Reception;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalConversationRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalReceptionRepository;
use Phil\ConversationBundle\Tests\Unit\Fixture\FixedContacts;
use Phil\ConversationBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reception::class)]
#[CoversClass(NoContacts::class)]
#[CoversClass(DbalReceptionRepository::class)]
#[CoversClass(DbalConversationRepository::class)]
#[CoversClass(ConversationTables::class)]
final class ReceptionTest extends TestCase
{
    public function testEveryoneMayWriteByDefault(): void
    {
        $reception = new Reception(new DbalReceptionRepository(Store::sqlite()));

        self::assertTrue($reception->mayStart(Store::id('ada'), Store::id('bob')));
        self::assertFalse($reception->blockedEitherWay(Store::id('ada'), Store::id('bob')));
        self::assertSame(WhoCanWrite::Everyone, $reception->settings(Store::id('bob'))->whoCanWrite());
    }

    public function testWhomARecipientLetsWriteDecides(): void
    {
        $store = new Store(contacts: new FixedContacts([['bob', 'ada']]));
        $reception = $store->reception;

        $reception->allow(Store::id('bob'), WhoCanWrite::Contacts);
        self::assertTrue($reception->mayStart(Store::id('ada'), Store::id('bob')));
        self::assertFalse($reception->mayStart(Store::id('cy'), Store::id('bob')));
        self::assertTrue($reception->mayStart(Store::id('bob'), Store::id('cy')), "the setting is the recipient's");

        $reception->allow(Store::id('bob'), WhoCanWrite::Nobody);
        self::assertFalse($reception->mayStart(Store::id('ada'), Store::id('bob')));
        self::assertSame(WhoCanWrite::Nobody, $reception->settings(Store::id('bob'))->whoCanWrite());

        $reception->allow(Store::id('bob'), WhoCanWrite::Everyone);
        self::assertTrue($reception->mayStart(Store::id('cy'), Store::id('bob')));
        self::assertFalse($store->connection->fetchOne('SELECT 1 FROM phil_conversation_reception'), 'the default keeps no row');
    }

    public function testContactsAreNobodyWithoutARelation(): void
    {
        $reception = new Reception(new DbalReceptionRepository(Store::sqlite()));
        $reception->allow(Store::id('bob'), WhoCanWrite::Contacts);

        self::assertFalse($reception->mayStart(Store::id('ada'), Store::id('bob')));
        self::assertFalse(new NoContacts()->areContacts(Store::id('ada'), Store::id('bob')));
    }

    public function testABlockStopsBothWays(): void
    {
        $reception = new Store()->reception;
        $reception->block(Store::id('bob'), Store::id('ada'));

        self::assertFalse($reception->mayStart(Store::id('ada'), Store::id('bob')));
        self::assertFalse($reception->mayStart(Store::id('bob'), Store::id('ada')));
        self::assertTrue($reception->blockedEitherWay(Store::id('ada'), Store::id('bob')));
        self::assertTrue($reception->blockedEitherWay(Store::id('bob'), Store::id('ada')));
        self::assertTrue($reception->mayStart(Store::id('cy'), Store::id('bob')));

        $reception->unblock(Store::id('bob'), Store::id('ada'));
        self::assertTrue($reception->mayStart(Store::id('ada'), Store::id('bob')));
        self::assertFalse($reception->blockedEitherWay(Store::id('ada'), Store::id('bob')));
    }

    public function testTheSettingsAreKeptAndForgotten(): void
    {
        $store = new Store();
        $store->reception->allow(Store::id('cy'), WhoCanWrite::Nobody);
        $store->reception->allow(Store::id('bob'), WhoCanWrite::Nobody);
        $store->reception->block(Store::id('bob'), Store::id('ada'));
        $store->reception->block(Store::id('bob'), Store::id('cy'));
        $store->reception->block(Store::id('cy'), Store::id('bob'));

        $settings = $store->receptions->of(Store::id('bob'));
        self::assertSame(WhoCanWrite::Nobody, $settings->whoCanWrite());
        self::assertSame(['ada', 'cy'], array_map(strval(...), $settings->blocked()));

        self::assertSame(4, $store->receptions->forget(Store::id('bob')), 'the setting, the two blocks of bob, and bob among the blocks of cy');
        self::assertSame(WhoCanWrite::Everyone, $store->receptions->of(Store::id('bob'))->whoCanWrite());
        self::assertSame([], $store->receptions->of(Store::id('cy'))->blocked());
        self::assertSame(WhoCanWrite::Nobody, $store->receptions->of(Store::id('cy'))->whoCanWrite(), 'the setting of another stays');
        self::assertSame(0, $store->receptions->forget(Store::id('bob')));
    }
}
