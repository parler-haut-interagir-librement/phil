<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Unit;

use Phil\AudienceSegments\AudienceConditions;
use Phil\AudienceSegments\Exception\InvalidSegment;
use Phil\AudienceSegments\SegmentJson;
use Phil\AudienceSegments\Tests\Support\MemoryFacts;
use Phil\Rules\Condition\Any;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Exception\InvalidRule;
use Phil\Rules\Exception\LimitExceeded;
use Phil\Rules\Limits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(SegmentJson::class)]
#[CoversClass(InvalidSegment::class)]
final class SegmentJsonTest extends TestCase
{
    private const string SEGMENT = '{"version":1,"name":"Fair buyers / Montréal","condition":{"any":[{"type":"ticket.holder","operands":{"event":"spring-fair","ticket_types":["day","weekend"]}},{"type":"person.tagged","operands":{"tags":["press"]}}]}}';

    private SegmentJson $json;

    protected function setUp(): void
    {
        $facts = new MemoryFacts();
        $this->json = new SegmentJson(AudienceConditions::types(participation: $facts, tags: $facts));
    }

    public function testReadsTheDefinition(): void
    {
        $segment = $this->json->read('fair-buyers', self::SEGMENT);

        self::assertSame('fair-buyers', $segment->id);
        self::assertSame('Fair buyers / Montréal', $segment->name);
        self::assertEquals(new Any(
            new Leaf('ticket.holder', ['event' => 'spring-fair', 'ticket_types' => ['day', 'weekend']]),
            new Leaf('person.tagged', ['tags' => ['press']]),
        ), $segment->rule->condition);
    }

    public function testWritesWhatItReads(): void
    {
        $segment = $this->json->read('fair-buyers', self::SEGMENT);

        self::assertSame(self::SEGMENT, $this->json->write($segment));
        self::assertSame(self::SEGMENT, $this->json->write($this->json->fromArray('x', $this->json->toArray($segment))));
        self::assertSame(['version' => 1, 'name' => 'Fair buyers / Montréal', 'condition' => ['any' => [
            ['type' => 'ticket.holder', 'operands' => ['event' => 'spring-fair', 'ticket_types' => ['day', 'weekend']]],
            ['type' => 'person.tagged', 'operands' => ['tags' => ['press']]],
        ]]], $this->json->toArray($segment));
        self::assertSame(hash('sha256', '{"version":1,"condition":{"any":[{"type":"ticket.holder","operands":{"event":"spring-fair","ticket_types":["day","weekend"]}},{"type":"person.tagged","operands":{"tags":["press"]}}]}}'), $this->json->fingerprint($segment));
    }

    /**
     * @return iterable<string, array{string, class-string<Throwable>, string}>
     */
    public static function refused(): iterable
    {
        yield 'not JSON' => ['{', InvalidRule::class, 'The rule is not valid JSON: Syntax error'];
        yield 'a text' => ['"x"', InvalidSegment::class, 'The segment definition is malformed: a segment is an object with "version", "name" and "condition".'];
        yield 'a list' => ['[]', InvalidSegment::class, 'a segment is an object with "version", "name" and "condition" only.'];
        yield 'another key' => ['{"version":1,"name":"x","condition":{"type":"person.tagged","operands":{"tags":["a"]}},"id":"y"}', InvalidSegment::class, 'a segment is an object with "version", "name" and "condition" only.'];
        yield 'no name' => ['{"version":1,"condition":{"type":"person.tagged","operands":{"tags":["a"]}}}', InvalidSegment::class, 'A segment name holds 1 to 200 bytes'];
        yield 'a name as number' => ['{"version":1,"name":3,"condition":{"type":"person.tagged","operands":{"tags":["a"]}}}', InvalidSegment::class, 'A segment name holds 1 to 200 bytes'];
        yield 'another version' => ['{"version":2,"name":"x","condition":{"type":"person.tagged","operands":{"tags":["a"]}}}', InvalidRule::class, 'The rule is of version 2'];
        yield 'an unregistered type' => ['{"version":1,"name":"x","condition":{"type":"purchase.made","operands":{}}}', InvalidRule::class, 'is of type "purchase.made", which no package registered.'];
    }

    /**
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('refused')]
    public function testRefuses(string $definition, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessageIsOrContains($message);

        $this->json->read('s', $definition);
    }

    public function testBoundsTheBytes(): void
    {
        $json = new SegmentJson(AudienceConditions::types(tags: new MemoryFacts()), new Limits(maxBytes: 87));
        $definition = '{"version":1,"name":"x","condition":{"type":"person.tagged","operands":{"tags":["a"]}}}';

        self::assertSame(87, \strlen($definition));
        self::assertSame('x', $json->read('s', $definition)->name);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The rule holds 88 bytes; at most 87 are read.');

        $json->read('s', $definition.' ');
    }

    public function testTellsTheDepthOfTheJsonFromTheOperandRefusedAtTheLimit(): void
    {
        $json = new SegmentJson(AudienceConditions::types(tags: new MemoryFacts()), new Limits(maxDepth: 1));

        try {
            $json->read('s', '{"version":1,"name":"x","condition":{"type":"person.tagged","operands":{"tags":[["a"]]}}}');
            self::fail('The nested list is refused.');
        } catch (InvalidRule $invalidRule) {
            self::assertStringContainsString('item 0 must be an identifier', $invalidRule->getMessage());
        }

        $this->expectException(LimitExceeded::class);

        $json->read('s', '{"version":1,"name":"x","condition":{"type":"person.tagged","operands":{"tags":[[["a"]]]}}}');
    }

    public function testBoundsTheDepthOfTheJson(): void
    {
        $json = new SegmentJson(AudienceConditions::types(tags: new MemoryFacts()), new Limits(maxDepth: 1));

        self::assertSame('x', $json->read('s', '{"version":1,"name":"x","condition":{"type":"person.tagged","operands":{"tags":["a"]}}}')->name);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The condition at "/condition" is nested deeper than 1 levels.');

        $json->read('s', '{"version":1,"name":"x","condition":{"type":"person.tagged","operands":{"tags":[[["a"]]]}}}');
    }
}
