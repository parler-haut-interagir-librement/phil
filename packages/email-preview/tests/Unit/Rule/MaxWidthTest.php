<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit\Rule;

use Phil\EmailPreview\Rule\MaxWidth;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MaxWidth::class)]
final class MaxWidthTest extends RuleTestCase
{
    public function testTheRuleIsNamedAndDefaultsTo600Pixels(): void
    {
        self::assertSame('max-width', new MaxWidth()->name());
        self::assertSame(600, new MaxWidth()->pixels);
    }

    public function testElementsUpTo600PixelsPass(): void
    {
        $html = '<table width="600"><tr><td style="width: 600px; max-width:600px; min-width: 300px">A</td></tr></table>'
            .'<div style="width:100%; max-width: 40em">B</div><table width="100%"><tr><td width="auto">C</td></tr></table>'
            .'<img src="cid:a" width="600" style="height: 900px">';

        self::assertSame([], self::findings(new MaxWidth(), $html));
    }

    public function testWiderAttributesAndStylesAreErrors(): void
    {
        $html = '<table width="601"><tr><td style="width:700px">A</td></tr></table><div style="color: red; MIN-WIDTH: 800PX !important">B</div><img src="cid:a" width="640px">';

        self::assertSame([
            '[error] max-width: An element is wider than 600 pixels. (table width="601")',
            '[error] max-width: An element is wider than 600 pixels. (td style="width: 700px")',
            '[error] max-width: An element is wider than 600 pixels. (div style="min-width: 800PX !important")',
            '[error] max-width: An element is wider than 600 pixels. (img width="640px")',
        ], self::findings(new MaxWidth(), $html));
    }

    public function testTheLimitCanBeChanged(): void
    {
        self::assertSame(['[error] max-width: An element is wider than 480 pixels. (table width="600")'], self::findings(new MaxWidth(480), '<table width="600"><tr><td width="480">A</td></tr></table>'));
    }
}
