<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Signature\Protocol;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * The address under which a site's agent answers, such as `https://shop.example.com/_sites-console`: read by
 * `phil/url`, `https` on a public host unless insecure addresses are allowed for development, without a query or a
 * fragment. Each action is a path below it.
 */
final readonly class AgentUrl
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @param bool $allowInsecure whether `http` and `localhost` are accepted, for development and tests
     */
    public static function fromString(string $text, bool $allowInsecure = false): self
    {
        try {
            $url = Url::fromString($text, $allowInsecure);
        } catch (UrlException) {
            throw InvalidValue::because('The address of an agent is an absolute http or https address on a host name.');
        }

        if (!$allowInsecure && 'https' !== $url->scheme) {
            throw InvalidValue::because('The address of an agent is an https address.');
        }

        if (null !== $url->query || null !== $url->fragment) {
            throw InvalidValue::because('The address of an agent has no query and no fragment.');
        }

        return new self(rtrim($url->toString(), '/'));
    }

    public function endpoint(string $action): string
    {
        if (!\in_array($action, Protocol::ACTIONS, true)) {
            throw InvalidValue::because(\sprintf('The action of a request is one of %s.', implode(', ', Protocol::ACTIONS)));
        }

        return $this->value.'/'.$action;
    }
}
