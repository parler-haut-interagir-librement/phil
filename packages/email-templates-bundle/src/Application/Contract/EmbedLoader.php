<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Contract;

use Phil\EmailTemplatesBundle\Application\Rendering\EmbeddedImage;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;

/**
 * Reads the images a template embeds by content identifier.
 */
interface EmbedLoader
{
    /**
     * @param string $path the relative path the template declares
     *
     * @throws RenderingFailed when the image cannot be read, or is not one an email may embed
     */
    public function load(string $name, string $path): EmbeddedImage;
}
