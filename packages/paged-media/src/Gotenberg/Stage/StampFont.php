<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

/**
 * The 14 standard PDF fonts a text stamp is drawn in. They draw Western European text; Gotenberg draws other text with
 * an installed font of the same family when one has its characters.
 */
enum StampFont: string
{
    case Helvetica = 'Helvetica';
    case HelveticaBold = 'Helvetica-Bold';
    case HelveticaOblique = 'Helvetica-Oblique';
    case HelveticaBoldOblique = 'Helvetica-BoldOblique';
    case TimesRoman = 'Times-Roman';
    case TimesBold = 'Times-Bold';
    case TimesItalic = 'Times-Italic';
    case TimesBoldItalic = 'Times-BoldItalic';
    case Courier = 'Courier';
    case CourierBold = 'Courier-Bold';
    case CourierOblique = 'Courier-Oblique';
    case CourierBoldOblique = 'Courier-BoldOblique';
    case Symbol = 'Symbol';
    case ZapfDingbats = 'ZapfDingbats';
}
