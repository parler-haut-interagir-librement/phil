<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverKeys;
use Override;
use Phil\UxKit\Test\BrowserTestCase;
use Phil\UxKit\Test\RecipesPage;
use Phil\UxKit\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the browser and the controllers do together, which nothing short of a browser can show: the top layer, the place
 * on screen, the focus, Escape and the arrows, in clear, in dark and right to left, against the stylesheet.
 */
#[CoversNothing]
final class InteractiveRecipesTest extends BrowserTestCase
{
    #[Override]
    protected static function recipesPage(): RecipesPage
    {
        return new RecipesPage(
            new TestKernel('test', true),
            TestKernel::KIT_DIR,
            __DIR__.'/resources',
            TestKernel::KIT_DIR.'/../ux-css/dist/phil-ux.css',
            sys_get_temp_dir().'/phil-ux-kit-browser',
            __DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js',
        );
    }

    #[DataProvider('modes')]
    public function testADialogIsModalKeepsTheFocusAndEscapeGivesItBack(string $page): void
    {
        $this->open($page);
        $this->focus('#open-dialog');
        $this->press(WebDriverKeys::ENTER);
        $this->waitForFocus('#in-dialog');

        self::assertTrue($this->read('document.querySelector("#dialog-modal").matches(":modal")'));
        self::assertSame('hidden', $this->read('getComputedStyle(document.documentElement).overflow'));
        self::assertTrue($this->read('(() => { const b = document.querySelector("#dialog-modal").getBoundingClientRect(), c = document.querySelector("#dialog-modal .isDialogClose").getBoundingClientRect(); return c.top >= b.top && c.bottom <= b.bottom && c.left >= b.left && c.right <= b.right; })()'));

        $this->press(WebDriverKeys::ESCAPE);
        $this->waitForFocus('#open-dialog');

        self::assertFalse($this->read('document.querySelector("#dialog-modal").open'));
        $this->waitUntil('document.querySelector("#open-dialog").getAttribute("aria-expanded") === "false"');
    }

    /**
     * The browser closes the dialog by `closedby="any"` where it runs it, the controller where it does not; and the
     * trigger follows either way.
     */
    #[DataProvider('modes')]
    public function testAClickOnTheBackdropClosesADialogAndTheCancelButtonToo(string $page): void
    {
        $this->open($page);
        $this->click('#open-dialog');
        $this->waitUntil('document.querySelector("#dialog-modal").open');
        $this->waitUntil('document.querySelector("#open-dialog").getAttribute("aria-expanded") === "true"');

        $this->clickBeside('#dialog-modal');

        $this->waitUntil('!document.querySelector("#dialog-modal").open');
        $this->waitUntil('document.querySelector("#open-dialog").getAttribute("aria-expanded") === "false"');

        $this->click('#open-dialog');
        $this->waitUntil('document.querySelector("#dialog-modal").open');
        $this->click('#cancel-dialog');
        $this->waitUntil('!document.querySelector("#dialog-modal").open');
        $this->waitUntil('document.querySelector("#open-dialog").getAttribute("aria-expanded") === "false"');
    }

    #[DataProvider('modes')]
    public function testASheetLiesAgainstItsEdge(string $page): void
    {
        $this->open($page);
        $this->focus('#open-sheet');
        $this->press(WebDriverKeys::ENTER);
        $this->waitUntil('document.querySelector("#sheet-panel").open');
        $this->waitUntil('getComputedStyle(document.querySelector("#sheet-panel")).translate === "0px" || getComputedStyle(document.querySelector("#sheet-panel")).translate === "none"');

        $expected = $this->isRightToLeft($page)
            ? 'document.querySelector("#sheet-panel").getBoundingClientRect().left === 0'
            : 'Math.round(document.querySelector("#sheet-panel").getBoundingClientRect().right) === Math.round(document.documentElement.getBoundingClientRect().right)';
        $this->waitUntil($expected);

        $this->press(WebDriverKeys::ESCAPE);
        $this->waitForFocus('#open-sheet');
    }

