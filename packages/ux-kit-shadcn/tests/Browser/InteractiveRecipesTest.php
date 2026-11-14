<?php

declare(strict_types=1);

namespace Phil\UxKitShadcn\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverKeys;
use Override;
use Phil\UxKit\Test\BrowserTestCase;
use Phil\UxKit\Test\RecipesPage;
use Phil\UxKitShadcn\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The controllers are those of the PHIL UX kit, the markup and the stylesheet are not: what the utilities do once
 * compiled, with the controllers acting on them, in clear, in dark and right to left.
 */
#[CoversNothing]
final class InteractiveRecipesTest extends BrowserTestCase
{
    private const string ROTATION = 'getComputedStyle(document.querySelector("%s > span > [data-tree-target=toggle]")).rotate';

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
            TailwindStylesheet::compile(),
            sys_get_temp_dir().'/phil-ux-kit-shadcn-browser',
            __DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js',
        );
    }

    #[Override]
    protected static function screenshots(): string
    {
        return __DIR__.'/screenshots';
    }

    /**
     * Each layout of the images, its part of the page and its reference screenshot.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function layouts(): iterable
    {
        yield 'a fixed image' => ['#layout-fixed', 'image-fixed'];
        yield 'a constrained image' => ['#layout-constrained', 'image-constrained'];
        yield 'an image across its box' => ['#layout-full-width', 'image-full-width'];
        yield 'an image cropped to a ratio' => ['#layout-ratio', 'image-ratio'];
        yield 'a gallery in a grid' => ['#layout-grid', 'gallery-grid'];
        yield 'a figure of three columns' => ['#layout-columns', 'figure-columns'];
    }

    /**
     * Each layout looks as it did when its reference was taken, within the tolerance of `Screenshots`: a change of the
     * utilities or of a recipe that moves, resizes or crops an image shows here before a release.
     */
    #[DataProvider('layouts')]
    public function testEachLayoutLooksAsItsReference(string $selector, string $name): void
    {
        $this->open('index.html');
        $this->assertLooksLike($selector, $name);
    }

    #[DataProvider('modes')]
    public function testAnAnimatedHeadlineShowsOneWordMovesAndPauses(string $page): void
    {
        $this->open($page);
        $this->waitUntil('!document.querySelector("[data-animated-headline-target=toggle]").hidden');

        self::assertGreaterThan(0, $this->read('document.querySelector("[data-animated-headline-target=reveal]").getBoundingClientRect().width'));
        $this->waitUntil('[...document.querySelectorAll("[data-animated-headline-target=word]")].filter((word) => getComputedStyle(word).visibility === "visible").length === 1');
        $this->waitUntil('document.querySelector("[data-animated-headline-target=word].isCurrent").textContent !== "sites"');

        $this->focus('[data-animated-headline-target=toggle]');
        $this->press(WebDriverKeys::ENTER);
        $this->waitUntil('document.querySelector("[data-animated-headline-target=toggle]").textContent === "Play animation"');
    }

    #[DataProvider('modes')]
    public function testATreeMovesAtTheKeyboardAndFolds(string $page): void
    {
        $forward = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT;
        $back = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_RIGHT : WebDriverKeys::ARROW_LEFT;

        $this->open($page);

        self::assertSame([0, -1, -1, -1, -1], $this->read('[...document.querySelectorAll("#tree [role=treeitem]")].map((item) => item.tabIndex)'));
        self::assertSame('none', $this->read('getComputedStyle(document.querySelector("#notes > [role=group]")).display'));

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
        $this->waitUntil('getComputedStyle(document.querySelector("#notes > [role=group]")).display === "block"');
        $this->press($forward);
        $this->waitForFocus('#deep');
        $this->press($back);
        $this->waitForFocus('#notes');
        $this->press($back);
        $this->waitUntil('document.querySelector("#notes").getAttribute("aria-expanded") === "false"');
        $this->waitUntil('getComputedStyle(document.querySelector("#notes > [role=group]")).display === "none"');

        $this->press(WebDriverKeys::SPACE);
        $this->waitUntil('document.querySelector("#notes").getAttribute("aria-selected") === "true"');
    }

    #[DataProvider('modes')]
    public function testATreeItemDrawsItsOwnStateAndNotThatOfItsParent(string $page): void
    {
        $this->open($page);
        $this->waitUntil(\sprintf('%s === %s', \sprintf(self::ROTATION, '#docs'), $this->isRightToLeft($page) ? '"-90deg"' : '"90deg"'));

        self::assertSame('none', $this->read(\sprintf(self::ROTATION, '#notes')), 'a closed branch inside an open one keeps its arrow');

        $this->focus('#docs');
        $this->press(WebDriverKeys::SPACE);
        $this->waitUntil('document.querySelector("#docs").getAttribute("aria-selected") === "true"');

        $row = 'getComputedStyle(document.querySelector("%s > span")).backgroundColor';
        $this->waitUntil(\sprintf($row, '#docs').' !== "rgba(0, 0, 0, 0)"');

        self::assertSame('rgba(0, 0, 0, 0)', $this->read(\sprintf($row, '#report')), 'the row of a child stays unselected');

        $this->waitUntil('getComputedStyle(document.querySelector("#docs > span")).boxShadow !== "none"');

        self::assertSame('none', $this->read('getComputedStyle(document.querySelector("#report > span")).boxShadow'), 'the ring of the focus stays on its row');
    }

    /**
     * The carousel's buttons, hidden without the controller, shown once it runs; the next button and the arrow key
     * forward scroll one slide and its gap along the direction of the page, the previous button disabled
     * at the start and the next one at the end.
     */
    #[DataProvider('modes')]
    public function testACarouselScrollsOneSlideAtItsButtonsAndArrows(string $page): void
    {
        $forward = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT;
        $scrolled = 'Math.abs(document.querySelector("#slides [data-carousel-target=viewport]").scrollLeft)';
        $width = 'document.querySelector("#slides [data-carousel-target=viewport]").clientWidth';
        $step = 'Math.abs(document.querySelector("#slide-two").offsetLeft - document.querySelector("#slide-one").offsetLeft)';

        $this->open($page);
        $this->waitUntil('!document.querySelector("#slides-next").hidden');

        self::assertTrue($this->read('document.querySelector("#slides-previous").disabled'), 'nothing before the first slide');
        self::assertFalse($this->read('document.querySelector("#slides-next").disabled'));
        self::assertGreaterThan(0, $this->read('document.querySelector("#slides-next").getBoundingClientRect().width'));
        self::assertEqualsWithDelta($this->read($width), $this->read('document.querySelector("#slide-one").getBoundingClientRect().width'), 1, 'a slide as wide as the viewport');

        $this->click('#slides-next');
        $this->waitUntil(\sprintf('Math.abs(%s - %s) <= 1', $scrolled, $step));
        $this->waitUntil('!document.querySelector("#slides-previous").disabled');

        $this->focus('#slides');
        $this->press($forward);
        $this->waitUntil(\sprintf('Math.abs(%s - 2 * %s) <= 1', $scrolled, $step));
        $this->waitUntil('document.querySelector("#slides-next").disabled');

        self::assertSame('Three', $this->read('(() => { const v = document.querySelector("#slides [data-carousel-target=viewport]").getBoundingClientRect(), s = document.querySelector("#slide-three").getBoundingClientRect(); return Math.abs(v.left - s.left) <= 1 ? document.querySelector("#slide-three").textContent : ""; })()'), 'the last slide in view');
    }

    #[DataProvider('modes')]
    public function testANumberInputStopsAtItsLimits(string $page): void
    {
        $this->open($page);
        $this->waitUntil('!document.querySelector("[aria-label=Increase]").hidden');

        self::assertGreaterThan(0, $this->read('document.querySelector("[aria-label=Increase]").getBoundingClientRect().width'));

        $this->click('[aria-label="Increase"]');
        $this->waitUntil('document.querySelector("#guests-input").value === "3"');

        self::assertTrue($this->read('document.querySelector("[aria-label=Increase]").disabled'));
    }

    #[DataProvider('modes')]
    public function testAColourPickerKeepsItsPartsInStep(string $page): void
    {
        $this->open($page);
        $this->waitUntil('!document.querySelector("[aria-label=Plum]").hidden');

        self::assertSame('true', $this->read('document.querySelector("[aria-label=Blue]").getAttribute("aria-pressed")'));
        self::assertGreaterThan(0, $this->read('document.querySelector("[aria-label=Plum]").getBoundingClientRect().width'));

        $this->click('[aria-label="Plum"]');
        $this->waitUntil('document.querySelector("[data-color-picker-target=text]").value === "#993366"');

        self::assertSame('#993366', $this->read('document.querySelector("#accent").value'));
        self::assertSame('true', $this->read('document.querySelector("[aria-label=Plum]").getAttribute("aria-pressed")'));
        $paint = $this->read('getComputedStyle(document.querySelector("[aria-label=Plum]")).backgroundColor');
        self::assertIsString($paint);
        self::assertStringContainsString('153, 51, 102', $paint);
    }

    #[DataProvider('modes')]
    public function testATagGoesAwayAndTheFocusStaysNearby(string $page): void
    {
        $this->open($page);
        $this->waitUntil('!document.querySelector("#tag-one [data-tag-target=remove]").hidden');
        $this->focus('#tag-one [data-tag-target=remove]');
        $this->press(WebDriverKeys::ENTER);
        $this->waitForFocus('#tag-two [data-tag-target=remove]');

        self::assertNull($this->read('document.querySelector("#tag-one")'));
    }

    #[DataProvider('modes')]
    public function testAFileInputSievesWhatItIsGiven(string $page): void
    {
        $this->open($page);

        self::assertSame('none', $this->read('getComputedStyle(document.querySelector("[data-file-input-target=error]")).display'), 'an empty message takes no room');

        $this->perform('const t = new DataTransfer(); t.items.add(new File(["x"], "a.png")); t.items.add(new File(["x"], "b.txt")); const i = document.querySelector("#photos-field"); i.files = t.files; i.dispatchEvent(new Event("change", {bubbles: true}));');
        $this->waitUntil('document.querySelectorAll("[data-file-input-target=list] > *").length === 1');

        self::assertSame(1, $this->read('document.querySelector("#photos-field").files.length'));
        self::assertStringContainsString('1', (string) json_encode($this->read('document.querySelector("[data-file-input-target=error]").textContent')));
        self::assertFalse($this->read('document.querySelector("[data-file-input-target=clear]").hidden'));
        self::assertSame('block', $this->read('getComputedStyle(document.querySelector("[data-file-input-target=error]")).display'));
        self::assertLessThan(3, $this->read('document.querySelector("#photos-field").getBoundingClientRect().width'), 'the field is hidden in its zone');
    }

    #[DataProvider('modes')]
    public function testABeforeAfterCutsWhereItsSliderStands(string $page): void
    {
        $this->open($page);
        $this->waitUntil('document.querySelector("#compare").style.getPropertyValue("--phil-before-after-position") === "30%"');

        $inset = $this->isRightToLeft($page) ? 'inset(0px 0px 0px 70%)' : 'inset(0px 70% 0px 0px)';
        $this->waitUntil(\sprintf('getComputedStyle(document.querySelector("[data-before-after-target=before]")).clipPath === %s', json_encode($inset, \JSON_THROW_ON_ERROR)));

        self::assertSame('relative', $this->read('getComputedStyle(document.querySelector("#compare")).position'));
        self::assertSame('absolute', $this->read('getComputedStyle(document.querySelector("#compare"), "::after").position'));
        self::assertNotSame('none', $this->read('getComputedStyle(document.querySelector("#compare"), "::after").content'), 'the line of the cut is drawn');
        self::assertLessThan(2, $this->read('(() => { const c = document.querySelector("#compare"); return Math.abs(parseFloat(getComputedStyle(c, "::after").insetInlineStart) - c.clientWidth * 0.3); })()'));

        $this->focus('#compare input');
        $this->press(WebDriverKeys::END);
        $this->waitUntil('document.querySelector("#compare").style.getPropertyValue("--phil-before-after-position") === "100%"');
        $this->waitUntil('getComputedStyle(document.querySelector("[data-before-after-target=before]")).clipPath.startsWith("inset(0px")');
        self::assertSame('0', $this->read('getComputedStyle(document.querySelector("#compare input")).opacity'), 'the native slider stays invisible over the pictures');
    }

    #[DataProvider('modes')]
    public function testARatingFillsItsStarsUpToTheChoice(string $page): void
    {
        $forward = $this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT;
        $stars = '[...document.querySelectorAll("#note label:has(input:not([value=\'0\']))")].map((star) => getComputedStyle(star).color)';

        $this->open($page);
        $this->waitUntil(\sprintf('new Set(%s).size === 2', $stars));

        $before = $this->read($stars);
        self::assertIsArray($before);
        self::assertSame($before[0], $before[2]);
        self::assertNotSame($before[2], $before[3]);

        $this->focus('#note input:checked');
        $this->press($forward);
        $this->waitUntil('document.querySelector("#note input:checked").value === "4"');
        $this->waitUntil(\sprintf('(() => { const c = %s; return c[3] === c[0] && c[4] !== c[0]; })()', $stars));
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
        $this->waitUntil('!document.querySelector("[data-copy-button-target=trigger]").hidden');
        $this->click('[data-copy-button-target=trigger]');
        $this->waitUntil('document.querySelector("#copy [role=status]").textContent !== ""');
    }

    /**
     * The series take the chart tokens, each its own, and the second line its dash.
     */
    #[DataProvider('modes')]
    public function testAChartColoursEachSeries(string $page): void
    {
        $this->open($page);

        self::assertNotSame(
            $this->read('getComputedStyle(document.querySelector("#bars [data-series=\'1\'] rect")).fill'),
            $this->read('getComputedStyle(document.querySelector("#bars [data-series=\'2\'] rect")).fill'),
        );
        self::assertSame('none', $this->read('getComputedStyle(document.querySelector("#lines path[data-series=\'1\']")).strokeDasharray'));
        self::assertNotSame('none', $this->read('getComputedStyle(document.querySelector("#lines path[data-series=\'2\']")).strokeDasharray'));
        self::assertSame('none', $this->read('getComputedStyle(document.querySelector("#lines path[data-series=\'2\']")).fill'));
    }

    #[DataProvider('modes')]
    public function testTheRecipesFitAPhone(string $page): void
    {
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(375, 800));
        $this->open($page);

        self::assertSame([], $this->read('[...document.querySelectorAll("#own > *")].filter((part) => part.getBoundingClientRect().right > document.documentElement.clientWidth + 1 || part.getBoundingClientRect().left < -1).map((part) => part.dataset.slot)'));

        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
    }

    /**
     * The utilities of a deferred gallery reach the stylesheet: its figures off screen are skipped and kept at the ratio
     * of their image; the preview of an image far down is painted until the image arrives.
     */
    #[DataProvider('plainAndStrict')]
    public function testADeferredGalleryAndAPreviewInTailwind(string $page): void
    {
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
        $this->open($page);

        self::assertSame(
            ['auto', true, true],
            $this->read('(() => { const items = [...document.querySelectorAll("#archive li")], last = items[items.length - 1], previous = items[items.length - 2], ratio = (item) => { const box = item.getBoundingClientRect(), style = getComputedStyle(item); return (box.height - 2 * parseFloat(style.paddingTop)) / (box.width - 2 * parseFloat(style.paddingLeft)); }; return [getComputedStyle(last).contentVisibility, last.getBoundingClientRect().top > innerHeight && Math.abs(ratio(last) - 0.5) < 0.02, previous.getBoundingClientRect().top > innerHeight && Math.abs(ratio(previous) - 1) < 0.02]; })()'),
        );
        self::assertTrue($this->read('getComputedStyle(document.querySelector("#previewed")).backgroundImage.startsWith("url(\"data:image/svg+xml")'));

        $this->perform('document.querySelector("#previewed").scrollIntoView()');
        $this->waitUntil('getComputedStyle(document.querySelector("#previewed")).backgroundImage === "none"');
    }

    public function testTheDarkThemeRepaintsTheRecipes(): void
    {
        $paint = 'getComputedStyle(document.querySelector("#slides-next")).backgroundColor';

        $this->open('index.html');
        $clear = $this->read($paint);
        $this->open('dark.html');

        self::assertNotSame($clear, $this->read($paint));
    }

    /**
     * axe-core on the images of 0.5 in Tailwind: the caption over its image, the carousel, the points, the hero, the video
     * and the figure of several images; then on the frames, the token and the pixel art and a gallery of
     * lightboxes; in clear, in dark and right to left, and under the strict policy.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testTheNewImageRecipesPassAxe(string $page): void
    {
        $this->open($page);
        $this->perform((string) file_get_contents(__DIR__.'/node_modules/axe-core/axe.min.js'));
        $this->browser()->manage()->timeouts()->setScriptTimeout(30);

        foreach (['#figures', '#frames', '#festival'] as $part) {
            $violations = $this->browser()->executeAsyncScript(\sprintf('const done = arguments[arguments.length - 1]; axe.run(document.querySelector(%s), {runOnly: {type: "tag", values: ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]}}).then((result) => done(result.violations.map((violation) => violation.id + ": " + violation.nodes.map((node) => node.target.join(" ")).join(", "))), (error) => done(["axe: " + error]));', json_encode($part, \JSON_THROW_ON_ERROR)));

            self::assertSame([], $violations, $part);
        }
    }

    /**
     * The utilities of the images of 0.5 reach the stylesheet: the caption on the foot of its image, the carousel in one
     * row with its buttons named, each point at its place, the hero covered, the video at its ratio, the figure in its
     * columns.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testTheNewImageRecipesInTailwind(string $page): void
    {
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
        $this->open($page);

        self::assertSame(
            [true, true, true, true, [[20, 30], [72.5, 64]], true, true, 3],
            $this->read('(() => { const image = document.querySelector("#overlaid").getBoundingClientRect(), caption = document.querySelector("#overlaid").closest("figure").querySelector("figcaption").getBoundingClientRect(), list = document.querySelector("#strip ul"), items = [...list.children], plan = document.querySelector("#plan img").getBoundingClientRect(), hero = document.querySelector("#hero").getBoundingClientRect(), cover = document.querySelector("#hero img").getBoundingClientRect(), video = document.querySelector("#clip").getBoundingClientRect(); return [Math.abs(caption.bottom - image.bottom) < 1 && Math.abs(caption.left - image.left) < 1, list.scrollWidth > list.clientWidth && items.every((item) => Math.abs(item.getBoundingClientRect().top - items[0].getBoundingClientRect().top) < 1), getComputedStyle(list).scrollSnapType === "x mandatory", getComputedStyle(list, "::scroll-button(inline-end)").content.includes("Next photo"), [...document.querySelectorAll("#plan li")].map((point) => { const box = point.getBoundingClientRect(); return [Math.round(((box.left + box.width / 2) - plan.left) / plan.width * 200) / 2, Math.round(((box.top + box.height / 2) - plan.top) / plan.height * 200) / 2]; }), Math.abs(cover.width - hero.width) < 1 && Math.abs(cover.height - hero.height) < 1, Math.abs(video.width / video.height - 16 / 9) < 0.02, getComputedStyle(document.querySelector("#moments")).gridTemplateColumns.split(" ").length]; })()'),
        );

        $this->focus('#strip ul');
        $this->press($this->isRightToLeft($page) ? WebDriverKeys::ARROW_LEFT : WebDriverKeys::ARROW_RIGHT);
        $this->waitUntil('Math.abs(document.querySelector("#strip ul").scrollLeft) > 0');
    }

    /**
     * The button plays the celebration of the shared controller: the message is said, the canvas comes over the whole
     * page above it, by the utilities and the CSSOM alike, letting the pointer through, is drawn on in the chart tokens
     * and goes; the DOM event plays it too.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testACelebrationSaysItsMessageDrawsOverThePageAndGoes(string $page): void
    {
        $this->open($page);
        $this->click('#celebrate');
        $this->waitUntil('document.querySelector("#victory canvas")');

        self::assertSame(['true', 'none', 'fixed', '50', true], $this->read('(() => { const canvas = document.querySelector("#victory canvas"), style = getComputedStyle(canvas), box = canvas.getBoundingClientRect(); return [canvas.getAttribute("aria-hidden"), style.pointerEvents, style.position, style.zIndex, Math.round(box.width) === document.documentElement.clientWidth && Math.round(box.height) === document.documentElement.clientHeight]; })()'));
        $this->waitUntil('(() => { const canvas = document.querySelector("#victory canvas"); if (!canvas) { return false; } const pixels = canvas.getContext("2d").getImageData(0, 0, canvas.width, canvas.height).data; for (let alpha = 3; alpha < pixels.length; alpha += 4) { if (pixels[alpha] > 0) { return true; } } return false; })()');
        $this->waitUntil('document.querySelector("#victory [role=status]").textContent === "You won!"');
        $this->waitUntil('!document.querySelector("#victory canvas")');

        $this->perform('window.dispatchEvent(new CustomEvent("celebrate", {detail: {message: "Bingo", effect: "fireworks"}}))');
        $this->waitUntil('document.querySelector("#victory canvas")');
        $this->waitUntil('document.querySelector("#victory [role=status]").textContent === "Bingo"');
        $this->waitUntil('!document.querySelector("#victory canvas")');
    }

    /**
     * Under reduced motion, emulated through the DevTools protocol, the message is said and no canvas ever comes.
     */
    #[DataProvider('plainAndStrict')]
    public function testACelebrationDrawsNothingUnderReducedMotion(string $page): void
    {
        $this->emulate([['name' => 'prefers-reduced-motion', 'value' => 'reduce']]);

        try {
            $this->open($page);
            $this->perform('window.drawn = 0; new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => { if ("CANVAS" === node.nodeName) { window.drawn++; } }))).observe(document.querySelector("#victory"), {childList: true});');
            $this->click('#celebrate');
            $this->waitUntil('document.querySelector("#victory [role=status]").textContent === "You won!"');
            usleep(300_000);

            self::assertSame(0, $this->read('window.drawn'));
        } finally {
            $this->emulate([]);
        }
    }

    /**
     * axe-core on the celebration in Tailwind while it plays, in clear, in dark and right to left.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testACelebrationPassesAxe(string $page): void
    {
        $this->open($page);
        $this->perform((string) file_get_contents(__DIR__.'/node_modules/axe-core/axe.min.js'));
        $this->browser()->manage()->timeouts()->setScriptTimeout(30);
        $this->click('#celebrate');
        $this->waitUntil('document.querySelector("#victory canvas") && document.querySelector("#victory [role=status]").textContent !== ""');

        self::assertSame([], $this->browser()->executeAsyncScript('const done = arguments[arguments.length - 1]; axe.run(document.querySelector("#victory"), {runOnly: {type: "tag", values: ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]}}).then((result) => done(result.violations.map((violation) => violation.id + ": " + violation.nodes.map((node) => node.target.join(" ")).join(", "))), (error) => done(["axe: " + error]));'));
    }

    /**
     * The recipes render under the strict policy with nothing refused, and the policy holds: a style attribute written
     * afterwards is refused, and seen.
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
     * The utilities of the frame, the token, the pixel art and the name of a view transition reach the stylesheet: the
     * corners, the line and the shadow, a square disc in its ring, pixels kept square, and a name under which a view
     * transition moves the image.
     */
    #[DataProvider('modes')]
    #[DataProvider('strictModes')]
    public function testAFrameATokenPixelArtAndATransitionInTailwind(string $page): void
    {
        $this->open($page);
        $this->perform('document.querySelector("#frames").scrollIntoView()');

        self::assertSame(
            [true, 'solid', true, true, true, 'cover', '4px', 'pixelated', 'poster'],
            $this->read('(() => { const framed = getComputedStyle(document.querySelector("#framed")), pawn = document.querySelector("#pawn"), token = getComputedStyle(pawn), box = pawn.getBoundingClientRect(); return [framed.borderTopLeftRadius !== "0px", framed.borderTopStyle, framed.boxShadow !== "none", parseFloat(token.borderTopLeftRadius) >= box.width / 2, Math.abs(box.width - box.height) < 1, token.objectFit, token.borderTopWidth, getComputedStyle(document.querySelector("#pixels")).imageRendering, getComputedStyle(document.querySelector("#poster")).viewTransitionName]; })()'),
        );

        $this->browser()->manage()->timeouts()->setScriptTimeout(10);
        self::assertTrue($this->browser()->executeAsyncScript('const done = arguments[arguments.length - 1]; const transition = document.startViewTransition(() => document.querySelector("#poster").classList.add("moved")); transition.ready.then(() => done(document.documentElement.getAnimations({subtree: true}).some((animation) => animation.effect.pseudoElement === "::view-transition-group(poster)")), (error) => done(String(error)));'));
    }

    /**
     * On paper, through the print variant: the carousel a grid printed whole, without markers or buttons; a figure never
     * cut, its caption kept with its image and its veil printed; an image never taller than the page; a deferred gallery
     * rendered whole; the close button of a lightbox and the canvas of a celebration left out.
     */
    #[DataProvider('plainAndStrict')]
    public function testTheImagesPrintLegiblyInTailwind(string $page): void
    {
        $this->open($page);
        $this->click('#celebrate');
        $this->waitUntil('document.querySelector("#victory canvas")');
        $this->emulate([], 'print');

        try {
            self::assertSame(
                ['row', true, 'none', 'avoid', 'avoid', 'exact', true, 'visible', 'none', 'none', 'avoid'],
                $this->read('(() => { const strip = document.querySelector("#strip ul"), style = getComputedStyle(strip), figure = document.querySelector("#overlaid").closest("figure"), caption = figure.querySelector("figcaption"); return [style.gridAutoFlow, strip.scrollWidth <= strip.clientWidth + 1, style.scrollMarkerGroup, getComputedStyle(figure).breakInside, getComputedStyle(caption).breakBefore, getComputedStyle(caption).printColorAdjust, getComputedStyle(document.querySelector("#photo")).maxHeight.endsWith("px"), getComputedStyle(document.querySelector("#archive li")).contentVisibility, getComputedStyle(document.querySelector("#lightbox-festival-1 [data-slot=lightbox-close]")).display, getComputedStyle(document.querySelector("#victory canvas")).display, getComputedStyle(document.querySelector("#festival li")).breakInside]; })()'),
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
    public function testTheImagesReadInTheDirectionOfThePageInTailwind(string $page): void
    {
        $rtl = $this->isRightToLeft($page);
        $this->browser()->manage()->window()->setSize(new WebDriverDimension(1280, 800));
        $this->open($page);

        self::assertSame(
            [$rtl, $rtl, $rtl, $rtl, !$rtl],
            $this->read('(() => { const caption = document.querySelector("#photo").closest("figure").querySelector("figcaption"), range = document.createRange(); range.selectNodeContents(caption.firstChild); const text = range.getBoundingClientRect(), box = caption.getBoundingClientRect(), gallery = [...document.querySelectorAll("#festival li")].map((item) => item.getBoundingClientRect()), strip = document.querySelector("#strip ul"), first = strip.firstElementChild.getBoundingClientRect(), list = strip.getBoundingClientRect(), start = getComputedStyle(strip, "::scroll-button(inline-start)").content; return [Math.abs(text.right - box.right) < Math.abs(text.left - box.left), gallery[0].left > gallery[1].left, Math.abs(first.right - list.right) < Math.abs(first.left - list.left), start.includes("\u203A"), start.includes("\u2039")]; })()'),
        );

        $this->perform('document.querySelector("#festival").scrollIntoView()');
        $this->focus('[commandfor="lightbox-festival-1"]');
        $this->press(WebDriverKeys::ENTER);
        $this->waitUntil('document.querySelector("#lightbox-festival-1").open');

        self::assertSame($rtl, $this->read('(() => { const close = document.querySelector("#lightbox-festival-1 [data-slot=lightbox-close]").getBoundingClientRect(), dialog = document.querySelector("#lightbox-festival-1").getBoundingClientRect(); return close.left + close.width / 2 < dialog.left + dialog.width / 2; })()'));
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
