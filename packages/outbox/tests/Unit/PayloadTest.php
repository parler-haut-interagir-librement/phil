<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit;

use JsonException;
use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\Payload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Payload::class)]
#[CoversClass(InvalidRecord::class)]
final class PayloadTest extends TestCase
{
    public function testAPayloadHoldsIdentifiersByName(): void
    {
        $payload = Payload::of(['set' => 'é/42', 'holder' => 7, 'first' => false, 'note' => null, 'z9_Ab' => 'x']);

        self::assertSame(['set' => 'é/42', 'holder' => 7, 'first' => false, 'note' => null, 'z9_Ab' => 'x'], $payload->values);
        self::assertSame('{"set":"é/42","holder":7,"first":false,"note":null,"z9_Ab":"x"}', $payload->toJson());
        self::assertTrue($payload->has('note'));
        self::assertFalse($payload->has('missing'));
        self::assertSame('é/42', $payload->get('set'));
        self::assertSame(7, $payload->get('holder'));
        self::assertNull($payload->get('missing'));
        self::assertSame('é/42', $payload->string('set'));
        self::assertSame(7, $payload->int('holder'));
    }

    public function testAnEmptyPayloadIsAnObject(): void
    {
        self::assertSame('{}', Payload::of([])->toJson());
        self::assertSame([], Payload::fromJson('{}')->values);
        self::assertSame([], Payload::fromJson('[]')->values);
    }

    public function testAPayloadIsReadBackFromItsJson(): void
    {
        $payload = Payload::of(['set' => 's', 'holder' => -3, 'first' => true]);

        self::assertSame($payload->values, Payload::fromJson($payload->toJson())->values);
        self::assertSame(['a' => 1], Payload::fromJson(" {\n \"a\" : 1 } ")->values);
    }

    public function testAStringOrAnIntegerThatIsNotThereIsRefused(): void
    {
        $payload = Payload::of(['set' => 's', 'holder' => 7]);

        try {
            $payload->string('holder');
            self::fail('An integer is not a string.');
        } catch (InvalidRecord $invalidRecord) {
            self::assertSame('The payload has no string "holder".', $invalidRecord->getMessage());
        }

        try {
            $payload->int('set');
            self::fail('A string is not an integer.');
        } catch (InvalidRecord $invalidRecord) {
            self::assertSame('The payload has no integer "set".', $invalidRecord->getMessage());
        }

        $this->expectException(InvalidRecord::class);
        $payload->int('missing');
    }

    public function testAMissingStringIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The payload has no string "missing".');

