<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Tests\Unit;

use Phil\ContentWarnings\BaseCatalogue;
use Phil\ContentWarnings\ContentWarning;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Decision;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Severity;
use Phil\ContentWarnings\WarningMatcher;
use Phil\ContentWarnings\WarningPreferences;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WarningMatcher::class)]
#[CoversClass(Decision::class)]
final class WarningMatcherTest extends TestCase
{
    public function testContentWithoutWarningsIsShown(): void
    {
        $decision = new WarningMatcher()->decide(ContentWarnings::none(), WarningPreferences::defaults(Display::Hide));

        self::assertSame([Display::Show, [], []], [$decision->display, $decision->warnings, $decision->because]);
        self::assertSame([true, false, false, false], [$decision->isShown(), $decision->isFolded(), $decision->isHidden(), $decision->hasWarnings()]);
    }

    public function testTheStrictestDisplayWinsAndNamesItsWarnings(): void
    {
        $matcher = new WarningMatcher(BaseCatalogue::load());
        $warnings = ContentWarnings::of(new ContentWarning('spiders'), new ContentWarning('gore', Severity::Severe), new ContentWarning('war'), new ContentWarning('torture', Severity::Mild));
        $preferences = WarningPreferences::defaults(Display::Show)
            ->withTheme('gore', Display::Hide)
            ->withCategory('violence', Display::Hide, Severity::Moderate)
            ->withTheme('spiders', Display::Fold);

        $decision = $matcher->decide($warnings, $preferences);

        self::assertSame(Display::Hide, $decision->display);
        self::assertSame(['spiders', 'gore', 'war', 'torture'], array_map(static fn (ContentWarning $warning): string => $warning->theme, $decision->warnings));
        self::assertSame(['gore', 'war'], array_map(static fn (ContentWarning $warning): string => $warning->theme, $decision->because));
        self::assertSame([false, false, true, true], [$decision->isShown(), $decision->isFolded(), $decision->isHidden(), $decision->hasWarnings()]);

        $folded = $matcher->decide(ContentWarnings::forThemes('spiders', 'clowns'), $preferences);
        self::assertSame([Display::Fold, ['spiders']], [$folded->display, array_map(static fn (ContentWarning $warning): string => $warning->theme, $folded->because)]);
        self::assertSame([false, true, false], [$folded->isShown(), $folded->isFolded(), $folded->isHidden()]);
    }

    public function testWithoutCatalogueCategoriesAreNotRead(): void
    {
        $preferences = WarningPreferences::defaults(Display::Show)->withCategory('violence', Display::Hide);

        self::assertSame(Display::Show, new WarningMatcher()->decide(ContentWarnings::forThemes('gore'), $preferences)->display);
        self::assertSame(Display::Hide, new WarningMatcher(BaseCatalogue::load())->decide(ContentWarnings::forThemes('gore'), $preferences)->display);
    }
}
