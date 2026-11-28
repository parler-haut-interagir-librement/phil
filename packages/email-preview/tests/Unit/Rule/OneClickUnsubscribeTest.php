<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit\Rule;

use Phil\EmailPreview\Rule\OneClickUnsubscribe;
use Phil\EmailPreview\Sending;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OneClickUnsubscribe::class)]
final class OneClickUnsubscribeTest extends RuleTestCase
{
    public function testTheRuleIsNamed(): void
    {
        self::assertSame('one-click-unsubscribe', new OneClickUnsubscribe()->name());
    }

    public function testAnEmailNotSentInBulkOwesNoHeader(): void
    {
        self::assertSame([], self::findings(new OneClickUnsubscribe(), sending: new Sending()));
    }

    public function testABulkEmailWithBothHeadersPasses(): void
    {
        $sending = Sending::bulk([
            'list-unsubscribe' => '<mailto:leave@a.test>, <HTTPS://a.test/leave/abc>',
            'List-Unsubscribe-Post' => ' List-Unsubscribe=One-Click ',
        ]);

        self::assertSame([], self::findings(new OneClickUnsubscribe(), sending: $sending));
    }

    public function testABulkEmailWithoutHeadersHasTwoErrors(): void
    {
        self::assertSame([
            '[error] one-click-unsubscribe: An email sent in bulk has no List-Unsubscribe header.',
            '[error] one-click-unsubscribe: An email sent in bulk has no List-Unsubscribe-Post header.',
        ], self::findings(new OneClickUnsubscribe(), sending: Sending::bulk(['List-Unsubscribe' => '  '])));
    }

    public function testTheAddressMustBeHttpsAndThePostValueExact(): void
    {
        $sending = Sending::bulk([
            'List-Unsubscribe' => '<mailto:leave@a.test>, <http://a.test/leave>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=one-click',
        ]);

        self::assertSame([
            '[error] one-click-unsubscribe: The List-Unsubscribe header gives no https address to unsubscribe in one click. (<mailto:leave@a.test>, <http://a.test/leave>)',
            '[error] one-click-unsubscribe: The List-Unsubscribe-Post header is not "List-Unsubscribe=One-Click". (List-Unsubscribe=one-click)',
        ], self::findings(new OneClickUnsubscribe(), sending: $sending));
    }

    public function testAnHttpsAddressOutsideAngleBracketsIsNotOne(): void
    {
        $sending = Sending::bulk(['List-Unsubscribe' => 'https://a.test/leave', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);

        self::assertSame(['[error] one-click-unsubscribe: The List-Unsubscribe header gives no https address to unsubscribe in one click. (https://a.test/leave)'], self::findings(new OneClickUnsubscribe(), sending: $sending));
    }
}
