<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Twig;

use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\Url\CookielessVideo;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * `privacy_embed(url, category, { title: …, preview: … })`: a third-party frame that is not loaded until its consent
 * category is granted.
 *
 * Until then the page shows a preview the site serves itself — a still of the video, a map drawn ahead of time —, what
 * loading the frame shares and with whom, a button that grants the category, and, with JavaScript, one that loads this
 * frame only, without saving a choice. Nothing reaches the third party before one of them is pressed.
 *
 * The address is one HTTPS address of a host name, read by phil/url's `Url` as a browser reads it: no IP address, no
 * `localhost`, no credentials, spaces or control characters, and at most `MAX_URL_LENGTH` characters. A YouTube or
 * Vimeo video becomes its player without cookies, by phil/url's `CookielessVideo`; any other address is kept as given,
 * its host as the provider. Even that player reaches the third party with the visitor's address, so the frame stays
 * behind the consent of its category.
 */
final readonly class EmbedExtension
{
    public const array OPTIONS = ['title', 'preview', 'width', 'height', 'allow', 'provider'];

    public const string DEFAULT_ALLOW = 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen';

    public const int MAX_URL_LENGTH = 2048;

    public function __construct(
        private ConsentContext $context,
        private ConsentPolicy $policy,
        private PrivacyPages $pages,
        private RequestStack $requests,
    ) {
    }

    /**
     * @param array<string, mixed> $options `title` (required: the frame's accessible name), `preview` (a path of this
     *                                      site), `width` and `height` (pixels), `allow` (the frame's permissions),
     *                                      `provider` (the name shown in place of the host)
     */
    #[AsTwigFunction(name: 'privacy_embed', needsEnvironment: true, isSafe: ['html'])]
    public function embed(Environment $twig, string $url, string $category, array $options = []): string
    {
        $unknown = array_diff(array_keys($options), self::OPTIONS);

        if ([] !== $unknown) {
            throw InvalidPrivacyValue::because(\sprintf('privacy_embed() has no option "%s"; it knows %s.', reset($unknown), implode(', ', self::OPTIONS)));
        }

        if (!isset($this->policy->categories[$category])) {
            throw InvalidPrivacyValue::because(\sprintf('privacy_embed() names the category "%s", which is not a consent category.', $category));
        }

        $title = $options['title'] ?? null;

        if (!\is_string($title) || '' === trim($title)) {
            throw InvalidPrivacyValue::because('privacy_embed() needs a "title": the frame\'s name for a screen reader.');
        }

        $source = $this->source($url);
        $request = $this->requests->getMainRequest();

        return $twig->render('@PhilPrivacy/embed/frame.html.twig', [
            'source' => $source,
            'category' => $this->policy->categories[$category],
            'granted' => $this->context->state()->isGranted($category),
            'title' => $title,
            'preview' => $this->preview($options['preview'] ?? null),
            'width' => $this->pixels('width', $options['width'] ?? null),
            'height' => $this->pixels('height', $options['height'] ?? null),
            'allow' => \is_string($options['allow'] ?? null) ? $options['allow'] : self::DEFAULT_ALLOW,
            'provider' => \is_string($options['provider'] ?? null) ? $options['provider'] : $source['provider'],
            'csrf_token' => $this->pages->csrfToken(),
            'target' => $request instanceof Request ? PrivacyPages::localTarget($request->getRequestUri()) ?? '' : '',
            'locale' => $request?->getLocale() ?? 'en',
        ]);
    }

    /**
     * The frame's address, `url`, as given or as the player without cookies of a video; the address given, `original`,
     * for the link that opens it on the third party's site; and the name of the service, `provider`.
     *
     * @return array{url: string, original: string, provider: string}
     *
     * @throws InvalidPrivacyValue when the address is not one HTTPS address of a host name
     */
    private function source(string $url): array
    {
        if ('' === $url || \strlen($url) > self::MAX_URL_LENGTH || 1 === preg_match('/[\x00-\x20\x7F]/', $url)) {
            throw InvalidPrivacyValue::because(\sprintf('An embedded address is one HTTPS address of at most %d characters, without spaces.', self::MAX_URL_LENGTH));
        }

        try {
            $address = Url::fromString($url);
        } catch (InvalidUrl) {
            $address = null;
        }

        if (!$address instanceof Url || 'https' !== $address->scheme) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not an HTTPS address of a host name, without credentials.', $url));
        }

        $video = CookielessVideo::fromUrl($address);

        return $video instanceof CookielessVideo
            ? ['url' => $video->url->toString(), 'original' => $url, 'provider' => $video->type->label()]
            : ['url' => $url, 'original' => $url, 'provider' => $address->host];
    }

    /**
     * A path of this site, or none: a preview fetched from the third party would reach it before the consent.
     */
    private function preview(mixed $preview): ?string
    {
        if (null === $preview) {
            return null;
        }

        if (!\is_string($preview) || null === PrivacyPages::localTarget($preview)) {
            throw InvalidPrivacyValue::because('The preview of privacy_embed() is a path of this site, such as "/media/video-still.webp": an address elsewhere would be fetched before the consent.');
        }

        return $preview;
    }

    private function pixels(string $name, mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!\is_int($value) || $value < 1 || $value > 10000) {
            throw InvalidPrivacyValue::because(\sprintf('The %s of privacy_embed() is a number of pixels from 1 to 10000.', $name));
        }

        return $value;
    }
}
