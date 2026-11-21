<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Infrastructure\Audit\NullAuditRecorder;
use Phil\IdentityProviderGoogle\Application\LinkedSessions;
use Phil\IdentityProviderGoogle\Tests\Fixture\FakeGoogle;
use Phil\IdentityProviderGoogle\Tests\Fixture\RiscHarness;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkedSessions::class)]
final class LinkedSessionsTest extends TestCase
{
    public function testTheAccountOfASubjectAndItsSessionsClosed(): void
    {
        $harness = new RiscHarness();
        $linked = new LinkedSessions($harness->identities, $harness->accounts, new Sessions($harness->sessions, new Journal(new NullAuditRecorder()), $harness->clock));

        self::assertSame($harness->account->id(), $linked->accountOf('google', FakeGoogle::SUBJECT));
        self::assertNull($linked->accountOf('google', 'another'));
        self::assertNull($linked->accountOf('other', FakeGoogle::SUBJECT));
        self::assertSame(2, $linked->close($harness->account->id()));
        self::assertSame([], $harness->openSessions());
        self::assertSame(0, $linked->close('01JDUNKNOWN000000000000000'));
    }

    public function testWithoutTheSessionsModuleNothingIsClosed(): void
    {
        $harness = new RiscHarness();
        $linked = new LinkedSessions($harness->identities, $harness->accounts);

        self::assertSame(0, $linked->close($harness->account->id()));
        self::assertCount(2, $harness->openSessions());
    }
}
