<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Unit;

use Phil\CssSanitizer\Check\Selectors;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Syntax\Parser;
use Phil\CssSanitizer\Syntax\Tokenizer;
use Phil\CssSanitizer\Url\RefuseUrls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Selectors::class)]
#[UsesClass(Policy::class)]
#[UsesClass(Parser::class)]
#[UsesClass(Tokenizer::class)]
#[UsesClass(RefuseUrls::class)]
final class SelectorsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function allowed(): iterable
    {
        foreach ([
            'p', '*', 'P', '.a', '#main', 'p.a.b#c', '*.a', 'a b', "a \n b", 'a /**/ b', 'a>b', 'a > b', 'a + b',
            'a ~ b', 'a>b+c~d e', 'p::before', 'p:before', 'p:after', 'p:first-line', 'p:first-letter', 'p::marker',
            '::selection', 'li:first-child', 'li:FIRST-CHILD', 'a:hover', 'a:link', 'a:hover:focus', ':root',
            'li:nth-child(2n+1)', 'li:nth-child( odd )', 'li:nth-last-child(-n + 3)', 'p:nth-of-type(2)',
            'p:nth-last-of-type(n-1)', 'p:not(.a)', 'p:not(.a, #b)', 'p:is(h1, h2) em', 'p:where(.a > b)',
            ':not(:not(.a))', 'p:lang(fr)', 'p:lang("fr", en)', 'p:dir(ltr)', 'p:dir(RTL)', '[lang]', '[ lang ]',
            '[lang=fr]', '[lang="fr"]', '[lang|=fr]', '[lang~=fr]', '[lang^=fr]', '[lang$=fr]', '[lang*=fr]',
            '[lang=fr i]', '[lang="fr" s]', '[LANG=fr]', 'p[dir=rtl]::after', 'p::before::marker', 'p::BEFORE', 'p:BEFORE', 'p:before::marker',
        ] as $selector) {
            yield $selector => [$selector];
        }
    }

    #[DataProvider('allowed')]
    public function testItAllowsSelectors(string $selector): void
    {
        self::assertTrue($this->check($selector));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        foreach ([
            '', '#1a', '.', '.1', '. a', 'a >', '> a', 'a > > b', 'a || b', 'ns|a', '*|a', '|a', '&', '& a', 'a &',
            '[value]', '[value^="a"]', 'a[lang]b', '[lang ~ x]', '[lang = ]', '[lang = fr x]',
            '[lang=fr i s]', '[lang=fr x]', '[lang=1]', '[lang=(x)]', '[1]', '["lang"]', '[lang|x]', '[lang==fr]',
            '[lang^fr]', '(a)', '{a}', 'a:visited', 'a:has(b)', 'input:checked', 'a:unknown', 'a:not', 'a:nth-child',
            'a:lang', ':nth-child()', ':nth-child(2n of .a)', ':nth-child(2n+1 .a)', ':nth-child(1, 2)', ':nth-child(*)',
            ':lang()', ':lang(.a)', ':dir()', ':dir(up)', ':dir(ltr rtl)', ':dir("ltr")', ':not()', ':not(::before)',
            ':not(:before)', ':is(a,)', ':where(a >)', ':x(a)', 'p::part(a)', 'p::slotted(a)', 'p::cue',
            'p::before.a', 'p::before b', 'p::before > b', 'p::before:hover', 'p::before[lang]', 'p:first-line.a',
            'p::', 'p:', 'p::1', ':1', 'a:"b"', 'p::before p', 'p 1', 'a*hover', '[lang (x)]', '[lang ~(x)]', 'a, b', 'a;', 'url(x)', '"a"', 'a!',
        ] as $selector) {
            yield '«'.$selector.'»' => [$selector];
        }
    }

    #[DataProvider('refused')]
    public function testItRefusesSelectors(string $selector): void
    {
        self::assertFalse($this->check($selector));
    }

    public function testThePolicyDecides(): void
    {
        $policy = new Policy()->withAttributes('data-*', 'Title')->withPseudoClasses('checked', 'nth-col')->withoutPseudoClasses('hover', 'not')->withoutPseudoElements('before');

        self::assertTrue($this->check('[data-x="1"]', $policy));
        self::assertTrue($this->check('[data-]', $policy));
        self::assertTrue($this->check('[title]', $policy));
        self::assertFalse($this->check('[datax]', $policy));
        self::assertFalse($this->check('[x-data-y]', $policy));
        self::assertTrue($this->check('input:checked', $policy));
        self::assertFalse($this->check(':nth-col(1)', $policy));
        self::assertFalse($this->check('a:hover', $policy));
        self::assertFalse($this->check('a:not(.b)', $policy));
        self::assertFalse($this->check('p::before', $policy));
        self::assertFalse($this->check('p:before', $policy));
        self::assertTrue($this->check('p::after', $policy));
        self::assertFalse($this->check(':is(a)', new Policy()->withoutPseudoClasses('is')));
        self::assertFalse($this->check(':nth-child(1)', new Policy()->withoutPseudoClasses('nth-child')));
        self::assertFalse($this->check(':lang(fr)', new Policy()->withoutPseudoClasses('lang')));
        self::assertFalse($this->check(':dir(ltr)', new Policy()->withoutPseudoClasses('dir')));
    }

    private function check(string $selector, Policy $policy = new Policy()): bool
    {
        return new Selectors($policy)->allows(Parser::trim(new Parser()->parseComponentValues($selector)));
    }
}
