<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Erasure;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Erasure\AttentionReason;
use Phil\PrivacyBundle\Domain\Erasure\ErasureInventory;
use Phil\PrivacyBundle\Domain\Erasure\ErasureProgress;
use Phil\PrivacyBundle\Domain\Erasure\ErasureRequest;
use Phil\PrivacyBundle\Domain\Erasure\ErasureStep;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\Erasure\StepState;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErasureInventory::class)]
#[CoversClass(ErasureRequest::class)]
#[CoversClass(ErasureStep::class)]
#[CoversClass(ErasureProgress::class)]
#[CoversClass(RequestState::class)]
#[CoversClass(StepState::class)]
#[CoversClass(AttentionReason::class)]
#[CoversClass(ErasureEffect::class)]
final class ErasureDomainTest extends TestCase
{
    private const string VERSION = '0123456789abcdef';

    public function testTheInventoryIsSortedAndVersionedByItsModulesAndTheirKind(): void
    {
        $inventory = ErasureInventory::of([$this->module('forum'), $this->module('billing')], [$this->module('mailing')]);

        self::assertSame(['billing' => false, 'forum' => false, 'mailing' => true], $inventory->modules);
        self::assertSame(substr(hash('sha256', "billing:local\nforum:local\nmailing:external"), 0, 16), $inventory->version);
        self::assertMatchesRegularExpression(ErasureInventory::VERSION_PATTERN, $inventory->version);
        self::assertSame($inventory->version, ErasureInventory::of([$this->module('billing'), $this->module('forum')], [$this->module('mailing')])->version);
        self::assertNotSame($inventory->version, ErasureInventory::of([$this->module('billing'), $this->module('forum'), $this->module('mailing')])->version, 'The kind of a module is part of the version.');
        self::assertNotSame($inventory->version, ErasureInventory::of([$this->module('billing')], [$this->module('mailing')])->version);
        self::assertTrue($inventory->has('forum'));
        self::assertFalse($inventory->has('crm'));
        self::assertTrue($inventory->isExternal('mailing'));
        self::assertFalse($inventory->isExternal('forum'));
        self::assertFalse($inventory->isExternal('crm'));
    }

    public function testAModuleDeclaredTwiceIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('The module "forum" is declared twice in the erasure inventory.');

