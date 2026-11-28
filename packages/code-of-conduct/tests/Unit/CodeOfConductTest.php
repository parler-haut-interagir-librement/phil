<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Tests\Unit;

use DateTimeImmutable;
use Phil\CodeOfConduct\AcceptanceStatus;
use Phil\CodeOfConduct\ChangeKind;
use Phil\CodeOfConduct\CodeOfConduct;
use Phil\CodeOfConduct\Event\CodeAccepted;
use Phil\CodeOfConduct\Event\CodeVersionPublished;
use Phil\CodeOfConduct\Exception\ActionRefused;
use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;
use Phil\CodeOfConduct\Memory\InMemoryAcceptanceStore;
use Phil\CodeOfConduct\Memory\InMemoryCodeVersionStore;
use Phil\CodeOfConduct\Tests\Support\RecordingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(CodeOfConduct::class)]
#[CoversClass(CodeVersionPublished::class)]
#[CoversClass(CodeAccepted::class)]
#[CoversClass(ActionRefused::class)]
final class CodeOfConductTest extends TestCase
{
    private RecordingDispatcher $events;

    private MockClock $clock;

    private CodeOfConduct $code;

    protected function setUp(): void
    {
        $this->events = new RecordingDispatcher();
        $this->clock = new MockClock('2026-12-01 10:00:00');
        $this->code = new CodeOfConduct(new InMemoryCodeVersionStore(), new InMemoryAcceptanceStore(), $this->clock, $this->events);
    }

    public function testVersionsAreNumberedOneAfterTheOther(): void
    {
        $first = $this->code->publish('club', 'Code', 'Be kind.', ChangeKind::Minor);
        $second = $this->code->publish('club', 'Code', 'Be kind, always.', ChangeKind::Minor);
        $other = $this->code->publish('fest', 'Code', 'Be kind.');

        self::assertSame([1, 2, 1], [$first->number, $second->number, $other->number]);
        self::assertSame(ChangeKind::Major, $first->kind);
        self::assertSame(ChangeKind::Minor, $second->kind);
        self::assertEquals(new DateTimeImmutable('2026-12-01 10:00:00'), $first->publishedAt);
        self::assertSame($second, $this->code->current('club'));
        self::assertSame($first, $this->code->version('club', 1));
        self::assertNull($this->code->version('club', 3));
        self::assertNull($this->code->current('none'));
        self::assertEquals([new CodeVersionPublished('club', 1), new CodeVersionPublished('club', 2), new CodeVersionPublished('fest', 1)], $this->events->events);
    }

    public function testAMemberAcceptsTheVersionInForce(): void
    {
        $version = $this->code->publish('club', 'Code', 'Be kind.');
        $this->events->events = [];
        $acceptance = $this->code->accept('club', 'sam', 1);

        self::assertSame($version->hash, $acceptance->hash);
        self::assertSame(1, $acceptance->version);
        self::assertEquals(new DateTimeImmutable('2026-12-01 10:00:00'), $acceptance->acceptedAt);
        self::assertSame(AcceptanceStatus::Accepted, $this->code->status('club', 'sam'));
        self::assertEquals([new CodeAccepted('club', 'sam', 1)], $this->events->events);
    }

    public function testTheStatusFollowsMajorAndMinorVersions(): void
    {
        $statuses = [$this->code->status('club', 'sam')];
        $this->code->publish('club', 'Code', 'Be kind.');
        $statuses[] = $this->code->status('club', 'sam');
        $this->code->accept('club', 'sam', 1);
        $this->code->publish('club', 'Code', 'Be kind, fixed a typo.', ChangeKind::Minor);
        $statuses[] = $this->code->status('club', 'sam');
        $this->code->publish('club', 'Code', 'A new rule.', ChangeKind::Major);
        $statuses[] = $this->code->status('club', 'sam');
        $this->code->accept('club', 'sam', 3);
        $statuses[] = $this->code->status('club', 'sam');
        $statuses[] = $this->code->status('club', 'alex');
        $statuses[] = $this->code->status('fest', 'sam');

        self::assertSame([
            AcceptanceStatus::NoCode,
            AcceptanceStatus::NotAccepted,
            AcceptanceStatus::Accepted,
            AcceptanceStatus::Outdated,
            AcceptanceStatus::Accepted,
            AcceptanceStatus::NotAccepted,
            AcceptanceStatus::NoCode,
        ], $statuses);
    }

    public function testAMinorVersionAcceptedAfterAMajorOneHolds(): void
    {
        $this->code->publish('club', 'Code', 'Be kind.');
        $this->code->publish('club', 'Code', 'Be kind, typo.', ChangeKind::Minor);
        $this->code->accept('club', 'sam', 2);

        self::assertSame(AcceptanceStatus::Accepted, $this->code->status('club', 'sam'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function staleVersions(): iterable
    {
        yield 'an older version' => [1];
        yield 'a version not published' => [3];
    }

    #[DataProvider('staleVersions')]
    public function testOnlyTheVersionInForceIsAccepted(int $version): void
    {
        $this->code->publish('club', 'Code', 'Be kind.');
        $this->code->publish('club', 'Code', 'Be kind, always.');

        $this->expectException(ActionRefused::class);
        $this->expectExceptionMessageIsOrContains('Only the version of the code in force is accepted; read it again.');

        $this->code->accept('club', 'sam', $version);
    }

    public function testAScopeWithoutACodeAcceptsNothing(): void
    {
        $this->expectException(ActionRefused::class);

        $this->code->accept('club', 'sam', 1);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'publish' => ['publish', 'A scope'];
        yield 'current' => ['current', 'A scope'];
        yield 'version' => ['version', 'A scope'];
        yield 'accept, scope' => ['acceptScope', 'A scope'];
        yield 'accept, member' => ['acceptMember', 'A member'];
        yield 'accept, member before the version' => ['acceptMemberStaleVersion', 'A member'];
        yield 'status, scope' => ['statusScope', 'A scope'];
        yield 'status, member' => ['statusMember', 'A member'];
    }

    #[DataProvider('badIdentifiers')]
    public function testEveryPathChecksItsIdentifiers(string $path, string $what): void
    {
        $this->code->publish('club', 'Code', 'Be kind.');

        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains($what.' is named by');

        match ($path) {
            'publish' => $this->code->publish('', 'Code', 'Text'),
            'current' => $this->code->current(''),
            'version' => $this->code->version('', 1),
            'acceptScope' => $this->code->accept('', 'sam', 1),
            'acceptMember' => $this->code->accept('club', '', 1),
            'acceptMemberStaleVersion' => $this->code->accept('club', '', 99),
            'statusScope' => $this->code->status('', 'sam'),
            default => $this->code->status('club', ''),
        };
    }

    public function testItWorksWithoutADispatcher(): void
    {
        $code = new CodeOfConduct(new InMemoryCodeVersionStore(), new InMemoryAcceptanceStore(), $this->clock);
        $code->publish('club', 'Code', 'Be kind.');
        $code->accept('club', 'sam', 1);

        self::assertSame(AcceptanceStatus::Accepted, $code->status('club', 'sam'));
    }
}
