<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\Twig;

use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNoticesBundle\Application\Contract\FooterLinkProvider;
use Phil\LegalNoticesBundle\Infrastructure\Http\DocumentController;
use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The links a footer shows: each document with a version in force, then the policies of `phil/terms-bundle` the
 * configuration names, then the links of the other packages. Each link carries its address and the translation key and
 * domain of its label.
 */
final readonly class FooterLinks
{
    public const string TERMS_ROUTE = 'phil_terms_policy';

    /**
     * @param list<string>                 $termsPolicies
     * @param iterable<FooterLinkProvider> $providers
     */
    public function __construct(
        private LegalNotices $notices,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private array $termsPolicies = [],
        private iterable $providers = [],
    ) {
    }

    /**
     * @return list<array{url: string, label: string, domain: string}>
     */
    public function links(): array
    {
        $now = $this->clock->now();
        $links = [];

        foreach (DocumentType::cases() as $type) {
            if ($this->notices->inForce($type, $now) instanceof LegalDocumentVersion) {
                $links[] = ['url' => $this->urls->generate(DocumentController::route($type)), 'label' => 'link.'.$type->value, 'domain' => 'phil_legal_notices'];
            }
        }

        foreach ($this->termsPolicies as $policy) {
            $links[] = ['url' => $this->urls->generate(self::TERMS_ROUTE, ['policy' => $policy]), 'label' => 'policy.'.$policy, 'domain' => 'phil_terms'];
        }

        foreach ($this->providers as $provider) {
            array_push($links, ...$provider->footerLinks());
        }

        return $links;
    }
}