    #[DataProvider('modes')]
    public function testAPopoverOpensAgainstItsTriggerAndEscapeClosesIt(string $page): void
    {
        $this->open($page);
        $this->focus('[popovertarget="filters"]');
        $this->press(WebDriverKeys::ENTER);
        $this->waitForFocus('#in-popover');
        $this->waitUntil('document.querySelector("#filters").dataset.placed === "bottom"');

        self::assertTrue($this->read('document.querySelector("#filters").matches(":popover-open")'));
        $this->waitUntil('(() => { const p = document.querySelector("#filters").getBoundingClientRect(), t = document.querySelector("[popovertarget=filters]").getBoundingClientRect(); return p.top >= t.bottom && p.top - t.bottom < 12; })()');
        self::assertSame('0px', $this->read('getComputedStyle(document.querySelector("#filters")).marginTop'));

        $this->press(WebDriverKeys::ESCAPE);
        $this->waitForFocus('[popovertarget="filters"]');

        self::assertFalse($this->read('document.querySelector("#filters").matches(":popover-open")'));
    }

    #[DataProvider('modes')]
    public function testAMenuOpensAtTheArrowAndItsSubmenuAtTheSideArrow(string $page): void
    {
        $forward = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT;

        $this->open($page);
        $this->focus('#dropdown-menu-actions-trigger');
        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitForFocus('#dropdown-menu-actions-content .isDropdownItem');
        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitForFocus('#more');
        $this->press($forward);
        $this->waitForFocus('#archive');

        self::assertTrue($this->read('document.querySelector("#more").parentElement.querySelector("[popover]").matches(":popover-open")'));
        $this->waitUntil('document.querySelector("#more").getAttribute("aria-expanded") === "true"');

        $this->press(WebDriverKeys::ESCAPE);
        $this->waitForFocus('#more');

        self::assertTrue($this->read('document.querySelector("#dropdown-menu-actions-content").matches(":popover-open")'), 'Escape closes the submenu alone');

        $this->press(WebDriverKeys::ESCAPE);
        $this->waitForFocus('#dropdown-menu-actions-trigger');
    }

    #[DataProvider('modes')]
    public function testASelectChoosesAtTheKeyboard(string $page): void
    {
        $this->open($page);
        $this->focus('#fruit');
        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitForFocus('#select-fruit-content [data-value="b"]');

        $this->waitUntil('document.querySelector("#select-fruit-content").dataset.placed === "bottom"');

        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitForFocus('#select-fruit-content [data-value="c"]');
        $this->press(WebDriverKeys::ENTER);
        $this->waitForFocus('#fruit');

        self::assertSame('Cherry', $this->read('document.querySelector(".isSelectValue").textContent'));
        self::assertSame('c', $this->read('document.querySelector("select[name=fruit]").value'));
    }

    #[DataProvider('modes')]
    public function testTabsMoveAtTheArrowsAndOnlyOnePanelShows(string $page): void
    {
        $forward = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT;

        $this->open($page);
        $this->focus('#tab-first');
        $this->press($forward);
        $this->waitForFocus('#tab-second');

        self::assertSame(['none', 'block', 'none'], $this->read('[...document.querySelectorAll(".isTabPanel")].map((panel) => getComputedStyle(panel).display)'));
        self::assertSame('true', $this->read('document.querySelector("#tab-second").getAttribute("aria-selected")'));
    }

    #[DataProvider('modes')]
    public function testAnAccordionKeepsOneItemOpen(string $page): void
    {
        $this->open($page);
        $this->focus('#faq-one-trigger');
        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitForFocus('#faq-two-trigger');
        $this->press(WebDriverKeys::SPACE);
        $this->waitUntil('document.querySelector("[data-value=two]").open');

        self::assertSame([false, true, false], $this->read('[...document.querySelectorAll(".isAccordionItem")].map((item) => item.open)'));
        $this->waitUntil('document.querySelector("#faq-two-content").getBoundingClientRect().height > 0');
    }

    #[DataProvider('modes')]
    public function testATooltipShowsOnTheFocusAndEscapeDismissesIt(string $page): void
    {
        $this->open($page);
        $this->focus('#hint_trigger');
        $this->waitUntil('document.querySelector("#hint_wrapper").matches(":popover-open")');

        self::assertTrue($this->read('(() => { const w = document.querySelector("#hint_wrapper").getBoundingClientRect(), t = document.querySelector("#hint_trigger").getBoundingClientRect(); return w.bottom <= t.top; })()'));
        self::assertSame('rgba(0, 0, 0, 0)', $this->read('getComputedStyle(document.querySelector("#hint_wrapper")).backgroundColor'));

        $this->press(WebDriverKeys::ESCAPE);
        $this->waitUntil('!document.querySelector("#hint_wrapper").matches(":popover-open")');
    }

