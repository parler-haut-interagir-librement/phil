<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use DateInterval;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;
use Phil\PrivacyBundle\Domain\Consent\ConsentToken;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cookie that keeps a choice: a site of its own (no `Domain`, so no subdomain reads it), signed, out of the reach of
 * scripts, and sent on the site's own navigation.
 */
final readonly class ConsentCookie
{
    private DateInterval $lifetime;

    public function __construct(
        private ConsentToken $token,
        private ClockInterface $clock,
        private string $name,
        string $lifetime,
    ) {
        $this->lifetime = new DateInterval($lifetime);
    }

    public function read(Request $request): ?ConsentChoice
    {
        $value = $request->cookies->getString($this->name);

        return '' === $value ? null : $this->token->decode($value);
    }

    public function write(Request $request, Response $response, ConsentChoice $choice): void
    {
        $response->headers->setCookie(Cookie::create(
            $this->name,
            $this->token->encode($choice),
            $this->clock->now()->add($this->lifetime),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        ));
    }
}
