<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\OpenApi\Rule;

use Phil\ApiContract\OpenApi\Rule\DeprecationSunset;
use Phil\ApiContract\OpenApi\Rule\JsonContent;
use Phil\ApiContract\OpenApi\Rule\NoVersionInPath;
use Phil\ApiContract\OpenApi\Rule\OperationId;
use Phil\ApiContract\OpenApi\Rule\ProblemDetails;
use Phil\ApiContract\OpenApi\Rule\SuccessResponse;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NoVersionInPath::class)]
#[CoversClass(SuccessResponse::class)]
#[CoversClass(JsonContent::class)]
#[CoversClass(ProblemDetails::class)]
#[CoversClass(DeprecationSunset::class)]
#[CoversClass(OperationId::class)]
final class RecommendedRulesTest extends RuleTestCase
{
    public function testTheRulesAreRecommended(): void
    {
        foreach ([[new NoVersionInPath(), Severity::Error], [new SuccessResponse(), Severity::Error], [new JsonContent(), Severity::Warning], [new ProblemDetails(), Severity::Warning], [new DeprecationSunset(), Severity::Warning], [new OperationId(), Severity::Warning]] as [$rule, $severity]) {
            self::assertSame([RuleLevel::Recommended, $severity], [$rule->level(), $rule->severity()], $rule->id());
            self::assertSame($rule::ID, $rule->id());
        }

        self::assertSame(
            ['no-version-in-path', 'success-response', 'json-content', 'problem-details', 'deprecation-sunset', 'operation-id'],
            [NoVersionInPath::ID, SuccessResponse::ID, JsonContent::ID, ProblemDetails::ID, DeprecationSunset::ID, OperationId::ID],
        );
        self::assertSame('No path and no server URL carries a version segment such as `v1` or `v2.1`.', new NoVersionInPath()->description());
        self::assertSame('Every operation declares a `2XX` or `3XX` response.', new SuccessResponse()->description());
        self::assertSame('Every request body and response body of an operation is JSON: `application/json` or a `+json` media type.', new JsonContent()->description());
        self::assertSame('Every error response (`4XX`, `5XX` or `default`) with a body offers it as `application/problem+json`.', new ProblemDetails()->description());
        self::assertSame('Every operation marked `deprecated` documents a `Sunset` header on one of its responses.', new DeprecationSunset()->description());
        self::assertSame('Every operation has a non-empty `operationId`.', new OperationId()->description());
    }

    public function testNoPathNorServerCarriesAVersion(): void
    {
        self::assertSame([], $this->findings(new NoVersionInPath(), [
            'servers' => [['url' => 'https://api.example.com/shop'], ['url' => '/{basePath}'], 'stray', ['url' => 42], ['description' => 'no url']],
            'paths' => ['/orders/{id}' => [], '/v' => [], '/vx1' => [], '/version/1' => [], '/v1a' => [], '/vv1' => [], '/v1.' => []],
        ]));
        self::assertSame([], $this->findings(new NoVersionInPath(), ['servers' => 'none', 'paths' => 'none']));
        self::assertSame([
            ['/servers/0/url', 'The server URL "https://api.example.com/v1" carries a version segment.'],
            ['/servers/2/url', 'The server URL "https://api.example.com/V2.1/" carries a version segment.'],
            ['/paths/~1v2~1orders', 'The path "/v2/orders" carries a version segment.'],
            ['/paths/~1orders~1v10', 'The path "/orders/v10" carries a version segment.'],
            ['/paths/~1api~1v3.0.1~1items', 'The path "/api/v3.0.1/items" carries a version segment.'],
        ], $this->findings(new NoVersionInPath(), [
            'servers' => [['url' => 'https://api.example.com/v1'], ['url' => 'https://api.example.com'], ['url' => 'https://api.example.com/V2.1/']],
            'paths' => ['/v2/orders' => [], '/orders' => [], '/orders/v10' => [], '/api/v3.0.1/items' => []],
        ]));
    }

    public function testEveryOperationHasASuccess(): void
    {
        foreach (['200', '201', '204', '2XX', '2xx', '301', '3XX', 200, 304] as $code) {
            self::assertSame([], $this->findings(new SuccessResponse(), ['paths' => ['/a' => ['get' => ['responses' => [$code => []]]]]]), (string) $code);
        }

        foreach ([[], ['responses' => 'none'], ['responses' => ['404' => [], 'default' => [], '1XX' => [], '200x' => [], '20' => [], 'x200' => [], '2000' => [], '4XX' => []]]] as $operation) {
            self::assertSame([['/paths/~1a/get/responses', 'GET /a declares no success response.']], $this->findings(new SuccessResponse(), ['paths' => ['/a' => ['get' => $operation]]]));
        }
    }

