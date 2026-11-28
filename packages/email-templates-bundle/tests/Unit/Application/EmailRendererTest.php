<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Application;

use Phil\EmailTemplatesBundle\Application\Contract\StyleInliner;
use Phil\EmailTemplatesBundle\Application\EmailComposer;
use Phil\EmailTemplatesBundle\Application\FixedSiteScope;
use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\EmbeddedImage;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedEmail;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedParts;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;
use Phil\EmailTemplatesBundle\Application\ResolvedTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidVariables;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\NoStyleInliner;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

#[CoversClass(EmailRenderer::class)]
#[CoversClass(RenderContext::class)]
#[CoversClass(RenderedParts::class)]
#[CoversClass(RenderedEmail::class)]
#[CoversClass(RenderOptions::class)]
#[CoversClass(EmbeddedImage::class)]
#[CoversClass(EmailComposer::class)]
#[CoversClass(ResolvedTemplate::class)]
#[CoversClass(FixedSiteScope::class)]
final class EmailRendererTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testATemplateRendersIntoTheLayoutWithItsStylesInlined(): void
    {
        $email = $this->stage->renderer->render('welcome', ['name' => 'Ada', 'link' => 'https://example.com/go', 'items' => ['Tea']], new RenderOptions(segments: ['vip', 'gold']));

        self::assertSame('welcome', $email->key->value);
        self::assertSame('main', $email->site->value);
        self::assertSame('en', $email->locale->value);
        self::assertNull($email->version);
        self::assertSame(TemplateOrigin::Default, $email->origin);
        self::assertSame('Welcome, Ada', $email->subject);
        self::assertSame('Your account is ready.', $email->preheader);
        self::assertSame(['vip'], $email->segments, 'A segment the template does not declare is dropped.');
        self::assertStringContainsString('<title>Welcome, Ada</title>', $email->html);
        self::assertStringContainsString('<p style="margin: 0 0 16px;">Hello Ada.</p>', $email->html);
        self::assertStringContainsString('<p class="vip" style="margin: 0 0 16px;">Thank you for being a VIP.</p>', $email->html);
        self::assertStringNotContainsString('Welcome aboard', $email->html);
        self::assertStringContainsString('<img src="cid:logo" alt="Logo" width="40"', $email->html);
        self::assertStringContainsString('@media (prefers-color-scheme: dark)', $email->html);
        self::assertStringContainsString('<meta name="color-scheme" content="light dark">', $email->html);
        self::assertSame("Hello Ada.\n\n[Logo]\n\nThank you for being a VIP.\n\nStart (https://example.com/go)\n\nTea\n", $email->text);
        self::assertCount(1, $email->embeds);
        self::assertSame('logo', $email->embeds[0]->name);
        self::assertSame('image/png', $email->embeds[0]->contentType);
        self::assertFalse($email->isClippedByGmail());
    }

    public function testTheLocaleFallsBackToItsLanguageAndAMarkdownBodyIsHtml(): void
    {
        $email = $this->stage->renderer->render(TemplateKey::fromString('welcome'), ['name' => 'Ada'], new RenderOptions(Site::fromString('other'), 'fr-CA'));

        self::assertSame('fr', $email->locale->value);
        self::assertSame('other', $email->site->value);
        self::assertSame('Bienvenue, Ada', $email->subject);
        self::assertStringContainsString('Bonjour <strong>Ada</strong>.', $email->html);
        self::assertSame("Bonjour Ada.\n", $email->text);
        self::assertSame([], $email->embeds);
    }

    public function testAHandWrittenTextPartAndADefaultValue(): void
    {
        $email = $this->stage->renderer->render('plain');

        self::assertSame('Plain 7', $email->subject);
        self::assertSame("Code: 7\n", $email->text);
    }

    public function testTheValuesAreCheckedAgainstTheSchema(): void
    {
        $this->expectException(InvalidVariables::class);
        $this->expectExceptionMessageIsOrContains('"name" is required');

        $this->stage->renderer->render('welcome', ['link' => 'https://example.com/']);
    }

    public function testAnUnknownTemplate(): void
    {
        $this->expectException(UnknownTemplate::class);

        $this->stage->renderer->render('nothing');
    }

    public function testAVersionOfTheSiteIsRendered(): void
    {
        $this->stage->publish('plain', 'en', TemplateDefinition::fromArray(['subject' => 'V1 {{ code }}', 'body' => '<p>1</p>', 'variables' => ['code' => ['type' => 'integer', 'default' => 1]]]));
        $this->stage->publish('plain', 'en', TemplateDefinition::fromArray(['subject' => 'V2 {{ code }}', 'body' => '<p>2</p>', 'variables' => ['code' => ['type' => 'integer', 'default' => 1]]]));
        $this->stage->templates->save('plain', 'en', TemplateDefinition::fromArray(['subject' => 'V3 {{ code }}', 'body' => '<p>3</p>', 'variables' => ['code' => ['type' => 'integer', 'default' => 1]]]), 2);

        self::assertSame('V2 1', $this->stage->renderer->render('plain')->subject);
        $first = $this->stage->renderer->render('plain', [], new RenderOptions(version: 1));
        self::assertSame('V1 1', $first->subject);
        self::assertSame(1, $first->version);
        self::assertSame(TemplateOrigin::Site, $first->origin);
    }

    public function testAnEmbeddedImageNeedsALoader(): void
    {
        $renderer = new EmailRenderer($this->stage->resolver, $this->stage->engine, Stage::layout($this->stage->theme), new NoStyleInliner(), new FixedSiteScope(Site::shared()));

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The template embeds the image "logo", and no images directory is configured.');
        $renderer->render('welcome', ['name' => 'Ada']);
    }

    public function testAnEmailLargerThanTheCeilingIsRefused(): void
    {
        $stage = new Stage(maxHtmlBytes: 2000);

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The email renders more than 1 KiB of HTML.');
        $stage->renderer->render('plain');
    }

    public function testTheLayoutReceivesTheRenderAndTheInlinerTheDocument(): void
    {
        $inliner = new class implements StyleInliner {
            public ?string $seen = null;

            public function inline(string $html): string
            {
                $this->seen = $html;

                return $html.'<!-- inlined -->';
            }
        };
        $renderer = new EmailRenderer($this->stage->resolver, $this->stage->engine, Stage::layout($this->stage->theme, '@Fixtures/bare.html.twig', ['name' => 'Brand', 'logo' => null]), $inliner, new FixedSiteScope(Site::fromString('main')));
        $email = $renderer->render('plain', ['code' => 3]);

        self::assertSame("<html><body><h1>Plain 3</h1><div><p>Code 3</p></div><p>main|en||Brand</p></body></html>\n", $inliner->seen);
        self::assertStringEndsWith('<!-- inlined -->', $email->html);
    }

    public function testSamplesRenderTheTemplateAsThePreviewDoes(): void
    {
        $template = $this->stage->resolver->resolve(TemplateKey::fromString('welcome'), Site::shared(), Locale::fromString('en'));
        $email = $this->stage->renderer->renderSamples($template, Site::shared(), ['new']);

        self::assertSame('Welcome, Ada', $email->subject);
        self::assertStringContainsString('Welcome aboard.', $email->text);
        self::assertStringContainsString('Cake', $email->text);
    }

    public function testAnEmailForGmailAndForABrowser(): void
    {
        $image = new EmbeddedImage('logo', 'PNG', 'image/png');
        $email = new RenderedEmail(TemplateKey::fromString('t'), Site::shared(), Locale::fromString('en'), null, TemplateOrigin::Default, 's', 'p', '<img src="cid:logo"><img src=\'cid:logo\'><img src="cid:logo2">'.str_repeat('a', 10), 't', [$image]);

        self::assertSame('data:image/png;base64,UE5H', $image->dataUri());
        self::assertSame('<img src="data:image/png;base64,UE5H"><img src=\'data:image/png;base64,UE5H\'><img src="cid:logo2">aaaaaaaaaa', $email->htmlForBrowser());
        self::assertSame('<p>x</p>', new RenderedEmail(TemplateKey::fromString('t'), Site::shared(), Locale::fromString('en'), null, TemplateOrigin::Default, 's', 'p', '<p>x</p>', 't')->htmlForBrowser());
        self::assertFalse(new RenderedEmail(TemplateKey::fromString('t'), Site::shared(), Locale::fromString('en'), null, TemplateOrigin::Default, 's', 'p', str_repeat('a', RenderedEmail::GMAIL_CLIP_BYTES), 't')->isClippedByGmail());
        self::assertTrue(new RenderedEmail(TemplateKey::fromString('t'), Site::shared(), Locale::fromString('en'), null, TemplateOrigin::Default, 's', 'p', str_repeat('a', RenderedEmail::GMAIL_CLIP_BYTES + 1), 't')->isClippedByGmail());
    }

    public function testAComposedEmailIsReadyToSend(): void
    {
        $composer = new EmailComposer($this->stage->renderer);
        $email = $composer->compose('welcome', ['name' => 'Ada'], new RenderOptions(segments: ['new']));

        self::assertSame('Welcome, Ada', $email->getSubject());
        self::assertIsString($email->getHtmlBody());
        self::assertStringContainsString('Welcome aboard.', (string) $email->getTextBody());
        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertInstanceOf(DataPart::class, $attachments[0]);
        self::assertSame('logo', $attachments[0]->getFilename());
        self::assertSame('inline', $attachments[0]->getDisposition());
        self::assertSame('image/png', $attachments[0]->getMediaType().'/'.$attachments[0]->getMediaSubtype());

        self::assertSame('"Shop" <shop@example.com>', new EmailComposer($this->stage->renderer, new Address('shop@example.com', 'Shop'))->compose('plain')->getFrom()[0]->toString());
        self::assertSame('own@example.com', new EmailComposer($this->stage->renderer, new Address('shop@example.com'))->compose('plain', email: new Email()->from('own@example.com'))->getFrom()[0]->getAddress());
        $given = new Email()->from('shop@example.com');
        self::assertSame($given, $composer->compose('plain', [], new RenderOptions(), $given));
        self::assertSame('Plain 7', $given->getSubject());
        self::assertSame([], $given->getAttachments());
        $email->to('ada@example.com')->from('shop@example.com');
        self::assertStringContainsString('Content-ID: <', $email->toString(), 'The image is attached inline with a content identifier.');
        self::assertStringNotContainsString('cid:logo"', $email->toString(), 'The HTML names the image by its content identifier.');
    }
}
