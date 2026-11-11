<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Tests\Benchmark;

use Generator;
use Phil\ContentWarnings\BaseCatalogue;
use Phil\ContentWarnings\ContentWarning;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Decision;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Severity;
use Phil\ContentWarnings\WarningMatcher;
use Phil\ContentWarnings\WarningPreferences;

/**
 * A listing page screens every item it shows against the reader's preferences, so the cost of one decision is
 * multiplied by the size of the page; the base catalogue is loaded once per worker.
 *
 * @Groups({"content-warnings"})
 */
final class WarningMatcherBench
{
    private readonly WarningMatcher $matcher;

    private readonly WarningPreferences $preferences;

    /**
     * @var array<string, ContentWarnings>
     */
    private array $warnings;

    public function __construct()
    {
        $catalogue = BaseCatalogue::load();
        $this->matcher = new WarningMatcher($catalogue);
        $preferences = WarningPreferences::defaults()->withCategory('violence', Display::Hide, Severity::Moderate);

        foreach (\array_slice(array_keys($catalogue->themes()), 0, 40) as $theme) {
            $preferences = $preferences->withTheme($theme, Display::Fold);
        }

        $this->preferences = $preferences;
        $themes = array_keys($catalogue->themes());
        $this->warnings = [
            'none' => ContentWarnings::none(),
            'three' => ContentWarnings::of(new ContentWarning('gore', Severity::Severe), new ContentWarning('spiders'), new ContentWarning('alcohol', Severity::Mild)),
            'a hundred' => ContentWarnings::of(...array_map(static fn (int $index): ContentWarning => new ContentWarning($themes[$index % \count($themes)].'-'.$index, Severity::Moderate), range(1, 100))),
        ];
    }

    /**
     * @ParamProviders("warnings")
     *
     * @Revs(1000)
     *
     * @Iterations(5)
     *
     * @param array{warnings: string} $params
     */
    public function benchDecide(array $params): Decision
    {
        return $this->matcher->decide($this->warnings[$params['warnings']], $this->preferences);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchLoadTheBaseCatalogue(): void
    {
        BaseCatalogue::load();
    }

    /**
     * @return Generator<string, array{warnings: string}>
     */
    public function warnings(): Generator
    {
        yield 'none' => ['warnings' => 'none'];
        yield 'three' => ['warnings' => 'three'];
        yield 'a hundred' => ['warnings' => 'a hundred'];
    }
}
