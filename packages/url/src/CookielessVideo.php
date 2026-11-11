<?php

declare(strict_types=1);

namespace Phil\Url;

/**
 * The player of a video, in its version that writes no cookies, for the platforms that offer one; a video is told by
 * the rule of its `KnownUrlType`, then by its path:
 *
 * - **YouTube**: every form of a video's address (`watch?v=`, `youtu.be/`, `embed/`, `shorts/`, `live/`, `v/`) becomes
 *   `https://www.youtube-nocookie.com/embed/<id>`, its start time kept; the player writes nothing until it is played.
 * - **Vimeo**: `vimeo.com/<id>` and `player.vimeo.com/video/<id>` become the player with `dnt=1`, its "do not track",
 *   which drops its session data; the hash of an unlisted video is kept.
 *
 * Any other address, a channel or a profile on these platforms included, has no such player. Even without cookies, the
 * player still reaches the platform, with the visitor's address.
 */
final readonly class CookielessVideo
{
    private const string YOUTUBE_ID = '/^[A-Za-z0-9_-]{11}$/D';

    private function __construct(
        public KnownUrlType $type,
        public Url $url,
    ) {
    }

    /**
     * The player without cookies of the video at this address; null when the address is no YouTube or Vimeo video.
     *
     * @throws Exception\InvalidUrl when the text is no address `Url` accepts
     */
    public static function fromUrl(Url|string $url): ?self
    {
        $url = $url instanceof Url ? $url : Url::fromString($url);
        parse_str($url->query ?? '', $query);

        if (KnownUrlType::YouTube->accepts($url)) {
            $id = self::youtubeId($url, $query);

            if (null === $id) {
                return null;
            }

            $start = self::seconds($query['start'] ?? $query['t'] ?? null);

            return new self(KnownUrlType::YouTube, Url::fromString('https://www.youtube-nocookie.com/embed/'.$id.(null === $start ? '' : '?start='.$start)));
        }

        if (KnownUrlType::Vimeo->accepts($url) && 1 === preg_match('#^/(?:video/)?(\d{1,20})(?:/([0-9a-f]{1,32}))?/?$#D', $url->path, $match)) {
            $hash = $match[2] ?? (\is_string($query['h'] ?? null) && 1 === preg_match('/^[0-9a-f]{1,32}$/D', $query['h']) ? $query['h'] : '');

            return new self(KnownUrlType::Vimeo, Url::fromString('https://player.vimeo.com/video/'.$match[1].'?'.('' === $hash ? '' : 'h='.$hash.'&').'dnt=1'));
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private static function youtubeId(Url $url, array $query): ?string
    {
        $segments = $url->segments();
        $candidate = match (true) {
            $url->isOn('youtu.be') => $segments[0] ?? null,
            '/watch' === $url->path => $query['v'] ?? null,
            \in_array($segments[0] ?? null, ['embed', 'shorts', 'live', 'v'], true) => $segments[1] ?? null,
            default => null,
        };

        return \is_string($candidate) && 1 === preg_match(self::YOUTUBE_ID, $candidate) ? $candidate : null;
    }

    /**
     * A start time in seconds, from `90`, `90s` or `1h2m3s`; null for anything else.
     */
    private static function seconds(mixed $value): ?int
    {
        if (\is_string($value) && 1 === preg_match('/^(?:(\d{1,2})h)?(?:(\d{1,4})m)?(?:(\d{1,6})s?)?$/D', $value, $match)) {
            $seconds = (int) ($match[1] ?? 0) * 3600 + (int) ($match[2] ?? 0) * 60 + (int) ($match[3] ?? 0);

            if (0 !== $seconds) {
                return $seconds;
            }
        }

        return null;
    }
}
