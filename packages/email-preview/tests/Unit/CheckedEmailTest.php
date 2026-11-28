<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit;

use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Exception\EmailTooLarge;
use Phil\EmailPreview\Sending;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedEmail;
use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

#[CoversClass(CheckedEmail::class)]
#[CoversClass(EmailTooLarge::class)]
final class CheckedEmailTest extends TestCase
{
    public function testTheHtmlIsParsedOnce(): void
    {
        $email = new CheckedEmail('Hi', '<p>One<p>Two', 'One Two');

        self::assertSame(2, $email->document->getElementsByTagName('p')->length);
        self::assertSame('Hi', $email->subject);
        self::assertSame('One Two', $email->text);
        self::assertFalse($email->sending->bulk);
    }

    public function testAnEmptyHtmlIsAnEmptyDocument(): void
    {
        self::assertSame(0, new CheckedEmail('Hi', '', 'Text')->document->getElementsByTagName('a')->length);
    }

    public function testAnHtmlLargerThanTheLimitIsRefused(): void
    {
        self::assertSame(CheckedEmail::MAX_HTML_BYTES, \strlen(new CheckedEmail('Hi', str_repeat('a', CheckedEmail::MAX_HTML_BYTES), '')->html));

        try {
            new CheckedEmail('Hi', str_repeat('a', CheckedEmail::MAX_HTML_BYTES + 1), '');
            self::fail('An HTML larger than the limit is not parsed.');
        } catch (EmailTooLarge $emailTooLarge) {
            self::assertSame(\sprintf('The HTML of the email holds %d bytes; at most %d are checked.', CheckedEmail::MAX_HTML_BYTES + 1, CheckedEmail::MAX_HTML_BYTES), $emailTooLarge->getMessage());
        }
    }

    public function testARenderedPreviewIsReadAsItWouldBeSent(): void
    {
        $rendered = new RenderedEmail(TemplateKey::fromString('welcome'), Site::shared(), Locale::fromString('en'), null, TemplateOrigin::Default, 'Welcome', 'Pre', '<a href="https://a.test/">A</a>', 'A (https://a.test/)');
        $sending = Sending::bulk();
        $email = CheckedEmail::fromRendered($rendered, $sending);

        self::assertSame('Welcome', $email->subject);
        self::assertSame('<a href="https://a.test/">A</a>', $email->html);
        self::assertSame('A (https://a.test/)', $email->text);
        self::assertSame($sending, $email->sending);
        self::assertFalse(CheckedEmail::fromRendered($rendered)->sending->bulk);
    }

    public function testAMimeEmailGivesItsHeadersAndRecipients(): void
    {
        $mime = new Email()
            ->subject('News')
            ->to('ana@a.test')
            ->cc('bob@a.test')
            ->bcc('cy@a.test')
            ->html('<p>News</p>')
            ->text('News');
        $mime->getHeaders()->addTextHeader('List-Unsubscribe', '<https://a.test/leave>');
        $mime->getHeaders()->addTextHeader('List-Unsubscribe', '<https://a.test/other>');

        $email = CheckedEmail::fromMime($mime, bulk: true);

        self::assertSame('News', $email->subject);
        self::assertSame('<p>News</p>', $email->html);
        self::assertSame('News', $email->text);
        self::assertTrue($email->sending->bulk);
        self::assertSame('<https://a.test/leave>', $email->sending->header('list-unsubscribe'));
        self::assertSame(['ana@a.test', 'bob@a.test', 'cy@a.test'], $email->sending->recipientTokens);
        self::assertFalse(CheckedEmail::fromMime($mime)->sending->bulk);
    }

    public function testAMimeEmailWithoutPartsOrWithStreamsIsRead(): void
    {
        $empty = CheckedEmail::fromMime(new Email());

        self::assertSame('', $empty->subject);
        self::assertSame('', $empty->html);
        self::assertSame('', $empty->text);

        $html = fopen('php://memory', 'r+');
        $text = fopen('php://memory', 'r+');
        self::assertIsResource($html);
        self::assertIsResource($text);
        fwrite($html, '<p>Streamed</p>');
        fwrite($text, 'Streamed');

        $email = CheckedEmail::fromMime(new Email()->html($html)->text($text));

        self::assertSame('<p>Streamed</p>', $email->html);
        self::assertSame('Streamed', $email->text);
    }

    public function testAStreamedHtmlLargerThanTheLimitIsRefused(): void
    {
        $html = fopen('php://memory', 'r+');
        self::assertIsResource($html);
        fwrite($html, str_repeat('a', CheckedEmail::MAX_HTML_BYTES + 2));

        try {
            CheckedEmail::fromMime(new Email()->html($html));
            self::fail('A stream is read no further than one byte past the limit, then refused.');
        } catch (EmailTooLarge $emailTooLarge) {
            self::assertStringContainsString(\sprintf('holds %d bytes', CheckedEmail::MAX_HTML_BYTES + 1), $emailTooLarge->getMessage());
        }
    }
}
