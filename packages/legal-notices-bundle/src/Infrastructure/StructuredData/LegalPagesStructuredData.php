<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\StructuredData;

use Override;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Field;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNotices\SiteIdentity;
use Phil\LegalNoticesBundle\Infrastructure\Http\DocumentController;
use Phil\StructuredData\Node;
use Phil\StructuredData\Type\Organization;
use Phil\StructuredData\Type\WebPage;
use Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider;
use Phil\StructuredDataBundle\Application\PageContext;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The legal notice and the privacy policy as `WebPage` nodes, for `phil/structured-data-bundle`, at the page's
 * `#webpage` so that they merge with the node of the site: the document's name, the version shown, when it was
 * published and took effect, and its publisher. Only the page of the version in force and those of each version: any
 * other page gives no node.
 */
final readonly class LegalPagesStructuredData implements StructuredDataProvider
{
    public const string DOMAIN = 'phil_legal_notices';

    public function __construct(
        private LegalNotices $notices,
        private ClockInterface $clock,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    #[Override]
    public function nodes(PageContext $page): iterable
    {
        if (!$page->request instanceof Request || null === $page->url) {
            return [];
        }

        $attributes = $page->request->attributes;
        $route = $attributes->get('_canonical_route', $attributes->get('_route'));

        foreach (DocumentType::cases() as $type) {
            $version = match ($route) {
                DocumentController::route($type) => $this->notices->inForce($type, $this->clock->now()),
                DocumentController::route($type, 'version') => $this->notices->history($type)->find($attributes->getInt('number')),
                default => null,
            };

            if ($version instanceof LegalDocumentVersion) {
                return [$this->node($type, $version, $page, $page->url)];
            }
        }

        return [];
    }

    private function node(DocumentType $type, LegalDocumentVersion $version, PageContext $page, string $url): Node
    {
        return WebPage::of(
            $url,
            $page->pageId('webpage'),
            name: $this->translator?->trans('title.'.$type->value, domain: self::DOMAIN, locale: $page->locale),
            inLanguage: $page->locale,
            datePublished: $version->publishedAt,
            dateModified: $version->effectiveAt,
            properties: ['version' => $version->number, 'publisher' => $this->publisher($this->notices->identity())],
        );
    }

    private function publisher(SiteIdentity $identity): ?Node
    {
        $name = $identity->get(Field::PublisherName);

        return null === $name ? null : Organization::of($name, email: $identity->get(Field::ContactEmail), properties: ['address' => $identity->get(Field::PublisherAddress)]);
    }
}
