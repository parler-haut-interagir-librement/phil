<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The recipes of the Shadcn kit written on native elements: they work without a controller of their own.
 */
#[CoversNothing]
final class NativeRecipesTest extends KitTestCase
{
    public function testAnAlertDialogIsDismissedByEscapeButNotByTheBackdrop(): void
    {
        $html = $this->render('<twig:AlertDialog id="del"><twig:AlertDialog:Trigger><twig:Button {{ ...alert_dialog_trigger_attrs }}>Delete</twig:Button></twig:AlertDialog:Trigger><twig:AlertDialog:Content><twig:AlertDialog:Header><twig:AlertDialog:Title>Sure?</twig:AlertDialog:Title><twig:AlertDialog:Description>Gone.</twig:AlertDialog:Description></twig:AlertDialog:Header><twig:AlertDialog:Footer><twig:AlertDialog:Cancel>No</twig:AlertDialog:Cancel><twig:AlertDialog:Action variant="destructive" type="submit" form="f">Yes</twig:AlertDialog:Action></twig:AlertDialog:Footer></twig:AlertDialog:Content></twig:AlertDialog>');

        self::assertStringContainsString('<div data-slot="alert-dialog" data-dialog-open-value="false" class="hasDialog" data-controller="dialog">', $html);
        self::assertStringContainsString('data-dialog-target="trigger" aria-haspopup="dialog" command="show-modal" commandfor="alert-dialog-del">Delete</button>', $html);
        self::assertStringContainsString('<dialog id="alert-dialog-del" role="alertdialog" closedby="closerequest" aria-labelledby="alert-dialog-del-title" aria-describedby="alert-dialog-del-description" data-slot="alert-dialog-content" data-dialog-target="dialog" class="isDialog" data-action="close-&gt;dialog#closed">', $html);
        self::assertStringNotContainsString('closeOnClickOutside', $html);
        self::assertStringContainsString('<header data-slot="alert-dialog-header" class="isDialogHeader"><h2 id="alert-dialog-del-title" data-slot="alert-dialog-title" class="isDialogTitle">Sure?</h2><p id="alert-dialog-del-description" data-slot="alert-dialog-description" class="isDialogDescription">Gone.</p></header>', $html);
        self::assertStringContainsString('<button data-slot="alert-dialog-cancel" data-variant="outline" data-size="default" class="isOutline" type="button" autofocus="" data-action="click-&gt;dialog#close" command="close" commandfor="alert-dialog-del">No</button>', $html);
        self::assertStringContainsString('<button data-slot="alert-dialog-action" data-variant="destructive" data-size="default" class="isDanger" type="submit" data-action="click-&gt;dialog#close" command="close" commandfor="alert-dialog-del" form="f">Yes</button>', $html);
        self::assertStringContainsString('data-dialog-open-value="true"', $this->render('<twig:AlertDialog id="x" :open="true" />'));
    }

    public function testAnAspectRatioKeepsANumberOrAQuotientAndNothingElse(): void
    {
        self::assertSame(
            '<div data-slot="aspect-ratio" class="isAspectRatio" style="--phil-aspect-ratio: 16 / 9"><img src="a" alt=""></div>',
            $this->render('<twig:AspectRatio ratio="16 / 9"><img src="a" alt=""></twig:AspectRatio>'),
        );
        self::assertStringContainsString('style="--phil-aspect-ratio: 1.5"', $this->render('<twig:AspectRatio :ratio="1.5" />'));
        self::assertStringContainsString('style="--phil-aspect-ratio: 1"', $this->render('<twig:AspectRatio ratio="1;color:red" />'));
        self::assertStringContainsString('style="--phil-aspect-ratio: 1"', $this->render('<twig:AspectRatio ratio="4/3 " />'));
        self::assertStringContainsString('style="--phil-aspect-ratio: 1"', $this->render('<twig:AspectRatio :ratio="[4, 3]" />'));
        self::assertStringContainsString('style="--phil-aspect-ratio: 1"', $this->render('<twig:AspectRatio />'));
    }

