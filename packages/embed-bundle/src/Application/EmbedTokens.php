<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Application;

use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbedToken;
use Phil\EmbedBundle\Domain\ValueObject\EmbedClaims;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Psr\Clock\ClockInterface;

/**
 * Issues the token of an embed for one origin, and reads the token a framed request carries back into the context
 * the page is shown in. The token names its origin: the browser enforces it through `frame-ancestors`, so that a token
 * copied to another site shows nothing there.
 */
final readonly class EmbedTokens
{
    public function __construct(
        private EmbedTokenCodec $codec,
        private Embeds $embeds,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidEmbed when the embed does not exist, does not allow the origin or has no such theme
     */
    public function issue(string $embed, Origin $origin, ?string $theme = null, ?string $subject = null): string
    {
        $definition = $this->embeds->get($embed);

        if (!$definition->allows($origin)) {
            throw InvalidEmbed::because(\sprintf('The embed "%s" does not allow %s.', $embed, $origin->toString()));
        }

        if (null !== $theme && !$definition->hasTheme($theme)) {
            throw InvalidEmbed::because(\sprintf('The embed "%s" has no theme "%s".', $embed, $theme));
        }

        $now = $this->clock->now()->getTimestamp();

        return $this->codec->encode(EmbedClaims::of($embed, $origin, $theme, $subject, $now, null === $definition->ttl ? null : $now + $definition->ttl));
    }

    /**
     * The context of a request for the embed, from its token and the theme the page asks for, if the embed has it.
     *
     * @throws InvalidEmbedToken when the token is not valid, or not for this embed or an origin it allows
     */
    public function context(string $embed, string $token, ?string $theme = null): EmbedContext
    {
        $claims = $this->codec->decode($token);

        if ($claims->embed !== $embed) {
            throw InvalidEmbedToken::because(\sprintf('The token is for the embed "%s", not "%s".', $claims->embed, $embed));
        }

        try {
            $definition = $this->embeds->get($embed);
        } catch (InvalidEmbed $invalidEmbed) {
            throw InvalidEmbedToken::because($invalidEmbed->getMessage());
        }

        if (!$definition->allows($claims->origin)) {
            throw InvalidEmbedToken::because(\sprintf('The embed "%s" no longer allows %s.', $embed, $claims->origin->toString()));
        }

        $chosen = null !== $theme && $definition->hasTheme($theme) ? $theme : ($claims->theme ?? $definition->defaultTheme);

        return new EmbedContext($embed, $claims->origin->toString(), $chosen, $claims->subject, $definition->themes);
    }
}
