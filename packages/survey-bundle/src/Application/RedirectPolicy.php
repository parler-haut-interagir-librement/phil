<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * Where an ending may send a respondent: an absolute `http` or `https` address, read by phil/url, on one of the
 * origins the application allows — its scheme, host and port all equal. Any other address is refused, so that a
 * questionnaire never becomes an open redirect; with no origin allowed, no ending redirects.
 */
final readonly class RedirectPolicy
{
    /** @var array<string, true> */
    private array $origins;

    /**
     * @param list<string> $origins        the allowed origins, such as `https://example.org`
     * @param bool         $allowLocalhost whether `localhost` is a host, for development and tests
     *
     * @throws InvalidQuestionnaire when an origin is not an origin: an address with a path, a query or a fragment
     */
    public function __construct(array $origins = [], private bool $allowLocalhost = false)
    {
        $read = [];

        foreach ($origins as $origin) {
            try {
                $url = Url::fromString($origin, $allowLocalhost);
            } catch (UrlException $urlException) {
                throw InvalidQuestionnaire::because(\sprintf('The allowed origin "%s" is refused: %s', mb_substr($origin, 0, 80), $urlException->getMessage()));
            }

            if ('/' !== $url->path || null !== $url->query || null !== $url->fragment) {
                throw InvalidQuestionnaire::because(\sprintf('An allowed origin is a scheme, a host and a port only, such as https://example.org; "%s" is not.', mb_substr($origin, 0, 80)));
            }

            $read[$this->origin($url)] = true;
        }

        $this->origins = $read;
    }

    /**
     * The address, normalised, when its origin is allowed.
     *
     * @throws InvalidQuestionnaire when it is not an address, or not on an allowed origin
     */
    public function check(string $redirect): string
    {
        try {
            $url = Url::fromString($redirect, $this->allowLocalhost);
        } catch (UrlException $urlException) {
            throw InvalidQuestionnaire::because(\sprintf('The redirect "%s" is refused: %s', mb_substr($redirect, 0, 80), $urlException->getMessage()));
        }

        if (!isset($this->origins[$this->origin($url)])) {
            throw InvalidQuestionnaire::because(\sprintf('The redirect "%s" is not on an origin the application allows.', mb_substr($redirect, 0, 80)));
        }

        return $url->toString();
    }

    public function allows(string $redirect): bool
    {
        try {
            $this->check($redirect);

            return true;
        } catch (InvalidQuestionnaire) {
            return false;
        }
    }

    private function origin(Url $url): string
    {
        return $url->scheme.'://'.$url->host.(null === $url->port ? '' : ':'.$url->port);
    }
}
