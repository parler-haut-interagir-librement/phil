<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Tests\Benchmark;

use Phil\CommerceProductTemplate\Artwork;
use Phil\CommerceProductTemplate\Axis;
use Phil\CommerceProductTemplate\AxisValue;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\Exclusion;
use Phil\CommerceProductTemplate\Face;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\LanguageVersion;
use Phil\CommerceProductTemplate\Pricing;
use Phil\CommerceProductTemplate\PrintZone;
use Phil\CommerceProductTemplate\SkuPattern;
use Phil\CommerceProductTemplate\Template;
use Phil\CommerceProductTemplate\TemplateArray;
use Phil\CommerceProductTemplate\VariantPlanner;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A full shirt template — 18 sizes, 50 colours, 3 versions — planned for a design in two languages: 5 400 products,
 * each generation of a catalogue plans them once; and the template read back from its stored array, as each request
 * that reads a template does.
 */
final readonly class VariantPlannerBench
{
    private Template $template;

    private Design $design;

    /** @var array<string, mixed> */
    private array $stored;

    public function __construct()
    {
        $values = static fn (string $prefix, int $count): array => array_map(static fn (int $index): AxisValue => AxisValue::of($prefix.$index, Label::of(['en' => $prefix.$index]), photos: ['front' => 'blanks/'.$prefix.$index.'.jpg']), range(1, $count));
        $this->template = Template::of(
            'unisex',
            Label::of(['en' => 'Unisex shirt']),
            SkuPattern::parse('{design}{lang}{version}{size}{colour}'),
            Pricing::of(Money::of(2_500, Currency::of('CAD')))->withPrice('size', 'S18', Money::of(2_800, Currency::of('CAD'))),
            [
                Axis::of('version', Label::of(['en' => 'Version']), ...$values('V', 3)),
                Axis::of('size', Label::of(['en' => 'Size']), ...$values('S', 18)),
                Axis::of('colour', Label::of(['en' => 'Colour']), ...$values('C', 50)),
            ],
            [Face::of('front', Label::of(['en' => 'Front']), PrintZone::of('chest', 50, 60, 300, 400))],
            [Exclusion::of(['colour' => ['C1', 'C2'], 'size' => ['S17', 'S18']])],
        );
        $this->design = Design::of('NAQ001', Label::of(['en' => 'Gnome']), [LanguageVersion::of('F', 'fr'), LanguageVersion::of('E', 'en')], [Artwork::of('front', 'art/front.png'), Artwork::of('front', 'art/front-en.png', language: 'E')]);
        $this->stored = TemplateArray::toArray($this->template);
    }

    public function benchPlan(): void
    {
        new VariantPlanner()->plan($this->template, $this->design);
    }

    public function benchReadBack(): void
    {
        TemplateArray::fromArray($this->stored);
    }
}
