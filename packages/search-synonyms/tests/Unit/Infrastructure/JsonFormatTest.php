<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Tests\Unit\Infrastructure;

use Phil\SearchSynonyms\Domain\Exception\InvalidSynonyms;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymSet;
use Phil\SearchSynonyms\Infrastructure\Format\JsonSynonyms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonSynonyms::class)]
final class JsonFormatTest extends TestCase
{
    public function testADocumentIsReadAndWrittenBack(): void
    {
        $list = new JsonSynonyms()->read('{"language": "fr-CA", "rules": [{"synonyms": ["JDR", "jeu de rôle"]}, {"from": ["donjon"], "to": ["donjon", "dungeon/cachot"]}]}');

        self::assertSame('fr', $list->language);
        self::assertSame([[['jdr', 'jeu de rôle'], true], [['donjon'], false]], array_map(static fn (SynonymSet $set): array => [$set->from, $set->equivalent], $list->sets));
        self::assertSame(['donjon', 'dungeon/cachot'], $list->sets[1]->to);

        $json = new JsonSynonyms()->write($list);
        self::assertSame(<<<'JSON'
            {
                "language": "fr",
                "rules": [
                    {
                        "synonyms": [
                            "jdr",
                            "jeu de rôle"
                        ]
                    },
                    {
                        "from": [
                            "donjon"
                        ],
                        "to": [
                            "donjon",
                            "dungeon/cachot"
                        ]
                    }
                ]
            }

            JSON, $json);
        self::assertEquals($list, new JsonSynonyms()->read($json));
        self::assertEquals(SynonymList::of('en'), new JsonSynonyms()->read('{"language":"en","rules":[]}'));
    }

    public function testADocumentNotShapedSoIsRefused(): void
    {
        foreach ([
            ['{', 'A synonyms document is JSON: Syntax error'],
            ['[[[[[["deep"]]]]]]', 'A synonyms document is JSON: Maximum stack depth exceeded'],
            ['"x"', 'A synonyms document is an object with "language", a tag, and "rules", a list.'],
            ['{"rules": []}', 'A synonyms document is an object with "language", a tag, and "rules", a list.'],
            ['{"language": 1, "rules": []}', 'A synonyms document is an object with "language", a tag, and "rules", a list.'],
            ['{"language": "fr"}', 'A synonyms document is an object with "language", a tag, and "rules", a list.'],
            ['{"language": "fr", "rules": {"a": {}}}', 'A synonyms document is an object with "language", a tag, and "rules", a list.'],
            ['{"language": "français", "rules": []}', 'A language is a tag such as "fr" or "fr-CA"; "français" is not.'],
            ['{"language": "fr", "rules": ["x"]}', 'Rule 1: A rule is an object.'],
            ['{"language": "fr", "rules": [{"synonyms": ["a", "b"]}, {"from": ["a"]}]}', 'Rule 2: A rule has "synonyms", or "from" and "to".'],
            ['{"language": "fr", "rules": [{"to": ["a"]}]}', 'Rule 1: A rule has "synonyms", or "from" and "to".'],
            ['{"language": "fr", "rules": [{"synonyms": "a"}]}', 'Rule 1: Phrases are a list of at most 64 strings.'],
            ['{"language": "fr", "rules": [{"synonyms": {"a": "b"}}]}', 'Rule 1: Phrases are a list of at most 64 strings.'],
            ['{"language": "fr", "rules": [{"synonyms": ["a", 2]}]}', 'Rule 1: Phrases are a list of at most 64 strings.'],
            ['{"language": "fr", "rules": [{"from": ["a"], "to": "b"}]}', 'Rule 1: Phrases are a list of at most 64 strings.'],
            ['{"language": "fr", "rules": [{"synonyms": '.json_encode(array_map(static fn (int $n): string => 'w'.$n, range(0, 64))).'}]}', 'Rule 1: Phrases are a list of at most 64 strings.'],
            ['{"language": "fr", "rules": [{"synonyms": ["a"]}]}', 'Rule 1: Equivalent synonyms are two phrases or more.'],
            ['{"language": "fr", "rules": ['.implode(',', array_fill(0, 10_001, '{}')).']}', 'A language holds 10000 rules at most; 10001 given.'],
            [str_repeat(' ', 1_048_577), 'A synonyms document is 1048576 bytes at most.'],
        ] as [$json, $message]) {
            try {
                new JsonSynonyms()->read($json);
                self::fail($message);
            } catch (InvalidSynonyms $invalidSynonyms) {
                // The reason PHP gives a JSON error may say where it is, from PHP 8.6 on.
                str_starts_with($message, 'A synonyms document is JSON: ') ? self::assertStringStartsWith($message, $invalidSynonyms->getMessage()) : self::assertSame($message, $invalidSynonyms->getMessage());
            }
        }

        self::assertCount(64, new JsonSynonyms()->read('{"language": "fr", "rules": [{"synonyms": '.json_encode(array_map(static fn (int $n): string => 'w'.$n, range(1, 64))).'}]}')->sets[0]->from);
        self::assertSame('fr', new JsonSynonyms()->read(str_pad('{"language":"fr","rules":[]}', 1_048_576))->language);
    }

    public function testTheDepthOfADocumentIsBounded(): void
    {
        $this->expectException(InvalidSynonyms::class);
        $this->expectExceptionMessageIsOrContains('A synonyms document is JSON: Maximum stack depth exceeded.');
        new JsonSynonyms()->read('{"language": "fr", "rules": [{"synonyms": [["a"]]}]}');
    }

    public function testASyntaxErrorEndsWithAFullStop(): void
    {
        $this->expectException(InvalidSynonyms::class);
        $this->expectExceptionMessageIsOrContains('A synonyms document is JSON: Syntax error.');
        new JsonSynonyms()->read('{');
    }

    public function testADocumentOfExactlyTheLimitOfRulesIsRead(): void
    {
        $rules = [];

        for ($i = 0; $i < SynonymList::MAX_SETS; ++$i) {
            $rules[] = ['synonyms' => ['a'.$i, 'b'.$i]];
        }

        $list = new JsonSynonyms()->read(json_encode(['language' => 'fr', 'rules' => $rules], \JSON_THROW_ON_ERROR));

        self::assertCount(SynonymList::MAX_SETS, $list->sets);
    }
}
