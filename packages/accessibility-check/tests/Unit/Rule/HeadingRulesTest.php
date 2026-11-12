<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Rule;

use Phil\AccessibilityCheck\HeadingPolicy;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Rule\EmptyHeading;
use Phil\AccessibilityCheck\Rule\Heading;
use Phil\AccessibilityCheck\Rule\HeadingH1;
use Phil\AccessibilityCheck\Rule\HeadingOrder;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HeadingOrder::class)]
#[CoversClass(HeadingH1::class)]
#[CoversClass(EmptyHeading::class)]
#[CoversClass(Heading::class)]
final class HeadingRulesTest extends RuleTestCase
{
    private const string PAGE = <<<'HTML'
        <h1>Title</h1><h2>A</h2><h4>Skipped</h4><h3>Back</h3><h2 hidden>Hidden</h2><h5> </h5>
        <div role="heading" aria-level="2">Role</div><div role="heading">Role default</div><h2 aria-level="6">Six</h2><h3 aria-level="x">Three</h3>
        <h1>Again</h1><h1>Third</h1>
        HTML;

    private const array BLOCKS = [
        ['id' => 'a', 'type' => 'header', 'data' => ['text' => 'Intro', 'level' => 1]],
        ['id' => 'b', 'type' => 'header', 'data' => ['text' => '<b>Deep</b>', 'level' => 3]],
        ['id' => 'c', 'type' => 'raw', 'data' => ['html' => '<h1>Raw</h1><h2></h2>']],
        ['id' => 'd', 'type' => 'header', 'data' => ['text' => 'Default']],
        ['id' => 'e', 'type' => 'header', 'data' => ['text' => '', 'level' => '4']],
        ['id' => 'f', 'type' => 'header', 'data' => ['text' => 'x', 'level' => 9]],
        ['id' => 'g', 'type' => 'header', 'data' => ['text' => 'y', 'level' => true]],
    ];

    public function testTheRulesAreDescribed(): void
    {
        self::assertSame(['heading-order', SuccessCriterion::InfoAndRelationships, Severity::Warning], [new HeadingOrder()->id(), new HeadingOrder()->criterion(), new HeadingOrder()->severity()]);
        self::assertSame(['heading-h1', SuccessCriterion::InfoAndRelationships, Severity::Warning], [new HeadingH1()->id(), new HeadingH1()->criterion(), new HeadingH1()->severity()]);
        self::assertSame(['empty-heading', SuccessCriterion::HeadingsAndLabels, Severity::Warning], [new EmptyHeading()->id(), new EmptyHeading()->criterion(), new EmptyHeading()->severity()]);
    }

    public function testAHeadingGoesOneLevelDeeperAtMost(): void
    {
        self::assertSame([
            'heading-order.skipped @ h4 (level=4, previous=2)',
            'heading-order.skipped @ h5 (level=5, previous=3)',
            'heading-order.skipped @ h2:nth-of-type(3) (level=6, previous=2)',
        ], self::html(new HeadingOrder(), self::PAGE));
        self::assertSame(['heading-order.skipped @ h3 (level=3, previous=1)'], self::html(new HeadingOrder(), '<h3>x</h3>', new Policy(headings: HeadingPolicy::None)));
        self::assertSame([], self::html(new HeadingOrder(), '<h3>x</h3><h4>y</h4><h2>z</h2>'));
        self::assertSame([], self::html(new HeadingOrder(), '<h2>a</h2><x-h5>b</x-h5><h5-x>c</h5-x><h3 aria-level="x5">d</h3><h3 aria-level="5x">e</h3>'));
        self::assertSame([], self::html(new HeadingOrder(), '<h2>x</h2>', new Policy(headings: HeadingPolicy::None)));
    }

    public function testTheHeadingsOfADocumentAreItsHeaderBlocksAndTheHeadingsOfItsFields(): void
    {
        self::assertSame([
            'heading-order.skipped @ blocks[1].data.text (level=3, previous=1)',
            'heading-order.skipped @ blocks[4].data.text (level=4, previous=2)',
        ], self::ujo(new HeadingOrder(), self::BLOCKS));
    }

    public function testTheFirstLevelHeadingsFollowThePolicy(): void
    {
        self::assertSame(['heading-h1.multiple @ h1:nth-of-type(2) (position=2)', 'heading-h1.multiple @ h1:nth-of-type(3) (position=3)'], self::html(new HeadingH1(), self::PAGE));
        self::assertSame(['heading-h1.forbidden @ h1:nth-of-type(1)', 'heading-h1.forbidden @ h1:nth-of-type(2)', 'heading-h1.forbidden @ h1:nth-of-type(3)'], self::html(new HeadingH1(), self::PAGE, new Policy(headings: HeadingPolicy::None)));
        self::assertSame([], self::html(new HeadingH1(), self::PAGE, new Policy(headings: HeadingPolicy::Any)));
        self::assertSame(['heading-h1.multiple @ blocks[2].data.html h1 (position=2)'], self::ujo(new HeadingH1(), self::BLOCKS));
        self::assertSame(['heading-h1.forbidden @ blocks[0].data.text', 'heading-h1.forbidden @ blocks[2].data.html h1'], self::ujo(new HeadingH1(), self::BLOCKS, new Policy(headings: HeadingPolicy::None)));
    }

    public function testAWholePageWithoutFirstLevelHeadingIsNoted(): void
    {
        self::assertSame(['heading-h1.missing !notice'], self::html(new HeadingH1(), '<!doctype html><h2>x</h2>'));
        self::assertSame([], self::html(new HeadingH1(), '<!doctype html><h1>x</h1>'));
        self::assertSame([], self::html(new HeadingH1(), '<h2>x</h2>'));
        self::assertSame([], self::html(new HeadingH1(), '<!doctype html><h2>x</h2>', new Policy(headings: HeadingPolicy::None)));
        self::assertSame([], self::html(new HeadingH1(), '<!doctype html><h2>x</h2>', new Policy(headings: HeadingPolicy::Any)));
    }

    public function testAHeadingHasText(): void
    {
        self::assertSame(['empty-heading.empty @ h5 (level=5)'], self::html(new EmptyHeading(), self::PAGE));
        self::assertSame([], self::html(new EmptyHeading(), '<h2><img alt="Logo"></h2><h3 aria-label="Named"></h3>'));
        self::assertSame(['empty-heading.empty @ blocks[2].data.html h2 (level=2)', 'empty-heading.empty @ blocks[4].data.text (level=4)'], self::ujo(new EmptyHeading(), self::BLOCKS));
    }
}
