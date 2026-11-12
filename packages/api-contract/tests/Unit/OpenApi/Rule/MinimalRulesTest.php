<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\OpenApi\Rule;

use Phil\ApiContract\OpenApi\Rule\Info;
use Phil\ApiContract\OpenApi\Rule\LocalReferences;
use Phil\ApiContract\OpenApi\Rule\OpenApiVersion;
use Phil\ApiContract\OpenApi\Rule\OperationIdUnique;
use Phil\ApiContract\OpenApi\Rule\OperationResponses;
use Phil\ApiContract\OpenApi\Rule\PathParameters;
use Phil\ApiContract\OpenApi\Rule\Paths;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OpenApiVersion::class)]
#[CoversClass(Info::class)]
#[CoversClass(Paths::class)]
#[CoversClass(OperationResponses::class)]
#[CoversClass(PathParameters::class)]
#[CoversClass(OperationIdUnique::class)]
#[CoversClass(LocalReferences::class)]
final class MinimalRulesTest extends RuleTestCase
{
    public function testTheRulesAreMinimalErrors(): void
    {
        foreach ([new OpenApiVersion(), new Info(), new Paths(), new OperationResponses(), new PathParameters(), new OperationIdUnique(), new LocalReferences()] as $rule) {
            self::assertSame([RuleLevel::Minimal, Severity::Error], [$rule->level(), $rule->severity()], $rule->id());
            self::assertSame($rule::ID, $rule->id());
        }

        self::assertSame(
            ['openapi-version', 'info', 'paths', 'operation-responses', 'path-parameters', 'operation-id-unique', 'local-references'],
            [OpenApiVersion::ID, Info::ID, Paths::ID, OperationResponses::ID, PathParameters::ID, OperationIdUnique::ID, LocalReferences::ID],
        );
        self::assertSame('The document declares OpenAPI 3.0, 3.1 or 3.2 in its `openapi` field.', new OpenApiVersion()->description());
        self::assertSame('The document has an `info` object with a non-empty `title` and `version`.', new Info()->description());
        self::assertSame('The document has a `paths` object whose every key is a path starting with `/`, holding a path item.', new Paths()->description());
        self::assertSame('Every operation has a non-empty `responses` object.', new OperationResponses()->description());
        self::assertSame('Every `{name}` of a path template is a required path parameter of each of its operations, and every path parameter appears in the template.', new PathParameters()->description());
        self::assertSame('No two operations share an `operationId`.', new OperationIdUnique()->description());
        self::assertSame('Every local reference (`$ref` starting with `#`) points at a value of the document.', new LocalReferences()->description());
    }

    public function testTheVersionIsAnOpenApiThree(): void
    {
        foreach (['3.0.0', '3.0.4', '3.1.1', '3.2.0', '3.1.10'] as $version) {
            self::assertSame([], $this->findings(new OpenApiVersion(), ['openapi' => $version]), $version);
        }

        $finding = [['/openapi', 'The document declares no OpenAPI version 3.0, 3.1 or 3.2: "openapi": "3.1.0".']];

        foreach ([[], ['swagger' => '2.0'], ['openapi' => 3.1], ['openapi' => '3.1'], ['openapi' => '3.3.0'], ['openapi' => '4.0.0'], ['openapi' => '3.1.0-rc1'], ['openapi' => "3.1.0\n"], ['openapi' => 'v3.1.0']] as $document) {
            self::assertSame($finding, $this->findings(new OpenApiVersion(), $document), json_encode($document, \JSON_THROW_ON_ERROR));
        }
    }

    public function testTheInfoNamesTheApiAndTheDocument(): void
    {
        self::assertSame([], $this->findings(new Info(), ['info' => ['title' => 'Shop', 'version' => '2026-11-01']]));
        self::assertSame([['/info', 'The document has no info object.']], $this->findings(new Info(), []));
        self::assertSame([['/info', 'The document has no info object.']], $this->findings(new Info(), ['info' => 'Shop']));
        self::assertSame(
            [['/info/title', 'The info object has no title.'], ['/info/version', 'The info object has no version.']],
            $this->findings(new Info(), ['info' => ['title' => ' ', 'version' => 1]]),
        );
        self::assertSame([['/info/version', 'The info object has no version.']], $this->findings(new Info(), ['info' => ['title' => 'Shop', 'version' => '']]));
        self::assertSame([['/info/title', 'The info object has no title.']], $this->findings(new Info(), ['info' => ['version' => '1']]));
    }

    public function testThePathsArePathTemplates(): void
    {
        self::assertSame([], $this->findings(new Paths(), ['paths' => ['/orders' => [], '/orders/{id}' => ['get' => []]]]));
        self::assertSame([], $this->findings(new Paths(), ['paths' => []]), 'An API without paths yet is still an object of paths.');
        self::assertSame([['/paths', 'The document has no paths object.']], $this->findings(new Paths(), []));
        self::assertSame([['/paths', 'The document has no paths object.']], $this->findings(new Paths(), ['paths' => 'none']));
        self::assertSame([['/paths', 'The document has no paths object.']], $this->findings(new Paths(), ['paths' => [['get' => []]]]));
        self::assertSame(
            [['/paths/orders', 'The path "orders" does not start with "/".'], ['/paths/~1items', 'The path "/items" holds no path item object.'], ['/paths/42', 'The path "42" does not start with "/".']],
            $this->findings(new Paths(), ['paths' => ['orders' => [], '/items' => 'get', '/fine' => [], 42 => []]]),
        );
    }