        Payload::of([])->string('missing');
    }

    public function testAPayloadHoldsAtMostSixteenValues(): void
    {
        $values = [];

        for ($index = 0; $index < 16; ++$index) {
            $values['v'.$index] = $index;
        }

        self::assertCount(16, Payload::of($values)->values);

        $values['v16'] = 16;
        $this->expectExceptionMessageIsOrContains('A payload holds at most 16 values, not 17.');

        Payload::of($values);
    }

    public function testAStringValueTakesAtMost255Bytes(): void
    {
        self::assertSame(str_repeat('é', 127).'a', Payload::of(['s' => str_repeat('é', 127).'a'])->string('s'));

        $this->expectExceptionMessageIsOrContains('The value of "s" is not valid UTF-8 of at most 255 bytes.');

        Payload::of(['s' => str_repeat('é', 128)]);
    }

    public function testAStringValueIsValidUtf8(): void
    {
        $this->expectExceptionMessageIsOrContains('The value of "s" is not valid UTF-8 of at most 255 bytes.');

        Payload::of(['s' => "\xC3"]);
    }

    public function testThePayloadTakesAtMost4096BytesOfJson(): void
    {
        $values = [];

        for ($index = 0; $index < 15; ++$index) {
            $values['v'.$index] = str_repeat('a', 255);
        }

        $values['last'] = str_repeat('a', 4096 - \strlen(Payload::of($values)->toJson()) - 10);
        self::assertSame(4096, \strlen(Payload::of($values)->toJson()));

        $values['last'] .= 'a';
        $this->expectExceptionMessageIsOrContains('A payload takes at most 4096 bytes of JSON.');

        Payload::of($values);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusedValues(): iterable
    {
        yield 'a float' => [['amount' => 1.5], 'The value of "amount" is a float: a payload holds strings, integers, booleans and null.'];
        yield 'a list' => [['ids' => [1, 2]], 'The value of "ids" is a array: a payload holds strings, integers, booleans and null.'];
        yield 'an object' => [['user' => new stdClass()], 'The value of "user" is a stdClass: a payload holds strings, integers, booleans and null.'];
        yield 'a numeric name' => [[0 => 'a'], '"0" is not a payload name: 1 to 64 letters, digits or "_", starting with a lowercase letter.'];
        yield 'a capital first' => [['Set' => 'a'], '"Set" is not a payload name: 1 to 64 letters, digits or "_", starting with a lowercase letter.'];
        yield 'a dash' => [['set-id' => 'a'], '"set-id" is not a payload name: 1 to 64 letters, digits or "_", starting with a lowercase letter.'];
        yield 'an empty name' => [['' => 'a'], '"" is not a payload name: 1 to 64 letters, digits or "_", starting with a lowercase letter.'];
        yield 'a newline after the name' => [["set\n" => 'a'], "\"set\n\" is not a payload name: 1 to 64 letters, digits or \"_\", starting with a lowercase letter."];
        yield 'a long name' => [['a'.str_repeat('b', 64) => 'a'], '"a'.str_repeat('b', 64).'" is not a payload name: 1 to 64 letters, digits or "_", starting with a lowercase letter.'];
        yield 'a very long name' => [['a'.str_repeat('b', 80) => 'a'], '"a'.str_repeat('b', 68).'…" is not a payload name: 1 to 64 letters, digits or "_", starting with a lowercase letter.'];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    #[DataProvider('refusedValues')]
    public function testAValueOrANameOutOfBoundsIsRefused(array $values, string $message): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        Payload::of($values);
    }

    public function testTheLongestNameIsAccepted(): void
    {
        self::assertTrue(Payload::of(['a'.str_repeat('b', 63) => 1])->has('a'.str_repeat('b', 63)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedJson(): iterable
    {
        yield 'not JSON' => ['{', 'A payload is a JSON object of identifiers, one level deep.'];
        yield 'a nested object' => ['{"a":{"b":1}}', 'A payload is a JSON object of identifiers, one level deep.'];
        yield 'a string' => ['"a"', 'A payload is a JSON object of identifiers, one level deep.'];
        yield 'a number' => ['12', 'A payload is a JSON object of identifiers, one level deep.'];
        yield 'a list' => ['[1]', '"0" is not a payload name: 1 to 64 letters, digits or "_", starting with a lowercase letter.'];
        yield 'a large integer' => ['{"a":99999999999999999999}', 'The value of "a" is a float: a payload holds strings, integers, booleans and null.'];
        yield 'too long' => ['{"a":"'.str_repeat(' ', 4090).'"}', 'A payload takes at most 4096 bytes of JSON.'];
    }

    #[DataProvider('refusedJson')]
    public function testJsonThatIsNotAPayloadIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        Payload::fromJson($json);
    }

    public function testTheJsonErrorIsKeptAsThePreviousException(): void
    {
        try {
            Payload::fromJson('{');
            self::fail('Text that is not JSON is read.');
        } catch (InvalidRecord $invalidRecord) {
            self::assertInstanceOf(JsonException::class, $invalidRecord->getPrevious());
        }
    }

    public function testJsonOfExactly4096BytesIsRead(): void
    {
        $json = '{"a":1'.str_repeat(' ', 4096 - 7).'}';

        self::assertSame(4096, \strlen($json));
        self::assertSame(['a' => 1], Payload::fromJson($json)->values);
    }
}
