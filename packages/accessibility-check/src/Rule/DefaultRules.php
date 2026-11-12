<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Phil\AccessibilityCheck\Contract\Rule;

/**
 * The rules of the library, in the order they run: text alternatives, headings, names of links and buttons, link
 * texts, contrast, tables, languages, frames, captions, identifiers and drawings.
 */
final class DefaultRules
{
    /**
     * @return list<Rule>
     */
    public static function all(): array
    {
        return [
            new ImageAlt(),
            new HeadingOrder(),
            new HeadingH1(),
            new EmptyHeading(),
            new LinkName(),
            new ButtonName(),
            new LinkText(),
            new ColorContrast(),
            new TableHeaders(),
            new HtmlLang(),
            new LangChange(),
            new FrameTitle(),
            new VideoCaptions(),
            new DuplicateId(),
            new SvgName(),
        ];
    }
}
