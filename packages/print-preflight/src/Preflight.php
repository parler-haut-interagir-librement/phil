<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

use Phil\PrintPreflight\Check\Check;
use Phil\PrintPreflight\Check\Colours;
use Phil\PrintPreflight\Check\Fonts;
use Phil\PrintPreflight\Check\Hairlines;
use Phil\PrintPreflight\Check\ImageResolution;
use Phil\PrintPreflight\Check\InkCoverage;
use Phil\PrintPreflight\Check\Overprint;
use Phil\PrintPreflight\Check\PageCount;
use Phil\PrintPreflight\Check\PageGeometry;
use Phil\PrintPreflight\Check\Placement;
use Phil\PrintPreflight\Check\Transparency;

/**
 * Checks a document against a printer's profile and reports what would stop the print, what may print wrong and what
 * the printer will convert.
 */
final readonly class Preflight
{
    /**
     * @var list<Check>
     */
    public array $checks;

    /**
     * @param list<Check>|null $checks the checks to run, in order; the built-in ones when null
     */
    public function __construct(
        public PrintProfile $profile = new PrintProfile(),
        ?array $checks = null,
    ) {
        $this->checks = $checks ?? self::defaultChecks();
    }

    /**
     * The built-in checks, in the order their findings are reported: the page count, the page sizes and bleeds, the
     * fonts, the resolution of images, the colours, the ink coverage, overprint, transparency, hairlines, the placement
     * of elements.
     *
     * @return list<Check>
     */
    public static function defaultChecks(): array
    {
        return [
            new PageCount(), new PageGeometry(), new Fonts(), new ImageResolution(), new Colours(), new InkCoverage(),
            new Overprint(), new Transparency(), new Hairlines(), new Placement(),
        ];
    }

    /**
     * The same preflight with one more check, run after the others.
     */
    public function withCheck(Check $check): self
    {
        return new self($this->profile, [...$this->checks, $check]);
    }

    /**
     * The report of every check on the document: the findings the profile ignores left out, the others at the
     * severity the profile gives them, those about the whole document first, then page by page.
     */
    public function check(Document $document): Report
    {
        $findings = [];

        foreach ($this->checks as $check) {
            foreach ($check->check($document, $this->profile) as $finding) {
                if (!$this->profile->ignores($finding)) {
                    $findings[] = $finding->withSeverity($this->profile->severityOf($finding));
                }
            }
        }

        // A finding about the whole document has no page, which sorts before page 1.
        usort($findings, static fn (Finding $a, Finding $b): int => $a->page <=> $b->page);

        return new Report($findings);
    }
}