    public function testACollapsible(): void
    {
        self::assertSame(
            '<details data-slot="collapsible" open class="isCollapsible"><summary data-slot="collapsible-trigger" class="isCollapsibleTrigger">More</summary><div data-slot="collapsible-content" class="isCollapsibleContent">Text</div></details>',
            $this->render('<twig:Collapsible :open="true"><twig:Collapsible:Trigger>More</twig:Collapsible:Trigger><twig:Collapsible:Content>Text</twig:Collapsible:Content></twig:Collapsible>'),
        );
        self::assertSame('<details data-slot="collapsible" inert class="isCollapsible"></details>', $this->render('<twig:Collapsible :disabled="true" />'));
    }

    public function testAKeyboardShortcut(): void
    {
        self::assertSame(
            '<kbd data-slot="kbd-group" class="isKbdGroup"><kbd data-slot="kbd">Ctrl</kbd> + <kbd data-slot="kbd">K</kbd></kbd>',
            $this->render('<twig:Kbd:Group><twig:Kbd>Ctrl</twig:Kbd> + <twig:Kbd>K</twig:Kbd></twig:Kbd:Group>'),
        );
    }

    public function testAFieldWithItsLabelHelpAndError(): void
    {
        self::assertSame(
            '<div role="group" data-slot="field" data-orientation="vertical" data-invalid="true" class="isField isDanger"><label data-slot="field-label" class="isFieldLabel" for="e">Email</label><input data-slot="input" id="e"><p data-slot="field-description" class="isFieldDescription">We write.</p><div role="alert" data-slot="field-error" class="isFieldError"><p>Bad</p></div></div>',
            $this->render('<twig:Field :invalid="true"><twig:Field:Label for="e">Email</twig:Field:Label><twig:Input id="e" /><twig:Field:Description>We write.</twig:Field:Description><twig:Field:Error :errors="[\'Bad\']" /></twig:Field>'),
        );
        self::assertStringContainsString('data-orientation="horizontal" class="isField isHorizontal"', $this->render('<twig:Field orientation="horizontal" />'));
        self::assertStringContainsString('data-orientation="responsive" class="isField isResponsive"', $this->render('<twig:Field orientation="responsive" />'));
        self::assertStringContainsString('data-orientation="vertical" class="isField"', $this->render('<twig:Field orientation="diagonal" />'));
    }

    public function testAFieldErrorListsSeveralWritesOneAndNothingForNone(): void
    {
        self::assertSame(
            '<div role="alert" data-slot="field-error" class="isFieldError"><ul><li>A</li><li>&lt;B&gt;</li></ul></div>',
            $this->render('<twig:Field:Error :errors="[\'A\', \'<B>\']" />'),
        );
        self::assertSame('', $this->render('<twig:Field:Error />'));
        self::assertSame('', $this->render('<twig:Field:Error> </twig:Field:Error>'));
        self::assertSame('<div role="alert" data-slot="field-error" class="isFieldError">Hand</div>', $this->render('<twig:Field:Error :errors="[\'A\']">Hand</twig:Field:Error>'));
    }

    public function testASetOfFields(): void
    {
        self::assertSame(
            '<fieldset data-slot="field-set" class="isFieldSet"><legend data-slot="field-legend" data-variant="label" class="isFieldLegend isSmall">L</legend><div data-slot="field-group" class="isFieldGroup"><div role="group" data-slot="field" data-orientation="vertical" class="isField"><div data-slot="field-content" class="isFieldContent"><div data-slot="field-title" class="isFieldTitle">T</div></div></div></div><div role="separator" data-slot="field-separator" class="isFieldSeparator">or</div></fieldset>',
            $this->render('<twig:Field:Set><twig:Field:Legend variant="label">L</twig:Field:Legend><twig:Field:Group><twig:Field><twig:Field:Content><twig:Field:Title>T</twig:Field:Title></twig:Field:Content></twig:Field></twig:Field:Group><twig:Field:Separator>or</twig:Field:Separator></twig:Field:Set>'),
        );
        self::assertSame('<legend data-slot="field-legend" data-variant="legend" class="isFieldLegend">L</legend>', $this->render('<twig:Field:Legend>L</twig:Field:Legend>'));
    }

