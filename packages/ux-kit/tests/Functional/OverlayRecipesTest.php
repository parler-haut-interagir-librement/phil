<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Popover, Dialog, Sheet, Tooltip, DropdownMenu.
 */
#[CoversNothing]
final class OverlayRecipesTest extends KitTestCase
{
    public function testAPopoverOnThePopoverApi(): void
    {
        $html = $this->render('<twig:Popover id="p" name="g"><twig:Popover:Trigger><twig:Button {{ ...popover_trigger_attrs }}>Open</twig:Button></twig:Popover:Trigger><twig:Popover:Content side="top" align="end" aria-label="Filters">x</twig:Popover:Content></twig:Popover>');

        self::assertStringStartsWith('<div data-slot="popover" data-state="closed" data-popover-open-value="false" data-popover-name-value="g" class="hasPopover" data-controller="popover" data-action="popover:open@window-&gt;popover#handleGroupOpen click@window-&gt;popover#handleOutsideClick keydown.esc@window-&gt;popover#handleEscape">', $html);
        self::assertStringContainsString('<button data-slot="popover-trigger" data-variant="default" data-size="default" class="isPrimary" type="button" data-popover-target="trigger" data-action="click-&gt;popover#toggle" aria-haspopup="dialog" data-state="closed" popovertarget="p">Open</button>', $html);
        self::assertStringContainsString('<div id="p" popover role="dialog" data-slot="popover-content" data-popover-target="content" data-side="top" data-align="end" data-state="closed" class="isPopover" data-action="toggle-&gt;popover#sync" aria-label="Filters">x</div>', $html);
    }

    public function testAPopoverOpenOrWithAGeneratedId(): void
    {
        self::assertStringContainsString('data-popover-open-value="true"', $this->render('<twig:Popover id="p" :open="true">x</twig:Popover>'));
        self::assertMatchesRegularExpression('/popovertarget="popover-\d+"/', $this->render('<twig:Popover><twig:Popover:Trigger><twig:Button {{ ...popover_trigger_attrs }}>o</twig:Button></twig:Popover:Trigger></twig:Popover>'));
    }

    public function testADialogOnTheNativeElement(): void
    {
        $html = $this->render('<twig:Dialog id="d"><twig:Dialog:Trigger><twig:Button {{ ...dialog_trigger_attrs }}>Open</twig:Button></twig:Dialog:Trigger><twig:Dialog:Content><twig:Dialog:Header><twig:Dialog:Title>T</twig:Dialog:Title><twig:Dialog:Description>D</twig:Dialog:Description></twig:Dialog:Header><twig:Dialog:Footer><twig:Dialog:Close><twig:Button {{ ...dialog_close_attrs }}>Cancel</twig:Button></twig:Dialog:Close></twig:Dialog:Footer></twig:Dialog:Content></twig:Dialog>');

        self::assertStringStartsWith('<div data-slot="dialog" data-dialog-open-value="false" class="hasDialog" data-controller="dialog">', $html);
        self::assertStringContainsString('data-action="click-&gt;dialog#open" data-dialog-target="trigger" aria-haspopup="dialog" command="show-modal" commandfor="dialog-d">Open</button>', $html);
        self::assertStringContainsString('<dialog id="dialog-d" closedby="any" aria-labelledby="dialog-d-title" aria-describedby="dialog-d-description" data-slot="dialog-content" data-dialog-target="dialog" class="isDialog" data-action="click-&gt;dialog#closeOnClickOutside close-&gt;dialog#closed">', $html);
        self::assertStringContainsString('<h2 id="dialog-d-title" data-slot="dialog-title" class="isDialogTitle">T</h2><p id="dialog-d-description" data-slot="dialog-description" class="isDialogDescription">D</p>', $html);
        self::assertStringContainsString('<footer data-slot="dialog-footer" class="isDialogFooter">', $html);
        self::assertStringContainsString('command="close" commandfor="dialog-d">Cancel</button>', $html);
        self::assertStringContainsString('data-slot="dialog-close" data-variant="ghost" data-size="icon-sm" class="isGhost hasIcon isIconOnly isSmall isDialogClose" type="button" data-action="click-&gt;dialog#close" command="close" commandfor="dialog-d"><span aria-hidden="true">&times;</span><span class="isVisuallyHidden">Close</span></button>', $html);
    }

