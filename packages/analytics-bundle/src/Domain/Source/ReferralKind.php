<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Source;

/**
 * The class of a page view's source, as {@see ReferrerClassifier} derives it from the `Referer`.
 *
 * Only the class is counted, never the referrer itself.
 */
enum ReferralKind: string
{
    /** No referrer, or one that has no web origin. */
    case Direct = 'direct';

    /** A search engine. */
    case Search = 'search';

    /** A social network. */
    case Social = 'social';

    /** Any other site. */
    case Website = 'website';

    /** The site itself, or another origin the application declares its own: a page view, not a visit. */
    case Internal = 'internal';

    /** A referrer on the spam list: discarded before anything is counted. */
    case Spam = 'spam';

    /**
     * **A visit is a page view whose source is not the site itself.** Counting visits this way needs no visitor
     * identifier: it is less exact than counting unique visitors, and it is the only measure that keeps to
     * aggregates.
     */
    public function isVisit(): bool
    {
        return match ($this) {
            self::Direct, self::Search, self::Social, self::Website => true,
            self::Internal, self::Spam => false,
        };
    }
}
