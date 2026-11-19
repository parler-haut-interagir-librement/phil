<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Infrastructure\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `document_import_report_title()`: the title the application configured for the report of an import, which
 * `@PhilDocumentImport/report.html.twig` shows unless it is given one.
 */
final class ImportReportExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $title,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [new TwigFunction('document_import_report_title', fn (): string => $this->title)];
    }
}
