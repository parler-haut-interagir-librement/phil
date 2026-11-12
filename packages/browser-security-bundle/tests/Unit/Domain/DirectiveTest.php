<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Domain;

use Phil\BrowserSecurityBundle\Domain\Enum\Directive;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Directive::class)]
#[CoversClass(InlineKind::class)]
final class DirectiveTest extends TestCase
{
    public function testWhatEachDirectiveTakes(): void
    {
        $tokens = [Directive::Sandbox, Directive::UpgradeInsecureRequests, Directive::RequireTrustedTypesFor, Directive::TrustedTypes];
        $locations = [Directive::DefaultSrc, Directive::FrameAncestors, Directive::FormAction, Directive::BaseUri];

        foreach (Directive::cases() as $directive) {
            $sources = !\in_array($directive, $tokens, true);
            self::assertSame($sources, $directive->takesSources(), $directive->value);
            self::assertSame($sources && !\in_array($directive, $locations, true), $directive->fallsBackOnDefault(), $directive->value);
            self::assertSame($sources && !\in_array($directive, [Directive::FrameAncestors, Directive::FormAction, Directive::BaseUri], true), $directive->takesScriptKeywords(), $directive->value);
        }
    }

    public function testTheDirectivesOfEachInlineKind(): void
    {
        self::assertSame(Directive::ScriptSrc, InlineKind::Script->directive());
        self::assertSame(Directive::ScriptSrcElem, InlineKind::Script->elementDirective());
        self::assertSame(Directive::StyleSrc, InlineKind::Style->directive());
        self::assertSame(Directive::StyleSrcElem, InlineKind::Style->elementDirective());
    }
}
