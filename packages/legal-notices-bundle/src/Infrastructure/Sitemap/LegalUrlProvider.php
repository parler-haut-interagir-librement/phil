<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\Sitemap;

use Override;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNoticesBundle\Infrastructure\Http\DocumentController;
use Phil\Sitemap\Url;
use Phil\Sitemap\UrlContainer;
use Phil\Sitemap\UrlProvider;
use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The page of each document with a version in force, for `phil/sitemap-bundle`, last changed when that version took
 * effect.
 */
final readonly class LegalUrlProvider implements UrlProvider
{
    public function __construct(
        private LegalNotices $notices,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private string $section = 'default',
    ) {
    }

    #[Override]
    public function provide(UrlContainer $urls, ?string $section): void
    {
        if (null !== $section && $this->section !== $section) {
            return;
        }

        $now = $this->clock->now();

        foreach (DocumentType::cases() as $type) {
            $version = $this->notices->inForce($type, $now);

            if ($version instanceof LegalDocumentVersion) {
                $urls->add(new Url($this->urls->generate(DocumentController::route($type), [], UrlGeneratorInterface::ABSOLUTE_URL), $version->effectiveAt), $this->section);
            }
        }
    }
}
