<?php

declare(strict_types=1);

namespace Phil\Fonts\Name;

/**
 * The names a `name` table gives a face, by their predefined identifier.
 */
enum NameId: int
{
    case Copyright = 0;
    case Family = 1;
    case Subfamily = 2;
    case UniqueIdentifier = 3;
    case FullName = 4;
    case Version = 5;
    case PostScriptName = 6;
    case Trademark = 7;
    case Manufacturer = 8;
    case Designer = 9;
    case Description = 10;
    case VendorUrl = 11;
    case DesignerUrl = 12;
    case LicenceDescription = 13;
    case LicenceUrl = 14;
    case TypographicFamily = 16;
    case TypographicSubfamily = 17;
    case CompatibleFullName = 18;
    case SampleText = 19;
    case PostScriptCidName = 20;
    case WwsFamily = 21;
    case WwsSubfamily = 22;
    case LightBackgroundPalette = 23;
    case DarkBackgroundPalette = 24;
    case VariationsPostScriptPrefix = 25;
}
