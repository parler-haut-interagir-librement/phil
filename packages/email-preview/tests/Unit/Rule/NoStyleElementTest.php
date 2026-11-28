<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit\Rule;

use Phil\EmailPreview\Rule\NoStyleElement;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NoStyleElement::class)]
final class NoStyleElementTest extends RuleTestCase
{
    public function testTheRuleIsNamed(): void
    {
        self::assertSame('no-style-element', new NoStyleElement()->name());
    }

    public function testInlinedStylesPass(): void
    {
        self::assertSame([], self::findings(new NoStyleElement(), '<html><head><title>T</title></head><body><p style="color:red">Hi</p></body></html>'));
    }

    public function testStyleElementsAreOneErrorWithTheirCount(): void
    {
        $html = '<html><head><style>p{color:red}</style></head><body><style>a{}</style><p>Hi</p></body></html>';

        self::assertSame(['[error] no-style-element: The email keeps a <style> element: inline its styles. (2)'], self::findings(new NoStyleElement(), $html));
        self::assertSame(['[error] no-style-element: The email keeps a <style> element: inline its styles. (1)'], self::findings(new NoStyleElement(), '<style></style>'));
    }
}
