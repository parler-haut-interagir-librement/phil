<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Draft::class)]
final class DraftTest extends TestCase
{
    private const string OWNER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testAFirstRevisionIsSavedNowInUtcToTheSecondAndKeptForItsDuration(): void
    {
        $draft = Draft::first(self::OWNER, DraftKey::of('abcdefghijklmnop'), 'sealed', new DateTimeImmutable('2026-11-08 10:00:00.750 America/Montreal'), 60);

        self::assertSame(self::OWNER, $draft->owner());
        self::assertSame('abcdefghijklmnop', $draft->key()->value);
        self::assertSame(1, $draft->revision());
        self::assertSame('sealed', $draft->payload());
        self::assertSame('2026-11-08 15:00:00.000000 UTC', $draft->savedAt()->format('Y-m-d H:i:s.u e'));
        self::assertSame('2026-11-08 15:01:00 UTC', $draft->expiresAt()->format('Y-m-d H:i:s e'));
        self::assertSame(Draft::TABLE, 'phil_form_draft');
    }

    public function testTheNextRevisionReplacesTheContentAndStartsItsDurationAgain(): void
    {
        $first = Draft::first(self::OWNER, DraftKey::of('abcdefghijklmnop'), 'one', new DateTimeImmutable('2026-11-08 15:00:00 UTC'), 60);
        $next = $first->next('two', new DateTimeImmutable('2026-11-08 15:00:30.5 UTC'), 120);

        self::assertSame(2, $next->revision());
        self::assertSame('two', $next->payload());
        self::assertSame(self::OWNER, $next->owner());
        self::assertSame('abcdefghijklmnop', $next->key()->value);
        self::assertSame('2026-11-08 15:00:30.000000', $next->savedAt()->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-11-08 15:02:30', $next->expiresAt()->format('Y-m-d H:i:s'));
        self::assertSame(1, $first->revision(), 'a draft is a value');
    }

    public function testADraftHasExpiredFromItsExpiryOn(): void
    {
        $draft = Draft::first(self::OWNER, DraftKey::of('abcdefghijklmnop'), 'x', new DateTimeImmutable('2026-11-08 15:00:00 UTC'), 60);

        self::assertFalse($draft->hasExpired(new DateTimeImmutable('2026-11-08 15:00:59 UTC')));
        self::assertTrue($draft->hasExpired(new DateTimeImmutable('2026-11-08 15:01:00 UTC')));
        self::assertTrue($draft->hasExpired(new DateTimeImmutable('2026-11-08 15:01:01 UTC')));
    }

    public function testADraftIsRestoredAsAStoreKeptIt(): void
    {
        $savedAt = new DateTimeImmutable('2026-11-08 15:00:00 UTC');
        $expiresAt = new DateTimeImmutable('2026-11-08 15:00:01 UTC');
        $draft = Draft::restore(self::OWNER, DraftKey::of('abcdefghijklmnop'), 1, 'x', $savedAt, $expiresAt);

        self::assertSame($savedAt, $draft->savedAt());
        self::assertSame($expiresAt, $draft->expiresAt());
    }

    public function testTheDurationIsPositive(): void
    {
        $now = new DateTimeImmutable('2026-11-08 15:00:00 UTC');
        self::assertSame('2026-11-08 15:00:01', Draft::first(self::OWNER, DraftKey::of('abcdefghijklmnop'), 'x', $now, 1)->expiresAt()->format('Y-m-d H:i:s'));

        $this->expectException(InvalidDraft::class);
        $this->expectExceptionMessageIsOrContains('A draft is kept for a positive number of seconds.');

        Draft::first(self::OWNER, DraftKey::of('abcdefghijklmnop'), 'x', $now, 0);
    }

    public function testTheNextDurationIsPositive(): void
    {
        $now = new DateTimeImmutable('2026-11-08 15:00:00 UTC');

        $this->expectException(InvalidDraft::class);

        Draft::first(self::OWNER, DraftKey::of('abcdefghijklmnop'), 'x', $now, 1)->next('y', $now, 0);
    }

    public function testTheOwnerIsAHash(): void
    {
        foreach (['', 'bob', strtoupper(self::OWNER), self::OWNER.'a', substr(self::OWNER, 1), self::OWNER."\n"] as $owner) {
            try {
                Draft::first($owner, DraftKey::of('abcdefghijklmnop'), 'x', new DateTimeImmutable(), 60);
                self::fail($owner);
            } catch (InvalidDraft $invalid) {
                self::assertSame('The owner of a draft is a hash of 64 hexadecimal digits.', $invalid->getMessage());
            }
        }
    }

    public function testTheRevisionIsPositiveAndTheExpiryAfterTheSave(): void
    {
        $at = new DateTimeImmutable('2026-11-08 15:00:00 UTC');

        foreach ([[0, $at->modify('+1 second'), 'The revision of a draft is positive.'], [1, $at, 'A draft expires after it was saved.'], [1, $at->modify('-1 second'), 'A draft expires after it was saved.']] as [$revision, $expiresAt, $message]) {
            try {
                Draft::restore(self::OWNER, DraftKey::of('abcdefghijklmnop'), $revision, 'x', $at, $expiresAt);
                self::fail($message);
            } catch (InvalidDraft $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }
    }

    public function testTheKeyColumnsHoldAHashAndAKey(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $metadata = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config)->getClassMetadata(Draft::class);

        self::assertSame(64, $metadata->getFieldMapping('owner')->length);
        self::assertSame(64, $metadata->getFieldMapping('key')->length);
        self::assertSame(['owner', 'key'], $metadata->getIdentifierFieldNames());
    }
}
