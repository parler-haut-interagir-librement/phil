<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\NewsletterArchive\Application\NeutralWebVersion;
use Phil\NewsletterArchive\Tests\Fixture\Stage;
use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The web version holds nothing of a subscriber: no unsubscription or preference link, no placeholder, no tracking
 * pixel, nothing an inline style hides, nothing that runs; what the editor wrote stays.
 */
#[CoversClass(NeutralWebVersion::class)]
final class NeutralWebVersionTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testATemplateWithTheSubscribersAddressesGivesANeutralPage(): void
    {
        $html = $this->stage->renderer->render($this->stage->campaign('c1', 'Hello world', template: 'branded'), $this->stage->lists->get('l1'));

        self::assertSame(
            "<h1>Hello world</h1>\n<p>Hello <strong>readers</strong>.</p>\n<p><a href=\"https://example.com/\">Our site</a></p>\n<p>Sent to News on default.</p>\n\n\n<p>Your link: </p>",
            $html,
        );
    }

    public function testTheHouseLayoutGivesTheBodyAlone(): void
    {
        $html = $this->stage->renderer->render($this->stage->campaign('c1', 'Hello world'), $this->stage->lists->get('l1'));

        self::assertSame("<p>Hello <strong>readers</strong>.</p>\n<p><a href=\"https://example.com/\">Our site</a></p>", $html);
    }

    public function testATemplateThatIsGoneGivesTheBodyAlone(): void
    {
        $html = $this->stage->renderer->render($this->stage->campaign('c1', 'Hello world', template: 'gone'), null);

        self::assertSame("<p>Hello <strong>readers</strong>.</p>\n<p><a href=\"https://example.com/\">Our site</a></p>", $html);
    }

    public function testATemplateWithoutAListNamesNone(): void
    {
        $this->template('<body><p>To {{ list_name }}.</p></body>');

        self::assertSame('<p>To .</p>', $this->stage->renderer->render($this->stage->campaign('c1', 'Hi', template: 'plain'), null));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fragments(): iterable
    {
        yield 'pixel of one' => ['<p>a</p><img src="https://t.example/p.gif" width="1" alt="">', '<p>a</p>'];
        yield 'pixel of zero' => ['<p>a</p><img src="https://t.example/p.gif" height="0" alt="">', '<p>a</p>'];
        yield 'pixel in px' => ['<p>a</p><img src="https://t.example/p.gif" width=" 1px " alt="">', '<p>a</p>'];
        yield 'pixel of 1.0' => ['<p>a</p><img src="https://t.example/p.gif" width="1.0" alt="">', '<p>a</p>'];
        yield 'image of the placeholder' => ['<p>a</p><img src="{{ unsubscribe_url }}" alt="">', '<p>a</p>'];
        yield 'image of ten' => ['<img src="https://example.com/i.png" width="10" alt="A cat">', '<img src="https://example.com/i.png" width="10" alt="A cat" />'];
        yield 'image of 10px' => ['<img src="https://example.com/i.png" height="10px" alt="A cat">', '<img src="https://example.com/i.png" height="10px" alt="A cat" />'];
        yield 'hidden' => ['<p>a</p><div style="color: red; display: none">secret</div>', '<p>a</p>'];
        yield 'hidden first' => ['<p>a</p><span style="DISPLAY:NONE">secret</span>', '<p>a</p>'];
        yield 'shown' => ['<p style="display: block">a</p>', '<p>a</p>'];
        yield 'nondisplay' => ['<p style="x-display: none">a</p>', '<p>a</p>'];
        yield 'link beside text' => ['<p>Leave: <a href="{{ unsubscribe_url }}">here</a></p>', '<p>Leave: </p>'];
        yield 'link in a span' => ['<p><span><a href="{{ unsubscribe_url }}">x</a></span> kept</p>', '<p> kept</p>'];
        yield 'link with an image' => ['<p><a href="{{ unsubscribe_url }}">x</a><img src="https://example.com/i.png" alt="i"></p>', '<p><img src="https://example.com/i.png" alt="i" /></p>'];
        yield 'link in a list' => ['<ul><li><a href="{{ preferences_url }}">Preferences</a></li><li>Kept</li></ul>', '<ul><li>Kept</li></ul>'];
        yield 'link and separator in the body itself' => ['<a href="{{ unsubscribe_url }}">U</a> | ', '|'];
        yield 'separators' => ['<p><a href="{{ preferences_url }}">P</a> | <a href="{{ unsubscribe_url }}">U</a> - –</p>', ''];
        yield 'script' => ['<p>a</p><script>alert(1)</script><p onclick="x()">b</p>', '<p>a</p><p>b</p>'];
        yield 'placeholder in text' => ['<p>{{ unsubscribe_url }}x</p>', '<p>x</p>'];
    }

    #[DataProvider('fragments')]
    public function testWhatATemplateWritesIsNeutralized(string $body, string $expected): void
    {
        $this->template('<!DOCTYPE html><html><body>'.$body.'</body></html>');

        self::assertSame($expected, $this->stage->renderer->render($this->stage->campaign('c1', 'Hi', template: 'plain'), null));
    }

    public function testTheImagesOfTheBodyStay(): void
    {
        $html = $this->stage->renderer->render($this->stage->campaign('c1', 'Hi', body: "Text\n\n![A cat](https://example.com/cat.png)"), null);

        self::assertStringContainsString('<img src="https://example.com/cat.png" alt="A cat"', $html);
    }

    public function testAnEmptyBodyGivesNothing(): void
    {
        $this->template('<!DOCTYPE html><html><head><title>x</title></head></html>');

        self::assertSame('', $this->stage->renderer->render($this->stage->campaign('c1', 'Hi', template: 'plain'), null));
    }

    private function template(string $source): void
    {
        $this->stage->templates->save(new Template('plain', Site::default(), '{{ subject }}', $source, new DateTimeImmutable()));
    }
}
