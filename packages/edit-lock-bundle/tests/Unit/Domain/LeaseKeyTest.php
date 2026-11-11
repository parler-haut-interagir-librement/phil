<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Domain;

use Phil\EditLockBundle\Domain\Exception\EditLockException;
use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LeaseKey::class)]
#[CoversClass(Holder::class)]
#[CoversClass(InvalidLease::class)]
final class LeaseKeyTest extends TestCase
{
    public function testAKeyNamesAResourceAndMaybeAPart(): void
    {
        $whole = LeaseKey::of('article:42');
        $part = LeaseKey::of('article:42', 'aB3_-x');

        self::assertSame(['article:42', null, true, 'article:42'], [$whole->resource, $whole->part, $whole->isWhole(), $whole->toString()]);
        self::assertSame(['article:42', 'aB3_-x', false, 'article:42#aB3_-x'], [$part->resource, $part->part, $part->isWhole(), $part->toString()]);
        self::assertSame(255, \strlen(LeaseKey::of(str_repeat('r', 255))->resource));
        self::assertSame(64, \strlen(LeaseKey::of('r', str_repeat('p', 64))->part ?? ''));
        self::assertSame('é r', LeaseKey::of('é r')->resource);
    }

    public function testKeysOverlapAndCover(): void
    {
        $whole = LeaseKey::of('a');
        $one = LeaseKey::of('a', 'one');
        $two = LeaseKey::of('a', 'two');
        $elsewhere = LeaseKey::of('b', 'one');

        self::assertSame([true, true, true, true, false, false, false], [
            $whole->overlaps($one), $one->overlaps($whole), $one->overlaps(LeaseKey::of('a', 'one')), $whole->overlaps(LeaseKey::of('a')),
            $one->overlaps($two), $one->overlaps($elsewhere), LeaseKey::of('b')->overlaps($one),
        ]);
        self::assertSame([true, true, false, false, false], [
            $whole->covers($one), $one->covers(LeaseKey::of('a', 'one')), $one->covers($whole), $one->covers($two), LeaseKey::of('b')->covers($one),
        ]);
        self::assertSame([true, false, false], [$one->equals(LeaseKey::of('a', 'one')), $one->equals($whole), $one->equals($elsewhere)]);
    }

    public function testABadKeyIsRefused(): void
    {
        foreach ([['', null], [str_repeat('r', 256), null], ["a\nb", null], ["a\x7Fb", null], ["\x00", null], ['a', ''], ['a', 'b c'], ['a', str_repeat('p', 65)], ['a', "p\n"]] as [$resource, $part]) {
            try {
                LeaseKey::of($resource, $part);
                self::fail((string) json_encode([$resource, $part]));
            } catch (InvalidLease $invalidLease) {
                self::assertInstanceOf(EditLockException::class, $invalidLease);
                self::assertStringStartsWith('' === $resource || \strlen($resource) > 255 || 1 === preg_match('/[\x00-\x1F\x7F]/', $resource) ? 'A resource' : 'A part', $invalidLease->getMessage());
            }
        }
    }

    public function testAHolderHasAnIdentifierAndAName(): void
    {
        $holder = Holder::of('7', '  Ada  ');

        self::assertSame(['7', 'Ada'], [$holder->id, $holder->name]);
        self::assertTrue($holder->is(Holder::of('7', 'Other')));
        self::assertFalse($holder->is(Holder::of('8', 'Ada')));
        self::assertSame(255, \strlen(Holder::of(str_repeat('i', 255), 'x')->id));
        self::assertSame(255, mb_strlen(Holder::of('i', str_repeat('é', 255))->name));

        foreach ([['', 'Ada', 'A holder'], [str_repeat('i', 256), 'Ada', 'A holder'], ['7', '   ', 'The name'], ['7', str_repeat('é', 256), 'The name']] as [$id, $name, $message]) {
            try {
                Holder::of($id, $name);
                self::fail($message);
            } catch (InvalidLease $invalidLease) {
                self::assertStringStartsWith($message, $invalidLease->getMessage());
            }
        }
    }
}
