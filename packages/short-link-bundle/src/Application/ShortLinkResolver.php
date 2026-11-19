<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application;

use Phil\RedirectsBundle\Application\LocationBuilder;
use Phil\RedirectsBundle\Application\RedirectMatch;
use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;
use Phil\RedirectsBundle\Domain\Exception\InvalidRedirect;
use Phil\RedirectsBundle\Domain\ValueObject\RedirectRule;
use Phil\RedirectsBundle\Domain\ValueObject\SourcePattern;
use Phil\RedirectsBundle\Domain\ValueObject\Target;
use Phil\ShortLinkBundle\Application\Code\SqidCodec;
use Phil\ShortLinkBundle\Domain\Contract\ShortLinkRepository;
use Phil\ShortLinkBundle\Domain\Enum\LinkState;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Psr\Clock\ClockInterface;

/**
 * Answers a request on a short link domain, on the resolver of phil/redirects-bundle: the link becomes a redirect
 * rule of the redirects — an exact source on the domain, the link's target, status and query policy —, and the
 * redirects' location builder writes the location, so that a short link goes where a redirect would and is checked
 * the same way.
 *
 * The root of a domain sends to the application's home page when one is configured, and is not found otherwise.
 * The path is the code, with a trailing slash ignored; anything else — a character out of the alphabet, a second
 * segment, a code longer than a code — is not found without reading the database. A link not yet in force, or served
 * on the domain of another locale, is not found; one no longer in force is gone. A visit is counted only when the
 * link is still in force at the moment of the count, in one statement: two requests racing for the last visit
 * redirect once, and the other is gone. A visit of a machine — a robot, a prefetch, a link preview — is redirected
 * the same way, counted apart, and never uses up the maximum number of visits.
 *
 * It keeps no state: the same service answers every request.
 */
final readonly class ShortLinkResolver
{
    public function __construct(
        private ShortLinkRepository $repository,
        private ShortLinkDomains $domains,
        private SqidCodec $codec,
        private LocationBuilder $locations,
        private ClockInterface $clock,
        private ?string $homeUrl = null,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * Whether a request on this host is one for the short links.
     */
    public function handles(string $host): bool
    {
        return $this->domains->isShortLinkHost($host);
    }

    /**
     * The answer to a request, none when its host is not a short link domain.
     *
     * @param VisitorClass $visitor who the request comes from, as the listener's `VisitorClassifier` tells
     */
    public function resolve(string $host, string $path, string $query = '', VisitorClass $visitor = VisitorClass::Human): ?Resolution
    {
        $domain = $this->domains->forHost($host);

        if (!$domain instanceof ShortLinkDomain) {
            return null;
        }

        if (!str_starts_with($path, '/')) {
            return Resolution::notFound();
        }

        $code = substr($path, 1);
        $code = str_ends_with($code, '/') ? substr($code, 0, -1) : $code;

        if ('' === $code) {
            return null === $this->homeUrl ? Resolution::notFound() : Resolution::redirect($this->homeUrl, RedirectStatus::Found);
        }

        if (!$this->codec->accepts($code, max: ShortLink::MAX_CODE_LENGTH)) {
            return Resolution::notFound();
        }

        $link = $this->repository->findByCode($code);

        if (!$link instanceof ShortLink || !$link->isServedIn($domain->locale)) {
            return Resolution::notFound();
        }

        $now = $this->clock->now();
        $state = $link->stateAt($now);

        if ($state->isGone()) {
            return Resolution::gone();
        }

        if (LinkState::Scheduled === $state) {
            return Resolution::notFound();
        }

        $location = $this->location($link, $domain, $query);
        $id = $link->id();

        if (null === $location || null === $id) {
            return Resolution::notFound();
        }

        if (!$this->repository->recordVisit($id, $now, $visitor)) {
            return Resolution::gone();
        }

        return Resolution::redirect($location, $link->status(), new ShortLinkVisit($id, $link->code(), $domain->host, $link->locale(), $visitor));
    }

    private function location(ShortLink $link, ShortLinkDomain $domain, string $query): ?string
    {
        try {
            $rule = new RedirectRule(SourcePattern::exact('/'.$link->code(), $domain->host), Target::url($link->target(), $this->allowLocalhost), $link->status(), $link->query());
        } catch (InvalidRedirect) {
            return null;
        }

        return $this->locations->build(new RedirectMatch($rule), $query);
    }
}
