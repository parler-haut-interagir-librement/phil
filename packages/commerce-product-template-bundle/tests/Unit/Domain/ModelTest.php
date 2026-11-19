<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Domain;

use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\Template;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\GenerationReport;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintElement;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\LootShirts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Shirts;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\PrintCompositor\Box;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductTemplate::class)]
#[CoversClass(ProductDesign::class)]
#[CoversClass(ProductGroup::class)]
#[CoversClass(GroupMember::class)]
#[CoversClass(GenerationReport::class)]
final class ModelTest extends TestCase
{
    public function testATemplateKeepsItsDefinitionKindTaxCategoryAndOptions(): void
    {
        $template = ProductTemplate::create(Shirts::template(), ProductKind::Physical, 'goods');

        self::assertSame('unisex', $template->code);
        self::assertEquals(Shirts::template(), $template->template());
        self::assertSame(ProductKind::Physical, $template->kind());
        self::assertSame('goods', $template->taxCategory());
        self::assertSame([], $template->options());

        $template->redefine(Shirts::template(['WH']));
        self::assertEquals(Shirts::template(['WH']), $template->template());

        $template->classify(ProductKind::Service, str_repeat('t', 64));
        self::assertSame([ProductKind::Service, str_repeat('t', 64)], [$template->kind(), $template->taxCategory()]);

        $ink = TemplateOption::of(OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow')]));
        $loot = TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot'), ['version' => ['L']]);
        $template->declareOptions(...['ink' => $ink, 'loot' => $loot]);
        self::assertEquals([$ink, $loot], $template->options());

        $template->declareOptions();
        self::assertSame([], $template->options());
    }

    public function testATemplateIsRedefinedUnderItsOwnCode(): void
    {
        $other = Template::of('fitted', Label::of(['en' => 'Fitted']), Shirts::template()->skuPattern, Shirts::template()->pricing, Shirts::template()->axes(), Shirts::template()->faces());

        $this->expectExceptionObject(InvalidValue::because('The template unisex is redefined under its own code, not fitted.'));

        Shirts::productTemplate()->redefine($other);
    }

    public function testATaxCategoryHoldsOneToSixtyFourCharacters(): void
    {
        foreach ([static fn (): ProductTemplate => ProductTemplate::create(Shirts::template(), ProductKind::Physical, ''), static function (): void { Shirts::productTemplate()->classify(ProductKind::Physical, str_repeat('t', 65)); }] as $call) {
            try {
                $call();
                self::fail('The tax category is refused.');
            } catch (InvalidValue $invalid) {
                self::assertSame('A tax category holds 1 to 64 characters.', $invalid->getMessage());
            }
        }
    }

    public function testADesignKeepsItsDefinitionAndOptions(): void
    {
        $design = ProductDesign::create(Shirts::design());

        self::assertSame('NAQ001', $design->code);
        self::assertEquals(Shirts::design(), $design->design());
        self::assertSame([], $design->options());

        $design->redefine(Shirts::design(['F']));
        self::assertEquals(Shirts::design(['F']), $design->design());

        $loot = TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot'));
        $design->declareOptions(...['loot' => $loot]);
        self::assertEquals([$loot], $design->options());

        $this->expectExceptionObject(InvalidValue::because('The design NAQ001 is redefined under its own code, not NAQ002.'));

        $design->redefine(Design::of('NAQ002', Label::of(['en' => 'x'])));
    }

    public function testADesignKeepsItsPrintLayoutInOrder(): void
    {
        $design = ProductDesign::create(Shirts::design());
        self::assertSame([], $design->printLayout());

        $design->declarePrintLayout(...['a' => LootShirts::layout()[1], 'b' => LootShirts::layout()[0]]);
        self::assertEquals([LootShirts::layout()[1], LootShirts::layout()[0]], $design->printLayout());

        $element = PrintElement::image('front', 'chest', 'a.png', new Box(0, 0, 1, 1));
        $design->declarePrintLayout(...array_fill(0, ProductDesign::MAX_PRINT_ELEMENTS, $element));
        self::assertCount(64, $design->printLayout());

        $this->expectExceptionObject(InvalidValue::because('A print layout holds at most 64 elements.'));

        $design->declarePrintLayout(...array_fill(0, 65, $element));
    }

    public function testAGroupIsNamedByItsDesignAndTemplate(): void
    {
        $group = ProductGroup::of('NAQ001', 'unisex');

        self::assertSame(['NAQ001:unisex', 'NAQ001', 'unisex', true], [$group->id, $group->design, $group->template, $group->isActive()]);
        self::assertSame('A:b', ProductGroup::idOf('A', 'b'));

        $group->withdraw();
        self::assertFalse($group->isActive());

        $group->restore();
        self::assertTrue($group->isActive());
    }

    public function testAMemberKeepsItsValuesLanguageAndMockups(): void
    {
        $member = GroupMember::of('NAQ001FOMBK', 'NAQ001:unisex', ['size' => 'M', 'colour' => 'BK'], 'F');

        self::assertSame(['NAQ001FOMBK', 'NAQ001:unisex', ['size' => 'M', 'colour' => 'BK'], 'F', true, []], [$member->sku, $member->groupId, $member->values(), $member->language(), $member->isActive(), $member->mockups()]);

        $member->place(['size' => 'L'], null);
        self::assertSame([['size' => 'L'], null], [$member->values(), $member->language()]);

        $member->showMockups(['front' => 'mockups/a.webp', 'back' => 'mockups/b.webp']);
        self::assertSame(['back' => 'mockups/b.webp', 'front' => 'mockups/a.webp'], $member->mockups());

        $member->withdraw();
        self::assertFalse($member->isActive());

        $member->restore();
        self::assertTrue($member->isActive());
    }

    public function testAMemberHasAValueOnOneAxisAtLeast(): void
    {
        foreach ([static fn (): GroupMember => GroupMember::of('A', 'A:b', [], null), static function (): void { GroupMember::of('A', 'A:b', ['size' => 'M'], null)->place([], null); }] as $call) {
            try {
                $call();
                self::fail('A member without values is refused.');
            } catch (InvalidValue $invalid) {
                self::assertSame('The member A has a value on one axis at least.', $invalid->getMessage());
            }
        }
    }

    public function testAReportTellsWhetherItChangedAnything(): void
    {
        self::assertTrue(new GenerationReport()->changedNothing());
        self::assertTrue(new GenerationReport(unchanged: ['A'])->changedNothing());
        self::assertFalse(new GenerationReport(created: ['A'])->changedNothing());
        self::assertFalse(new GenerationReport(updated: ['A'])->changedNothing());
        self::assertFalse(new GenerationReport(restored: ['A'])->changedNothing());
        self::assertFalse(new GenerationReport(withdrawn: ['A'])->changedNothing());
    }
}
