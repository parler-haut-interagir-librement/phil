<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Selection;

use DateTimeImmutable;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Internal\Check;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\PendingSelection;
use Phil\BulkActions\Selection\Selection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PendingSelection::class)]
#[CoversClass(Selection::class)]
#[CoversClass(FilterCriteria::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidInput::class)]
final class PendingSelectionTest extends TestCase
{
    private const string FINGERPRINT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testItIsUsableByItsOwnerUntilItExpires(): void
    {
        $pending = $this->pending();

        self::assertTrue($pending->isUsableBy('ana', new DateTimeImmutable('2026-11-21 10:14:59+00:00')));
        self::assertFalse($pending->isUsableBy('ana', new DateTimeImmutable('2026-11-21 10:15:00+00:00')));
        self::assertFalse($pending->isUsableBy('ana', new DateTimeImmutable('2026-11-21 05:15:00-05:00')));
        self::assertFalse($pending->isUsableBy('bob', new DateTimeImmutable('2026-11-21 10:00:00+00:00')));
        self::assertFalse($pending->isUsableBy('an', new DateTimeImmutable('2026-11-21 10:00:00+00:00')));
    }

    public function testItReadsBackWhatItStored(): void
    {
        $pending = $this->pending();
        $data = $pending->toArray();

        self::assertSame([
            'fingerprint' => self::FINGERPRINT,
            'actor' => 'ana',
            'target' => 'products',
            'action' => 'edit',
            'selection' => $pending->selection->toArray(),
            'expires_at' => '2026-11-21T10:15:00+00:00',
        ], $data);
        self::assertEquals($pending, PendingSelection::fromArray($data));
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'a fingerprint too short' => [substr(self::FINGERPRINT, 1), 'ana', 'products', 'edit', 'The fingerprint must be a SHA-256 in hexadecimal.'];
        yield 'a fingerprint with a character before' => ['g'.self::FINGERPRINT, 'ana', 'products', 'edit', 'The fingerprint must be a SHA-256 in hexadecimal.'];
        yield 'a fingerprint in capitals' => [strtoupper(self::FINGERPRINT), 'ana', 'products', 'edit', 'The fingerprint must be a SHA-256 in hexadecimal.'];
        yield 'a fingerprint with a line feed' => [self::FINGERPRINT."\n", 'ana', 'products', 'edit', 'The fingerprint must be a SHA-256 in hexadecimal.'];
        yield 'an empty actor' => [self::FINGERPRINT, '', 'products', 'edit', 'The actor must have 1 to 191 bytes'];
        yield 'a target with a space' => [self::FINGERPRINT, 'ana', 'my products', 'edit', 'The target must be a letter'];
        yield 'an action with a slash' => [self::FINGERPRINT, 'ana', 'products', 'ed/it', 'The action must be a letter'];
    }

    #[DataProvider('invalid')]
    public function testItRefusesWhatNoSelectorWrites(string $fingerprint, string $actor, string $target, string $action, string $message): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        new PendingSelection($fingerprint, $actor, $target, $action, Selection::identifiers(['a']), new DateTimeImmutable());
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function storedRows(): iterable
    {
        foreach (['fingerprint', 'actor', 'target', 'action', 'expires_at'] as $key) {
            yield $key.' missing' => [$key, null, 'The stored selection must have its '.$key.'.'];
            yield $key.' not a string' => [$key, 12, 'The stored selection must have its '.$key.'.'];
        }

        yield 'items missing' => ['selection', null, 'The stored selection must have its items and its expiry.'];
        yield 'an expiry in another format' => ['expires_at', '2026-11-21 10:15', 'The stored selection must have its items and its expiry.'];
    }

    #[DataProvider('storedRows')]
    public function testItRefusesAStoredRowItCannotHaveWritten(string $key, mixed $value, string $message): void
    {
        $data = $this->pending()->toArray();
        $data[$key] = $value;

        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        PendingSelection::fromArray($data);
    }

    private function pending(): PendingSelection
    {
        return new PendingSelection(
            self::FINGERPRINT,
            'ana',
            'products',
            'edit',
            Selection::filter(FilterCriteria::of('status', ['status' => 'draft']), ['p1']),
            new DateTimeImmutable('2026-11-21 10:15:00+00:00'),
        );
    }
}
