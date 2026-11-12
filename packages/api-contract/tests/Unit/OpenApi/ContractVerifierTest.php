<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\OpenApi;

use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\Exception\InvalidContractValue;
use Phil\ApiContract\OpenApi\ContractVerifier;
use Phil\ApiContract\OpenApi\Finding;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Phil\ApiContract\OpenApi\Report;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContractVerifier::class)]
#[CoversClass(Report::class)]
#[CoversClass(Finding::class)]
#[CoversClass(RuleLevel::class)]
#[CoversClass(Severity::class)]
final class ContractVerifierTest extends TestCase
{
    public function testEachLevelHoldsTheLevelsBeforeIt(): void
    {
        self::assertSame(
            [[true, false, false], [true, true, false], [true, true, true]],
            array_map(static fn (RuleLevel $level): array => array_map($level->includes(...), RuleLevel::cases()), RuleLevel::cases()),
        );
        self::assertSame(['minimal', 'recommended', 'strict'], array_map(static fn (RuleLevel $level): string => $level->value, RuleLevel::cases()));
    }

    public function testAnErrorReachesEveryThresholdAndAWarningOnlyItsOwn(): void
    {
        self::assertTrue(Severity::Error->reaches(Severity::Error));
        self::assertTrue(Severity::Error->reaches(Severity::Warning));
        self::assertTrue(Severity::Warning->reaches(Severity::Warning));
        self::assertFalse(Severity::Warning->reaches(Severity::Error));
    }

    public function testTheBuiltInRulesRunByLevel(): void
    {
        $verifier = ContractVerifier::withBuiltInRules();
        $ids = static fn (RuleLevel $level): array => array_map(static fn (OpenApiRule $rule): string => $rule->id(), $verifier->rulesAt($level));
        $minimal = ['openapi-version', 'info', 'paths', 'operation-responses', 'path-parameters', 'operation-id-unique', 'local-references'];
        $recommended = [...$minimal, 'no-version-in-path', 'success-response', 'json-content', 'problem-details', 'deprecation-sunset', 'operation-id'];

        self::assertSame($minimal, $ids(RuleLevel::Minimal));
        self::assertSame($recommended, $ids(RuleLevel::Recommended));
        self::assertSame([...$recommended, 'extensible-enum', 'error-responses', 'operation-description', 'security-requirement'], $ids(RuleLevel::Strict));
        self::assertCount(17, ContractVerifier::builtInRules());

        foreach (ContractVerifier::builtInRules() as $rule) {
            self::assertStringEndsWith('.', $rule->description(), $rule->id());
            self::assertSame(\in_array($rule->id(), ['json-content', 'problem-details', 'deprecation-sunset', 'operation-id'], true) || RuleLevel::Strict === $rule->level() ? Severity::Warning : Severity::Error, $rule->severity(), $rule->id());
        }
    }

    public function testAVerificationReportsTheFindingsOfItsLevelInRuleOrder(): void
    {
        $verifier = new ContractVerifier([new FixedRule('first', RuleLevel::Recommended, Severity::Warning, 2), new FixedRule('second', RuleLevel::Minimal, Severity::Error, 1), new FixedRule('third', RuleLevel::Strict, Severity::Error, 1)]);
        $document = OpenApiDocument::fromArray([]);

        $report = $verifier->verify($document);
        self::assertSame(RuleLevel::Recommended, $report->level);
        self::assertSame(['first', 'first', 'second'], array_map(static fn (Finding $finding): string => $finding->ruleId, $report->findings));
        self::assertSame(['/first/0', '/first/1', '/second/0'], array_map(static fn (Finding $finding): string => $finding->location, $report->findings));
        self::assertTrue($report->fails());
        self::assertTrue($report->fails(Severity::Warning));
        self::assertSame(['second'], array_map(static fn (Finding $finding): string => $finding->ruleId, $report->reaching(Severity::Error)));
        self::assertCount(3, $report->reaching(Severity::Warning));

        $minimal = $verifier->verify($document, RuleLevel::Minimal);
        self::assertSame(['second'], array_map(static fn (Finding $finding): string => $finding->ruleId, $minimal->findings));
        self::assertSame(['level' => 'minimal', 'findings' => [['rule' => 'second', 'severity' => 'error', 'location' => '/second/0', 'message' => 'Broken 0.']]], $minimal->toArray());

        $warnings = new ContractVerifier([new FixedRule('only', RuleLevel::Minimal, Severity::Warning, 1)])->verify($document);
        self::assertFalse($warnings->fails());
        self::assertSame([], $warnings->reaching(Severity::Error));
        self::assertTrue($warnings->fails(Severity::Warning));
        self::assertFalse(new Report(RuleLevel::Strict, [])->fails(Severity::Warning));
    }

