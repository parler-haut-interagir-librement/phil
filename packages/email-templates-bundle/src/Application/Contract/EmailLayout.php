<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Contract;

use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedParts;

/**
 * The house layout every email is wrapped in: the document, its preheader, the body of the template and its styles,
 * light and dark. It is trusted code of the application, never edited from the administration.
 */
interface EmailLayout
{
    /**
     * @return string the whole HTML document
     */
    public function wrap(RenderedParts $parts, RenderContext $context): string;
}
