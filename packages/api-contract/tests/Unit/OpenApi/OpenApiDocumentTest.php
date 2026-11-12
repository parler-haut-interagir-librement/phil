<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\OpenApi;

use Phil\ApiContract\Exception\UnreadableDocument;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Phil\ApiContract\OpenApi\Operation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenApiDocument::class)]
#[CoversClass(Operation::class)]
#[CoversClass(UnreadableDocument::class)]
final class OpenApiDocumentTest extends TestCase
{
    public function testAJsonObjectIsRead(): void
    {
        $document = OpenApiDocument::fromJson('{"openapi": "3.1.0", "big": 123456789012345678901234567890}');

        self::assertSame(['openapi' => '3.1.0', 'big' => '123456789012345678901234567890'], $document->data);
        self::assertSame([], OpenApiDocument::fromJson('{}')->data);
        self::assertSame([], OpenApiDocument::fromArray([])->data);
    }

    public function testWhatIsNotAJsonObjectIsUnreadable(): void
    {
        foreach ([
            'not json' => 'The document is not JSON: Syntax error',
            '"text"' => 'The document is not a JSON object.',
            '[1, 2]' => 'The document is a list, not an object.',
            str_repeat('[', 128).str_repeat(']', 128) => 'The document is not JSON: Maximum stack depth exceeded',
        ] as $json => $message) {
            try {
                OpenApiDocument::fromJson($json);
                self::fail($message);
            } catch (UnreadableDocument $unreadableDocument) {
                // PHP's own words follow, which its versions word differently: "Syntax error near location 1:1".
                self::assertStringStartsWith($message, $unreadableDocument->getMessage());
                self::assertStringEndsWith('.', $unreadableDocument->getMessage());
            }
        }
    }

    public function testTheTextIsBoundedInBytes(): void
    {
        $exact = '{"a":"'.str_repeat('x', OpenApiDocument::MAX_BYTES - 8).'"}';
        self::assertSame(OpenApiDocument::MAX_BYTES, \strlen($exact));
        self::assertArrayHasKey('a', OpenApiDocument::fromJson($exact)->data);

        $this->expectExceptionObject(UnreadableDocument::because('The document is larger than 8388608 bytes.'));
        OpenApiDocument::fromJson($exact.' ');
    }

    public function testAnArrayIsBoundedInDepthAndValues(): void
    {
        $deep = ['leaf'];

        for ($level = 1; $level < OpenApiDocument::MAX_DEPTH; ++$level) {
            $deep = ['a' => $deep];
        }

        self::assertSame(128, OpenApiDocument::MAX_DEPTH);
        self::assertArrayHasKey('a', OpenApiDocument::fromArray($deep)->data);

        try {
            OpenApiDocument::fromArray(['a' => $deep]);
            self::fail('Too deep.');
        } catch (UnreadableDocument $unreadableDocument) {
            self::assertSame('The document is deeper than 128 levels.', $unreadableDocument->getMessage());
        }

        $wide = ['a' => array_fill(0, OpenApiDocument::MAX_VALUES - 1, 1)];
        self::assertCount(1, OpenApiDocument::fromArray($wide)->data);
        $wide['b'] = 1;

        $this->expectExceptionObject(UnreadableDocument::because('The document holds more than 500000 values.'));
        OpenApiDocument::fromArray($wide);
    }

    public function testValuesAreCountedAtEveryDepth(): void
    {
        $nested = ['a' => ['b' => array_fill(0, OpenApiDocument::MAX_VALUES - 1, 1)]];

        $this->expectExceptionObject(UnreadableDocument::because('The document holds more than 500000 values.'));
        OpenApiDocument::fromArray($nested);
    }

    public function testAPointerEscapesItsTokens(): void
    {
        self::assertSame('/paths/~1orders~1{id}/get', OpenApiDocument::pointer('paths', '/orders/{id}', 'get'));
        self::assertSame('/a~0b/200', OpenApiDocument::pointer('a~b', 200));
        self::assertSame('', OpenApiDocument::pointer());
    }

    public function testALocalReferenceIsFollowed(): void
    {
        $document = OpenApiDocument::fromArray([
            'components' => ['schemas' => ['a/b' => ['type' => 'string'], 'm~n' => 1, 'nil' => null, 'é t' => 2]],
            'list' => ['zero', 'one'],
        ]);

        self::assertSame(['type' => 'string'], $document->at('#/components/schemas/a~1b'));
        self::assertSame(1, $document->at('#/components/schemas/m~0n'));
        self::assertSame(2, $document->at('#/components/schemas/%C3%A9%20t'));
        self::assertSame('one', $document->at('#/list/1'));
        self::assertSame($document->data, $document->at('#'));
        self::assertNull($document->at('#/components/schemas/nil'));
        self::assertNull($document->at('#/components/schemas/missing'));
        self::assertNull($document->at('#/list/1/deeper'));
        self::assertNull($document->at('#components'));
        self::assertNull($document->at('other.json#/components'));
        self::assertNull($document->at(''));
    }

