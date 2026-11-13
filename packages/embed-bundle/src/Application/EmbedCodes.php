<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Application;

use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The code another site pastes to embed a page: a `<phil-embed>` element that holds a plain link — what a reader
 * without JavaScript follows —, and the module script that turns it into a frame of the right height.
 *
 * The frame's address is the page's route with the token; the script is the bundle's, served by its own route under
 * a stable address. Every value is escaped.
 */
final readonly class EmbedCodes
{
    public const string TOKEN = 'embed_token';

    public const string THEME = 'embed_theme';

    public const string SCRIPT_ROUTE = 'phil_embed_script';

    public function __construct(
        private EmbedTokens $tokens,
        private UrlGeneratorInterface $urls,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * @param array<string, scalar> $parameters the parameters of the page's route
     * @param string                $fallback   the absolute address of the page as a reader without the frame sees it
     *
     * @throws InvalidEmbed when the embed refuses the origin or the theme, or the fallback is no address
     */
    public function snippet(string $embed, Origin $origin, string $route, array $parameters, string $fallback, string $title, ?string $theme = null, ?string $subject = null): string
    {
        try {
            $link = Url::fromString($fallback, $this->allowLocalhost)->toString();
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidEmbed::because(\sprintf('The fallback of an embed is an address: %s', $invalidUrl->getMessage()));
        }

        if ('' === trim($title)) {
            throw InvalidEmbed::because("The code of an embed names it: its title is the frame's and the link's.");
        }

        $source = $this->urls->generate($route, [...$parameters, self::TOKEN => $this->tokens->issue($embed, $origin, $theme, $subject)], UrlGeneratorInterface::ABSOLUTE_URL);
        $script = $this->urls->generate(self::SCRIPT_ROUTE, ['file' => 'phil-embed.js'], UrlGeneratorInterface::ABSOLUTE_URL);

        return \sprintf(
            "<phil-embed src=\"%s\" title=\"%s\"%s>\n    <a href=\"%s\">%s</a>\n</phil-embed>\n<script type=\"module\" src=\"%s\" crossorigin=\"anonymous\"></script>",
            $this->escape($source),
            $this->escape($title),
            null === $theme ? '' : ' theme="'.$this->escape($theme).'"',
            $this->escape($link),
            $this->escape($title),
            $this->escape($script),
        );
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
