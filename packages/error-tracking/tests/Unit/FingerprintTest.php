<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit;

use DateTimeImmutable;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\Fingerprinter;
use Phil\ErrorTracking\Frame;
use Phil\ErrorTracking\MessageNormalizer;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fingerprinter::class)]
#[CoversClass(MessageNormalizer::class)]
final class FingerprintTest extends TestCase
{
    #[TestWith(['User 42 not found', 'User <n> not found'])]
    #[TestWith(['Order 3f2c1a9e-0b1d-4c2e-9f3a-1b2c3d4e5f60 is gone', 'Order <uuid> is gone'])]
    #[TestWith(['No account for jane.doe+test@example.co.uk', 'No account for <email>'])]
    #[TestWith(['Could not reach https://api.example.com/v2/items?page=3 now', 'Could not reach <url> now'])]
    #[TestWith(['Key "user.42" missing', 'Key <string> missing'])]
    #[TestWith(["Column 'email' unknown", 'Column <string> unknown'])]
    #[TestWith(['Bad pointer 0xDEADbeef here', 'Bad pointer <hex> here'])]
    #[TestWith(['Token a3f9c2e81b7d4f60 refused', 'Token <hex> refused'])]
    #[TestWith(['Ratio 0.75 over 12', 'Ratio <n> over <n>'])]
    #[TestWith(['Item42 and abcdefabcdef', 'Item<n> and abcdefabcdef'])]
    #[TestWith(['Call to undefined method Foo::bar()', 'Call to undefined method Foo::bar()'])]
    public function testTheVariableValuesOfAMessageArePlaceholders(string $message, string $normalized): void
    {
        self::assertSame($normalized, new MessageNormalizer()->normalize($message));
    }

    public function testALongMessageIsCut(): void
    {
        self::assertSame(str_repeat('a', MessageNormalizer::MAX_LENGTH), new MessageNormalizer()->normalize(str_repeat('a', 600)));
        self::assertSame(512, MessageNormalizer::MAX_LENGTH);
    }

    public function testEventsThatDifferOnlyByTheirValuesShareAFingerprint(): void
    {
        $fingerprinter = new Fingerprinter();
        $first = $fingerprinter->fingerprint($this->event('User 42 not found', [new Frame('src/A.php', 10, 'App\A::run', true)]));

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $first);
        self::assertSame($first, $fingerprinter->fingerprint($this->event('User 43 not found', [new Frame('src/A.php', 99, 'App\A::run', true)])));
        self::assertSame($first, $fingerprinter->fingerprint($this->event('User 43 not found', [new Frame('src/A.php', 10, 'App\A::run', true), new Frame('vendor/x.php', 1, 'X::y')])));
        self::assertSame(
            hash('sha256', "RuntimeException\nUser <n> not found\nsrc/A.php|App\\A::run\n"),
            $first,
        );
    }

    public function testWhatTellsGroupsApart(): void
    {
        $fingerprinter = new Fingerprinter();
        $frames = [new Frame('src/A.php', 10, 'App\A::run', true)];
        $base = $fingerprinter->fingerprint($this->event('Gone', $frames));

        self::assertNotSame($base, $fingerprinter->fingerprint($this->event('Gone', $frames, 'LogicException')));
        self::assertNotSame($base, $fingerprinter->fingerprint($this->event('Lost', $frames)));
        self::assertNotSame($base, $fingerprinter->fingerprint($this->event('Gone', [new Frame('src/B.php', 10, 'App\A::run', true)])));
        self::assertNotSame($base, $fingerprinter->fingerprint($this->event('Gone', [new Frame('src/A.php', 10, 'App\A::stop', true)])));
    }

    public function testOnlyTheFirstFramesOfTheApplicationCount(): void
    {
        $frames = [];

        for ($index = 0; $index < Fingerprinter::MAX_FRAMES; ++$index) {
            $frames[] = new Frame('src/F'.$index.'.php', 1, null, true);
        }

        $fingerprinter = new Fingerprinter();
        $base = $fingerprinter->fingerprint($this->event('m', $frames));

        self::assertSame($base, $fingerprinter->fingerprint($this->event('m', [...$frames, new Frame('src/Extra.php', 1, null, true)])));
        self::assertNotSame($base, $fingerprinter->fingerprint($this->event('m', \array_slice($frames, 0, -1))));
        self::assertSame(10, Fingerprinter::MAX_FRAMES);
    }

    public function testTheChainCounts(): void
    {
        $fingerprinter = new Fingerprinter();
        $single = new ErrorEvent(str_repeat('a', 32), new DateTimeImmutable(), Source::Http, Severity::Error, [new ExceptionRecord('A', 'a')]);
        $chained = new ErrorEvent(str_repeat('a', 32), new DateTimeImmutable(), Source::Http, Severity::Error, [new ExceptionRecord('A', 'a'), new ExceptionRecord('B', 'b')]);

        self::assertSame(hash('sha256', "A\na\n\nB\nb\n"), $fingerprinter->fingerprint($chained));
        self::assertNotSame($fingerprinter->fingerprint($single), $fingerprinter->fingerprint($chained));
    }

    /**
     * @param list<Frame> $frames
     */
    private function event(string $message, array $frames, string $type = 'RuntimeException'): ErrorEvent
    {
        return new ErrorEvent(str_repeat('a', 32), new DateTimeImmutable(), Source::Http, Severity::Error, [new ExceptionRecord($type, $message, $frames)]);
    }
}