    public function testAChainOfReferencesIsResolvedUntilItBreaksOrLoops(): void
    {
        $chain = ['end' => ['type' => 'string']];

        for ($hop = 1; $hop <= 16; ++$hop) {
            $chain['r'.$hop] = ['$ref' => '#/r'.($hop - 1)];
        }

        $chain['r0'] = ['$ref' => '#/end'];
        $chain['loop'] = ['$ref' => '#/loop'];
        $chain['broken'] = ['$ref' => '#/nowhere'];
        $chain['typed'] = ['$ref' => 42];
        $document = OpenApiDocument::fromArray($chain);

        self::assertSame(['type' => 'string'], $document->resolve(['$ref' => '#/r14']), 'Sixteen hops are followed.');
        self::assertNull($document->resolve(['$ref' => '#/r15']), 'Seventeen hops are taken for a loop.');
        self::assertNull($document->resolve(['$ref' => '#/loop']));
        self::assertNull($document->resolve(['$ref' => '#/broken']));
        self::assertNull($document->resolve(['$ref' => '#/typed']));
        self::assertSame(['plain'], $document->resolve(['plain']));
        self::assertSame('text', $document->resolve('text'));
    }

    public function testTheOperationsAreListedPathByPath(): void
    {
        $document = OpenApiDocument::fromArray(['paths' => [
            '/b' => ['post' => ['operationId' => 'p'], 'get' => ['operationId' => 'g'], 'summary' => 'x', 'parameters' => []],
            '/a' => ['query' => ['operationId' => 'q'], 'trace' => 'not an object'],
            '/c' => 'not an object',
        ]]);

        self::assertSame(
            [['/b', 'get', '/paths/~1b/get', 'g'], ['/b', 'post', '/paths/~1b/post', 'p'], ['/a', 'query', '/paths/~1a/query', 'q']],
            array_map(static fn (Operation $operation): array => [$operation->path, $operation->method, $operation->pointer, $operation->operation['operationId']], $document->operations()),
        );
        self::assertSame('x', $document->operations()[0]->pathItem['summary']);
        self::assertSame(['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace', 'query'], OpenApiDocument::METHODS);
        self::assertSame([], OpenApiDocument::fromArray(['paths' => 'none'])->operations());
        self::assertSame([], OpenApiDocument::fromArray([])->operations());
    }

    public function testAPathKeyThatPhpReadsAsAnIntegerStaysAPath(): void
    {
        $operations = OpenApiDocument::fromJson('{"paths": {"42": {"get": {}}}}')->operations();

        self::assertSame('42', $operations[0]->path);
        self::assertSame('/paths/42/get', $operations[0]->pointer);
    }

    public function testTheParametersOfAnOperationOverrideThoseOfItsPath(): void
    {
        $document = OpenApiDocument::fromArray([
            'paths' => ['/orders/{id}' => [
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'description' => 'path level'],
                    ['$ref' => '#/components/parameters/Page'],
                    ['name' => 'id', 'in' => 'query'],
                    ['$ref' => '#/nowhere'],
                    ['name' => 42, 'in' => 'path'],
                    ['name' => 'x', 'in' => ['path']],
                ],
                'get' => ['parameters' => [['name' => 'id', 'in' => 'path', 'description' => 'operation level'], 'stray']],
                'put' => ['parameters' => 'not a list'],
            ]],
            'components' => ['parameters' => ['Page' => ['name' => 'page', 'in' => 'query']]],
        ]);
        [$get, $put] = $document->operations();

        self::assertSame([
            '/paths/~1orders~1{id}/parameters/1' => ['name' => 'page', 'in' => 'query'],
            '/paths/~1orders~1{id}/parameters/2' => ['name' => 'id', 'in' => 'query'],
            '/paths/~1orders~1{id}/get/parameters/0' => ['name' => 'id', 'in' => 'path', 'description' => 'operation level'],
        ], $get->parameters($document));
        self::assertSame(['/paths/~1orders~1{id}/parameters/0', '/paths/~1orders~1{id}/parameters/1', '/paths/~1orders~1{id}/parameters/2'], array_keys($put->parameters($document)));
        self::assertSame([], OpenApiDocument::fromArray(['paths' => ['/a' => ['get' => []]]])->operations()[0]->parameters($document));
    }

    public function testTheResponsesOfAnOperationAreResolved(): void
    {
        $document = OpenApiDocument::fromJson('{"paths": {"/a": {"get": {"responses": {"200": {"description": "ok"}, "4XX": {"$ref": "#/components/responses/P"}, "default": {"$ref": "#/nowhere"}, "500": "text"}}, "put": {}}}, "components": {"responses": {"P": {"description": "p"}}}}');
        [$get, $put] = $document->operations();

        self::assertSame([200 => ['description' => 'ok'], '4XX' => ['description' => 'p']], $get->responses($document));
        self::assertSame([], $put->responses($document));
    }

    public function testTheNodesAreWalkedWithoutExamplesNorExtensions(): void
    {
        $document = OpenApiDocument::fromArray([
            'a' => ['b' => [1], 'example' => ['skipped' => []], 'examples' => ['skipped' => []], 'x-tool' => ['skipped' => []], 'x' => 'scalar'],
            'properties' => ['example' => ['type' => 'string'], 'x-name' => ['type' => 'string'], 'properties' => ['example' => ['kept' => []]]],
            7 => [],
        ]);

        self::assertSame([
            '',
            '/a',
            '/a/b',
            '/properties',
            '/properties/example',
            '/properties/x-name',
            '/properties/properties',
            '/7',
        ], array_keys(iterator_to_array($document->nodes())));
        self::assertSame([1], iterator_to_array($document->nodes())['/a/b']);
    }
}