    #[DataProvider('modes')]
    public function testACarouselMovesAtItsButtonAndAtTheArrows(string $page): void
    {
        $forward = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT;

        $this->open($page);
        $this->waitUntil('!document.querySelector(".isCarouselNext").hidden');
        $this->click('.isCarouselNext');
        $this->waitUntil('Math.abs(document.querySelector(".isCarouselViewport").scrollLeft) > 0');

        $position = $this->read('Math.abs(document.querySelector(".isCarouselViewport").scrollLeft)');
        self::assertIsNumeric($position);

        $this->focus('.isCarouselFrame');
        $this->press($forward);
        $this->waitUntil(\sprintf('Math.abs(document.querySelector(".isCarouselViewport").scrollLeft) > %F', $position));
    }

    #[DataProvider('modes')]
    public function testAnAnimatedHeadlineMovesToTheNextWordAndPauses(string $page): void
    {
        $this->open($page);
        $this->waitUntil('!document.querySelector(".isRevealToggle").hidden');
        $this->waitUntil('document.querySelector(".isReveal .isCurrent").textContent !== "sites"');

        $this->focus('.isRevealToggle');
        $this->press(WebDriverKeys::ENTER);
        $this->waitUntil('document.querySelector(".isRevealToggle").textContent === "Play animation"');
    }

    #[DataProvider('modes')]
    public function testASwitchTogglesAtTheSpaceBar(string $page): void
    {
        $this->open($page);
        $this->focus('#switch');
        $this->press(WebDriverKeys::SPACE);
        $this->waitUntil('document.querySelector("#switch").checked');
    }

    #[DataProvider('modes')]
    public function testATooltipDrawsItsArrowOnTheSideItWentTo(string $page): void
    {
        $this->open($page);
        $this->focus('#hint_trigger');
        $this->waitUntil('document.querySelector("#hint_content").classList.contains("isTop")');

        self::assertSame('block', $this->read('getComputedStyle(document.querySelector("#hint_arrow")).display'));
        self::assertSame('rgba(0, 0, 0, 0)', $this->read('getComputedStyle(document.querySelector("#hint_wrapper"), "::backdrop").backgroundColor'), 'a tooltip does not dim the page');
        self::assertTrue($this->read('(() => { const a = document.querySelector("#hint_arrow").getBoundingClientRect(), b = document.querySelector("#hint_content").getBoundingClientRect(); return a.bottom > b.bottom && a.top < b.bottom; })()'));
    }

    #[DataProvider('modes')]
    public function testATreeMovesAtTheKeyboardAndFolds(string $page): void
    {
        $forward = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT;
        $back = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_RIGHT : WebDriverKeys::ARROW_LEFT;

        $this->open($page);

        self::assertSame([0, -1, -1, -1, -1], $this->read('[...document.querySelectorAll("#tree [role=treeitem]")].map((item) => item.tabIndex)'));

        $this->focus('#docs');
        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitForFocus('#report');
        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitForFocus('#notes');
        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitForFocus('#photos');
        $this->press(WebDriverKeys::ARROW_UP);
        $this->waitForFocus('#notes');
        $this->press($forward);
        $this->waitUntil('document.querySelector("#notes").getAttribute("aria-expanded") === "true"');
        $this->press($forward);
        $this->waitForFocus('#deep');
        $this->press($back);
        $this->waitForFocus('#notes');
        $this->press($back);
        $this->waitUntil('document.querySelector("#notes").getAttribute("aria-expanded") === "false"');

        self::assertSame('none', $this->read('getComputedStyle(document.querySelector("#notes > .isTreeGroup")).display'));

        $this->press(WebDriverKeys::SPACE);
        $this->waitUntil('document.querySelector("#notes").getAttribute("aria-selected") === "true"');
    }

    #[DataProvider('modes')]
    public function testADatePickerOpensOnItsDateAndChoosesAtTheKeyboard(string $page): void
    {
        $forward = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT;

        $this->open($page);
        $this->waitUntil('!document.querySelector(".isDatePickerTrigger").hidden');
        $this->focus('.isDatePickerTrigger');
        $this->press(WebDriverKeys::ENTER);
        $this->waitForFocus('[data-date="2026-03-15"]');

        self::assertSame('March 2026', $this->read('document.querySelector(".isDatePickerHeading").textContent'));
        self::assertTrue($this->read('document.querySelector("[data-date=\'2026-03-01\']").disabled'));

        $this->press($forward);
        $this->waitForFocus('[data-date="2026-03-16"]');
        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitForFocus('[data-date="2026-03-23"]');
        $this->press(WebDriverKeys::PAGE_DOWN);
        $this->waitForFocus('[data-date="2026-04-23"]');

        self::assertSame('April 2026', $this->read('document.querySelector(".isDatePickerHeading").textContent'));

        $this->press(WebDriverKeys::ENTER);
        $this->waitForFocus('.isDatePickerTrigger');

        self::assertSame('2026-04-23', $this->read('document.querySelector("#start").value'));
        self::assertFalse($this->read('document.querySelector("#start-panel").matches(":popover-open")'));
    }

