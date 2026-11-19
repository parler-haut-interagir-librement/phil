<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Application;

use Override;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceProductTemplateBundle\Application\TemplateOptions;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryDesigns;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryGroups;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryTemplates;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Shirts;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateOptions::class)]
final class TemplateOptionsTest extends TestCase
{
    private InMemoryTemplates $templates;

    private InMemoryDesigns $designs;

    private InMemoryGroups $groups;

    private TemplateOptions $options;

    #[Override]
    protected function setUp(): void
    {
        $template = Shirts::productTemplate();
        $template->declareOptions(
            TemplateOption::of(OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow', supplement: Shirts::cad(300))]), ['colour' => ['BK']]),
            TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot-6d6', Shirts::cad(1_000)), ['version' => ['C']]),
            TemplateOption::of(OptionDefinition::choice('gift', 'Gift wrap', [OptionChoice::of('yes', 'Yes')])),
        );
        $design = Shirts::productDesign();
        $design->declareOptions(TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot-gnome', Shirts::cad(1_000)), ['version' => ['C']]));

        $this->templates = new InMemoryTemplates($template);
        $this->designs = new InMemoryDesigns($design);
        $this->groups = new InMemoryGroups();
        $this->groups->add(ProductGroup::of('NAQ001', 'unisex'));
        $this->groups->addMember(GroupMember::of('NAQ001FCMBK', 'NAQ001:unisex', ['version' => 'C', 'size' => 'M', 'colour' => 'BK'], 'F'));
        $this->groups->addMember(GroupMember::of('NAQ001FOMPK', 'NAQ001:unisex', ['version' => 'O', 'size' => 'M', 'colour' => 'PK'], 'F'));

        $this->options = new TemplateOptions($this->groups, $this->templates, $this->designs, 'product');
    }

    public function testItDeclaresTheOptionsOfTheConfiguredType(): void
    {
        self::assertTrue($this->options->supports('product'));
        self::assertFalse($this->options->supports('offer'));
        self::assertTrue(new TemplateOptions($this->groups, $this->templates, $this->designs, 'shirt')->supports('shirt'));
    }

    public function testAProductHasTheOptionsWhoseConditionItsValuesMeetTheDesignsReplacingTheTemplates(): void
    {
        $options = $this->options->optionsOf(SellableReference::of('product', 'NAQ001FCMBK'), Currency::of('CAD'));

        self::assertSame(['ink', 'gift', 'loot'], array_map(static fn (OptionDefinition $option): string => $option->code, $options->options()));
        self::assertSame('loot-gnome', $options->option('loot')?->drawer);

        $pink = $this->options->optionsOf(SellableReference::of('product', 'NAQ001FOMPK'), Currency::of('CAD'));

        self::assertSame(['gift'], array_map(static fn (OptionDefinition $option): string => $option->code, $pink->options()));
    }

    public function testAProductOutsideAnyGroupHasNoOptions(): void
    {
        self::assertTrue($this->options->optionsOf(SellableReference::of('product', 'HANDMADE'), Currency::of('CAD'))->isEmpty());

        $this->groups->addMember(GroupMember::of('LOST', 'GONE:unisex', ['size' => 'M'], null));
        self::assertTrue($this->options->optionsOf(SellableReference::of('product', 'LOST'), Currency::of('CAD'))->isEmpty());
    }

    public function testAGroupWhoseTemplateAndDesignAreGoneHasNoOptions(): void
    {
        $options = new TemplateOptions($this->groups, new InMemoryTemplates(), new InMemoryDesigns(), 'product');

        self::assertTrue($options->optionsOf(SellableReference::of('product', 'NAQ001FCMBK'), Currency::of('CAD'))->isEmpty());
    }

    public function testASupplementInAnotherCurrencyIsRefused(): void
    {
        $this->expectExceptionObject(TemplateRefused::currency('The option ink', 'CAD', 'USD'));

        $this->options->optionsOf(SellableReference::of('product', 'NAQ001FCMBK'), Currency::of('USD'));
    }

    public function testTheSupplementOfAnOptionItselfIsCheckedToo(): void
    {
        $this->templates->find('unisex')?->declareOptions(TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot', Money::of(1, Currency::of('EUR')))));

        $this->expectExceptionObject(TemplateRefused::currency('The option loot', 'EUR', 'CAD'));

        new TemplateOptions($this->groups, $this->templates, new InMemoryDesigns(), 'product')->optionsOf(SellableReference::of('product', 'NAQ001FOMPK'), Currency::of('CAD'));
    }
}
