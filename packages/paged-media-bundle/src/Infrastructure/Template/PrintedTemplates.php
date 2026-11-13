<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Template;

use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Template\TemplateFonts;

/**
 * The library's printed templates as document templates, and the fonts they write in.
 *
 * @internal
 */
final class PrintedTemplates
{
    /** @var array<string, class-string> each template's class, by the name it is registered under */
    public const array TEMPLATES = [
        NameBadgesTemplate::NAME => NameBadgesTemplate::class,
        AddressLabelsTemplate::NAME => AddressLabelsTemplate::class,
        ProductLabelsTemplate::NAME => ProductLabelsTemplate::class,
        ShelfLabelsTemplate::NAME => ShelfLabelsTemplate::class,
        GiftCardsTemplate::NAME => GiftCardsTemplate::class,
        DownloadCardsTemplate::NAME => DownloadCardsTemplate::class,
        CalendarTemplate::NAME => CalendarTemplate::class,
        LargePrintTemplate::NAME => LargePrintTemplate::class,
    ];

    /**
     * The configured TrueType fonts, read once when the container builds the service; Helvetica otherwise.
     */
    public static function fonts(?string $regular, ?string $bold): TemplateFonts
    {
        return new TemplateFonts(
            null === $regular ? StandardFont::Helvetica : TrueTypeFont::fromFile($regular),
            null === $bold ? (null === $regular ? StandardFont::HelveticaBold : TrueTypeFont::fromFile($regular)) : TrueTypeFont::fromFile($bold),
        );
    }
}
