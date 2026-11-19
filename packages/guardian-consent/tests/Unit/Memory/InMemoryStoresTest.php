<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit\Memory;

use DateTimeImmutable;
use Phil\GuardianConsent\Actor;
use Phil\GuardianConsent\CalendarDate;
use Phil\GuardianConsent\ConsentProof;
use Phil\GuardianConsent\ConsentRequest;
use Phil\GuardianConsent\Decision;
use Phil\GuardianConsent\Feature;
use Phil\GuardianConsent\Guardian;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;
use Phil\GuardianConsent\Memory\InMemoryMinorStore;
use Phil\GuardianConsent\Memory\InMemoryProofStore;
use Phil\GuardianConsent\Memory\InMemoryRequestStore;
use Phil\GuardianConsent\Minor;
use Phil\GuardianConsent\NoticeVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryMinorStore::class)]
#[CoversClass(InMemoryRequestStore::class)]
#[CoversClass(InMemoryProofStore::class)]
final class InMemoryStoresTest extends TestCase
{
    public function testMinors(): void
    {
        $store = new InMemoryMinorStore();
        $one = $this->minor('m-1', '2026-11-20');
        $store->save($one);
        $store->save($this->minor('m-2', '2026-11-14'));
        $store->save($this->minor('m-0', '2026-11-14'));
        $store->save($this->minor('m-3', '2026-11-21'));
        $store->save($this->minor('m-4', '2026-11-01')->withHandoverNoticed(new DateTimeImmutable()));

        self::assertSame($one, $store->get(new MemberId('m-1')));
        self::assertNull($store->get(new MemberId('m-9')));
        self::assertSame(['m-0', 'm-2', 'm-1'], $this->members($store->dueForHandover(CalendarDate::fromString('2026-11-20'), 10)));
        self::assertSame(['m-0', 'm-2'], $this->members($store->dueForHandover(CalendarDate::fromString('2026-11-20'), 2)));
        self::assertSame([], $store->dueForHandover(CalendarDate::fromString('2026-11-13'), 10));

        $replaced = $this->minor('m-1', '2026-11-25');
        $store->save($replaced);
        self::assertSame($replaced, $store->get(new MemberId('m-1')));

        $store->remove(new MemberId('m-1'));
        $store->remove(new MemberId('m-9'));
        self::assertNull($store->get(new MemberId('m-1')));
        self::assertNotNull($store->get(new MemberId('m-2')));

        $store->reset();
        self::assertNull($store->get(new MemberId('m-2')));
    }

    public function testRequests(): void
    {
        $store = new InMemoryRequestStore();
        $late = $this->request('BAAAAAAAAAAAAAAAAAAAAA', 'm-1', 'g-1', '2026-11-14 12:00:00', '2026-11-15 12:00:00');
        $early = $this->request('CAAAAAAAAAAAAAAAAAAAAA', 'm-1', 'g-2', '2026-11-14 10:00:00', '2026-11-16 12:00:00');
        $tie = $this->request('AAAAAAAAAAAAAAAAAAAAAA', 'm-2', 'g-1', '2026-11-14 12:00:00', '2026-11-15 12:00:00');
        $store->save($late);
        $store->save($early);
        $store->save($tie);

        self::assertSame($late, $store->get('BAAAAAAAAAAAAAAAAAAAAA'));
        self::assertNull($store->get('DAAAAAAAAAAAAAAAAAAAAA'));
        self::assertSame([$early, $late], $store->ofMember(new MemberId('m-1')));
        self::assertSame([$tie, $late], $store->ofGuardian(new GuardianId('g-1')));
        self::assertSame([], $store->ofMember(new MemberId('m-9')));
        self::assertSame([$tie, $late], $store->expiredBy(new DateTimeImmutable('2026-11-15 12:00:00'), 10));
        self::assertSame([$tie], $store->expiredBy(new DateTimeImmutable('2026-11-16 12:00:00'), 1));
        self::assertSame([$tie, $late, $early], $store->expiredBy(new DateTimeImmutable('2026-11-16 12:00:00'), 10));
        self::assertSame([], $store->expiredBy(new DateTimeImmutable('2026-11-15 11:59:59'), 10));

        self::assertTrue($store->consume('BAAAAAAAAAAAAAAAAAAAAA'));
        self::assertFalse($store->consume('BAAAAAAAAAAAAAAAAAAAAA'));
        self::assertNull($store->get('BAAAAAAAAAAAAAAAAAAAAA'));
        self::assertSame([$early], $store->ofMember(new MemberId('m-1')));

        $store->reset();
        self::assertSame([], $store->ofMember(new MemberId('m-1')));
    }

    public function testProofs(): void
    {
        $store = new InMemoryProofStore();
        $first = $this->proof('m-1', 'g-1');
        $second = $this->proof('m-2', 'g-1');
        $third = $this->proof('m-1', null);
        $fourth = $this->proof('m-1', 'g-2');
        $store->append($first);
        $store->append($second);
        $store->append($third);
        $store->append($fourth);

        self::assertSame([$first, $third, $fourth], $store->ofMember(new MemberId('m-1')));
        self::assertSame([$first, $second], $store->ofGuardian(new GuardianId('g-1')));
        self::assertSame([], $store->ofGuardian(new GuardianId('g-9')));

        self::assertSame(2, $store->forgetGuardian(new GuardianId('g-1')));
        self::assertSame(0, $store->forgetGuardian(new GuardianId('g-1')));
        self::assertSame([], $store->ofGuardian(new GuardianId('g-1')));
        self::assertNull($store->ofMember(new MemberId('m-2'))[0]->guardian);
        self::assertSame([$fourth], $store->ofGuardian(new GuardianId('g-2')));

        self::assertSame(3, $store->removeMember(new MemberId('m-1')));
        self::assertSame(0, $store->removeMember(new MemberId('m-1')));
        self::assertSame([], $store->ofMember(new MemberId('m-1')));
        self::assertCount(1, $store->ofMember(new MemberId('m-2')));
        self::assertSame(1, $store->removeMember(new MemberId('m-2')));

        $store->append($first);
        $store->reset();
        self::assertSame([], $store->ofMember(new MemberId('m-1')));
    }

    private function minor(string $member, string $consentAgeOn): Minor
    {
        return new Minor(new MemberId($member), 'CA-QC', CalendarDate::fromString($consentAgeOn), new DateTimeImmutable('2026-01-01'));
    }

    /**
     * @param list<Minor> $minors
     *
     * @return list<string>
     */
    private function members(array $minors): array
    {
        return array_map(static fn (Minor $minor): string => $minor->member->value, $minors);
    }

    private function request(string $id, string $member, string $guardian, string $at, string $until): ConsentRequest
    {
        return new ConsentRequest($id, new MemberId($member), new Guardian(new GuardianId($guardian), 'p@example.org'), [new Feature('messages')], new NoticeVersion('v1'), new DateTimeImmutable($at), new DateTimeImmutable($until), 'hash');
    }

    private function proof(string $member, ?string $guardian): ConsentProof
    {
        static $count = 0;
        ++$count;

        return new ConsentProof(
            str_pad((string) $count, 21, 'A', \STR_PAD_LEFT).'A',
            new MemberId($member),
            new Feature('messages'),
            Decision::Granted,
            null === $guardian ? Actor::Member : Actor::Guardian,
            null === $guardian ? null : new GuardianId($guardian),
            null,
            null,
            new DateTimeImmutable(),
        );
    }
}
