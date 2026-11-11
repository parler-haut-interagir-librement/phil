<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class OwnRecipesTest extends KitTestCase
{
    public function testACopyButtonHidesUntilItsControllerRuns(): void
    {
        $html = $this->render('<twig:CopyButton text="<x>" copiedLabel="Fait" />');

        self::assertStringContainsString('data-copy-button-text-value="&lt;x&gt;"', $html);
        self::assertStringContainsString('data-copy-button-copied-label-value="Fait"', $html);
        self::assertStringContainsString('class="isCopyButton" data-controller="copy-button"', $html);
        self::assertStringContainsString('<button type="button" class="isCopyButtonTrigger" hidden data-copy-button-target="trigger" data-action="copy-button#copy">Copy</button>', $html);
        self::assertStringContainsString('<span class="isVisuallyHidden" role="status" aria-live="polite" data-copy-button-target="status">', $html);
    }

    public function testACopyButtonReadsAnotherElement(): void
    {
        self::assertStringContainsString('data-copy-button-source-value="#code"', $this->render('<twig:CopyButton source="#code" />'));
    }

    public function testAnAnimatedButton(): void
    {
        self::assertSame(
            '<button type="button" class="isAnimatedButton hasShine"><span class="isAnimatedButtonLabel">Go</span></button>',
            $this->render('<twig:AnimatedButton>Go</twig:AnimatedButton>'),
        );
    }

    public function testAnAnimatedLinkWithItsArrowAndAnEffectUnknown(): void
    {
        self::assertSame(
            '<a href="/x" class="isAnimatedButton hasArrow isButton"><span class="isAnimatedButtonLabel">Go</span><span class="isAnimatedButtonArrow" aria-hidden="true">&rarr;</span></a>',
            $this->render('<twig:AnimatedButton effect="arrow" href="/x">Go</twig:AnimatedButton>'),
        );
        self::assertStringContainsString('isAnimatedButton hasShine', $this->render('<twig:AnimatedButton effect="other">Go</twig:AnimatedButton>'));
    }

    public function testAnAnimatedButtonWaiting(): void
    {
        $html = $this->render('<twig:AnimatedButton effect="lift" type="submit" :loading="true">Go</twig:AnimatedButton>');

        self::assertStringContainsString('type="submit"', $html);
        self::assertStringContainsString('class="isAnimatedButton hasLift isLoading" aria-busy="true"', $html);
        self::assertStringContainsString('type="button"', $this->render('<twig:AnimatedButton type="other">Go</twig:AnimatedButton>'));
    }

    public function testAStepper(): void
    {
        self::assertSame(
            '<ol class="isStepper" aria-label="Order"><li class="isStepperItem isDone"><span class="isVisuallyHidden">Done:</span><a class="isStepperLabel" href="/a">A</a></li><li class="isStepperItem" aria-current="step"><span class="isStepperLabel">B</span></li><li class="isStepperItem"><span class="isStepperLabel">C</span></li></ol>',
            $this->render('<twig:Stepper label="Order"><twig:Stepper:Item state="done" href="/a" doneLabel="Done:">A</twig:Stepper:Item><twig:Stepper:Item state="current">B</twig:Stepper:Item><twig:Stepper:Item>C</twig:Stepper:Item></twig:Stepper>'),
        );
    }

    public function testAnActivityCalendar(): void
    {
        self::assertSame(
            '<div class="hasActivityCalendar"><ol class="isActivityCalendar" aria-label="Year" tabindex="0"><li class="isActivityCalendarBlank" aria-hidden="true"></li><li class="isActivityCalendarDay" data-level="4" title="Many"><span class="isVisuallyHidden">Many</span></li><li class="isActivityCalendarDay" data-level="0" title="&lt;none&gt;"><span class="isVisuallyHidden">&lt;none&gt;</span></li></ol></div>',
            $this->render('<twig:ActivityCalendar label="Year" :offset="1" :days="[{level: 9, label: \'Many\'}, {level: -1, label: \'<none>\'}]" />'),
        );
    }

    public function testAnActivityCalendarKeepsItsOffsetToAWeekAndDrawsItsLegend(): void
    {
        $html = $this->render('<twig:ActivityCalendar label="Year" :offset="30" :days="[]" lessLabel="Less" moreLabel="More" />');

        self::assertSame(6, substr_count($html, 'isActivityCalendarBlank'));
        self::assertStringContainsString('<p class="isActivityLegend"><span>Less</span><span class="isActivityCalendarDay" data-level="0" aria-hidden="true"></span>', $html);
        self::assertStringContainsString('data-level="4" aria-hidden="true"></span><span>More</span></p>', $html);
        self::assertStringNotContainsString('isActivityLegend', $this->render('<twig:ActivityCalendar label="Year" :days="[]" lessLabel="Less" />'));
        self::assertStringNotContainsString('isActivityCalendarBlank', $this->render('<twig:ActivityCalendar label="Year" :days="[]" />'));
    }

    public function testATreeAndItsBranches(): void
    {
        $html = $this->render('<twig:Tree label="Files"><twig:Tree:Item label="A" :branch="true" :expanded="true" value="a1"><twig:Tree:Item label="B" href="/b" :selected="true" /></twig:Tree:Item></twig:Tree>');

        self::assertStringStartsWith('<ul role="tree" aria-label="Files" data-action="keydown->tree#navigate click->tree#activate" class="isTree" data-controller="tree">', $html);
        self::assertStringContainsString('<li role="treeitem" tabindex="-1" aria-selected="false" aria-expanded="true" data-value="a1" data-tree-target="item" class="isTreeItem"><span class="isTreeRow"><span class="isTreeToggle" aria-hidden="true" data-tree-target="toggle"></span><span class="isTreeLabel">A</span></span><ul class="isTreeGroup" role="group">', $html);
        self::assertStringContainsString('aria-selected="true" data-value="B" data-tree-target="item" class="isTreeItem"><span class="isTreeRow"><a class="isTreeLabel" href="/b" tabindex="-1" data-tree-target="link">B</a></span></li>', $html);
    }

    public function testARatingField(): void
    {
        $html = $this->render('<twig:Rating label="Note" name="note" :value="2" :max="3" clearLabel="None" :required="true" />');

        self::assertStringStartsWith('<fieldset class="isRating"><legend class="isRatingLegend">Note</legend><div class="isRatingStars"><label class="isRatingClear"><input class="isVisuallyHidden" type="radio" name="note" value="0"><span>None</span></label>', $html);
        self::assertStringContainsString('<input class="isVisuallyHidden" type="radio" name="note" value="2" checked required><span class="isRatingIcon" aria-hidden="true">&#9733;</span><span class="isVisuallyHidden">2 of 3</span>', $html);
        self::assertSame(3, substr_count($html, 'class="isRatingStar"'));
    }

    public function testARatingKeepsItsNumberOfStarsAndCanBeDisabled(): void
    {
        $html = $this->render('<twig:Rating label="Note" name="n" :max="99" :disabled="true" />');

        self::assertSame(10, substr_count($html, 'class="isRatingStar"'));
        self::assertStringStartsWith('<fieldset class="isRating" disabled="">', $html);
        self::assertStringNotContainsString('isRatingClear', $html);
        self::assertSame(1, substr_count($this->render('<twig:Rating label="Note" name="n" :max="0" />'), 'class="isRatingStar"'));
    }

    public function testARatingReadOnly(): void
    {
        self::assertSame(
            '<span role="img" aria-label="Average 2.5 of 3" class="isRating isReadonly"><span class="isRatingStar isFilled" aria-hidden="true">&#9733;</span><span class="isRatingStar isFilled" aria-hidden="true">&#9733;</span><span class="isRatingStar isHalf" aria-hidden="true">&#9733;</span></span>',
            $this->render('<twig:Rating label="Average" :value="2.5" :max="3" />'),
        );
    }

    public function testANumberInput(): void
    {
        $html = $this->render('<twig:NumberInput id="n" name="count" :value="2" :min="1" :max="8" :step="2" label="Guests" :required="true" :disabled="true" />');

        self::assertStringContainsString('<input class="isNumberInputField" type="number" inputmode="decimal" step="2" id="n" name="count" value="2" min="1" max="8" aria-label="Guests" required disabled data-number-input-target="input" data-action="input->number-input#refresh change->number-input#refresh">', $html);
        self::assertStringContainsString('hidden tabindex="-1" aria-label="Decrease" data-number-input-target="decrement"', $html);
        self::assertStringContainsString('hidden tabindex="-1" aria-label="Increase" data-number-input-target="increment"', $html);
        self::assertStringContainsString('<input class="isNumberInputField" type="number" inputmode="decimal" step="1" data-number-input-target="input"', $this->render('<twig:NumberInput />'));
    }

    public function testAFileInput(): void
    {
        $html = $this->render('<twig:FileInput name="f" label="Add" hint="PNG" id="i" accept=".png" :multiple="true" :maxSize="10" :required="true" :disabled="true" />');

        self::assertStringContainsString('data-file-input-max-size-value="10"', $html);
        self::assertStringContainsString('data-file-input-item-class="isFileInputItem" data-file-input-dragging-class="isDragging"', $html);
        self::assertStringContainsString('<input class="isFileInputField" type="file" name="f" id="i" accept=".png" multiple required disabled data-file-input-target="input" data-action="change->file-input#list">', $html);
        self::assertStringContainsString('<span class="isFileInputLabel">Add</span><span class="isFileInputHint">PNG</span>', $html);
        self::assertStringContainsString('<button type="button" class="isFileInputClear" hidden data-file-input-target="clear" data-action="file-input#clear">Remove</button>', $html);
        self::assertStringNotContainsString('isFileInputHint', $this->render('<twig:FileInput name="f" label="Add" />'));
    }

    public function testATag(): void
    {
        self::assertSame('<span class="isTag"><span class="isTagLabel">A</span></span>', $this->render('<twig:Tag label="A" />'));
    }

    public function testARemovableTagWithItsField(): void
    {
        self::assertSame(
            '<span class="isTag isDanger" data-controller="tag" data-tag-name-value="7"><span class="isTagLabel">&lt;A&gt;</span><input type="hidden" name="tags[]" value="7"><button type="button" class="isTagRemove" hidden aria-label="Retirer &lt;A&gt;" data-tag-target="remove" data-action="tag#remove"><span aria-hidden="true">&times;</span></button></span>',
            $this->render('<twig:Tag label="<A>" value="7" name="tags[]" :removable="true" removeLabel="Retirer {label}" class="isDanger" />'),
        );
    }

    public function testARelativeTime(): void
    {
        self::assertSame(
            '<time datetime="2026-03-01T10:00:00+00:00" title="2026-03-01" data-relative-time-locale-value="fr" data-relative-time-length-value="short" data-relative-time-limit-value="60" class="isRelativeTime" data-controller="relative-time">2026-03-01</time>',
            $this->render('<twig:RelativeTime :datetime="d" format="Y-m-d" locale="fr" length="short" :limit="60" />', ['d' => new DateTimeImmutable('2026-03-01 10:00:00+00:00')]),
        );
    }

    public function testAProgressRing(): void
    {
        $html = $this->render('<twig:ProgressRing label="Up" :value="150" :max="300" />');

        self::assertStringStartsWith('<div role="progressbar" aria-label="Up" aria-valuemin="0" aria-valuemax="300" aria-valuenow="150" class="isProgressRing">', $html);
        self::assertStringContainsString('stroke-dasharray="50 100"', $html);
        self::assertStringContainsString('<span class="isProgressRingText" aria-hidden="true">50%</span>', $html);
    }

    public function testAProgressRingKeepsWithinItsEnds(): void
    {
        self::assertStringContainsString('aria-valuenow="100"', $this->render('<twig:ProgressRing label="Up" :value="150" />'));
        self::assertStringContainsString('stroke-dasharray="0 100"', $this->render('<twig:ProgressRing label="Up" :value="-5" />'));
        self::assertStringContainsString('>Two</span>', $this->render('<twig:ProgressRing label="Up" :value="2" text="Two" />'));
    }

    public function testAProgressRingNotKnowingHowFar(): void
    {
        $html = $this->render('<twig:ProgressRing label="Up" />');

        self::assertStringContainsString('class="isProgressRing isIndeterminate"', $html);
        self::assertStringNotContainsString('aria-valuenow', $html);
        self::assertStringNotContainsString('isProgressRingText', $html);
    }

    public function testASparkline(): void
    {
        self::assertSame(
            '<svg role="img" aria-label="Up" viewBox="0 0 106 26" width="106" height="26" class="isSparkline"><polyline class="isSparklineLine" points="3,23 53,13 103,3"></polyline><circle class="isSparklineDot" cx="103" cy="3" r="2"></circle></svg>',
            $this->render('<twig:Sparkline label="Up" :values="[1, 2, 3]" :width="106" :height="26" />'),
        );
    }

    public function testASparklineWithItsAreaFlatOrEmpty(): void
    {
        self::assertStringContainsString('<polygon class="isSparklineArea" points="3,24 3,21 93,3 93,24"></polygon>', $this->render('<twig:Sparkline label="Up" :values="[0, 1]" :area="true" />'));
        self::assertStringContainsString('points="3,12 93,12"', $this->render('<twig:Sparkline label="Flat" :values="[5, 5]" />'));
        self::assertStringContainsString('points="48,12"', $this->render('<twig:Sparkline label="One" :values="[5]" :area="true" />'));
        self::assertSame('', $this->render('<twig:Sparkline label="None" :values="[]" />'));
    }

    public function testASparklineKeepsTheLastValues(): void
    {
        self::assertSame(365, substr_count($this->render('<twig:Sparkline label="Year" :values="v" />', ['v' => range(1, 400)]), ','));
    }

    public function testABeforeAfter(): void
    {
        $html = $this->render('<twig:BeforeAfter label="Cut" :position="130"><twig:block name="before"><i>B</i></twig:block><twig:block name="after"><i>A</i></twig:block></twig:BeforeAfter>');

        self::assertSame(
            '<div class="isBeforeAfter" data-controller="before-after"><div class="isBeforeAfterAfter"><i>A</i></div><div class="isBeforeAfterBefore" data-before-after-target="before"><i>B</i></div><input class="isBeforeAfterRange" type="range" min="0" max="100" step="1" value="100" aria-label="Cut" data-before-after-target="range" data-action="input->before-after#move"></div>',
            $html,
        );
    }

    public function testAColourPicker(): void
    {
        $html = $this->render('<twig:ColorPicker label="Accent" id="c" name="col" value="#ABCDEF" :swatches="[\'#112233\', {value: \'#445566\', label: \'Slate\'}]" :disabled="true" />');

        self::assertStringContainsString('<input class="isColorPickerWell" type="color" value="#abcdef" aria-label="Accent" id="c" name="col" disabled data-color-picker-target="well"', $html);
        self::assertStringContainsString('aria-label="#112233" title="#112233" aria-pressed="false" disabled data-color-picker-target="swatch" data-color-picker-color-param="#112233"', $html);
        self::assertStringContainsString('aria-label="Slate" title="Slate"', $html);
    }

    public function testAColourPickerRefusesWhatIsNotAColour(): void
    {
        $html = $this->render('<twig:ColorPicker label="Accent" value="red;x" />');

        self::assertStringContainsString('type="color" value="#000000"', $html);
        self::assertStringNotContainsString('isColorPickerSwatches', $html);
    }

    public function testADatePicker(): void
    {
        $html = $this->render('<twig:DatePicker id="d" name="at" value="2026-03-01" min="2026-01-01" max="2026-12-31" label="At" locale="fr" :firstDay="0" :required="true" :disabled="true" />');

        self::assertStringContainsString('data-date-picker-locale-value="fr" data-date-picker-first-day-value="0"', $html);
        self::assertStringContainsString('data-date-picker-day-class="isDatePickerDay" data-date-picker-weekday-class="isDatePickerWeekday"', $html);
        self::assertStringContainsString('<input class="isDatePickerField" type="date" id="d" name="at" value="2026-03-01" min="2026-01-01" max="2026-12-31" aria-label="At" required disabled data-date-picker-target="input">', $html);
        self::assertStringContainsString('class="isDatePickerTrigger" hidden popovertarget="d-panel" aria-label="Choose a date" aria-haspopup="dialog" aria-expanded="false" disabled', $html);
        self::assertStringContainsString('<div class="isDatePickerPanel" id="d-panel" popover role="dialog" aria-labelledby="d-heading"', $html);
        self::assertStringContainsString('id="d-heading" aria-live="polite"', $html);
    }

    public function testADatePickerTakesTheFirstDayOfItsLanguage(): void
    {
        $html = $this->render('<twig:DatePicker id="d" />');

        self::assertStringContainsString('data-date-picker-locale-value="" data-date-picker-first-day-value="-1"', $html);
        self::assertStringContainsString('<input class="isDatePickerField" type="date" id="d" data-date-picker-target="input">', $html);
    }
}
