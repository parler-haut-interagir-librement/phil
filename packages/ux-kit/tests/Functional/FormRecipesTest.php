<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Input, Label, Textarea, Checkbox, Switch, Select.
 */
#[CoversNothing]
final class FormRecipesTest extends KitTestCase
{
    public function testAnInputAndItsLabel(): void
    {
        self::assertSame('<input data-slot="input" type="email" name="e">', $this->render('<twig:Input type="email" name="e" />'));
        self::assertSame('<label data-slot="label" for="e">Email</label>', $this->render('<twig:Label for="e">Email</twig:Label>'));
    }

    public function testATextareaKeepsItsValueExactly(): void
    {
        self::assertSame('<textarea data-slot="textarea" name="b">Hello</textarea>', $this->render('<twig:Textarea name="b">Hello</twig:Textarea>'));
    }

    public function testACheckbox(): void
    {
        self::assertSame('<input type="checkbox" data-slot="checkbox" name="t" checked="">', $this->render('<twig:Checkbox name="t" checked />'));
    }

    public function testASwitchIsACheckboxWithItsRole(): void
    {
        self::assertSame('<input type="checkbox" role="switch" data-slot="switch" data-size="default" class="isSwitch" name="n">', $this->render('<twig:Switch name="n" />'));
        self::assertSame('<input type="checkbox" role="switch" data-slot="switch" data-size="sm" checked class="isSwitch isSmall">', $this->render('<twig:Switch size="sm" :checked="true" />'));
    }

    public function testASelect(): void
    {
        $html = $this->render('<twig:Select id="f" name="fruit" value="a" :required="true"><twig:Select:Trigger><twig:Select:Value placeholder="Pick" /></twig:Select:Trigger><twig:Select:Content><twig:Select:Group id="g"><twig:Select:Label>L</twig:Select:Label><twig:Select:Item value="a">A</twig:Select:Item><twig:Select:Separator /><twig:Select:Item value="b" :disabled="true">B</twig:Select:Item></twig:Select:Group></twig:Select:Content></twig:Select>');

        self::assertStringStartsWith('<div data-slot="select" data-state="closed" data-disabled="false" data-select-open-value="false" data-select-value-value="a" class="isDropdown" data-controller="select"><select name="fruit" tabindex="-1" aria-hidden="true" data-select-target="hiddenSelect" class="isVisuallyHidden" required><option value="a"></option></select>', $html);
        self::assertStringContainsString('<button type="button" role="combobox" id="f" popovertarget="select-f-content" data-slot="select-trigger" data-select-target="trigger" data-size="default" data-state="closed" data-has-value="true" aria-haspopup="listbox" aria-controls="select-f-content" aria-expanded="false" class="isSelectTrigger" data-action="click-&gt;select#toggle keydown-&gt;select#onTriggerKeydown"><span data-slot="select-value" data-select-target="value" data-placeholder="Pick" class="isSelectValue">Pick</span><span class="isSelectIcon" aria-hidden="true" data-slot="select-trigger-icon">&#9662;</span></button>', $html);
        self::assertStringContainsString('<div id="select-f-content" popover role="listbox" tabindex="-1" aria-labelledby="f" data-slot="select-content" data-select-target="content" data-position="item-aligned" data-side="bottom" data-align="start" data-state="closed" class="isDropdownMenu isSelectContent" data-action="keydown-&gt;select#onContentKeydown toggle-&gt;select#sync">', $html);
        self::assertStringContainsString('<div role="group" aria-labelledby="select-group-g-label" data-slot="select-group" class="isSelectGroup"><div id="select-group-g-label" data-slot="select-label" class="isSelectLabel">L</div>', $html);
        self::assertStringContainsString('data-value="a" data-disabled="false" aria-selected="true" aria-disabled="false"', $html);
        self::assertStringContainsString('data-value="b" data-disabled="true" aria-selected="false" aria-disabled="true"', $html);
        self::assertStringContainsString('<span class="isSelectItemText" data-slot="select-item-text">A</span><span class="isSelectIndicator" aria-hidden="true" data-slot="select-item-indicator">&#10003;</span>', $html);
        self::assertStringContainsString('<span class="isSelectItemText" data-slot="select-item-text">B</span><span class="isSelectIndicator" aria-hidden="true" data-slot="select-item-indicator" hidden>&#10003;</span>', $html);
        self::assertStringContainsString('<div role="separator" aria-orientation="horizontal" data-slot="select-separator" class="isSelectSeparator"></div>', $html);
        self::assertStringContainsString('data-select-target="viewport" data-action="scroll->select#onViewportScroll:passive"', $html);
        self::assertStringContainsString('data-select-target="scrollUpButton"', $html);
        self::assertStringContainsString('data-select-target="scrollDownButton"', $html);
    }

    public function testASelectWithoutNameNeitherGroupIdAndDisabled(): void
    {
        $html = $this->render('<twig:Select id="f" :disabled="true"><twig:Select:Trigger size="sm"><twig:Select:Value /></twig:Select:Trigger><twig:Select:Content side="top" align="end" position="popper"><twig:Select:Group><twig:Select:Label>L</twig:Select:Label></twig:Select:Group></twig:Select:Content></twig:Select>');

        self::assertStringNotContainsString('<select', $html);
        self::assertStringContainsString('data-disabled="true"', $html);
        self::assertStringContainsString('data-has-value="false"', $html);
        self::assertStringContainsString('data-size="sm"', $html);
        self::assertStringContainsString(' disabled class="isSelectTrigger" data-action', $html);
        self::assertStringContainsString('data-position="popper" data-side="top" data-align="end"', $html);
        self::assertStringContainsString('<div role="group" data-slot="select-group" class="isSelectGroup"><div data-slot="select-label" class="isSelectLabel">L</div></div>', $html);
    }

    public function testADisabledSelectWithANameDisablesItsField(): void
    {
        self::assertStringContainsString('class="isVisuallyHidden" disabled>', $this->render('<twig:Select id="f" name="n" :disabled="true">x</twig:Select>'));
    }
}
