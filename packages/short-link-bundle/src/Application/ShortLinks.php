<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application;

use Phil\RedirectsBundle\Application\TargetPolicy;
use Phil\RedirectsBundle\Domain\Exception\InvalidRedirect;
use Phil\RedirectsBundle\Domain\ValueObject\Target;
use Phil\ShortLinkBundle\Application\Code\CodeGenerator;
use Phil\ShortLinkBundle\Application\Code\SqidCodec;
use Phil\ShortLinkBundle\Application\Contract\CreationThrottle;
use Phil\ShortLinkBundle\Application\Contract\TargetCheck;
use Phil\ShortLinkBundle\Application\Exception\ShortLinkNotFound;
use Phil\ShortLinkBundle\Application\Exception\ShortLinkRefused;
use Phil\ShortLinkBundle\Domain\Contract\ShortLinkRepository;
use Phil\ShortLinkBundle\Domain\Exception\InvalidShortLink;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;

/**
 * The short links: created, changed, enabled, disabled and removed, each save through the same gate.
 *
 * - The target is an absolute `http` or `https` URL read by phil/url, as the redirects read theirs, kept in its
 *   canonical form; never on a short link domain, so that no link sends to another; on a host the application allows
 *   ({@see TargetPolicy}) unless any host is allowed; and, when a check is configured, one that answers.
 * - A code left empty is drawn at random; a chosen code, when the application allows them, holds only characters of
 *   the alphabet, at least {@see self::MIN_CUSTOM_LENGTH}, and no word of the blocklist. A code is never reused.
 * - A locale is one that has a domain of its own.
 * - Creating spends one creation of the author's allowance.
 */
final readonly class ShortLinks
{
    public const int MIN_CUSTOM_LENGTH = 3;

    /** The codes drawn before giving up, when each is already taken. */
    public const int ATTEMPTS = 8;

    public function __construct(
        private ShortLinkRepository $repository,
        private ShortLinkDomains $domains,
        private SqidCodec $codec,
        private CodeGenerator $generator,
        private TargetPolicy $targets,
        private ClockInterface $clock,
        private bool $anyHost = false,
        private bool $customCodes = true,
        private bool $allowLocalhost = false,
        private ?TargetCheck $check = null,
        private ?CreationThrottle $throttle = null,
    ) {
    }

    /**
     * @throws ShortLinkNotFound
     */
    public function get(int $id): ShortLink
    {
        return $this->repository->find($id) ?? throw ShortLinkNotFound::withId($id);
    }

    /**
     * @param string $author who creates the link, for the allowance of creations
     *
     * @throws ShortLinkRefused
     */
    public function create(ShortLinkDraft $draft, string $author): ShortLink
    {
        $target = $this->target($draft->target);
        $terms = $draft->terms();
        $this->locale($terms->locale);
        $code = $this->chosenCode($draft->code);

        if ($this->throttle instanceof CreationThrottle && !$this->throttle->consume($author)) {
            throw ShortLinkRefused::because('rate_limited', 'Too many short links were created; try again later.');
        }

        $code ??= $this->drawnCode();

        try {
            $link = new ShortLink($code, $target, $this->clock->now(), $terms);
        } catch (InvalidShortLink $invalidShortLink) {
            throw ShortLinkRefused::from($invalidShortLink);
        }

        $this->repository->save($link);

        return $link;
    }

    /**
     * Changes the target and the terms of a link; its code stays.
     *
     * @throws ShortLinkNotFound
     * @throws ShortLinkRefused
     */
    public function update(int $id, ShortLinkDraft $draft): ShortLink
    {
        $link = $this->get($id);
        $target = $this->target($draft->target);
        $terms = $draft->terms();
        $this->locale($terms->locale);

        try {
            $link->change($target, $terms, $this->clock->now());
        } catch (InvalidShortLink $invalidShortLink) {
            throw ShortLinkRefused::from($invalidShortLink);
        }

        $this->repository->save($link);

        return $link;
    }

    /**
     * @throws ShortLinkNotFound
     */
    public function enable(int $id, bool $enabled): ShortLink
    {
        $link = $this->get($id);
        $link->enable($enabled, $this->clock->now());

        $this->repository->save($link);

        return $link;
    }

    /**
     * @throws ShortLinkNotFound
     */
    public function delete(int $id): void
    {
        if (!$this->repository->remove($id)) {
            throw ShortLinkNotFound::withId($id);
        }
    }

    /**
     * The address of a link, on the domain of its locale.
     */
    public function url(ShortLink $link): string
    {
        return $this->domains->forLocale($link->locale())->urlOf($link->code());
    }

    public function customCodesAllowed(): bool
    {
        return $this->customCodes;
    }

    /**
     * @throws ShortLinkRefused
     */
    private function target(?string $text): string
    {
        $text = trim((string) $text);

        try {
            $target = Target::url($text, $this->allowLocalhost);
            $url = Url::fromString($text, allowLocalhost: $this->allowLocalhost);
        } catch (InvalidRedirect|InvalidUrl) {
            throw ShortLinkRefused::because('target', 'The target is an absolute http or https URL.');
        }

        if ($this->domains->isShortLinkHost($url->host)) {
            throw ShortLinkRefused::because('target_loop', 'A short link does not send to another short link.');
        }

        if (!$this->anyHost && !$this->targets->allows($target)) {
            throw ShortLinkRefused::because('target_host', 'The host "%host%" is not one a short link may send to.', ['%host%' => $url->host]);
        }

        $refusal = $this->check?->refusal($url);

        if (null !== $refusal) {
            throw ShortLinkRefused::because('target_unreachable', 'The target could not be read: %reason%.', ['%reason%' => $refusal]);
        }

        return $url->toString();
    }

    /**
     * @throws ShortLinkRefused
     */
    private function locale(?string $locale): void
    {
        if (null !== $locale && !\in_array($locale, $this->domains->locales(), true)) {
            throw ShortLinkRefused::because('locale', 'The locale "%locale%" has no short link domain.', ['%locale%' => $locale]);
        }
    }

    /**
     * @throws ShortLinkRefused
     */
    private function chosenCode(?string $code): ?string
    {
        $code = trim((string) $code);

        if ('' === $code) {
            return null;
        }

        if (!$this->customCodes) {
            throw ShortLinkRefused::because('code_custom', 'Codes are drawn at random: leave the code empty.');
        }

        if (!$this->codec->accepts($code, self::MIN_CUSTOM_LENGTH, ShortLink::MAX_CODE_LENGTH)) {
            throw ShortLinkRefused::because('code_invalid', 'A code has between %min% and %max% characters of the alphabet.', ['%min%' => (string) self::MIN_CUSTOM_LENGTH, '%max%' => (string) ShortLink::MAX_CODE_LENGTH]);
        }

        if ($this->codec->isBlocked($code)) {
            throw ShortLinkRefused::because('code_blocked', 'The code "%code%" is refused.', ['%code%' => $code]);
        }

        if ($this->repository->isTaken($code)) {
            throw ShortLinkRefused::because('code_taken', 'The code "%code%" is already used.', ['%code%' => $code]);
        }

        return $code;
    }

    /**
     * @throws ShortLinkRefused
     */
    private function drawnCode(): string
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; ++$attempt) {
            $code = $this->generator->generate();

            if (!$this->repository->isTaken($code)) {
                return $code;
            }
        }

        throw ShortLinkRefused::because('code_exhausted', 'No free code was found; raise the largest number of a code.');
    }
}
