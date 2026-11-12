<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Domain;

use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\ValueObject\DraftContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DraftContent::class)]
final class DraftContentTest extends TestCase
{
    public function testAContentIsAVersionAndTheValuesOfEachName(): void
    {
        $content = DraftContent::of('v1', ['title' => ['Hello'], 'tags[]' => [], '7' => ['seven']]);

        self::assertSame('v1', $content->version);
        self::assertSame(['title' => ['Hello'], 'tags[]' => [], '7' => ['seven']], $content->fields);
        self::assertSame('{"version":"v1","fields":{"title":["Hello"],"tags[]":[],"7":["seven"]}}', $content->toJson());
    }

    public function testTheJsonKeepsTextAsItIsAndAnEmptyContentAsAnObject(): void
    {
        self::assertSame('{"version":"v","fields":{"a/b":["é"]}}', DraftContent::of('v', ['a/b' => ['é']])->toJson());
        self::assertSame('{"version":"v","fields":{}}', DraftContent::of('v', [])->toJson());
    }

    public function testAContentIsReadBackFromItsJson(): void
    {
        $content = DraftContent::fromJson('{"version":"v1","fields":{"title":["Hello"],"1":["one"]}}');

        self::assertSame('v1', $content->version);
        self::assertSame(['title' => ['Hello'], '1' => ['one']], $content->fields);
        self::assertSame(DraftContent::of('v', ['a' => ['b']])->toJson(), DraftContent::fromJson(DraftContent::of('v', ['a' => ['b']])->toJson())->toJson());
    }

    public function testTheLimitsAreInclusive(): void
    {
        $names = array_fill_keys(array_map(static fn (int $i): string => 'n'.$i, range(1, DraftContent::MAX_NAMES)), []);
        self::assertCount(1000, DraftContent::of('v', $names)->fields);
        self::assertCount(1000, DraftContent::of('v', ['a' => array_fill(0, DraftContent::MAX_VALUES, 'x')])->fields['a']);
        self::assertArrayHasKey(str_repeat('é', 255), DraftContent::of('v', [str_repeat('é', 255) => []])->fields);
        self::assertSame(str_repeat('a', 64), DraftContent::of(str_repeat('a', 64), [])->version);
    }

    /**
     * @return iterable<string, array{mixed, mixed, string}>
     */
    public static function invalid(): iterable
    {
        $version = 'The version of a draft is 1 to 64 letters, digits, "-" or "_".';
        $fields = 'The fields of a draft are an object of at most 1000 names.';

        yield 'no version' => [null, [], $version];
        yield 'an empty version' => ['', [], $version];
        yield 'a version too long' => [str_repeat('a', 65), [], $version];
        yield 'a version with a dot' => ['v.1', [], $version];
        yield 'a version with a new line' => ["v1\n", [], $version];
        yield 'a number as version' => [1, [], $version];
        yield 'no fields' => ['v', null, $fields];
        yield 'fields as text' => ['v', 'a', $fields];
        yield 'too many names' => ['v', array_fill_keys(array_map(static fn (int $i): string => 'n'.$i, range(0, 1000)), []), $fields];
        yield 'an empty name' => ['v', ['' => []], 'The name of a field is 1 to 255 characters.'];
        yield 'a name too long' => ['v', [str_repeat('é', 256) => []], 'The name of a field is 1 to 255 characters.'];
        yield 'values as text' => ['v', ['a' => 'b'], 'The values of "a" are a list of at most 1000 texts.'];
        yield 'values as a map' => ['v', ['a' => ['x' => 'b']], 'The values of "a" are a list of at most 1000 texts.'];
        yield 'too many values' => ['v', ['a' => array_fill(0, 1001, 'x')], 'The values of "a" are a list of at most 1000 texts.'];
        yield 'a number as value' => ['v', ['a' => ['b', 1]], 'The values of "a" are texts.'];
        yield 'a list as value' => ['v', ['a' => [['b']]], 'The values of "a" are texts.'];
    }

    #[DataProvider('invalid')]
    public function testAnythingElseIsRefused(mixed $version, mixed $fields, string $message): void
    {
        $this->expectException(InvalidDraft::class);
        $this->expectExceptionMessageIsOrContains($message);

        DraftContent::of($version, $fields);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidJson(): iterable
    {
        yield 'not json' => ['{', 'A stored draft is a JSON object.'];
        yield 'a text' => ['"x"', 'A stored draft is a JSON object.'];
        yield 'too deep' => ['{"version":"v","fields":{"a":[["b"]]},"x":[[[[1]]]]}', 'A stored draft is a JSON object.'];
        yield 'no version' => ['{"fields":{}}', 'The version of a draft is 1 to 64 letters, digits, "-" or "_".'];
        yield 'no fields' => ['{"version":"v"}', 'The fields of a draft are an object of at most 1000 names.'];
        yield 'a list as value, beyond the depth of a draft' => ['{"version":"v","fields":{"a":[["b"]]}}', 'A stored draft is a JSON object.'];
    }

    #[DataProvider('invalidJson')]
    public function testAStoredTextThatIsNoDraftIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidDraft::class);
        $this->expectExceptionMessageIsOrContains($message);

        DraftContent::fromJson($json);
    }
}
