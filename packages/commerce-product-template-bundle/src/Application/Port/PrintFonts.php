<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application\Port;

use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\PrintCompositor\TypeFace;

/**
 * The fonts texts are printed in, by the name a text option or a print element gives.
 *
 * `Infrastructure\Fonts\ConfiguredFonts` reads the files of the configuration's `production.fonts`.
 */
interface PrintFonts
{
    /**
     * @throws TemplateRefused for a name it does not know, or a file that is not a TrueType or OpenType font
     */
    public function typeFace(string $name): TypeFace;
}