    #[DataProvider('modes')]
    public function testANumberInputStopsAtItsLimits(string $page): void
    {
        $this->open($page);
        $this->waitUntil('!document.querySelector(".isNumberInputStep").hidden');
        $this->click('[aria-label="Increase"]');
        $this->waitUntil('document.querySelector("#guests").value === "3"');

        self::assertTrue($this->read('document.querySelector("[aria-label=Increase]").disabled'));
    }

    #[DataProvider('modes')]
    public function testAColourPickerKeepsItsPartsInStep(string $page): void
    {
        $this->open($page);
        $this->waitUntil('!document.querySelector(".isColorPickerSwatch").hidden');

        self::assertSame('true', $this->read('document.querySelector("[aria-label=Blue]").getAttribute("aria-pressed")'));

        $this->click('[aria-label="Plum"]');
        $this->waitUntil('document.querySelector(".isColorPickerValue").value === "#993366"');

        self::assertSame('#993366', $this->read('document.querySelector("#accent").value'));
        self::assertSame('true', $this->read('document.querySelector("[aria-label=Plum]").getAttribute("aria-pressed")'));
    }

    #[DataProvider('modes')]
    public function testATagGoesAwayAndTheFocusStaysNearby(string $page): void
    {
        $this->open($page);
        $this->waitUntil('!document.querySelector("#tag-one .isTagRemove").hidden');
        $this->focus('#tag-one .isTagRemove');
        $this->press(WebDriverKeys::ENTER);
        $this->waitForFocus('#tag-two .isTagRemove');

        self::assertNull($this->read('document.querySelector("#tag-one")'));
    }

    #[DataProvider('modes')]
    public function testAFileInputSievesWhatItIsGiven(string $page): void
    {
        $this->open($page);
        $this->perform('const t = new DataTransfer(); t.items.add(new File(["x"], "a.png")); t.items.add(new File(["x"], "b.txt")); const i = document.querySelector("#photos-field"); i.files = t.files; i.dispatchEvent(new Event("change", {bubbles: true}));');
        $this->waitUntil('document.querySelectorAll(".isFileInputItem").length === 1');

        self::assertSame(1, $this->read('document.querySelector("#photos-field").files.length'));
        self::assertStringContainsString('1', (string) json_encode($this->read('document.querySelector(".isFileInputError").textContent')));
        self::assertFalse($this->read('document.querySelector(".isFileInputClear").hidden'));
    }

    #[DataProvider('modes')]
    public function testABeforeAfterCutsWhereItsSliderStands(string $page): void
    {
        $this->open($page);
        $this->waitUntil('document.querySelector("#compare").style.getPropertyValue("--phil-before-after-position") === "30%"');
        $this->focus('#compare input');
        $this->press(WebDriverKeys::END);
        $this->waitUntil('document.querySelector("#compare").style.getPropertyValue("--phil-before-after-position") === "100%"');
    }

    #[DataProvider('modes')]
    public function testARelativeTimeSaysHowLongAgo(string $page): void
    {
        $this->open($page);
        $this->waitUntil('/years ago$/.test(document.querySelector("#moment").textContent)');
    }

    public function testACopyButtonSaysWhatHappened(): void
    {
        $this->open('index.html');
        $this->waitUntil('!document.querySelector(".isCopyButtonTrigger").hidden');
        $this->click('.isCopyButtonTrigger');
        $this->waitUntil('document.querySelector(".isCopyButton [role=status]").textContent !== ""');
    }