    public function testBodiesAreJson(): void
    {
        foreach (['application/json', 'Application/JSON', 'application/json; charset=utf-8', 'application/problem+json', 'application/vnd.example.order+json', 'application/merge-patch+json ; q=1'] as $type) {
            self::assertTrue(JsonContent::isJson($type), $type);
        }

        foreach (['text/json', 'application/jsonp', 'application/json-seq', 'application/+json', 'application/a+json+xml', 'application/a b+json', 'image/png', '*/*', 'application/x+jsonx', 'xapplication/json', 'application/json/x'] as $type) {
            self::assertFalse(JsonContent::isJson($type), $type);
        }

        self::assertSame([], $this->findings(new JsonContent(), ['paths' => ['/a' => [
            'post' => ['requestBody' => ['content' => ['application/json' => []]], 'responses' => ['201' => ['content' => ['application/json' => []]], '204' => ['description' => 'no body'], '400' => ['content' => 'none']]],
            'get' => ['requestBody' => 'none', 'responses' => ['200' => ['$ref' => '#/components/responses/Ok']]],
        ]], 'components' => ['responses' => ['Ok' => ['content' => ['application/hal+json' => []]]]]]));
        self::assertSame([
            ['/paths/~1a/post/requestBody/content/application~1xml', 'The media type "application/xml" is not JSON.'],
            ['/paths/~1a/post/responses/200/content/text~1csv', 'The media type "text/csv" is not JSON.'],
            ['/paths/~1a/post/responses/4XX/content/text~1html', 'The media type "text/html" is not JSON.'],
        ], $this->findings(new JsonContent(), ['paths' => ['/a' => ['post' => [
            'requestBody' => ['$ref' => '#/components/requestBodies/Xml'],
            'responses' => ['200' => ['content' => ['application/json' => [], 'text/csv' => []]], '4XX' => ['content' => ['text/html' => []]]],
        ]]], 'components' => ['requestBodies' => ['Xml' => ['content' => ['application/xml' => []]]]]]));
    }

    public function testErrorsAreProblemDetails(): void
    {
        self::assertSame([], $this->findings(new ProblemDetails(), ['paths' => ['/a' => ['get' => ['responses' => [
            '200' => ['content' => ['application/json' => []]],
            '404' => ['description' => 'no body'],
            '409' => ['content' => []],
            '422' => ['content' => ['application/problem+json' => []]],
            '5XX' => ['content' => ['Application/Problem+JSON; charset=utf-8' => [], 'text/html' => []]],
            'default' => ['$ref' => '#/components/responses/Problem'],
            '600' => ['content' => ['text/plain' => []]],
        ]]]], 'components' => ['responses' => ['Problem' => ['content' => ['application/problem+json' => []]]]]]));
        self::assertSame([
            ['/paths/~1a/get/responses/404/content', 'The 404 response of GET /a is not offered as application/problem+json.'],
            ['/paths/~1a/get/responses/4xx/content', 'The 4xx response of GET /a is not offered as application/problem+json.'],
            ['/paths/~1a/get/responses/500/content', 'The 500 response of GET /a is not offered as application/problem+json.'],
            ['/paths/~1a/get/responses/default/content', 'The default response of GET /a is not offered as application/problem+json.'],
        ], $this->findings(new ProblemDetails(), ['paths' => ['/a' => ['get' => ['responses' => [
            '404' => ['content' => ['application/json' => []]],
            '4xx' => ['content' => ['text/plain' => []]],
            '500' => ['content' => ['application/problem+xml' => []]],
            'default' => ['content' => ['application/json' => []]],
            '40' => ['content' => ['text/plain' => []]],
            'x404' => ['content' => ['text/plain' => []]],
            '4040' => ['content' => ['text/plain' => []]],
            'defaults' => ['content' => ['text/plain' => []]],
        ]]]]]));
    }

    public function testADeprecatedOperationAnnouncesItsSunset(): void
    {
        self::assertSame([], $this->findings(new DeprecationSunset(), ['paths' => ['/a' => [
            'get' => ['deprecated' => true, 'responses' => ['200' => ['headers' => 'none'], '404' => ['headers' => ['sunset' => []]]]],
            'post' => ['deprecated' => 'true', 'responses' => []],
            'put' => ['deprecated' => false],
            'patch' => ['deprecated' => true, 'responses' => ['200' => ['$ref' => '#/components/responses/Retiring']]],
        ]], 'components' => ['responses' => ['Retiring' => ['headers' => ['Deprecation' => [], 'Sunset' => []]]]]]));
        self::assertSame([
            ['/paths/~1a/get/deprecated', 'GET /a is deprecated but documents no Sunset header.'],
            ['/paths/~1a/delete/deprecated', 'DELETE /a is deprecated but documents no Sunset header.'],
        ], $this->findings(new DeprecationSunset(), ['paths' => ['/a' => [
            'get' => ['deprecated' => true, 'responses' => ['200' => ['headers' => ['Deprecation' => [], 'Link' => []]], '204' => []]],
            'delete' => ['deprecated' => true],
        ]]]));
    }

    public function testEveryOperationHasAnId(): void
    {
        self::assertSame([], $this->findings(new OperationId(), ['paths' => ['/a' => ['get' => ['operationId' => 'listA']]]]));
        self::assertSame([
            ['/paths/~1a/get', 'GET /a has no operationId.'],
            ['/paths/~1a/post', 'POST /a has no operationId.'],
            ['/paths/~1a/patch', 'PATCH /a has no operationId.'],
        ], $this->findings(new OperationId(), ['paths' => ['/a' => ['get' => [], 'post' => ['operationId' => ' '], 'patch' => ['operationId' => 7]]]]));
    }
}