    public function testEveryOperationHasResponses(): void
    {
        self::assertSame([], $this->findings(new OperationResponses(), ['paths' => ['/a' => ['get' => ['responses' => ['200' => []]]]]]));
        self::assertSame(
            [['/paths/~1a/get/responses', 'GET /a declares no response.'], ['/paths/~1a/post/responses', 'POST /a declares no response.'], ['/paths/~1b/delete/responses', 'DELETE /b declares no response.']],
            $this->findings(new OperationResponses(), ['paths' => ['/a' => ['get' => [], 'post' => ['responses' => []]], '/b' => ['delete' => ['responses' => 'none']]]]),
        );
    }

    public function testEveryTemplateVariableIsARequiredPathParameter(): void
    {
        $rule = new PathParameters();

        self::assertSame([], $this->findings($rule, ['paths' => [
            '/orders/{id}/lines/{line}' => [
                'parameters' => [['$ref' => '#/components/parameters/Id']],
                'get' => ['parameters' => [['name' => 'line', 'in' => 'path', 'required' => true], ['name' => 'page', 'in' => 'query']]],
            ],
            '/plain' => ['get' => []],
        ], 'components' => ['parameters' => ['Id' => ['name' => 'id', 'in' => 'path', 'required' => true]]]]));

        self::assertSame([
            ['/paths/~1orders~1{id}/get/parameters/0', 'The path parameter "other" is not in the template /orders/{id}.'],
            ['/paths/~1orders~1{id}/get', 'GET /orders/{id} does not declare the path parameter "id".'],
            ['/paths/~1orders~1{id}/put/parameters/0/required', 'The path parameter "id" is not marked required.'],
            ['/paths/~1orders~1{id}/post/parameters/0/required', 'The path parameter "id" is not marked required.'],
            ['/paths/~1a~1{x}~1{y}/get', 'GET /a/{x}/{y} does not declare the path parameter "x".'],
            ['/paths/~1a~1{x}~1{y}/get', 'GET /a/{x}/{y} does not declare the path parameter "y".'],
        ], $this->findings($rule, ['paths' => [
            '/orders/{id}' => [
                'get' => ['parameters' => [['name' => 'other', 'in' => 'path', 'required' => true]]],
                'post' => ['parameters' => [['name' => 'id', 'in' => 'path']]],
                'put' => ['parameters' => [['name' => 'id', 'in' => 'path', 'required' => 'true']]],
            ],
            '/a/{x}/{y}' => ['get' => ['parameters' => [['name' => 'x', 'in' => 'query']]]],
        ]]));
    }

    public function testAnOperationIdIsUsedOnce(): void
    {
        self::assertSame([], $this->findings(new OperationIdUnique(), ['paths' => ['/a' => ['get' => ['operationId' => 'a'], 'post' => ['operationId' => 'b'], 'put' => ['operationId' => 42], 'patch' => ['operationId' => 42]]]]));
        self::assertSame(
            [['/paths/~1b/get/operationId', 'The operationId "a" is already used at /paths/~1a/get.'], ['/paths/~1b/put/operationId', 'The operationId "a" is already used at /paths/~1a/get.']],
            $this->findings(new OperationIdUnique(), ['paths' => ['/a' => ['get' => ['operationId' => 'a']], '/b' => ['get' => ['operationId' => 'a'], 'post' => [], 'put' => ['operationId' => 'a']]]]),
        );
    }

    public function testEveryLocalReferenceResolves(): void
    {
        self::assertSame([], $this->findings(new LocalReferences(), [
            'paths' => ['/a' => ['get' => ['responses' => ['200' => ['$ref' => '#/components/responses/Ok']]]]],
            'components' => ['responses' => ['Ok' => ['description' => 'ok', 'content' => ['application/json' => ['schema' => ['$ref' => 'schemas.json#/Order']]]]]],
        ]));
        self::assertSame([
            ['/paths/~1a/get/responses/200/$ref', 'The reference "#/components/responses/Gone" points at nothing in the document.'],
            ['/components/schemas/A/properties/b/$ref', 'The reference "#/components/schemas/B" points at nothing in the document.'],
        ], $this->findings(new LocalReferences(), [
            'paths' => ['/a' => ['get' => ['responses' => ['200' => ['$ref' => '#/components/responses/Gone']], 'x-tool' => ['$ref' => '#/nowhere']]]],
            'components' => ['schemas' => ['A' => ['properties' => ['b' => ['$ref' => '#/components/schemas/B'], 'c' => ['$ref' => 42]], 'example' => ['$ref' => '#/nowhere']]]],
        ]));
    }
}