    public function testItemsInAList(): void
    {
        self::assertSame(
            '<ul role="list" data-slot="item-group" class="isItemGroup"><li data-slot="item" data-variant="outline" data-size="sm" class="isItem isOutline isSmall"><div data-slot="item-media" data-variant="icon" aria-hidden="true" class="isItemMedia hasIcon">*</div><div data-slot="item-content" class="isItemContent"><div data-slot="item-title" class="isItemTitle">T</div><p data-slot="item-description" class="isItemDescription">D</p></div><div data-slot="item-actions" class="isItemActions">A</div></li><li role="none" data-slot="item-separator" class="isItemSeparator"></li><a data-slot="item" data-variant="muted" data-size="default" class="isItem isMuted" href="/x"><div data-slot="item-header" class="isItemHeader">H</div><div data-slot="item-footer" class="isItemFooter">F</div></a></ul>',
            $this->render('<twig:Item:Group><twig:Item as="li" variant="outline" size="sm"><twig:Item:Media variant="icon">*</twig:Item:Media><twig:Item:Content><twig:Item:Title>T</twig:Item:Title><twig:Item:Description>D</twig:Item:Description></twig:Item:Content><twig:Item:Actions>A</twig:Item:Actions></twig:Item><twig:Item:Separator /><twig:Item as="a" href="/x" variant="muted"><twig:Item:Header>H</twig:Item:Header><twig:Item:Footer>F</twig:Item:Footer></twig:Item></twig:Item:Group>'),
        );
        self::assertSame(
            '<div data-slot="item" data-variant="other" data-size="default" class="isItem"><div data-slot="item-media" data-variant="image" aria-hidden="true" class="isItemMedia hasImage"></div><div data-slot="item-media" data-variant="default" aria-hidden="true" class="isItemMedia"></div></div>',
            $this->render('<twig:Item as="table" variant="other"><twig:Item:Media variant="image" /><twig:Item:Media /></twig:Item>'),
        );
    }

    public function testANativeSelect(): void
    {
        self::assertSame(
            '<select data-slot="native-select" data-size="sm" class="isSmall" name="c"><optgroup label="G" data-slot="native-select-optgroup"><option data-slot="native-select-option" value="a" selected="">A</option></optgroup></select>',
            $this->render('<twig:NativeSelect size="sm" name="c"><twig:NativeSelect:OptGroup label="G"><twig:NativeSelect:Option value="a" selected>A</twig:NativeSelect:Option></twig:NativeSelect:OptGroup></twig:NativeSelect>'),
        );
        self::assertSame('<select data-slot="native-select" data-size="default"></select>', $this->render('<twig:NativeSelect />'));
    }

    public function testAProgressKeepsItsValueWithinItsBounds(): void
    {
        self::assertSame('<progress data-slot="progress" max="100" value="100" aria-label="Upload"></progress>', $this->render('<twig:Progress :value="150" aria-label="Upload" />'));
        self::assertSame('<progress data-slot="progress" max="100" value="0"></progress>', $this->render('<twig:Progress :value="-3" :max="0" />'));
        self::assertSame('<progress data-slot="progress" max="8" value="3"></progress>', $this->render('<twig:Progress :value="3" :max="8" />'));
        self::assertSame('<progress data-slot="progress" max="100"></progress>', $this->render('<twig:Progress />'));
    }

    public function testAScrollAreaIsARegionOnlyWhenNamed(): void
    {
        self::assertSame('<div data-slot="scroll-area" data-orientation="horizontal" role="region" aria-label="Logs" tabindex="0" class="isScrollArea isHorizontal">x</div>', $this->render('<twig:ScrollArea label="Logs" orientation="horizontal">x</twig:ScrollArea>'));
        self::assertSame('<div data-slot="scroll-area" data-orientation="both" class="isScrollArea">y</div>', $this->render('<twig:ScrollArea orientation="both">y</twig:ScrollArea>'));
        self::assertSame('<div data-slot="scroll-area" data-orientation="vertical" class="isScrollArea isVertical">y</div>', $this->render('<twig:ScrollArea orientation="diagonal">y</twig:ScrollArea>'));
    }

