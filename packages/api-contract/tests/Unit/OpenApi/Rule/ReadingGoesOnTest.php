<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\OpenApi\Rule;

use Phil\ApiContract\OpenApi\ContractVerifier;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Phil\ApiContract\OpenApi\Operation;
use Phil\ApiContract\OpenApi\Rule\DeprecationSunset;
use Phil\ApiContract\OpenApi\Rule\ExtensibleEnum;
use Phil\ApiContract\OpenApi\Rule\NoVersionInPath;
use Phil\ApiContract\OpenApi\Rule\OperationIdUnique;
use Phil\ApiContract\OpenApi\Rule\PathParameters;
use Phil\ApiContract\OpenApi\Rule\ProblemDetails;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;
use Phil\ApiContract\Tests\Unit\OpenApi\FixedRule;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What a rule or a reader meets that it skips (an operation that is not deprecated, a parameter that is not a path
 * one, a response that is not an error) never ends its reading: what follows is read too.
 */
#[CoversClass(DeprecationSunset::class)]
#[CoversClass(OperationIdUnique::class)]
#[CoversClass(PathParameters::class)]
#[CoversClass(ProblemDetails::class)]
#[CoversClass(NoVersionInPath::class)]
#[CoversClass(ExtensibleEnum::class)]
#[CoversClass(ContractVerifier::class)]
#[CoversClass(OpenApiDocument::class)]
#[CoversClass(Operation::class)]
final class ReadingGoesOnTest extends RuleTestCase
{
    public function testAnOperationThatIsNotDeprecatedLeavesTheNextOneRead(): void
    {
        self::assertSame(
            [['/paths/~1b/get/deprecated', 'GET /b is deprecated but documents no Sunset header.']],
            $this->findings(new DeprecationSunset(), ['paths' => ['/a' => ['get' => ['responses' => []]], '/b' => ['get' => ['deprecated' => true]]]]),
        );
    }

    public function testAnOperationWithoutIdLeavesTheNextOnesRead(): void
    {
        self::assertSame(
            [['/paths/~1c/get/operationId', 'The operationId "x" is already used at /paths/~1b/get.']],
            $this->findings(new OperationIdUnique(), ['paths' => ['/a' => ['get' => []], '/b' => ['get' => ['operationId' => 'x']], '/c' => ['get' => ['operationId' => 'x']]]]),
        );
    }

    public function testAParameterThatIsNotInThePathLeavesTheNextOnesRead(): void
    {
        self::assertSame(
            [['/paths/~1a~1{id}/get/parameters/1/required', 'The path parameter "id" is not marked required.']],
            $this->findings(new PathParameters(), ['paths' => ['/a/{id}' => ['get' => ['parameters' => [['name' => 'q', 'in' => 'query'], ['name' => 'id', 'in' => 'path']]]]]]),
        );
    }

    public function testAResponseThatIsNotAnErrorLeavesTheNextOnesRead(): void
    {
        $locations = array_column($this->findings(new ProblemDetails(), ['paths' => ['/a' => ['get' => ['responses' => [
            '200' => ['content' => ['application/json' => []]],
            '404' => ['content' => ['application/json' => []]],
        ]]]]]), 0);

        self::assertSame(['/paths/~1a/get/responses/404/content'], $locations);
    }

    public function testTheCodeOfAnErrorResponseIsReadInAnyCase(): void
    {
        $locations = array_column($this->findings(new ProblemDetails(), ['paths' => ['/a' => ['get' => ['responses' => [
            '4xx' => ['content' => ['text/plain' => []]],
            'Default' => ['content' => ['text/plain' => []]],
        ]]]]]), 0);

        self::assertSame(['/paths/~1a/get/responses/4xx/content', '/paths/~1a/get/responses/Default/content'], $locations);
    }

    public function testTheProblemMediaTypeIsReadWithoutItsSpaces(): void
    {
        self::assertSame([], $this->findings(new ProblemDetails(), ['paths' => ['/a' => ['get' => ['responses' => [
            '400' => ['content' => [' Application/Problem+JSON ; charset=utf-8' => []]],
        ]]]]]));
    }

    public function testAVersionSegmentIsReadInAnyCase(): void
    {
        self::assertSame([['/paths/~1V2~1orders', 'The path "/V2/orders" carries a version segment.']], $this->findings(new NoVersionInPath(), ['paths' => ['/V2/orders' => []]]));
    }

    public function testAnEndOfLineIsNoPartOfACodeOrOfAVersion(): void
    {
        self::assertSame([], $this->findings(new NoVersionInPath(), ['paths' => ["/v1\n/orders" => []]]));
        self::assertSame([], $this->findings(new ProblemDetails(), ['paths' => ['/a' => ['get' => ['responses' => ["404\n" => ['content' => ['text/plain' => []]]]]]]]));
    }

    public function testAnEnumDeeperThanAServerVariableIsFound(): void
    {
        self::assertSame(
            [['/servers/0/variables/x/schema/enum', 'The schema closes its values with enum: list them in x-extensible-enum, which a value added later does not break.']],
            $this->findings(new ExtensibleEnum(), ['servers' => [['variables' => ['x' => ['schema' => ['enum' => ['a']]]]]]]),
        );
    }

    public function testTheRulesOfALevelAreAList(): void
    {
        $strict = new FixedRule('strict', RuleLevel::Strict, Severity::Warning, 0);
        $minimal = new FixedRule('minimal', RuleLevel::Minimal, Severity::Error, 0);

        self::assertSame([$minimal], new ContractVerifier([$strict, $minimal])->rulesAt(RuleLevel::Minimal));
        self::assertSame([$strict, $minimal], \array_slice(ContractVerifier::withBuiltInRules(...['a' => $strict, 'b' => $minimal])->rules, -2));
    }

    public function testADocumentSkipsWhatIsNoPathItemAndReadsOn(): void
    {
        $document = OpenApiDocument::fromArray(['paths' => ['/x' => 'text', '/y' => ['get' => ['responses' => []]]]]);

        self::assertSame(['/y'], array_map(static fn (Operation $operation): string => $operation->path, $document->operations()));
    }

    public function testAParameterListThatIsNoneAndAParameterThatIsNoParameterLeaveTheNextOnesRead(): void
    {
        $document = OpenApiDocument::fromArray(['paths' => ['/a' => ['get' => ['parameters' => [['no' => 'name'], ['name' => 'a', 'in' => 'query']]]]]]);

        self::assertSame(['/paths/~1a/get/parameters/1'], array_keys($document->operations()[0]->parameters($document)));
    }

    public function testADocumentReadsAPointerOnlyFromItsRoot(): void
    {
        $document = OpenApiDocument::fromArray(['' => ['b' => 5]]);

        self::assertNull($document->at('#a/b'));
        self::assertSame(5, $document->at('#//b'));
    }
}
