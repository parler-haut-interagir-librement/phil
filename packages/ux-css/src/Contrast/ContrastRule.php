<?php

declare(strict_types=1);

namespace Phil\UxCss\Contrast;

use Phil\UxCss\Color\Oklch;
use Phil\UxCss\Exception\InvalidColor;
use Phil\UxCss\Token\Scheme;
use Phil\UxCss\Token\TokenSheet;

/**
 * The contrast rule of phil-ux, checked on a token sheet — the package's own, or the same with a site's overrides.
 *
 * - **The palette**: every tone fits in sRGB, and two tones whose numbers differ by 50 or more reach 4.5:1, by 40 or
 *   more 3:1, whatever their hues.
 * - **The page**, in both schemes: the text, the muted text and the links reach 4.5:1 on the surface and on the raised
 *   surface, the surface 4.5:1 on the text (an inverted tooltip), and the focus ring 3:1 on both surfaces; the two
 *   highest levels of the activity calendar reach 3:1 on the surface, and two levels apart reach 3:1 on each other.
 * - **The grid**, in both schemes, for every role: `on-quiet` on `fill-quiet`, `on-loud` on `fill-loud` and `on-quiet`
 *   on both surfaces (a ghost or outlined control, an error message) reach 4.5:1; `border-loud` and `fill-loud` (a
 *   control's edge, a gauge, a switch) reach 3:1 on both surfaces.
 * - **The pairs a site adds**, through the constructor.
 */
final readonly class ContrastRule
{
    /**
     * Text of normal size, WCAG 2.2 success criterion 1.4.3.
     */
    public const float TEXT = 4.5;

    /**
     * Large text, borders, icons and focus rings, success criteria 1.4.3 and 1.4.11.
     */
    public const float NON_TEXT = 3.0;

    /**
     * @param list<Pair> $extraPairs
     */
    public function __construct(private array $extraPairs = [])
    {
    }

    /**
     * @return list<Violation>
     *
     * @throws InvalidColor
     */
    public function check(TokenSheet $sheet): array
    {
        return [...$this->checkPalette($sheet), ...$this->checkPairs($sheet)];
    }

    /**
     * The pairs checked on this sheet: those of the page, those of each role of the grid, then the extra ones.
     *
     * @return list<Pair>
     */
    public function pairs(TokenSheet $sheet): array
    {
        $surface = '--phil-color-surface';
        $raised = '--phil-color-surface-raised';
        $pairs = [
            new Pair('--phil-color-text', $surface, self::TEXT),
            new Pair('--phil-color-text', $raised, self::TEXT),
            new Pair('--phil-color-text-muted', $surface, self::TEXT),
            new Pair('--phil-color-text-muted', $raised, self::TEXT),
            new Pair('--phil-color-link', $surface, self::TEXT),
            new Pair('--phil-color-link', $raised, self::TEXT),
            new Pair($surface, '--phil-color-text', self::TEXT),
            new Pair('--phil-color-focus', $surface, self::NON_TEXT),
            new Pair('--phil-color-focus', $raised, self::NON_TEXT),
            new Pair('--phil-color-activity-3', $surface, self::NON_TEXT),
            new Pair('--phil-color-activity-4', $surface, self::NON_TEXT),
            new Pair('--phil-color-activity-3', '--phil-color-activity-1', self::NON_TEXT),
            new Pair('--phil-color-activity-4', '--phil-color-activity-2', self::NON_TEXT),
        ];

        foreach ($sheet->roles() as $role) {
            $prefix = '--phil-color-'.$role;
            $pairs[] = new Pair($prefix.'-on-quiet', $prefix.'-fill-quiet', self::TEXT);
            $pairs[] = new Pair($prefix.'-on-loud', $prefix.'-fill-loud', self::TEXT);
            $pairs[] = new Pair($prefix.'-on-quiet', $surface, self::TEXT);
            $pairs[] = new Pair($prefix.'-on-quiet', $raised, self::TEXT);
            $pairs[] = new Pair($prefix.'-border-loud', $surface, self::NON_TEXT);
            $pairs[] = new Pair($prefix.'-border-loud', $raised, self::NON_TEXT);
            $pairs[] = new Pair($prefix.'-fill-loud', $surface, self::NON_TEXT);
            $pairs[] = new Pair($prefix.'-fill-loud', $raised, self::NON_TEXT);
        }

        return [...$pairs, ...$this->extraPairs];
    }

    /**
     * @return list<Violation>
     *
     * @throws InvalidColor
     */
    private function checkPalette(TokenSheet $sheet): array
    {
        $violations = [];
        $tones = $sheet->tones();

        foreach ($tones as $tone) {
            if (!$tone->color->isInSrgbGamut()) {
                $violations[] = new Violation($tone->name(), $tone->name(), null, null, 0.0, 'outside the sRGB gamut');
            }
        }

        foreach ($tones as $light) {
            foreach ($tones as $dark) {
                $difference = $light->number - $dark->number;
                $minimum = match (true) {
                    $difference >= 50 => self::TEXT,
                    $difference >= 40 => self::NON_TEXT,
                    default => null,
                };

                if (null === $minimum) {
                    continue;
                }

                $ratio = $light->color->contrastWith($dark->color);

                if ($ratio < $minimum) {
                    $violations[] = new Violation($dark->name(), $light->name(), null, $ratio, $minimum, \sprintf(
                        '%.2f:1, below the %.1f:1 two tones %d apart must reach',
                        $ratio,
                        $minimum,
                        $difference,
                    ));
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     *
     * @throws InvalidColor
     */
    private function checkPairs(TokenSheet $sheet): array
    {
        $violations = [];

        foreach ($this->pairs($sheet) as $pair) {
            foreach (Scheme::cases() as $scheme) {
                $foreground = $sheet->resolve($pair->foreground, $scheme);
                $background = $sheet->resolve($pair->background, $scheme);

                if (!$foreground instanceof Oklch || !$background instanceof Oklch) {
                    $violations[] = new Violation($pair->foreground, $pair->background, $scheme, null, $pair->minimum, \sprintf(
                        '%s does not resolve to an OKLCH colour',
                        $foreground instanceof Oklch ? $pair->background : $pair->foreground,
                    ));

                    continue;
                }

                $ratio = $foreground->contrastWith($background);

                if ($ratio < $pair->minimum) {
                    $violations[] = new Violation($pair->foreground, $pair->background, $scheme, $ratio, $pair->minimum, \sprintf(
                        '%.2f:1, below %.1f:1',
                        $ratio,
                        $pair->minimum,
                    ));
                }
            }
        }

        return $violations;
    }
}
