<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit\Preview;

use Override;
use Phil\EmailPreview\EmailChecker;
use Phil\EmailPreview\Finding;
use Phil\EmailPreview\Preview\CheckedPreview;
use Phil\EmailPreview\Preview\PreviewChecks;
use Phil\EmailPreview\Rule\OneClickUnsubscribe;
use Phil\EmailPreview\Rule\TextPart;
use Phil\EmailPreview\Sending;
use Phil\EmailTemplatesBundle\Application\Contract\PreviewRenderer;
use Phil\EmailTemplatesBundle\Application\Preview\PreviewVariant;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedEmail;
use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PreviewChecks::class)]
#[CoversClass(CheckedPreview::class)]
final class PreviewChecksTest extends TestCase
{
    public function testEachVariantIsRenderedAndChecked(): void
    {
        $renderer = new class implements PreviewRenderer {
            /** @var list<bool> */
            public array $drafts = [];

            #[Override]
            public function variants(TemplateKey $key, Site $site, Locale $locale, bool $draft = false): array
            {
                $this->drafts[] = $draft;

                return [new PreviewVariant($key, $site, $locale, draft: $draft), new PreviewVariant($key, $site, $locale, ['vip'], draft: $draft)];
            }

            #[Override]
            public function render(PreviewVariant $variant): RenderedEmail
            {
                $text = [] === $variant->segments ? 'Hi' : '';

                return new RenderedEmail($variant->key, $variant->site, $variant->locale, null, TemplateOrigin::Default, 'Hi '.$variant->segmentList(), '', '<p>Hi</p>', $text);
            }
        };
        $key = TemplateKey::fromString('welcome');
        $site = Site::shared();
        $locale = Locale::fromString('en');

        $previews = new PreviewChecks($renderer, new EmailChecker([new TextPart()]))->check($key, $site, $locale, draft: true);

        self::assertSame([true], $renderer->drafts);
        self::assertCount(2, $previews);
        self::assertSame([], $previews[0]->variant->segments);
        self::assertSame('Hi ', $previews[0]->email->subject);
        self::assertTrue($previews[0]->report->passes());
        self::assertSame(['vip'], $previews[1]->variant->segments);
        self::assertSame('Hi vip', $previews[1]->email->subject);
        self::assertSame(['text-part'], array_map(static fn (Finding $finding): string => $finding->rule, $previews[1]->report->findings));

        new PreviewChecks($renderer)->check($key, $site, $locale);
        self::assertSame([true, false], $renderer->drafts);
    }

    public function testTheSendingIsGivenToEachCheck(): void
    {
        $renderer = new class implements PreviewRenderer {
            #[Override]
            public function variants(TemplateKey $key, Site $site, Locale $locale, bool $draft = false): array
            {
                return [new PreviewVariant($key, $site, $locale)];
            }

            #[Override]
            public function render(PreviewVariant $variant): RenderedEmail
            {
                return new RenderedEmail($variant->key, $variant->site, $variant->locale, null, TemplateOrigin::Default, 'Hi', '', '<p>Hi</p>', 'Hi');
            }
        };
        $checks = new PreviewChecks($renderer, new EmailChecker([new OneClickUnsubscribe()]));
        $key = TemplateKey::fromString('news');

        self::assertCount(2, $checks->check($key, Site::shared(), Locale::fromString('en'), sending: Sending::bulk())[0]->report->findings);
        self::assertCount(0, $checks->check($key, Site::shared(), Locale::fromString('en'))[0]->report->findings);
        self::assertSame(['text-part', 'one-click-unsubscribe', 'tracking-pixel', 'no-style-element', 'max-width', 'subject-length'], new PreviewChecks($renderer)->check($key, Site::shared(), Locale::fromString('en'))[0]->report->rules);
    }
}
