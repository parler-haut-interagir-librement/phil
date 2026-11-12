<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Source;

use Phil\AnalyticsBundle\Domain\ValueObject\HostList;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;

/**
 * Classifies a `Referer` into a {@see ReferralKind}, by the bundle's own code over lists the application supplies.
 *
 * The order: no usable referrer is direct; an origin the site owns is internal; a spam host is spam; then search,
 * then social, and anything else is a website. Only the referrer's origin is ever looked at: its path and query
 * are not read.
 */
final readonly class ReferrerClassifier
{
    public function __construct(
        private HostList $search,
        private HostList $social,
        private HostList $spam,
    ) {
    }

    /**
     * @param iterable<Origin> $ownOrigins the site's declared origins, and any other origin the application
     *                                     treats as the site itself
     */
    public function classify(?string $referer, iterable $ownOrigins): ReferralKind
    {
        $origin = null === $referer || '' === $referer ? null : Origin::ofUrl($referer);

        if (!$origin instanceof Origin) {
            return ReferralKind::Direct;
        }

        foreach ($ownOrigins as $own) {
            if ($own->equals($origin)) {
                return ReferralKind::Internal;
            }
        }

        return match (true) {
            $this->spam->matches($origin->host) => ReferralKind::Spam,
            $this->search->matches($origin->host) => ReferralKind::Search,
            $this->social->matches($origin->host) => ReferralKind::Social,
            default => ReferralKind::Website,
        };
    }
}
