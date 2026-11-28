<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit;

use ArrayIterator;
use Override;
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\EmailChecker;
use Phil\EmailPreview\Finding;
use Phil\EmailPreview\Rule\DefaultRules;
use Phil\EmailPreview\Rule\MaxWidth;
use Phil\EmailPreview\Rule\NoStyleElement;
use Phil\EmailPreview\Rule\OneClickUnsubscribe;
use Phil\EmailPreview\Rule\SubjectLength;
use Phil\EmailPreview\Rule\TextPart;
use Phil\EmailPreview\Rule\TrackingPixel;
use Phil\EmailPreview\Sending;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailChecker::class)]
#[CoversClass(DefaultRules::class)]
final class EmailCheckerTest extends TestCase
{
    public function testTheDefaultRulesRunInOrder(): void
    {
        self::assertEquals([new TextPart(), new OneClickUnsubscribe(), new TrackingPixel(), new NoStyleElement(), new MaxWidth(), new SubjectLength()], DefaultRules::all());
        self::assertSame(
            ['text-part', 'one-click-unsubscribe', 'tracking-pixel', 'no-style-element', 'max-width', 'subject-length'],
            new EmailChecker()->check(new CheckedEmail('Hi', '<p>Hi</p>', 'Hi'))->rules,
        );
    }

    public function testAWellMadeNewsletterPasses(): void
    {
        $html = <<<'HTML'
            <!doctype html>
            <html lang="en"><head><meta charset="utf-8"><title>News</title></head>
            <body style="margin:0">
              <table role="presentation" width="600" style="max-width:600px;width:100%">
                <tr><td style="padding:16px">
                  <img src="https://cdn.a.test/logo.png" width="120" height="40" alt="A">
                  <p>Our autumn news.</p>
                  <a href="https://a.test/news/autumn">Read it</a>
                  <p><a href="mailto:help@a.test">help@a.test</a> · <a href="https://a.test/leave/xyz">Unsubscribe</a></p>
                </td></tr>
              </table>
            </body></html>
            HTML;
        $text = "Our autumn news.\n\nRead it (https://a.test/news/autumn)\n\nhelp@a.test · Unsubscribe (https://a.test/leave/xyz)";
        $sending = Sending::bulk(['List-Unsubscribe' => '<https://a.test/leave/xyz>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'], ['ana@a.test']);

        $report = new EmailChecker()->check(new CheckedEmail('Our autumn news', $html, $text, $sending));

        self::assertSame([], $report->findings);
        self::assertTrue($report->passes(strict: true));
    }

    public function testABadNewsletterBreaksEveryRule(): void
    {
        $html = '<html><head><style>p{color:red}</style></head><body><table width="700"><tr><td>'
            .'<a href="https://a.test/news">Read</a><img src="https://t.test/o.gif?u=ana%40a.test" width="1" height="1">'
            .'</td></tr></table></body></html>';

        $report = new EmailChecker()->check(new CheckedEmail(str_repeat('News ', 12), $html, 'Read the news', Sending::bulk([], ['ana@a.test'])));

        self::assertSame(
            ['text-part', 'one-click-unsubscribe', 'one-click-unsubscribe', 'tracking-pixel', 'no-style-element', 'max-width', 'subject-length'],
            array_map(static fn (Finding $finding): string => $finding->rule, $report->findings),
        );
        self::assertCount(6, $report->errors());
        self::assertCount(1, $report->warnings());
        self::assertFalse($report->passes());
    }

    public function testTheRulesGivenReplaceTheDefaultOnes(): void
    {
        $rule = new class implements Rule {
            #[Override]
            public function name(): string
            {
                return 'house';
            }

            #[Override]
            public function check(CheckedEmail $email): iterable
            {
                yield Finding::warning('house', 'One.');
                yield Finding::error('house', 'Two.');
            }
        };

        $report = new EmailChecker(new ArrayIterator(['a' => $rule, 'b' => new NoStyleElement()]))->check(new CheckedEmail('Hi', '<p>Hi</p>', ''));

        self::assertSame(['house', 'no-style-element'], $report->rules);
        self::assertSame(['One.', 'Two.'], array_map(static fn (Finding $finding): string => $finding->message, $report->findings));
        self::assertSame([], new EmailChecker([])->check(new CheckedEmail('Hi', '<style></style>', ''))->rules);
    }
}