    public function testRulesAreRemovedById(): void
    {
        $verifier = ContractVerifier::withBuiltInRules(new FixedRule('house-rule', RuleLevel::Minimal, Severity::Error, 0));

        self::assertSame('house-rule', $verifier->rules[17]->id());
        $without = $verifier->without('operation-id', 'house-rule');
        self::assertCount(16, $without->rules);
        self::assertSame(['openapi-version', 'info'], [$without->rules[0]->id(), $without->rules[1]->id()]);
        self::assertNotContains('operation-id', array_map(static fn (OpenApiRule $rule): string => $rule->id(), $without->rules));
        self::assertCount(18, $verifier->without()->rules);

        $this->expectExceptionObject(InvalidContractValue::because('No rule has the id "operation-ids".'));
        $verifier->without('operation-id', 'operation-ids');
    }

    public function testARuleIdIsUniqueAndWellFormed(): void
    {
        foreach ([
            'A rule id is 1 to 64 lowercase letters, digits and hyphens, starting with a letter; "Upper" is not.' => [new FixedRule('Upper', RuleLevel::Minimal, Severity::Error, 0)],
            'A rule id is 1 to 64 lowercase letters, digits and hyphens, starting with a letter; "" is not.' => [new FixedRule('', RuleLevel::Minimal, Severity::Error, 0)],
            'A rule id is 1 to 64 lowercase letters, digits and hyphens, starting with a letter; "1st" is not.' => [new FixedRule('1st', RuleLevel::Minimal, Severity::Error, 0)],
            'A rule id is 1 to 64 lowercase letters, digits and hyphens, starting with a letter; "a\n" is not.' => [new FixedRule("a\n", RuleLevel::Minimal, Severity::Error, 0)],
            'A rule id is 1 to 64 lowercase letters, digits and hyphens, starting with a letter; "'.str_repeat('a', 64).'" is not.' => [new FixedRule(str_repeat('a', 65), RuleLevel::Minimal, Severity::Error, 0)],
            'A rule id is 1 to 64 lowercase letters, digits and hyphens, starting with a letter; "b�/" is not.' => [new FixedRule("b\xFF/", RuleLevel::Minimal, Severity::Error, 0)],
            'Two rules have the id "info".' => [...ContractVerifier::builtInRules(), new FixedRule('info', RuleLevel::Minimal, Severity::Error, 0)],
        ] as $message => $rules) {
            try {
                new ContractVerifier($rules);
                self::fail($message);
            } catch (InvalidContractValue $invalidContractValue) {
                self::assertSame($message, $invalidContractValue->getMessage());
            }
        }

        self::assertSame(str_repeat('a', 64), new ContractVerifier([new FixedRule(str_repeat('a', 64), RuleLevel::Minimal, Severity::Error, 0)])->rules[0]->id());
        self::assertSame('a-9', new ContractVerifier((static function (): iterable {
            yield 'key' => new FixedRule('a-9', RuleLevel::Minimal, Severity::Error, 0);
        })())->rules[0]->id());
    }

    public function testAFindingQuotesTheDocumentWithoutObeyingIt(): void
    {
        $rule = new FixedRule('rule', RuleLevel::Minimal, Severity::Warning, 0);
        $finding = Finding::of($rule, "/paths/~1a\e[31m", "Bad\u{85}\r\n\t text é.");

        self::assertSame(['rule', Severity::Warning], [$finding->ruleId, $finding->severity]);
        self::assertSame("/paths/~1a\u{FFFD}[31m", $finding->location);
        self::assertSame("Bad\u{FFFD}\u{FFFD}\u{FFFD}\u{FFFD} text é.", $finding->message);
        self::assertSame("a\u{FFFD}(b", new Finding('r', Severity::Error, "a\xC3\x28b", '')->location, 'Text that is not UTF-8 keeps its ASCII only.');

        $long = str_repeat('é', Finding::MAX_LENGTH);
        self::assertSame($long, new Finding('r', Severity::Error, '', $long)->message);
        self::assertSame(str_repeat('é', Finding::MAX_LENGTH - 1).'…', new Finding('r', Severity::Error, '', $long.'é')->message);
        self::assertSame(str_repeat('a', Finding::MAX_LENGTH - 1).'…', new Finding('r', Severity::Error, str_repeat('a', 2000)."\nend", '')->location);
        self::assertSame(['rule' => 'r', 'severity' => 'warning', 'location' => '/x', 'message' => 'm'], new Finding('r', Severity::Warning, '/x', 'm')->toArray());
    }
}