    public function testTheCloseButtonOfADialogIsAProp(): void
    {
        self::assertStringContainsString('>Fermer<', $this->render('<twig:Dialog id="d"><twig:Dialog:Content closeLabel="Fermer">x</twig:Dialog:Content></twig:Dialog>'));
        self::assertStringNotContainsString('dialog-close', $this->render('<twig:Dialog id="d"><twig:Dialog:Content :showCloseButton="false">x</twig:Dialog:Content></twig:Dialog>'));
        self::assertStringContainsString('data-dialog-open-value="true"', $this->render('<twig:Dialog id="d" :open="true">x</twig:Dialog>'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function edges(): iterable
    {
        yield 'right' => ['right', 'isSheet'];
        yield 'left' => ['left', 'isSheet isStart'];
        yield 'top' => ['top', 'isSheet isTop'];
        yield 'bottom' => ['bottom', 'isSheet isBottom'];
    }

    #[DataProvider('edges')]
    public function testTheEdgeOfASheetIsAClassOfTheStylesheet(string $side, string $class): void
    {
        self::assertStringContainsString('class="'.$class.'"', $this->render('<twig:Sheet id="s" side="'.$side.'"><twig:Sheet:Content>x</twig:Sheet:Content></twig:Sheet>'));
    }

    public function testASheetUsesTheControllerOfTheDialog(): void
    {
        $html = $this->render('<twig:Sheet id="s" side="left"><twig:Sheet:Trigger><twig:Button {{ ...sheet_trigger_attrs }}>Open</twig:Button></twig:Sheet:Trigger><twig:Sheet:Content><twig:Sheet:Header><twig:Sheet:Title>T</twig:Sheet:Title><twig:Sheet:Description>D</twig:Sheet:Description></twig:Sheet:Header><twig:Sheet:Footer><twig:Sheet:Close><twig:Button {{ ...sheet_close_attrs }}>Cancel</twig:Button></twig:Sheet:Close></twig:Sheet:Footer></twig:Sheet:Content></twig:Sheet>');

        self::assertStringStartsWith('<div data-slot="sheet" data-dialog-open-value="false" class="hasDialog" data-controller="dialog">', $html);
        self::assertStringContainsString('command="show-modal" commandfor="sheet-s"', $html);
        self::assertStringContainsString('<dialog id="sheet-s" closedby="any" aria-labelledby="sheet-s-title" aria-describedby="sheet-s-description" data-slot="sheet-content" data-dialog-target="dialog" data-side="left" class="isSheet isStart" data-action="click-&gt;dialog#closeOnClickOutside close-&gt;dialog#closed">', $html);
        self::assertStringContainsString('<h2 id="sheet-s-title" data-slot="sheet-title" class="isSheetTitle">T</h2><p id="sheet-s-description" data-slot="sheet-description" class="isSheetDescription">D</p>', $html);
        self::assertStringContainsString('command="close" commandfor="sheet-s">Cancel</button>', $html);
        self::assertStringContainsString('<footer data-slot="sheet-footer" class="isSheetFooter">', $html);
    }

    public function testATooltip(): void
    {
        $html = $this->render('<twig:Tooltip id="t" :delayDuration="200"><twig:Tooltip:Trigger><twig:Button {{ ...tooltip_trigger_attrs }}>B</twig:Button></twig:Tooltip:Trigger><twig:Tooltip:Content side="right" :sideOffset="4">Tip</twig:Tooltip:Content></twig:Tooltip>');

        self::assertSame(
            '<div id="t" data-slot="tooltip" data-tooltip-delay-duration-value="200" data-tooltip-wrapper-selector-value="#t_wrapper" data-tooltip-content-selector-value="#t_content" data-tooltip-arrow-selector-value="#t_arrow" class="hasTooltip" data-controller="tooltip"><button data-slot="tooltip-trigger" data-variant="default" data-size="default" class="isPrimary" type="button" id="t_trigger" aria-describedby="t_content" data-tooltip-target="trigger" data-action="mouseenter-&gt;tooltip#show mouseleave-&gt;tooltip#hide focus-&gt;tooltip#show blur-&gt;tooltip#hide">B</button><div id="t_wrapper" class="isTooltipWrapper" popover="manual" role="presentation" data-slot="tooltip-wrapper" data-side="right" data-side-offset="4" data-tooltip-target="wrapper" data-action="mouseenter->tooltip#show mouseleave->tooltip#hide"><div id="t_content" role="tooltip" data-slot="tooltip-content" data-side="right" data-tooltip-target="content" class="isTooltip">Tip<span id="t_arrow" class="isTooltipArrow" data-side="right" data-tooltip-target="arrow" aria-hidden="true"></span></div></div></div>',
            $html,
        );
    }

    public function testADropdownMenu(): void
    {
        $html = $this->render('<twig:DropdownMenu id="m"><twig:DropdownMenu:Trigger><twig:Button {{ ...dropdown_menu_trigger_attrs }}>Open</twig:Button></twig:DropdownMenu:Trigger><twig:DropdownMenu:Content><twig:DropdownMenu:Group><twig:DropdownMenu:Label>L</twig:DropdownMenu:Label><twig:DropdownMenu:Item as="a" href="/e">Edit<twig:DropdownMenu:Shortcut>K</twig:DropdownMenu:Shortcut></twig:DropdownMenu:Item></twig:DropdownMenu:Group><twig:DropdownMenu:Separator /><twig:DropdownMenu:Item variant="destructive" :disabled="true" :closeOnSelect="false">Del</twig:DropdownMenu:Item></twig:DropdownMenu:Content></twig:DropdownMenu>');

        self::assertStringStartsWith('<div data-slot="dropdown-menu" data-state="closed" data-dropdown-menu-open-value="false" class="isDropdown" data-controller="dropdown-menu">', $html);
        self::assertStringContainsString('<button data-slot="dropdown-menu-trigger" data-variant="default" data-size="default" class="isPrimary" type="button" id="dropdown-menu-m-trigger" popovertarget="dropdown-menu-m-content" data-dropdown-menu-target="trigger" aria-haspopup="menu" aria-controls="dropdown-menu-m-content" aria-expanded="false" data-state="closed" data-action="click-&gt;dropdown-menu#toggle keydown-&gt;dropdown-menu#onTriggerKeydown">Open</button>', $html);
        self::assertStringContainsString('<div id="dropdown-menu-m-content" popover role="menu" tabindex="-1" aria-labelledby="dropdown-menu-m-trigger" data-slot="dropdown-menu-content" data-dropdown-menu-target="content" data-state="closed" data-side="bottom" data-align="start" data-action="keydown->dropdown-menu#onContentKeydown toggle->dropdown-menu#sync" class="isDropdownMenu">', $html);
        self::assertStringContainsString('<a role="menuitem" data-slot="dropdown-menu-item" data-variant="default" data-inset="false" data-dropdown-menu-target="item" data-close-on-select="true" data-disabled="false" aria-disabled="false" class="isDropdownItem" data-action="click-&gt;dropdown-menu#closeFromItem" href="/e">Edit<span data-slot="dropdown-menu-shortcut" class="isDropdownShortcut">K</span></a>', $html);
        self::assertStringContainsString('<button role="menuitem" data-slot="dropdown-menu-item" data-variant="destructive" data-inset="false" data-dropdown-menu-target="item" data-close-on-select="false" data-disabled="true" aria-disabled="true" type="button" disabled class="isDropdownItem isDanger"', $html);
        self::assertStringContainsString('<div role="separator" aria-orientation="horizontal" data-slot="dropdown-menu-separator" class="isDropdownSeparator"></div>', $html);
        self::assertStringContainsString('<div data-slot="dropdown-menu-label" data-inset="false" class="isDropdownLabel">L</div>', $html);
    }

    public function testTheCheckboxAndRadioItemsOfADropdownMenu(): void
    {
        $checkbox = $this->render('<twig:DropdownMenu:CheckboxItem :checked="true">C</twig:DropdownMenu:CheckboxItem>');
        $off = $this->render('<twig:DropdownMenu:CheckboxItem :disabled="true">C</twig:DropdownMenu:CheckboxItem>');
        $radio = $this->render('<twig:DropdownMenu:RadioGroup value="a"><twig:DropdownMenu:RadioItem value="a" :checked="true" :closeOnSelect="true">A</twig:DropdownMenu:RadioItem></twig:DropdownMenu:RadioGroup>');

        self::assertStringContainsString('role="menuitemcheckbox"', $checkbox);
        self::assertStringContainsString('aria-checked="true"', $checkbox);
        self::assertStringContainsString('<span class="isDropdownIndicator" aria-hidden="true" data-checkbox-indicator>', $checkbox);
        self::assertStringContainsString('data-checkbox-indicator hidden>', $off);
        self::assertStringContainsString(' disabled ', $off);
        self::assertStringStartsWith('<div role="group" data-slot="dropdown-menu-radio-group" data-value="a" class="isDropdownGroup"><button type="button" role="menuitemradio"', $radio);
        self::assertStringContainsString('data-value="a" data-close-on-select="true"', $radio);
        self::assertStringContainsString('click-&gt;dropdown-menu#selectRadio', $radio);
    }

    public function testASubMenu(): void
    {
        $html = $this->render('<twig:DropdownMenu:Sub :openDelay="50" :closeDelay="80"><twig:DropdownMenu:SubTrigger :inset="true">More</twig:DropdownMenu:SubTrigger><twig:DropdownMenu:SubContent>x</twig:DropdownMenu:SubContent></twig:DropdownMenu:Sub>');

        self::assertStringStartsWith('<div data-slot="dropdown-menu-sub" data-dropdown-menu-sub-open-delay-value="50" data-dropdown-menu-sub-close-delay-value="80" class="isDropdownSub" data-controller="dropdown-menu-sub"', $html);
        self::assertStringContainsString('role="menuitem" aria-haspopup="menu" aria-expanded="false" data-slot="dropdown-menu-sub-trigger" data-inset="true" data-dropdown-menu-target="item" data-dropdown-menu-sub-target="trigger"', $html);
        self::assertStringContainsString('<div popover role="menu" data-slot="dropdown-menu-sub-content" data-dropdown-menu-sub-target="content" data-state="closed"', $html);
    }
}
