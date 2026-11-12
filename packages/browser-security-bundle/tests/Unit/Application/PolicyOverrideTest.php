<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use Phil\BrowserSecurityBundle\Application\Attribute\ContentSecurityPolicy;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\ValueObject\Policy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PolicyOverride::class)]
#[CoversClass(ContentSecurityPolicy::class)]
final class PolicyOverrideTest extends TestCase
{
    public function testReplaceThenAddThenRemove(): void
    {
        $policy = new Policy(['default-src' => ["'self'"], 'img-src' => ["'self'"], 'frame-src' => ["'self'"]]);
        $override = new PolicyOverride(add: ['img-src' => ['https://b.example']], replace: ['img-src' => ['https://a.example']], remove: ['frame-src']);

        self::assertSame("default-src 'self'; img-src https://a.example https://b.example", $override->apply($policy)->header());
    }

    public function testRemoveComesLast(): void
    {
        $policy = new Policy(['default-src' => ["'self'"]]);
        $override = new PolicyOverride(add: ['img-src' => ['data:']], remove: ['img-src']);

        self::assertSame("default-src 'self'", $override->apply($policy)->header());
    }

    public function testAnAttributeGivesItsOverride(): void
    {
        $attribute = new ContentSecurityPolicy('preview', ['img-src' => ['data:']], ['style-src' => ["'self'"]], ['frame-src'], true);

        self::assertEquals(new PolicyOverride('preview', ['img-src' => ['data:']], ['style-src' => ["'self'"]], ['frame-src'], true), $attribute->toOverride());
        self::assertEquals(new PolicyOverride(), new ContentSecurityPolicy()->toOverride());
    }

    public function testTheDefaultOfARoute(): void
    {
        $override = PolicyOverride::fromArray(['policy' => 'preview', 'add' => ['img-src' => ['data:']], 'replace' => ['style-src' => []], 'remove' => ['frame-src'], 'report_only' => false]);

        self::assertEquals(new PolicyOverride('preview', ['img-src' => ['data:']], ['style-src' => []], ['frame-src'], false), $override);
        self::assertEquals(new PolicyOverride(), PolicyOverride::fromArray([]));
        self::assertTrue(PolicyOverride::fromArray(['disabled' => true])->disabled);
        self::assertSame(
            ['img-src' => ['data:', 'https://a.example'], 'font-src' => ['data:', 'https://b.example']],
            PolicyOverride::fromArray(['add' => ['img-src' => ['data:', 'https://a.example'], 'font-src' => ['data:', 'https://b.example']]])->add,
        );
        self::assertSame(['img-src', 'font-src'], PolicyOverride::fromArray(['remove' => ['img-src', 'font-src']])->remove);
    }

    public function testADirectiveKeyedByANumberIsRefusedAsADirective(): void
    {
        foreach (['add', 'replace'] as $key) {
            try {
                PolicyOverride::fromArray([$key => [0 => ['data:']]])->apply(new Policy());
                self::fail('A directive keyed by a number was accepted.');
            } catch (InvalidPolicy $invalid) {
                self::assertSame('"0" is not a directive of a content security policy.', $invalid->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'not a map' => ['preview', 'The "_csp" default of a route is a map of policy, add, replace, remove, report_only and disabled.'];
        yield 'an unknown key' => [['policy' => 'a', 'mode' => 'x', 3 => 'y'], 'The "_csp" default of a route does not take "mode", "3".'];
        yield 'a policy that is not a name' => [['policy' => 3], 'The "policy" of a "_csp" default is a name, and its "report_only" and "disabled" booleans.'];
        yield 'a mode that is not a boolean' => [['report_only' => 'yes'], 'The "policy" of a "_csp" default is a name, and its "report_only" and "disabled" booleans.'];
        yield 'additions that are not a map' => [['add' => 'data:'], 'The "add" and "replace" of a "_csp" default are maps of sources by directive.'];
        yield 'replacements that are not a map' => [['replace' => 'data:'], 'The "add" and "replace" of a "_csp" default are maps of sources by directive.'];
        yield 'sources that are not a list' => [['add' => ['img-src' => 'data:']], 'A list of a "_csp" default holds strings only.'];
        yield 'sources keyed by name' => [['add' => ['img-src' => ['a' => 'data:']]], 'A list of a "_csp" default holds strings only.'];
        yield 'a source that is not a string' => [['add' => ['img-src' => ['data:', 1]]], 'A list of a "_csp" default holds strings only.'];
        yield 'removals that are not a list' => [['remove' => 'img-src'], 'A list of a "_csp" default holds strings only.'];
    }

    #[DataProvider('invalid')]
    public function testAnInvalidDefaultIsRefused(mixed $value, string $message): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        PolicyOverride::fromArray($value);
    }
}