    /**
     * A question waits for its answer: a click on the backdrop leaves it open, the cancel button has the focus, and
     * Escape, a request to close, dismisses it.
     */
    #[DataProvider('modes')]
    public function testAnAlertDialogStaysOpenOnAClickOutsideAndEscapeDismissesIt(string $page): void
    {
        $this->open($page);
        $this->click('#open-alert');
        $this->waitUntil('document.querySelector("#alert-dialog-erase").open');
        $this->waitForFocus('#alert-cancel');

        self::assertTrue($this->read('document.querySelector("#alert-dialog-erase").matches(":modal")'));
        $this->clickBeside('#alert-dialog-erase');
        self::assertTrue($this->read('document.querySelector("#alert-dialog-erase").open'));

        $this->press(WebDriverKeys::ESCAPE);
        $this->waitUntil('!document.querySelector("#alert-dialog-erase").open');
        $this->waitForFocus('#open-alert');

        $this->click('#open-alert');
        $this->waitUntil('document.querySelector("#alert-dialog-erase").open');
        $this->click('#alert-action');
        $this->waitUntil('!document.querySelector("#alert-dialog-erase").open');
        $this->waitUntil('document.querySelector("#open-alert").getAttribute("aria-expanded") === "false"');
    }

    /**
     * Radio buttons under the toggles: the arrows move the choice, and the pressed toggle and the focused one show.
     */
    #[DataProvider('modes')]
    public function testAToggleGroupMovesAtTheArrowsAndShowsWhatIsPressed(string $page): void
    {
        $forward = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT;

        $this->open($page);
        $released = $this->read('getComputedStyle(document.querySelector("#align > label:nth-child(2)")).backgroundColor');
        $this->focus('#align input[value=left]');
        $this->press($forward);
        $this->waitUntil('document.querySelector("#align input[value=center]").checked');

        self::assertFalse($this->read('document.querySelector("#align input[value=left]").checked'));
        self::assertNotSame($released, $this->read('getComputedStyle(document.querySelector("#align > label:nth-child(2)")).backgroundColor'));
        self::assertNotSame('none', $this->read('getComputedStyle(document.querySelector("#align > label:nth-child(2)")).outlineStyle'));
    }

    #[DataProvider('modes')]
    public function testAToggleIsPressedAtTheSpaceBar(string $page): void
    {
        $this->open($page);
        $this->focus('#bold input');
        $this->press(WebDriverKeys::SPACE);
        $this->waitUntil('document.querySelector("#bold input").checked');

        self::assertSame('Bold', $this->read('document.querySelector("#bold input").labels[0].textContent.trim().replace("B", "").trim()'));
    }

    #[DataProvider('modes')]
    public function testARadioGroupAndAScrollAreaAnswerTheKeyboard(string $page): void
    {
        $this->open($page);
        $this->focus('#plan-free');
        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitUntil('document.querySelector("#plan-pro").checked');

        $this->focus('#scroller');
        $this->press(WebDriverKeys::ARROW_DOWN);
        $this->waitUntil('document.querySelector("#scroller").scrollTop > 0');
    }

    /**
     * The series take their colour from a role, each its own, and the second line its dash; the drawing shrinks to a
     * phone.
     */
    #[DataProvider('modes')]
    public function testAChartColoursEachSeriesAndFitsAPhone(string $page): void
    {
        $this->open($page);

        self::assertNotSame(
            $this->read('getComputedStyle(document.querySelector("#bars [data-series=\'1\'] .isChartBar")).fill'),
            $this->read('getComputedStyle(document.querySelector("#bars [data-series=\'2\'] .isChartBar")).fill'),
        );
        self::assertSame('none', $this->read('getComputedStyle(document.querySelector("#lines .isChartLine[data-series=\'1\']")).strokeDasharray'));
        self::assertNotSame('none', $this->read('getComputedStyle(document.querySelector("#lines .isChartLine[data-series=\'2\']")).strokeDasharray'));

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(375, 800));
        $this->open($page);

