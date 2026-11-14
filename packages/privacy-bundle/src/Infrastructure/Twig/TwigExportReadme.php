<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Twig;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\ExportReadme;
use Twig\Environment;

/**
 * The README of an export rendered from `@PhilPrivacy/export/README.txt.twig`, in the language of the request; an
 * application overrides the template to speak of itself.
 */
final readonly class TwigExportReadme implements ExportReadme
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[Override]
    public function render(array $modules, DateTimeImmutable $madeAt): string
    {
        return $this->twig->render('@PhilPrivacy/export/README.txt.twig', ['modules' => $modules, 'made_at' => $madeAt]);
    }
}
