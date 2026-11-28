<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Phil\WorkshopsBundle\Domain\Model\Workshop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Workshop::class)]
#[CoversClass(InvalidWorkshop::class)]
final class WorkshopTest extends TestCase
{
    public function testAWorkshopKeepsItsTrimmedTitleAndStartsActive(): void
    {
        $workshop = Workshop::create('  Peindre des figurines  ', new DateTimeImmutable('2026-11-01 08:00', new DateTimeZone('America/Toronto')));

        self::assertSame('Peindre des figurines', $workshop->title());
        self::assertTrue($workshop->isActive());
        self::assertSame('2026-11-01T13:00:00+00:00', $workshop->createdAt()->format(\DATE_ATOM));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $workshop->id);
        self::assertNotSame($workshop->id, Workshop::create('Autre', new DateTimeImmutable())->id);
    }

    public function testItIsRenamedAndDeactivated(): void
    {
        $workshop = Workshop::create('A', new DateTimeImmutable());
        $workshop->rename(' B ');
        $workshop->activate(false);

        self::assertSame('B', $workshop->title());
        self::assertFalse($workshop->isActive());

        $workshop->activate(true);
        self::assertTrue($workshop->isActive());
    }

    public function testATitleOfTheLongestLengthIsTaken(): void
    {
        self::assertSame(200, mb_strlen(Workshop::create(str_repeat('é', 200), new DateTimeImmutable())->title()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badTitles(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('é', 201)];
        yield 'two lines' => ["Peindre\ndes figurines"];
        yield 'a control character' => ["Peindre\x7F"];
        yield 'a null byte' => ["Pein\x00dre"];
    }

    #[DataProvider('badTitles')]
    public function testABadTitleIsRefused(string $title): void
    {
        $this->expectException(InvalidWorkshop::class);
        $this->expectExceptionMessageIsOrContains('A workshop title has 1 to 200 characters, on one line.');

        Workshop::create($title, new DateTimeImmutable());
    }

    #[DataProvider('badTitles')]
    public function testABadTitleIsRefusedOnRenaming(string $title): void
    {
        $workshop = Workshop::create('A', new DateTimeImmutable());

        try {
            $workshop->rename($title);
            self::fail('The title is refused.');
        } catch (InvalidWorkshop) {
            self::assertSame('A', $workshop->title());
        }
    }
}
