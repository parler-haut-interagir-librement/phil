<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Application;

use Phil\EmailTemplatesBundle\Application\Preview\PreviewVariant;
use Phil\EmailTemplatesBundle\Application\Preview\SamplePreviews;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SamplePreviews::class)]
#[CoversClass(PreviewVariant::class)]
final class PreviewTest extends TestCase
{
    public function testEverySegmentAloneThenAllOfThem(): void
    {
        $stage = new Stage();
        $variants = $stage->previews->variants(TemplateKey::fromString('welcome'), Site::fromString('main'), Locale::fromString('en'));

        self::assertSame(['', 'vip', 'new', 'vip,new'], array_map(static fn (PreviewVariant $variant): string => $variant->segmentList(), $variants));
        self::assertSame(['vip', 'new'], $variants[3]->segments);
        self::assertNull($variants[0]->version);

        $email = $stage->previews->render($variants[2]);
        self::assertStringContainsString('Welcome aboard.', $email->text);
        self::assertStringNotContainsString('VIP', $email->text);
    }

    public function testOneSegmentHasNoCombinedVariant(): void
    {
        $stage = new Stage();
        $stage->publish('plain', 'en', TemplateDefinition::fromArray(['subject' => 'V1', 'body' => '<p>{% if segment("a") %}A{% endif %}</p>', 'segments' => ['a']]));

        self::assertCount(2, $stage->previews->variants(TemplateKey::fromString('plain'), Site::fromString('main'), Locale::fromString('en')));
        self::assertCount(1, $stage->previews->variants(TemplateKey::fromString('plain'), Site::fromString('other'), Locale::fromString('en')));
    }

    public function testAVersionIsPreviewed(): void
    {
        $stage = new Stage();
        $stage->publish('plain', 'en', TemplateDefinition::fromArray(['subject' => 'V1', 'body' => '<p>1</p>']));
        $stage->publish('plain', 'en', TemplateDefinition::fromArray(['subject' => 'V2', 'body' => '<p>2</p>']));
        $stage->templates->save('plain', 'en', TemplateDefinition::fromArray(['subject' => 'V3', 'body' => '<p>3</p>']), 2);

        self::assertSame('V1', $stage->previews->render(new PreviewVariant(TemplateKey::fromString('plain'), Site::fromString('main'), Locale::fromString('en'), [], 1))->subject);
        self::assertSame('V2', $stage->previews->render(new PreviewVariant(TemplateKey::fromString('plain'), Site::fromString('main'), Locale::fromString('en')))->subject);
        self::assertSame('V3', $stage->previews->render(new PreviewVariant(TemplateKey::fromString('plain'), Site::fromString('main'), Locale::fromString('en'), draft: true))->subject);
        self::assertSame('V2', $stage->previews->render(new PreviewVariant(TemplateKey::fromString('plain'), Site::fromString('main'), Locale::fromString('en'), [], 2, true))->subject, 'A version wins over the draft.');
    }

    public function testTheVariantsOfADraft(): void
    {
        $stage = new Stage();
        $stage->templates->save('plain', 'en', TemplateDefinition::fromArray(['subject' => 'Draft', 'body' => '<p>{% if segment("a") %}A{% endif %}{% if segment("b") %}B{% endif %}</p>', 'segments' => ['a', 'b']]), 0);

        $key = TemplateKey::fromString('plain');
        $main = Site::fromString('main');
        $en = Locale::fromString('en');

        self::assertCount(1, $stage->previews->variants($key, $main, $en));
        $variants = $stage->previews->variants($key, $main, $en, true);
        self::assertSame([[], ['a'], ['b'], ['a', 'b']], array_map(static fn (PreviewVariant $variant): array => $variant->segments, $variants));
        self::assertSame([true, true, true, true], array_map(static fn (PreviewVariant $variant): bool => $variant->draft, $variants));
        self::assertSame([false], array_map(static fn (PreviewVariant $variant): bool => $variant->draft, $stage->previews->variants($key, $main, $en)));
        self::assertSame('Draft', $stage->previews->render($variants[3])->subject);
    }
}
