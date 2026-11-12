<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use Phil\BrowserSecurityBundle\Application\Exception\UnknownPolicy;
use Phil\BrowserSecurityBundle\Application\PolicyDefinition;
use Phil\BrowserSecurityBundle\Application\PolicyRegistry;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\Syntax\SourceSyntax;
use Phil\BrowserSecurityBundle\Domain\ValueObject\Policy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PolicyRegistry::class)]
#[CoversClass(PolicyDefinition::class)]
#[CoversClass(UnknownPolicy::class)]
final class PolicyRegistryTest extends TestCase
{
    public function testThePoliciesOfTheConfiguration(): void
    {
        $registry = PolicyRegistry::fromConfig([
            'default' => ['directives' => ['default-src' => ["'self'"], 'img-src' => ["'self'"]], 'strict_dynamic' => true],
            'preview' => ['extends' => 'default', 'directives' => ['img-src' => ['data:']], 'report' => false],
            'bare' => [],
        ]);

        self::assertSame(['default', 'preview', 'bare'], $registry->names());
        self::assertTrue($registry->has('preview'));
        self::assertFalse($registry->has('other'));
        self::assertTrue($registry->get('default')->strictDynamic);
        self::assertTrue($registry->get('default')->report);
        self::assertFalse($registry->get('preview')->strictDynamic);
        self::assertFalse($registry->get('preview')->report);
        self::assertSame("default-src 'self'; img-src data:", $registry->get('preview')->policy->header());
        self::assertSame('', $registry->get('bare')->policy->header());
    }

    public function testAnUnknownPolicy(): void
    {
        $this->expectException(UnknownPolicy::class);
        $this->expectExceptionMessageIsOrContains('No content security policy is named "other".');

        new PolicyRegistry(['default' => new PolicyDefinition(new Policy(), false, true)])->get('other');
    }

    public function testInlineCodeIsAllowedByItsHashAfterTheParent(): void
    {
        $registry = PolicyRegistry::fromConfig([
            'base' => ['directives' => ['default-src' => ["'self'"], 'script-src' => ["'self'"]]],
            'page' => ['extends' => 'base', 'inline' => ['script' => ['a()'], 'style' => ['b{}']]],
        ]);

        self::assertSame(
            "default-src 'self'; script-src 'self' ".SourceSyntax::hashOf('a()')."; style-src 'self' ".SourceSyntax::hashOf('b{}'),
            $registry->get('page')->policy->header(),
        );
    }

    public function testTrustedTypes(): void
    {
        $registry = PolicyRegistry::fromConfig([
            'on' => ['trusted_types' => ['enabled' => true, 'policies' => ['app', 'dompurify'], 'allow_duplicates' => true]],
            'bare' => ['trusted_types' => ['enabled' => true]],
            'off' => ['trusted_types' => ['enabled' => false, 'policies' => ['app']]],
        ]);

        self::assertSame("require-trusted-types-for 'script'; trusted-types app dompurify 'allow-duplicates'", $registry->get('on')->policy->header());
        self::assertSame("require-trusted-types-for 'script'; trusted-types", $registry->get('bare')->policy->header());
        self::assertSame('', $registry->get('off')->policy->header());
    }

    public function testAPolicyExtendingAnUnknownOne(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('The policy "page" extends "base", which is not declared.');

        PolicyRegistry::fromConfig(['page' => ['extends' => 'base']]);
    }

    public function testAPolicyExtendingItself(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('The policy "a" extends itself: a → b → a.');

        PolicyRegistry::fromConfig(['a' => ['extends' => 'b'], 'b' => ['extends' => 'a']]);
    }
}
