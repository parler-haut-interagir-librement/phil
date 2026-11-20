<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Infrastructure\Fonts;

use Override;
use Phil\CommerceProductTemplateBundle\Application\Port\PrintFonts;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\PrintCompositor\Exception\PrintCompositorException;
use Phil\PrintCompositor\TypeFace;

/**
 * `PrintFonts` from the configuration: a TrueType or OpenType file by font name. A file is read each time it is
 * asked for, so the service holds nothing between two requests.
 */
final readonly class ConfiguredFonts implements PrintFonts
{
    /**
     * @param array<string, string> $files the path of each font file, by name
     */
    public function __construct(
        private array $files,
    ) {
    }

    #[Override]
    public function typeFace(string $name): TypeFace
    {
        $file = $this->files[$name] ?? throw TemplateRefused::unknownFont($name);

        try {
            return TypeFace::fromFile($file);
        } catch (PrintCompositorException $printCompositorException) {
            throw TemplateRefused::unreadable(\sprintf('The print font %s', $name), $printCompositorException->getMessage());
        }
    }
}
