<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\AccountDeletion;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountDeletion::class)]
final class AccountDeletionTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    public function testAMalformedIdentifierIsRefused(): void
    {
        foreach ([str_repeat('a', 31), str_repeat('A', 32), self::ID."\n", ' '.self::ID] as $id) {
            try {
                $this->deletion(DeletionPlace::Requested, $id);
                self::fail('Refused: '.$id);
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertStringContainsString('32 lowercase', $invalidPrivacyValue->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{DeletionPlace, bool}>
     */
    public static function places(): iterable
    {
        yield 'requested' => [DeletionPlace::Requested, true];
        yield 'exported' => [DeletionPlace::Exported, true];
        yield 'erased' => [DeletionPlace::Erased, false];
        yield 'cancelled' => [DeletionPlace::Cancelled, false];
    }

    #[DataProvider('places')]
    public function testOpenOnlyWhileThereIsSomethingToDo(DeletionPlace $place, bool $open): void
    {
        self::assertSame($open, $this->deletion($place)->isOpen());
        self::assertSame($open, $this->deletion($place)->isDue(new DateTimeImmutable('2026-02-01T00:00:00Z')));
    }

    public function testDueFromTheFirstInstantAfterTheDelay(): void
    {
        $deletion = $this->deletion(DeletionPlace::Requested);

        self::assertFalse($deletion->isDue(new DateTimeImmutable('2026-01-30T23:59:59Z')));
        self::assertTrue($deletion->isDue(new DateTimeImmutable('2026-01-31T00:00:00Z')));
    }

    public function testCancellableOnlyDuringTheDelay(): void
    {
        $deletion = $this->deletion(DeletionPlace::Requested);

        self::assertTrue($deletion->isCancellable(new DateTimeImmutable('2026-01-30T23:59:59Z')));
        self::assertFalse($deletion->isCancellable(new DateTimeImmutable('2026-01-31T00:00:00Z')));
        self::assertFalse($this->deletion(DeletionPlace::Exported)->isCancellable(new DateTimeImmutable('2026-01-02T00:00:00Z')));
    }

    public function testThePlaceTheExportAndTheClosureAreKept(): void
    {
        $deletion = $this->deletion(DeletionPlace::Requested);
        self::assertNull($deletion->closedAt());
        self::assertNull($deletion->exportReference());

        $deletion->setPlace(DeletionPlace::Erased);
        $deletion->exportedTo('ref');

        $closed = new DateTimeImmutable('2026-02-01T00:00:00Z');
        $deletion->closeAt($closed);

        self::assertSame(DeletionPlace::Erased, $deletion->getPlace());
        self::assertSame('ref', $deletion->exportReference());
        self::assertSame($closed, $deletion->closedAt());
    }

    private function deletion(DeletionPlace $place, string $id = self::ID): AccountDeletion
    {
        return new AccountDeletion($id, DataSubject::fromString('member-1'), $place, new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2026-01-31T00:00:00Z'));
    }
}
