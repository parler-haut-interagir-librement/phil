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
    /**
     * Whether a pixel of the canvas of the celebration has been drawn.
     */
    private const string DRAWN = '(() => { const canvas = document.querySelector("#victory canvas"); if (!canvas) { return false; } const pixels = canvas.getContext("2d").getImageData(0, 0, canvas.width, canvas.height).data; for (let alpha = 3; alpha < pixels.length; alpha += 4) { if (pixels[alpha] > 0) { return true; } } return false; })()';

    /**
     * Counts, in `window.drawn`, the canvases that come into the celebration.
     */
    private const string WATCH = 'window.drawn = 0; new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => { if ("CANVAS" === node.nodeName) { window.drawn++; } }))).observe(document.querySelector("#victory"), {childList: true});';

    /**
     * The page in clear, then under the strict policy.
     *
     * @return iterable<string, array{string}>
     */
    public static function plainAndStrict(): iterable
    {
        yield 'clear' => ['index.html'];
        yield 'clear under a strict policy' => ['strict.html'];
    }

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
    #[DataProvider('strictModes')]
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
    #[DataProvider('plainAndStrict')]
    public function testAPictureChoosesItsCropByTheWidthOfTheScreen(string $page): void
    {
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(375, 800));
        $this->open($page);
        $this->perform('document.querySelector("#directed").scrollIntoView()');
        $this->waitUntil('document.querySelector("#directed").complete && document.querySelector("#directed").currentSrc');

        self::assertSame([true, 1], $this->read('(() => { const image = document.querySelector("#directed"), box = image.getBoundingClientRect(); return [image.currentSrc.includes("navy"), Math.round(box.width / box.height)]; })()'));

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
        $this->open($page);
        $this->perform('document.querySelector("#directed").scrollIntoView()');
        $this->waitUntil('document.querySelector("#directed").complete && document.querySelector("#directed").currentSrc');

        self::assertSame([true, 2], $this->read('(() => { const image = document.querySelector("#directed"), box = image.getBoundingClientRect(); return [image.currentSrc.includes("teal"), Math.round(box.width / box.height)]; })()'));
    }

    /**
     * A ratio of its own: the box kept square whatever the picture, the picture covering it, cropped on its left.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
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
    #[DataProvider('strictModes')]
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
    #[DataProvider('strictModes')]
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
    #[DataProvider('plainAndStrict')]
    public function testAGalleryFitsItsColumnsToItsContainer(string $page): void
    {
        $columns = 'getComputedStyle(document.querySelector("#festival > ul")).gridTemplateColumns.split(" ").length';

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(375, 800));
        $this->open($page);
        $phone = $this->read($columns);
        self::assertIsInt($phone);
        self::assertLessThanOrEqual(2, $phone);
        self::assertSame([], $this->read('[...document.querySelectorAll("#images > *")].filter((part) => part.getBoundingClientRect().right > document.documentElement.clientWidth + 1).map((part) => part.className)'));

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
        $this->open($page);
        self::assertGreaterThan(max(2, $phone), $this->read($columns));
    }

    /**
     * The variants of a picture follow the reader's system: the dark one where it is dark, the still one where motion
     * is reduced, the fallback otherwise.
     */
    #[DataProvider('plainAndStrict')]
    public function testAPictureFollowsTheColourSchemeAndTheMotionOfTheSystem(string $page): void
    {
        $source = 'document.querySelector("#themed").currentSrc';

        $light = [['name' => 'prefers-color-scheme', 'value' => 'light'], ['name' => 'prefers-reduced-motion', 'value' => 'no-preference']];

        foreach ([[$light, 'teal'], [[['name' => 'prefers-color-scheme', 'value' => 'dark']], 'navy'], [[['name' => 'prefers-reduced-motion', 'value' => 'reduce'], ['name' => 'prefers-color-scheme', 'value' => 'dark']], 'olive']] as [$features, $colour]) {
            $this->emulate($features);
            $this->open($page);
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
    #[DataProvider('strictModes')]
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
    #[DataProvider('plainAndStrict')]
    public function testADeferredGallerySkipsItsFiguresOffScreenAtTheirRatio(string $page): void
    {
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
        $this->open($page);

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
    #[DataProvider('plainAndStrict')]
    public function testAPreviewIsPaintedUntilTheImageArrives(string $page): void
    {
        $this->open($page);

        self::assertTrue($this->read('getComputedStyle(document.querySelector("#previewed")).backgroundImage.startsWith("url(\"data:image/svg+xml")'));

        $this->perform('document.querySelector("#previewed").scrollIntoView()');
        $this->waitUntil('getComputedStyle(document.querySelector("#previewed")).backgroundImage === "none"');
    }

    /**
     * Where colours are forced, the picture shows its variant drawn for them; its placeholder paints its box otherwise.
     */
    #[DataProvider('plainAndStrict')]
    public function testAPictureShowsItsVariantForForcedColours(string $page): void
    {
        $source = 'document.querySelector("#forced").currentSrc';

        $this->emulate([['name' => 'forced-colors', 'value' => 'active']]);
        $this->open($page);
        $this->perform('document.querySelector("#forced").scrollIntoView()');
        $this->waitUntil('document.querySelector("#forced").complete && '.$source);
        $forced = $this->read($source);
        self::assertIsString($forced);
        self::assertStringContainsString('black', $forced);

        $this->emulate([['name' => 'forced-colors', 'value' => 'none']]);
        $this->open($page);
        $this->perform('document.querySelector("#forced").scrollIntoView()');
        $this->waitUntil('document.querySelector("#forced").complete && '.$source);
        $plain = $this->read($source);
        self::assertIsString($plain);
        self::assertStringContainsString('teal', $plain);
        self::assertNotSame('rgba(0, 0, 0, 0)', $this->read('getComputedStyle(document.querySelector("#forced")).backgroundColor'));

        $this->emulate([]);
    }

    /**
     * axe-core on what 0.5 adds: the caption over its image, the gallery laid out as a carousel, the image with its points,
     * the hero, the video with its captions and transcript, and the figure of several images; then on the frames, the
     * token and the pixel art of 0.7; in clear, in dark and right to left, and under the strict policy.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testTheNewImageRecipesPassAxe(string $page): void
    {
        $this->open($page);
        $this->perform((string) file_get_contents(__DIR__.'/node_modules/axe-core/axe.min.js'));

        self::assertSame([], $this->axe('#figures'));
        self::assertSame([], $this->axe('#frames'));
    }

    /**
     * The caption lies over the foot of its image, as wide as it, its light text on a dark veil.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testACaptionOverItsImageLiesOnItsFoot(string $page): void
    {
        $this->open($page);
        $this->perform('document.querySelector("#overlaid").scrollIntoView()');

        self::assertSame(
            [true, true, true, 'oklch(0.99 0 0)'],
            $this->read('(() => { const image = document.querySelector("#overlaid").getBoundingClientRect(), caption = document.querySelector("#overlaid").closest("figure").querySelector("figcaption"), box = caption.getBoundingClientRect(); return [Math.abs(box.bottom - image.bottom) < 1, Math.abs(box.left - image.left) < 1 && Math.abs(box.right - image.right) < 1, box.top > image.top, getComputedStyle(caption).color]; })()'),
        );
    }

    /**
     * A gallery laid out as a carousel: one row wider than its box, which snaps, and which the arrows scroll once the list
     * has the focus; the browser draws a marker per figure and names its buttons from the attributes of the list.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testACarouselGalleryIsARowThatSnapsAndScrollsAtTheArrows(string $page): void
    {
        $this->open($page);
        $list = 'document.querySelector("#strip ul")';

        self::assertSame(
            [true, true, 'x mandatory', true],
            $this->read('(() => { const list = '.$list.', items = [...list.children], style = getComputedStyle(list); return [list.scrollWidth > list.clientWidth, items.every((item) => Math.abs(item.getBoundingClientRect().top - items[0].getBoundingClientRect().top) < 1), style.scrollSnapType, style.scrollMarkerGroup.startsWith("after")]; })()'),
        );
        self::assertStringContainsString('Next photo', (string) json_encode($this->read('getComputedStyle('.$list.', "::scroll-button(inline-end)").content')));

        $this->perform($list.'.scrollIntoView()');
        $this->focus('#strip ul');
        $this->press($this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT);
        $this->waitUntil('Math.abs('.$list.'.scrollLeft) > 0');
    }

    /**
     * Each point stands at its place on the image, the same right to left, the typed attr() placing the one between two
     * classes exactly; on a phone, the points read as a list under the image.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testThePointsOfAnImageStandAtTheirPlace(string $page): void
    {
        $this->open($page);
        $this->perform('document.querySelector("#plan").scrollIntoView()');

        self::assertSame(
            [[20, 30], [72.5, 64]],
            $this->read('(() => { const image = document.querySelector("#plan img").getBoundingClientRect(); return [...document.querySelectorAll("#plan .isHotspot")].map((point) => { const box = point.getBoundingClientRect(); return [Math.round(((box.left + box.width / 2) - image.left) / image.width * 200) / 2, Math.round(((box.top + box.height / 2) - image.top) / image.height * 200) / 2]; }); })()'),
        );

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(375, 800));
        $this->open($page);

        self::assertTrue($this->read('(() => { const image = document.querySelector("#plan img").getBoundingClientRect(); return [...document.querySelectorAll("#plan .isHotspot")].every((point) => point.getBoundingClientRect().top >= image.bottom && point.getBoundingClientRect().right <= document.documentElement.clientWidth); })()'));

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
    }

    /**
     * The image of a hero covers its whole box, its content on a veil over it.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testAHeroCoversItsBoxWithItsImage(string $page): void
    {
        $this->open($page);

        self::assertSame(
            [true, true, 'cover'],
            $this->read('(() => { const hero = document.querySelector("#hero").getBoundingClientRect(), image = document.querySelector("#hero img"), box = image.getBoundingClientRect(), content = document.querySelector("#hero .isHeroContent").getBoundingClientRect(); return [Math.abs(box.width - hero.width) < 1 && Math.abs(box.height - hero.height) < 1, content.top >= hero.top && content.bottom <= hero.bottom, getComputedStyle(image).objectFit]; })()'),
        );
    }

    /**
     * The video waits for its reader: nothing fetched, its space reserved at its ratio on a phone, its captions shown;
     * played by the reader, its file arrives.
     */
    #[DataProvider('plainAndStrict')]
    public function testAVideoWaitsForItsReader(string $page): void
    {
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(375, 800));
        $this->open($page);
        $video = 'document.querySelector("#clip")';

        self::assertSame(
            [true, 0, true, 'showing'],
            $this->read('(() => { const video = '.$video.', box = video.getBoundingClientRect(); return [video.paused, video.readyState, Math.abs(box.width / box.height - 16 / 9) < 0.02, video.textTracks[0].mode]; })()'),
        );

        $this->perform($video.'.muted = true; '.$video.'.play()');
        $this->waitUntil($video.'.readyState > 0 && '.$video.'.videoWidth === 320');

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
    }

    /**
     * Several images under one caption, in as many columns as asked, the caption under them all.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testAFigureLaysItsImagesInColumnsUnderOneCaption(string $page): void
    {
        $this->open($page);

        self::assertSame(
            [3, true, true],
            $this->read('(() => { const images = [...document.querySelectorAll("#moments img")], caption = document.querySelector("#moments").closest("figure").querySelector("figcaption").getBoundingClientRect(); return [getComputedStyle(document.querySelector("#moments")).gridTemplateColumns.split(" ").length, images.every((image) => Math.abs(image.getBoundingClientRect().top - images[0].getBoundingClientRect().top) < 1), images.every((image) => image.getBoundingClientRect().bottom <= caption.top)]; })()'),
        );
    }

    /**
     * The button of the demonstration plays the celebration: its canvas comes over the whole page, hidden from screen
     * readers and letting the pointer through, something is drawn on it, the message is said, and the canvas goes.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testACelebrationSaysItsMessageDrawsOverThePageAndGoes(string $page): void
    {
        $this->open($page);

        self::assertSame(['status', 'polite', '', false], $this->read('(() => { const region = document.querySelector("#victory .isCelebrationMessage"); return [region.getAttribute("role"), region.getAttribute("aria-live"), region.textContent, Boolean(document.querySelector("#victory canvas"))]; })()'));

        $this->click('#celebrate');
        $this->waitUntil('document.querySelector("#victory canvas")');

        self::assertSame(['true', 'none', 'fixed', true, true], $this->read('(() => { const canvas = document.querySelector("#victory canvas"), style = getComputedStyle(canvas), box = canvas.getBoundingClientRect(); return [canvas.getAttribute("aria-hidden"), style.pointerEvents, style.position, Math.round(box.width) === document.documentElement.clientWidth && Math.round(box.height) === document.documentElement.clientHeight, !document.elementsFromPoint(innerWidth / 2, innerHeight / 2).includes(canvas)]; })()'));
        $this->waitUntil(self::DRAWN);
        $this->waitUntil('document.querySelector("#victory .isCelebrationMessage").textContent === "You won!"');
        $this->waitUntil('!document.querySelector("#victory canvas")');
    }

    /**
     * Any element of the page plays the celebration by the DOM event `celebrate`, its `detail` giving the message and
     * the effect of this time.
     */
    #[DataProvider('plainAndStrict')]
    public function testTheEventCelebratePlaysTheCelebrationWithItsOwnMessage(string $page): void
    {
        $this->open($page);
        $this->perform('document.querySelector("#own").dispatchEvent(new CustomEvent("celebrate", {bubbles: true, detail: {message: "Bingo", effect: "fireworks"}}))');

        $this->waitUntil('document.querySelector("#victory canvas")');
        $this->waitUntil(self::DRAWN);
        $this->waitUntil('document.querySelector("#victory .isCelebrationMessage").textContent === "Bingo"');
        $this->waitUntil('!document.querySelector("#victory canvas")');
    }

    /**
     * Where motion is reduced, by the system or by `data-motion="reduce"` on the root, the message is said and no canvas
     * ever comes.
     */
    #[DataProvider('plainAndStrict')]
    public function testNothingIsDrawnUnderReducedMotionButTheMessageIsSaid(string $page): void
    {
        $this->emulate([['name' => 'prefers-reduced-motion', 'value' => 'reduce']]);

        try {
            $this->open($page);
            $this->perform(self::WATCH);
            $this->click('#celebrate');
            $this->waitUntil('document.querySelector("#victory .isCelebrationMessage").textContent === "You won!"');
            usleep(300_000);

            self::assertSame(0, $this->read('window.drawn'));
        } finally {
            $this->emulate([]);
        }

        $this->open($page);
        $this->perform('document.documentElement.dataset.motion = "reduce"; '.self::WATCH);
        $this->perform('window.dispatchEvent(new CustomEvent("celebrate"))');
        $this->waitUntil('document.querySelector("#victory .isCelebrationMessage").textContent === "You won!"');
        usleep(300_000);

        self::assertSame(0, $this->read('window.drawn'));
    }

    /**
     * A celebration on a page that arrives on a victory plays as soon as it connects; removed from the page, it stops at
     * once and leaves the page free for the next one.
     */
    #[DataProvider('plainAndStrict')]
    public function testACelebrationPlaysAtOnceWithAutoplayAndStopsWhenRemoved(string $page): void
    {
        $arrive = 'const copy = document.querySelector("#celebrations > .isCelebration").cloneNode(true); copy.id = "%s"; copy.querySelector("button")?.remove(); copy.querySelector("canvas")?.remove(); copy.querySelector(".isCelebrationMessage").textContent = ""; copy.dataset.celebrationAutoplayValue = "true"; copy.dataset.celebrationMessageValue = "First place"; copy.dataset.celebrationDurationValue = "4000"; document.querySelector("#celebrations").replaceChildren(copy);';

        $this->open($page);
        $this->perform(\sprintf($arrive, 'arrival'));
        $this->waitUntil('document.querySelector("#arrival canvas")');
        $this->waitUntil('document.querySelector("#arrival .isCelebrationMessage").textContent === "First place"');

        $this->perform(\sprintf($arrive, 'next'));
        $this->waitUntil('document.querySelector("#next canvas")');

        self::assertSame(1, $this->read('document.querySelectorAll("canvas").length'));
    }

    /**
     * axe-core on the celebration, waiting and while it plays, in clear, in dark and right to left.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testACelebrationPassesAxe(string $page): void
    {
        $this->open($page);
        $this->perform((string) file_get_contents(__DIR__.'/node_modules/axe-core/axe.min.js'));

        self::assertSame([], $this->axe('#celebrations'));

        $this->click('#celebrate');
        $this->waitUntil('document.querySelector("#victory canvas") && document.querySelector("#victory .isCelebrationMessage").textContent !== ""');

        self::assertSame([], $this->axe('#celebrations'));
    }

    /**
     * The frame, the token, the pixel art and the name of a view transition come from the sheet alone: the corners, the
     * line and the shadow of the tokens, a square disc in its ring, pixels kept square, and a name under which a view
     * transition moves the image.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testAFrameATokenPixelArtAndATransitionComeFromTheSheet(string $page): void
    {
        $this->open($page);
        $this->perform('document.querySelector("#frames").scrollIntoView()');

        self::assertSame(
            [true, 'solid', true, '50%', true, 'cover', '4px', 'pixelated', 'poster'],
            $this->read('(() => { const framed = getComputedStyle(document.querySelector("#framed")), pawn = document.querySelector("#pawn"), token = getComputedStyle(pawn), box = pawn.getBoundingClientRect(); return [framed.borderTopLeftRadius !== "0px", framed.borderTopStyle, framed.boxShadow !== "none", token.borderTopLeftRadius, Math.abs(box.width - box.height) < 1, token.objectFit, token.borderTopWidth, getComputedStyle(document.querySelector("#pixels")).imageRendering, getComputedStyle(document.querySelector("#poster")).viewTransitionName]; })()'),
        );

        $this->browser()->manage()->timeouts()->setScriptTimeout(10);
        self::assertTrue($this->browser()->executeAsyncScript('const done = arguments[arguments.length - 1]; const transition = document.startViewTransition(() => document.querySelector("#poster").classList.add("isMoved")); transition.ready.then(() => done(document.documentElement.getAnimations({subtree: true}).some((animation) => animation.effect.pseudoElement === "::view-transition-group(poster)")), (error) => done(String(error)));'));
    }

    /**
     * On paper: the carousel a grid printed whole, without markers or buttons; a figure never cut, its caption kept
     * with its image and its veil printed; an image never taller than the page; a deferred gallery rendered whole; the
     * close button of a lightbox and the canvas of a celebration left out.
     */
    #[DataProvider('plainAndStrict')]
    public function testTheImagesPrintLegibly(string $page): void
    {
        $this->open($page);
        $this->click('#celebrate');
        $this->waitUntil('document.querySelector("#victory canvas")');
        $this->emulate([], 'print');

        try {
            self::assertSame(
                ['row', true, 'none', 'avoid', 'avoid', 'exact', true, 'visible', 'none', 'none', 'avoid'],
                $this->read('(() => { const strip = document.querySelector("#strip ul"), style = getComputedStyle(strip), figure = document.querySelector("#overlaid").closest("figure"), caption = figure.querySelector("figcaption"); return [style.gridAutoFlow, strip.scrollWidth <= strip.clientWidth + 1, style.scrollMarkerGroup, getComputedStyle(figure).breakInside, getComputedStyle(caption).breakBefore, getComputedStyle(caption).printColorAdjust, getComputedStyle(document.querySelector("#photo")).maxHeight.endsWith("px"), getComputedStyle(document.querySelector("#archive li")).contentVisibility, getComputedStyle(document.querySelector("#lightbox-festival-1 .isLightboxClose")).display, getComputedStyle(document.querySelector("#victory canvas")).display, getComputedStyle(document.querySelector("#festival li")).breakInside]; })()'),
            );
        } finally {
            $this->emulate([]);
        }
    }

    /**
     * Right to left: a caption reads from the right, a gallery and a carousel start on the right, the button at the
     * start of a carousel points to the right, and the close button of a lightbox sits in the top left corner; the
     * mirror left to right.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testTheImagesReadInTheDirectionOfThePage(string $page): void
    {
        $rtl = $this->isRightToLeft($page);
        $this->open($page);

        self::assertSame(
            [$rtl, $rtl, $rtl, $rtl, !$rtl],
            $this->read('(() => { const caption = document.querySelector("#photo").closest("figure").querySelector("figcaption"), range = document.createRange(); range.selectNodeContents(caption.firstChild); const text = range.getBoundingClientRect(), box = caption.getBoundingClientRect(), gallery = [...document.querySelectorAll("#festival li")].map((item) => item.getBoundingClientRect()), strip = document.querySelector("#strip ul"), first = strip.firstElementChild.getBoundingClientRect(), list = strip.getBoundingClientRect(), start = getComputedStyle(strip, "::scroll-button(inline-start)").content; return [Math.abs(text.right - box.right) < Math.abs(text.left - box.left), gallery[0].left > gallery[1].left, Math.abs(first.right - list.right) < Math.abs(first.left - list.left), start.includes("\u203A"), start.includes("\u2039")]; })()'),
        );

        $this->focus('[commandfor="lightbox-festival-1"]');
        $this->press(WebDriverKeys::ENTER);
        $this->waitUntil('document.querySelector("#lightbox-festival-1").open');

        self::assertSame($rtl, $this->read('(() => { const close = document.querySelector("#lightbox-festival-1 .isLightboxClose").getBoundingClientRect(), dialog = document.querySelector("#lightbox-festival-1").getBoundingClientRect(); return close.left + close.width / 2 < dialog.left + dialog.width / 2; })()'));
    }

    /**
     * Every recipe of the page renders under the strict policy with nothing refused, and the policy holds: a style
     * attribute written afterwards is refused, and seen.
     */
    #[DataProvider('strictModes')]
    public function testThePageRendersUnderAStrictPolicyThatRefusesWhatItForbids(string $page): void
    {
        $this->open($page);

        self::assertSame([], $this->read('window.violations'));

        $this->perform('document.body.insertAdjacentHTML("beforeend", "<b id=\\"probe\\" style=\\"color: rgb(255, 0, 0)\\">Refused</b>")');
        $this->waitUntil('window.violations.length === 1 && window.violations[0].startsWith("style-src-attr inline")');

        self::assertNotSame('rgb(255, 0, 0)', $this->read('getComputedStyle(document.querySelector("#probe")).color'));

        $this->perform('window.violations = []');
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
     * The media features Chrome emulates, through its DevTools protocol, and the medium, `print` or the screen when
     * empty; none restores those of the system.
     *
     * @param list<array{name: string, value: string}> $features
     */
    private function emulate(array $features, string $media = ''): void
    {
        $driver = $this->browser()->getWebDriver();
        self::assertInstanceOf(RemoteWebDriver::class, $driver);
        $driver->executeCustomCommand('/session/:sessionId/goog/cdp/execute', 'POST', ['cmd' => 'Emulation.setEmulatedMedia', 'params' => ['media' => $media, 'features' => $features]]);
    }
}