        self::assertSame([], $this->read('[...document.querySelectorAll("#native > *, #blocks > *")].filter((part) => part.getBoundingClientRect().right > document.documentElement.clientWidth + 1 || part.getBoundingClientRect().left < -1).map((part) => part.className)'));

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
    }

    #[DataProvider('modes')]
    public function testTheOwnRecipesFitAPhone(string $page): void
    {
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(375, 800));
        $this->open($page);

        self::assertSame([], $this->read('[...document.querySelectorAll("#own > *")].filter((part) => part.getBoundingClientRect().right > document.documentElement.clientWidth + 1 || part.getBoundingClientRect().left < -1).map((part) => part.className)'));

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
    }

    /**
     * A picture wider than a phone shrinks to its container and keeps the ratio of its width and height.
     */
    #[DataProvider('modes')]
    public function testAnImageFitsAPhoneAtItsRatio(string $page): void
    {
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(375, 800));
        $this->open($page);

        self::assertSame(
            ['block', true, true],
            $this->read('(() => { const image = document.querySelector("#photo"), box = image.getBoundingClientRect(), parent = image.closest("section").getBoundingClientRect(); return [getComputedStyle(image).display, box.width <= parent.width + 0.5, Math.abs(box.width / box.height - 2) < 0.02]; })()'),
        );

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
    }

    /**
     * Art direction: the source of a phone's media query on a phone, the fallback on a wide screen, each at its own ratio.
     */
    public function testAPictureChoosesItsCropByTheWidthOfTheScreen(): void
    {
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(375, 800));
        $this->open('index.html');
        $this->perform('document.querySelector("#directed").scrollIntoView()');
        $this->waitUntil('document.querySelector("#directed").complete && document.querySelector("#directed").currentSrc');

        self::assertSame([true, 1], $this->read('(() => { const image = document.querySelector("#directed"), box = image.getBoundingClientRect(); return [image.currentSrc.includes("navy"), Math.round(box.width / box.height)]; })()'));

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
        $this->open('index.html');
        $this->perform('document.querySelector("#directed").scrollIntoView()');
        $this->waitUntil('document.querySelector("#directed").complete && document.querySelector("#directed").currentSrc');

        self::assertSame([true, 2], $this->read('(() => { const image = document.querySelector("#directed"), box = image.getBoundingClientRect(); return [image.currentSrc.includes("teal"), Math.round(box.width / box.height)]; })()'));
    }

    /**
     * A ratio of its own: the box kept square whatever the picture, the picture covering it, cropped on its left.
     */
    #[DataProvider('modes')]
    public function testAnImageKeepsItsRatioCoveredAndCropped(string $page): void
    {
        $this->open($page);

        self::assertSame([200, 200, 'cover', '0% 50%'], $this->read('(() => { const image = document.querySelector("#square"), box = image.getBoundingClientRect(), style = getComputedStyle(image); return [Math.round(box.width), Math.round(box.height), style.objectFit, style.objectPosition]; })()'));
    }

    /**
     * axe-core on the images: the gallery, its lightboxes closed and open, the picture with its variants and the logo,
     * against the rules of WCAG 2.2 A and AA, in clear, in dark and right to left.
     */
    #[DataProvider('modes')]
    public function testTheImagesPassAxe(string $page): void
    {
        $this->open($page);
        $this->perform((string) file_get_contents(__DIR__.'/node_modules/axe-core/axe.min.js'));

        self::assertSame([], $this->axe('#images'));

        $this->focus('[commandfor="lightbox-festival-1"]');
        $this->press(WebDriverKeys::ENTER);
        // Measured once the dialog has faded in: its contrast is that of the page only at full opacity.
        $this->waitUntil('document.querySelector("#lightbox-festival-1").open && getComputedStyle(document.querySelector("#lightbox-festival-1")).opacity === "1"');

        self::assertSame([], $this->axe('#lightbox-festival-1'));
    }

    /**
     * The thumbnail opens its image in a modal dialog, the browser or the dialog controller running its invokers;
     * Escape closes it and gives the focus back to the thumbnail, a click on the backdrop closes it too.
     */
    #[DataProvider('modes')]
    public function testALightboxOpensAtItsThumbnailAndClosesAtEscapeOrOnItsBackdrop(string $page): void
    {
        $this->open($page);
        $this->focus('[commandfor="lightbox-festival-2"]');
        $this->press(WebDriverKeys::ENTER);
        $this->waitUntil('document.querySelector("#lightbox-festival-2").matches(":modal")');
        $this->waitUntil('document.querySelector("#lightbox-festival-2 img").complete');

        self::assertTrue($this->read('(() => { const image = document.querySelector("#lightbox-festival-2 img").getBoundingClientRect(); return image.bottom <= innerHeight && image.right <= innerWidth && image.width > 0; })()'));

        $this->press(WebDriverKeys::ESCAPE);
        $this->waitForFocus('[commandfor="lightbox-festival-2"]');

        $this->click('[commandfor="lightbox-festival-2"]');
        $this->waitUntil('document.querySelector("#lightbox-festival-2").open');
        $this->click('#lightbox-festival-2 .isLightboxClose');
        $this->waitUntil('!document.querySelector("#lightbox-festival-2").open');
    }

    /**
     * A gallery lays its images in columns as wide as its container allows: fewer on a phone, without overflowing it,
     * three at least on a wide screen.
     */
    public function testAGalleryFitsItsColumnsToItsContainer(): void
    {
        $columns = 'getComputedStyle(document.querySelector("#festival > ul")).gridTemplateColumns.split(" ").length';

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(375, 800));
        $this->open('index.html');
        $phone = $this->read($columns);
        self::assertIsInt($phone);
        self::assertLessThanOrEqual(2, $phone);
        self::assertSame([], $this->read('[...document.querySelectorAll("#images > *")].filter((part) => part.getBoundingClientRect().right > document.documentElement.clientWidth + 1).map((part) => part.className)'));

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
        $this->open('index.html');
        self::assertGreaterThan(max(2, $phone), $this->read($columns));
    }

    /**
     * The variants of a picture follow the reader's system: the dark one where it is dark, the still one where motion
     * is reduced, the fallback otherwise.
     */
    public function testAPictureFollowsTheColourSchemeAndTheMotionOfTheSystem(): void
    {
        $source = 'document.querySelector("#themed").currentSrc';

        $light = [['name' => 'prefers-color-scheme', 'value' => 'light'], ['name' => 'prefers-reduced-motion', 'value' => 'no-preference']];

        foreach ([[$light, 'teal'], [[['name' => 'prefers-color-scheme', 'value' => 'dark']], 'navy'], [[['name' => 'prefers-reduced-motion', 'value' => 'reduce'], ['name' => 'prefers-color-scheme', 'value' => 'dark']], 'olive']] as [$features, $colour]) {
            $this->emulate($features);
            $this->open('index.html');
            $this->perform('document.querySelector("#themed").scrollIntoView()');
            $this->waitUntil('document.querySelector("#themed").complete && '.$source);

            $current = $this->read($source);
            self::assertIsString($current);
            self::assertStringContainsString($colour, $current);
        }

        $this->emulate([]);
    }

    public function testTheDarkThemeRepaintsThePanels(): void
    {
        $this->open('index.html');
        $clear = $this->read('getComputedStyle(document.querySelector("#dialog-modal")).backgroundColor');
        $this->open('dark.html');

        self::assertNotSame($clear, $this->read('getComputedStyle(document.querySelector("#dialog-modal")).backgroundColor'));
    }

    /**
     * What the kit writes and the stylesheet does not dress would show here: a part with no class of its own.
     */
    public function testEveryPartOfThePageCarriesAClassOfTheSheet(): void
    {
        $this->open('index.html');

        self::assertSame([], $this->read('[...document.querySelectorAll("[data-slot]")].map((part) => part.dataset.slot).filter((slot, index, slots) => slots.indexOf(slot) === index).filter((slot) => !["checkbox", "input", "label", "textarea", "dialog-trigger", "sheet-trigger", "popover-trigger", "dropdown-menu-trigger", "tooltip-trigger", "select", "alert-dialog-trigger", "kbd", "native-select", "native-select-option", "native-select-optgroup", "progress", "radio-group-item"].includes(slot) && !document.querySelector(`[data-slot="${slot}"]`).matches("[class*=is], [class*=has]"))'));
    }

    /**
     * axe-core on what 0.4 adds: the picture with its variant for forced colours, its placeholder and its description,
     * the image far down with its preview, and the deferred gallery, with a lightbox open; in clear, in dark and right
     * to left.
     */
    #[DataProvider('modes')]
    public function testThePlaceholdersPreviewsDescriptionsAndDeferredGalleryPassAxe(string $page): void
    {
        $this->open($page);
        $this->perform((string) file_get_contents(__DIR__.'/node_modules/axe-core/axe.min.js'));

        self::assertSame([], $this->axe('#forced'));
        self::assertSame([], $this->axe('#images figure:has(#forced)'));
        self::assertSame([], $this->axe('#far'));

        $this->perform('document.querySelector("[commandfor=\\"lightbox-archive-12\\"]").scrollIntoView()');
        $this->focus('[commandfor="lightbox-archive-12"]');
        $this->press(WebDriverKeys::ENTER);
        $this->waitUntil('document.querySelector("#lightbox-archive-12").open && getComputedStyle(document.querySelector("#lightbox-archive-12")).opacity === "1"');

        self::assertSame([], $this->axe('#lightbox-archive-12'));
    }

    /**
     * A deferred gallery: the browser skips its figures off screen, each kept at the ratio of its image until it is
     * rendered; its cells reach into the gap by the width of the focus ring, which the focus of a thumbnail then shows.
     */
    public function testADeferredGallerySkipsItsFiguresOffScreenAtTheirRatio(): void
    {
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
        $this->open('index.html');

        self::assertSame(
            ['auto', true, true],
            $this->read('(() => { const items = [...document.querySelectorAll("#archive li")], last = items[items.length - 1], previous = items[items.length - 2], ratio = (item) => { const box = item.getBoundingClientRect(), style = getComputedStyle(item); return (box.height - 2 * parseFloat(style.paddingTop)) / (box.width - 2 * parseFloat(style.paddingLeft)); }; return [getComputedStyle(last).contentVisibility, last.getBoundingClientRect().top > innerHeight && Math.abs(ratio(last) - 0.5) < 0.02, previous.getBoundingClientRect().top > innerHeight && Math.abs(ratio(previous) - 1) < 0.02]; })()'),
        );

        $this->perform('document.querySelector("#archive li:last-child").scrollIntoView()');
        $this->focus('[commandfor="lightbox-archive-12"]');

        self::assertTrue($this->read('(() => { const item = document.querySelector("#archive li:last-child"), style = getComputedStyle(item), ring = getComputedStyle(document.querySelector("[commandfor=\\"lightbox-archive-12\\"]")); return parseFloat(style.paddingLeft) >= parseFloat(ring.outlineWidth) + parseFloat(ring.outlineOffset) && document.activeElement.matches(":focus-visible"); })()'));
    }

    /**
     * The preview of an image far down the page is painted behind it through the CSSOM until the image has been
     * fetched: here it fails, and the preview goes all the same.
     */
    public function testAPreviewIsPaintedUntilTheImageArrives(): void
    {
        $this->open('index.html');

        self::assertTrue($this->read('getComputedStyle(document.querySelector("#previewed")).backgroundImage.startsWith("url(\"data:image/svg+xml")'));

        $this->perform('document.querySelector("#previewed").scrollIntoView()');
        $this->waitUntil('getComputedStyle(document.querySelector("#previewed")).backgroundImage === "none"');
    }

    /**
     * Where colours are forced, the picture shows its variant drawn for them; its placeholder paints its box otherwise.
     */
    public function testAPictureShowsItsVariantForForcedColours(): void
    {
        $source = 'document.querySelector("#forced").currentSrc';

        $this->emulate([['name' => 'forced-colors', 'value' => 'active']]);
        $this->open('index.html');
        $this->perform('document.querySelector("#forced").scrollIntoView()');
        $this->waitUntil('document.querySelector("#forced").complete && '.$source);
        $forced = $this->read($source);
        self::assertIsString($forced);
        self::assertStringContainsString('black', $forced);

        $this->emulate([['name' => 'forced-colors', 'value' => 'none']]);
        $this->open('index.html');
        $this->perform('document.querySelector("#forced").scrollIntoView()');
        $this->waitUntil('document.querySelector("#forced").complete && '.$source);
        $plain = $this->read($source);
        self::assertIsString($plain);
        self::assertStringContainsString('teal', $plain);
        self::assertNotSame('rgba(0, 0, 0, 0)', $this->read('getComputedStyle(document.querySelector("#forced")).backgroundColor'));

        $this->emulate([]);
    }

    /**
     * The violations axe-core finds in a part of the page, each rule with the parts it found it in.
     *
     * @return list<string>
     */
    private function axe(string $selector): array
    {
        $this->browser()->manage()->timeouts()->setScriptTimeout(30);
        $violations = $this->browser()->executeAsyncScript(\sprintf(
            'const done = arguments[arguments.length - 1]; axe.run(document.querySelector(%s), {runOnly: {type: "tag", values: ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]}}).then((result) => done(result.violations.map((violation) => violation.id + ": " + violation.nodes.map((node) => node.target.join(" ")).join(", "))), (error) => done(["axe: " + error]));',
            json_encode($selector, \JSON_THROW_ON_ERROR),
        ));
        self::assertIsArray($violations);

        return array_values(array_filter($violations, is_string(...)));
    }

    /**
     * The media features Chrome emulates, through its DevTools protocol; none restores those of the system.
     *
     * @param list<array{name: string, value: string}> $features
     */
    private function emulate(array $features): void
    {
        $driver = $this->browser()->getWebDriver();
        self::assertInstanceOf(RemoteWebDriver::class, $driver);
        $driver->executeCustomCommand('/session/:sessionId/goog/cdp/execute', 'POST', ['cmd' => 'Emulation.setEmulatedMedia', 'params' => ['features' => $features]]);
    }
}
