<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Benchmark;

use Phil\EmailTemplatesBundle\Application\Rendering\HtmlToText;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;

/**
 * What sending one email costs: the render of a default template — the sandbox, Markdown, the layout, the inlined
 * styles, the plain text — with its compiled templates warm, as in a worker, then each step alone.
 */
final readonly class RenderBench
{
    private Stage $stage;

    /** @var array<string, mixed> */
    private array $values;

    private string $html;

    public function __construct()
    {
        $this->stage = new Stage();
        $this->values = [
            'recipient' => ['name' => 'Ada'],
            'item' => ['title' => 'Spring workshop', 'starts_at' => '2026-04-18T19:00:00-04:00', 'location' => '12 Market Street'],
            'reference' => 'A-1042',
            'manage_url' => 'https://example.com/account/bookings/A-1042',
        ];
        $this->html = $this->stage->renderer->render('confirmation', $this->values)->html;
    }

    public function benchRenderAMarkdownTemplate(): void
    {
        $this->stage->renderer->render('confirmation', $this->values, new RenderOptions(segments: ['first-time']));
    }

    public function benchRenderAnHtmlTemplate(): void
    {
        $this->stage->renderer->render('welcome', ['name' => 'Ada', 'link' => 'https://example.com/', 'items' => ['Tea', 'Cake']], new RenderOptions(segments: ['vip']));
    }

    public function benchResolveATemplate(): void
    {
        $this->stage->resolver->resolve(TemplateKey::fromString('confirmation'), Site::fromString('main'), Locale::fromString('fr_CA'));
    }

    public function benchPlainTextFromTheHtml(): void
    {
        HtmlToText::convert($this->html);
    }
}
