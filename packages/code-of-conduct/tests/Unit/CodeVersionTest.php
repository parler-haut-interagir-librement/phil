<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Tests\Unit;

use DateTimeImmutable;
use Phil\CodeOfConduct\Acceptance;
use Phil\CodeOfConduct\AcceptanceStatus;
use Phil\CodeOfConduct\ChangeKind;
use Phil\CodeOfConduct\CodeVersion;
use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodeVersion::class)]
#[CoversClass(Acceptance::class)]
#[CoversClass(AcceptanceStatus::class)]
final class CodeVersionTest extends TestCase
{
    public function testAVersionHashesItsTitleAndText(): void
    {
        $version = new CodeVersion('club', 1, ' Code ', " Be kind.\n ", ChangeKind::Major, new DateTimeImmutable('2026-12-01'));

        self::assertSame('Code', $version->title);
        self::assertSame('Be kind.', $version->text);
        self::assertSame(hash('sha256', "Code\n\nBe kind."), $version->hash);
        self::assertSame(1, $version->number);
    }

    public function testALaterVersionMayBeMinorAndTakesItsLongestFields(): void
    {
        $version = new CodeVersion('club', 2, str_repeat('é', 200), str_repeat('é', 100_000), ChangeKind::Minor, new DateTimeImmutable('2026-12-01'));

        self::assertSame(ChangeKind::Minor, $version->kind);
        self::assertSame(200, mb_strlen($version->title));
    }

    /**
     * @return iterable<string, array{string, int, string, string, ChangeKind, string}>
     */
    public static function refused(): iterable
    {
        yield 'version 0' => ['club', 0, 'Code', 'Text', ChangeKind::Major, 'A version of a code is numbered from 1.'];
        yield 'a minor first version' => ['club', 1, 'Code', 'Text', ChangeKind::Minor, 'The first version of a code is a major one.'];
        yield 'an empty title' => ['club', 1, ' ', 'Text', ChangeKind::Major, 'The title of a code holds 1 to 200 characters.'];
        yield 'a title of 201 characters' => ['club', 1, str_repeat('é', 201), 'Text', ChangeKind::Major, 'The title of a code holds 1 to 200 characters.'];
        yield 'a title on two lines' => ['club', 1, "Co\nde", 'Text', ChangeKind::Major, 'The title of a code holds 1 to 200 characters.'];
        yield 'a title with a tab' => ['club', 1, "Co\tde", 'Text', ChangeKind::Major, 'The title of a code holds 1 to 200 characters.'];
        yield 'a title with a carriage return' => ['club', 1, "Co\rde", 'Text', ChangeKind::Major, 'The title of a code holds 1 to 200 characters.'];
        yield 'an empty text' => ['club', 1, 'Code', '', ChangeKind::Major, 'The text of a code holds 1 to 100000 characters.'];
        yield 'a text too long' => ['club', 1, 'Code', str_repeat('a', 100_001), ChangeKind::Major, 'The text of a code holds 1 to 100000 characters.'];
        yield 'an empty scope' => ['', 1, 'Code', 'Text', ChangeKind::Major, 'A scope is named by'];
    }

    #[DataProvider('refused')]
    public function testAVersionOutOfBoundsIsRefused(string $scope, int $number, string $title, string $text, ChangeKind $kind, string $message): void
    {
        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains($message);

        new CodeVersion($scope, $number, $title, $text, $kind, new DateTimeImmutable('2026-12-01'));
    }

    public function testAnAcceptanceChecksItsIdentifiers(): void
    {
        $at = new DateTimeImmutable('2026-12-01');

        self::assertSame('sam', new Acceptance('club', 'sam', 1, 'hash', $at)->member);

        try {
            new Acceptance('', 'sam', 1, 'hash', $at);
            self::fail('An empty scope passed.');
        } catch (InvalidCodeOfConduct $invalidCodeOfConduct) {
            self::assertStringStartsWith('A scope is named', $invalidCodeOfConduct->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('A member is named');

        new Acceptance('club', '', 1, 'hash', $at);
    }

    public function testOnlyAnAcceptanceOrNoCodeLetsAMemberTakePart(): void
    {
        self::assertTrue(AcceptanceStatus::Accepted->mayTakePart());
        self::assertTrue(AcceptanceStatus::NoCode->mayTakePart());
        self::assertFalse(AcceptanceStatus::NotAccepted->mayTakePart());
        self::assertFalse(AcceptanceStatus::Outdated->mayTakePart());
    }
}
