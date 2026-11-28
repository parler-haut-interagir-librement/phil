<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Benchmark;

use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\EmailChecker;
use Phil\EmailPreview\Sending;

/**
 * Each preview is checked while an editor waits: a long newsletter — 200 articles, each in its table with an image and
 * two links, some 300 KB of HTML, three times what Gmail shows — is parsed once and read by the six default rules. The
 * time must stay in proportion to the size of the email.
 *
 * @BeforeMethods("setUp")
 */
final class EmailCheckerBench
{
    private readonly EmailChecker $checker;

    private string $html = '';

    private string $text = '';

    private readonly Sending $sending;

    public function __construct()
    {
        $this->checker = new EmailChecker();
        $this->sending = Sending::bulk(['List-Unsubscribe' => '<https://a.test/leave/abc>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'], ['ana@a.test', 'subscriber-4242']);
    }

    public function setUp(): void
    {
        $html = '<!doctype html><html lang="en"><head><title>News</title></head><body><table width="600" style="max-width:600px">';
        $text = '';

        for ($article = 1; $article <= 200; ++$article) {
            $html .= '<tr><td style="padding:16px;font-family:Arial,sans-serif;width:568px"><table role="presentation" width="568"><tr>'
                .'<td width="180"><img src="https://cdn.a.test/articles/'.$article.'.jpg" width="180" height="120" alt="Article '.$article.'" style="display:block;width:180px;height:120px"></td>'
                .'<td style="padding-left:12px"><h2 style="margin:0;font-size:20px">Article '.$article.'</h2>'
                .'<p style="margin:8px 0;line-height:1.5">'.str_repeat('A sentence of the article, long enough to read. ', 20).'</p>'
                .'<a href="https://a.test/articles/'.$article.'?utm_source=news" style="color:#0645ad">Read more</a> · '
                .'<a href="mailto:editor@a.test?subject=Article%20'.$article.'">Write to us</a></td></tr></table></td></tr>';
            $text .= 'Article '.$article."\n\nRead more (https://a.test/articles/".$article.'?utm_source=news) · Write to us (editor@a.test?subject=Article%20'.$article.")\n\n";
        }

        $this->html = $html.'</table></body></html>';
        $this->text = $text;
    }

    /**
     * @Revs(5)
     *
     * @Iterations(5)
     */
    public function benchCheckALongNewsletter(): void
    {
        $this->checker->check(new CheckedEmail('Our news of the month', $this->html, $this->text, $this->sending));
    }
}