        ErasureInventory::of([$this->module('forum')], [$this->module('forum')]);
    }

    public function testASelectionNamesDeclaredModulesOnly(): void
    {
        $inventory = ErasureInventory::of([$this->module('forum'), $this->module('billing'), $this->module('crm')]);

        self::assertSame(['billing', 'crm', 'forum'], $inventory->select());
        self::assertSame(['billing', 'forum'], $inventory->select(['forum', 'billing', 'forum']));

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('"a'.str_repeat('x', 62).'…" is not a module of the erasure inventory.');

        $inventory->select(['a'.str_repeat('x', 79)]);
    }

    public function testAnEmptySelectionIsRefused(): void
    {
        foreach ([static fn (): array => ErasureInventory::of([])->select(), fn (): array => ErasureInventory::of([$this->module('forum')])->select([])] as $select) {
            try {
                $select();
                self::fail('An empty selection was accepted.');
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertSame('An erasure request erases at least one module, and none is selected.', $invalidPrivacyValue->getMessage());
            }
        }
    }

    public function testAMemberRequestNamesThePersonAndHasNoProgressLink(): void
    {
        $at = new DateTimeImmutable('2026-11-20 10:00:00 UTC');
        $request = ErasureRequest::forSubject(DataSubject::fromString('member-7'), self::VERSION, $at);

        self::assertMatchesRegularExpression(ErasureRequest::ID_PATTERN, $request->id);
        self::assertSame(RequestState::Running, $request->state);
        self::assertSame($at, $request->requestedAt);
        self::assertNull($request->closedAt);
        self::assertNull($request->accessHash);
        self::assertTrue($request->isAbout(DataSubject::fromString('member-7')));
        self::assertFalse($request->isAbout(DataSubject::fromString('member-8')));
        self::assertFalse($request->isOpenedBy(str_repeat('a', 43)));
        self::assertSame(str_repeat('a', 32), ErasureRequest::forSubject(DataSubject::fromString('m'), self::VERSION, $at, str_repeat('a', 32))->id);
        self::assertNotSame($request->id, ErasureRequest::forSubject(DataSubject::fromString('member-7'), self::VERSION, $at)->id);
    }

    public function testAGuestRequestIsOpenedByItsSecretOnly(): void
    {
        [$request, $token] = ErasureRequest::forGuest(str_repeat('b', 32), self::VERSION, new DateTimeImmutable());

        self::assertNull($request->subject);
        self::assertSame(str_repeat('b', 32), $request->guestRequestId);
        self::assertMatchesRegularExpression(ErasureRequest::TOKEN_PATTERN, $token);
        self::assertSame(hash('sha256', $token), $request->accessHash);
        self::assertTrue($request->isOpenedBy($token));
        self::assertFalse($request->isOpenedBy(strrev($token)));
        self::assertFalse($request->isOpenedBy($token.'='));
        self::assertFalse($request->isOpenedBy(''));
        self::assertFalse($request->isAbout(DataSubject::fromString('member-7')));
        self::assertNotSame($token, ErasureRequest::forGuest(str_repeat('b', 32), self::VERSION, new DateTimeImmutable())[1]);
    }

    public function testASecretOfTheRightShapeWhoseHashMatchesIsStillCheckedForItsShape(): void
    {
        $token = 'short';
        $request = new ErasureRequest(str_repeat('a', 32), null, str_repeat('b', 32), self::VERSION, RequestState::Running, new DateTimeImmutable(), null, hash('sha256', $token));

        self::assertFalse($request->isOpenedBy($token));
    }

    /**
     * @return iterable<string, array{string, ?DataSubject, ?string, string, ?string, string}>
     */
    public static function refusedRequests(): iterable
    {
        $subject = DataSubject::fromString('member-7');

        yield 'an identifier of another shape' => ['ABC', $subject, null, self::VERSION, null, 'An erasure request identifier is 32 lowercase hexadecimal characters.'];
        yield 'an identifier with a new line' => [str_repeat('a', 32)."\n", $subject, null, self::VERSION, null, 'An erasure request identifier is 32 lowercase hexadecimal characters.'];
        yield 'nobody' => [str_repeat('a', 32), null, null, self::VERSION, null, 'An erasure request names either a person or the request made without an account it came from.'];
        yield 'both' => [str_repeat('a', 32), $subject, str_repeat('b', 32), self::VERSION, null, 'An erasure request names either a person or the request made without an account it came from.'];
        yield 'a guest request of another shape' => [str_repeat('a', 32), null, 'ada@example.org', self::VERSION, null, 'A request made without an account is named by 32 lowercase hexadecimal characters.'];
        yield 'a guest request with a new line' => [str_repeat('a', 32), null, str_repeat('b', 32)."\n", self::VERSION, null, 'A request made without an account is named by 32 lowercase hexadecimal characters.'];
        yield 'a version of another shape' => [str_repeat('a', 32), $subject, null, 'v1', null, 'The version of an erasure inventory is 16 lowercase hexadecimal characters.'];
        yield 'an access hash of another shape' => [str_repeat('a', 32), null, str_repeat('b', 32), self::VERSION, 'secret', 'An erasure request keeps the SHA-256 of the secret of its progress link, in hexadecimal.'];
        yield 'an access hash with a prefix' => [str_repeat('a', 32), null, str_repeat('b', 32), self::VERSION, 'z'.str_repeat('c', 64), 'An erasure request keeps the SHA-256 of the secret of its progress link, in hexadecimal.'];
        yield 'an access hash with a new line' => [str_repeat('a', 32), null, str_repeat('b', 32), self::VERSION, str_repeat('c', 64)."\n", 'An erasure request keeps the SHA-256 of the secret of its progress link, in hexadecimal.'];
    }

    #[DataProvider('refusedRequests')]
    public function testARequestOutOfShapeIsRefused(string $id, ?DataSubject $subject, ?string $guest, string $version, ?string $hash, string $message): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ErasureRequest($id, $subject, $guest, $version, RequestState::Running, new DateTimeImmutable(), null, $hash);
    }

    public function testAStepIsKeyedByItsRequestAndModule(): void
    {
        $at = new DateTimeImmutable('2026-11-20 10:00:00 UTC');
        $step = ErasureStep::pending(str_repeat('a', 32), 'forum', $at);

        self::assertSame(StepState::Pending, $step->state);
        self::assertSame(0, $step->attempts);
        self::assertSame([], $step->effects);
        self::assertSame($at, $step->updatedAt);
        self::assertNull($step->completedAt);
        self::assertNull($step->reason);
        self::assertSame(str_repeat('a', 32).':forum', $step->idempotencyKey());
        self::assertSame('r:m', ErasureStep::keyOf('r', 'm'));
    }

    public function testOnlyCompletedAndFailedRequestsAreClosed(): void
    {
        self::assertSame(['completed', 'failed'], array_values(array_map(static fn (RequestState $state): string => $state->value, array_filter(RequestState::cases(), static fn (RequestState $state): bool => $state->isClosed()))));
        self::assertSame(['pending', 'running', 'completed', 'needs_attention'], array_map(static fn (StepState $state): string => $state->value, StepState::cases()));
        self::assertSame(['retries_exhausted', 'unknown_module', 'subject_unavailable'], array_map(static fn (AttentionReason $reason): string => $reason->value, AttentionReason::cases()));
    }

    public function testOnlyADeletionOrAnAnonymisationLeavesNoPersonalData(): void
    {
        self::assertSame(['deleted', 'anonymised'], array_values(array_map(static fn (ErasureEffect $effect): string => $effect->value, array_filter(ErasureEffect::cases(), static fn (ErasureEffect $effect): bool => !$effect->leavesPersonalData()))));
        self::assertSame(['deleted', 'anonymised', 'pseudonymised', 'redacted', 'awaits_expiry', 'survives'], array_map(static fn (ErasureEffect $effect): string => $effect->value, ErasureEffect::cases()));
    }

    public function testTheProgressCountsTheModulesAndNamesNobody(): void
    {
        $at = new DateTimeImmutable('2026-11-20 10:00:00 UTC');
        $request = new ErasureRequest(str_repeat('a', 32), DataSubject::fromString('member-7'), null, self::VERSION, RequestState::NeedsAttention, $at);
        $progress = new ErasureProgress($request, [
            new ErasureStep($request->id, 'billing', StepState::Completed, 0, ['invoices' => ErasureEffect::Survives, 'cards' => ErasureEffect::Deleted], $at, $at->modify('+5 seconds')),
            new ErasureStep($request->id, 'forum', StepState::NeedsAttention, 3, [], $at, null, AttentionReason::RetriesExhausted),
            new ErasureStep($request->id, 'mailing', StepState::Running, 0, [], $at),
        ]);

        self::assertSame(['completed' => 1, 'total' => 3], $progress->counts());
        self::assertSame([
            'id' => $request->id,
            'state' => 'needs_attention',
            'inventory_version' => self::VERSION,
            'requested_at' => '2026-11-20T10:00:00+00:00',
            'closed_at' => null,
            'completed' => 1,
            'total' => 3,
            'modules' => [
                ['module' => 'billing', 'state' => 'completed', 'reason' => null, 'completed_at' => '2026-11-20T10:00:05+00:00', 'effects' => ['invoices' => ['effect' => 'survives', 'personal_data_left' => true], 'cards' => ['effect' => 'deleted', 'personal_data_left' => false]]],
                ['module' => 'forum', 'state' => 'needs_attention', 'reason' => 'retries_exhausted', 'completed_at' => null, 'effects' => []],
                ['module' => 'mailing', 'state' => 'running', 'reason' => null, 'completed_at' => null, 'effects' => []],
            ],
        ], $progress->toArray());
        self::assertStringNotContainsString('member-7', (string) json_encode($progress->toArray()));

        $closed = new ErasureProgress(new ErasureRequest(str_repeat('a', 32), DataSubject::fromString('m'), null, self::VERSION, RequestState::Completed, $at, $at->modify('+1 day')), []);
        self::assertSame('2026-11-21T10:00:00+00:00', $closed->toArray()['closed_at']);
        self::assertSame(['completed' => 0, 'total' => 0], $closed->counts());
    }

    private function module(string $name): ModuleName
    {
        return ModuleName::fromString($name);
    }
}
