<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\OpenApi\Rule;

use Phil\ApiContract\OpenApi\Rule\ErrorResponses;
use Phil\ApiContract\OpenApi\Rule\ExtensibleEnum;
use Phil\ApiContract\OpenApi\Rule\OperationDescription;
use Phil\ApiContract\OpenApi\Rule\SecurityRequirement;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ExtensibleEnum::class)]
#[CoversClass(ErrorResponses::class)]
#[CoversClass(OperationDescription::class)]
#[CoversClass(SecurityRequirement::class)]
final class StrictRulesTest extends RuleTestCase
{
    public function testTheRulesAreStrictWarnings(): void
    {
        foreach ([new ExtensibleEnum(), new ErrorResponses(), new OperationDescription(), new SecurityRequirement()] as $rule) {
            self::assertSame([RuleLevel::Strict, Severity::Warning], [$rule->level(), $rule->severity()], $rule->id());
            self::assertSame($rule::ID, $rule->id());
        }

        self::assertSame(['extensible-enum', 'error-responses', 'operation-description', 'security-requirement'], [ExtensibleEnum::ID, ErrorResponses::ID, OperationDescription::ID, SecurityRequirement::ID]);
        self::assertSame('No schema closes its values with `enum`; the values that may grow are listed in `x-extensible-enum`.', new ExtensibleEnum()->description());
        self::assertSame('Every operation declares a `4XX` response or a `default` one.', new ErrorResponses()->description());
        self::assertSame('Every operation has a non-empty `summary` or `description`.', new OperationDescription()->description());
        self::assertSame('The document, or else every operation, declares a `security` requirement; an empty one says the operation is public.', new SecurityRequirement()->description());
    }

    public function testNoSchemaClosesItsValues(): void
    {
        self::assertSame([], $this->findings(new ExtensibleEnum(), [
            'servers' => [['url' => '{scheme}://api.example.com', 'variables' => ['scheme' => ['default' => 'https', 'enum' => ['https', 'http']]]]],
            'paths' => ['/a' => ['servers' => [['variables' => ['region' => ['enum' => ['eu', 'us']]]]]]],
            'components' => ['schemas' => ['Status' => ['type' => 'string', 'x-extensible-enum' => ['open', 'closed']], 'Text' => ['enum' => 'not a list']]],
        ]));
        self::assertSame([
            ['/paths/~1a/get/parameters/0/schema/enum', 'The schema closes its values with enum: list them in x-extensible-enum, which a value added later does not break.'],
            ['/components/schemas/Status/enum', 'The schema closes its values with enum: list them in x-extensible-enum, which a value added later does not break.'],
            ['/components/schemas/Order/properties/state/enum', 'The schema closes its values with enum: list them in x-extensible-enum, which a value added later does not break.'],
            ['/components/schemas/servers/items/enum', 'The schema closes its values with enum: list them in x-extensible-enum, which a value added later does not break.'],
        ], $this->findings(new ExtensibleEnum(), [
            'paths' => ['/a' => ['get' => ['parameters' => [['name' => 'sort', 'in' => 'query', 'schema' => ['enum' => ['asc', 'desc']]]]]]],
            'components' => ['schemas' => [
                'Status' => ['enum' => ['open'], 'x-extensible-enum' => null],
                'Order' => ['properties' => ['state' => ['enum' => []]]],
                'servers' => ['items' => ['enum' => ['a']]],
            ]],
        ]));
    }

    public function testEveryOperationDeclaresAnError(): void
    {
        foreach (['400', '404', '4XX', '4xx', 'default', 'DEFAULT'] as $code) {
            self::assertSame([], $this->findings(new ErrorResponses(), ['paths' => ['/a' => ['get' => ['responses' => ['200' => [], $code => []]]]]]), $code);
        }

        foreach ([[], ['responses' => 'none'], ['responses' => ['200' => [], '500' => [], '5XX' => [], '40' => [], '4000' => [], 'x400' => [], 'defaults' => []]]] as $operation) {
            self::assertSame([['/paths/~1a/get/responses', 'GET /a declares no error response.']], $this->findings(new ErrorResponses(), ['paths' => ['/a' => ['get' => $operation]]]));
        }
    }

    public function testEveryOperationIsDescribed(): void
    {
        self::assertSame([], $this->findings(new OperationDescription(), ['paths' => ['/a' => ['get' => ['summary' => 'List'], 'post' => ['summary' => '', 'description' => 'Create'], 'put' => ['summary' => 3, 'description' => 'Replace']]]]));
        self::assertSame([
            ['/paths/~1a/get', 'GET /a has neither summary nor description.'],
            ['/paths/~1a/post', 'POST /a has neither summary nor description.'],
        ], $this->findings(new OperationDescription(), ['paths' => ['/a' => ['get' => [], 'post' => ['summary' => ' ', 'description' => ['text']]]]]));
    }

    public function testEveryOperationDeclaresItsSecurity(): void
    {
        self::assertSame([], $this->findings(new SecurityRequirement(), ['security' => [], 'paths' => ['/a' => ['get' => []]]]));
        self::assertSame([], $this->findings(new SecurityRequirement(), ['paths' => ['/a' => ['get' => ['security' => []], 'post' => ['security' => [['key' => []]]]]]]));
        self::assertSame([
            ['/paths/~1a/get', 'GET /a declares no security requirement.'],
            ['/paths/~1a/post', 'POST /a declares no security requirement.'],
        ], $this->findings(new SecurityRequirement(), ['security' => 'none', 'paths' => ['/a' => ['get' => [], 'post' => ['security' => 'key'], 'put' => ['security' => []]]]]));
    }
}