    public function testAToggleIsACheckboxInItsLabel(): void
    {
        self::assertSame(
            '<label data-slot="toggle" data-variant="outline" data-size="sm" class="isToggle isOutline isSmall"><input type="checkbox" class="isToggleInput" value="yes" name="bold" checked>B</label>',
            $this->render('<twig:Toggle name="bold" value="yes" :pressed="true" variant="outline" size="sm">B</twig:Toggle>'),
        );
        self::assertSame(
            '<label data-slot="toggle" data-variant="default" data-size="lg" class="isToggle isLarge"><input type="checkbox" class="isToggleInput" value="on" disabled>I</label>',
            $this->render('<twig:Toggle :disabled="true" size="lg">I</twig:Toggle>'),
        );
    }

    public function testAToggleGroupForOneChoice(): void
    {
        self::assertSame(
            '<div role="group" aria-label="Alignment" data-slot="toggle-group" data-type="single" class="isToggleGroup"><label data-slot="toggle-group-item" class="isToggle"><input type="radio" class="isToggleInput" name="align" value="left" checked>L</label><label data-slot="toggle-group-item" class="isToggle"><input type="radio" class="isToggleInput" name="align" value="right" disabled>R</label></div>',
            $this->render('<twig:ToggleGroup name="align" label="Alignment" value="left"><twig:ToggleGroup:Item value="left">L</twig:ToggleGroup:Item><twig:ToggleGroup:Item value="right" :disabled="true">R</twig:ToggleGroup:Item></twig:ToggleGroup>'),
        );
        self::assertStringNotContainsString('checked', $this->render('<twig:ToggleGroup name="a" label="A"><twig:ToggleGroup:Item value="x">X</twig:ToggleGroup:Item></twig:ToggleGroup>'));
    }

    public function testAToggleGroupForSeveralChoices(): void
    {
        self::assertSame(
            '<div role="group" aria-label="Style" data-slot="toggle-group" data-type="multiple" class="isToggleGroup"><label data-slot="toggle-group-item" class="isToggle isOutline isLarge"><input type="checkbox" class="isToggleInput" name="style[]" value="b" checked disabled>B</label><label data-slot="toggle-group-item" class="isToggle isOutline isLarge"><input type="checkbox" class="isToggleInput" name="style[]" value="u" disabled>U</label></div>',
            $this->render('<twig:ToggleGroup name="style" label="Style" type="multiple" :value="[\'b\', \'i\']" variant="outline" size="lg" :disabled="true"><twig:ToggleGroup:Item value="b">B</twig:ToggleGroup:Item><twig:ToggleGroup:Item value="u">U</twig:ToggleGroup:Item></twig:ToggleGroup>'),
        );
        self::assertStringContainsString('name="style[]" value="b" checked', $this->render('<twig:ToggleGroup name="style[]" label="S" type="multiple" value="b" size="sm"><twig:ToggleGroup:Item value="b">B</twig:ToggleGroup:Item></twig:ToggleGroup>'));
        self::assertStringContainsString('class="isToggle isSmall"', $this->render('<twig:ToggleGroup name="s" label="S" size="sm"><twig:ToggleGroup:Item value="b">B</twig:ToggleGroup:Item></twig:ToggleGroup>'));
    }

    public function testARadioGroup(): void
    {
        self::assertSame(
            '<div role="radiogroup" data-slot="radio-group" data-orientation="horizontal" class="isRadioGroup isHorizontal"><input type="radio" data-slot="radio-group-item" name="plan" value="free" required id="p1"><input type="radio" data-slot="radio-group-item" name="plan" value="pro" checked disabled required id="p2"></div>',
            $this->render('<twig:RadioGroup name="plan" value="pro" orientation="horizontal" :required="true"><twig:RadioGroup:Item value="free" id="p1" /><twig:RadioGroup:Item value="pro" id="p2" :disabled="true" /></twig:RadioGroup>'),
        );
        self::assertSame(
            '<div role="radiogroup" data-slot="radio-group" data-orientation="vertical" class="isRadioGroup"><input type="radio" data-slot="radio-group-item" name="plan" value="1" disabled></div>',
            $this->render('<twig:RadioGroup name="plan" :disabled="true" orientation="x"><twig:RadioGroup:Item value="1" /></twig:RadioGroup>'),
        );
    }
}
