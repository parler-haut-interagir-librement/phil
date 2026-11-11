<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

/**
 * The services an `embed` block may frame, each with the hosts its embedding address may point to: the services of
 * the Editor.js embed tool by default. A frame is rendered only from an `https` address on one of its service's hosts.
 */
final readonly class EmbedServices
{
    /** @var array<string, list<string>> */
    public const array DEFAULT = [
        'youtube' => ['www.youtube.com', 'www.youtube-nocookie.com'],
        'vimeo' => ['player.vimeo.com'],
        'facebook' => ['www.facebook.com'],
        'instagram' => ['www.instagram.com'],
        'twitter' => ['platform.twitter.com', 'twitframe.com'],
        'twitch-video' => ['player.twitch.tv'],
        'twitch-channel' => ['player.twitch.tv'],
        'miro' => ['miro.com'],
        'gfycat' => ['gfycat.com'],
        'imgur' => ['imgur.com'],
        'vine' => ['vine.co'],
        'aparat' => ['www.aparat.com'],
        'yandex-music-track' => ['music.yandex.ru'],
        'yandex-music-album' => ['music.yandex.ru'],
        'yandex-music-playlist' => ['music.yandex.ru'],
        'coub' => ['coub.com'],
        'codepen' => ['codepen.io'],
        'pinterest' => ['assets.pinterest.com'],
        'github' => ['gist.github.com'],
        'reddit' => ['embed.reddit.com'],
        'figma' => ['www.figma.com'],
        'whimsical' => ['whimsical.com'],
    ];

    /**
     * @param array<string, list<string>> $services each service, to its hosts in lower case
     */
    public function __construct(
        public array $services = self::DEFAULT,
    ) {
    }

    /**
     * @param list<string> $hosts
     */
    public function with(string $service, array $hosts): self
    {
        return new self([...$this->services, $service => $hosts]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->services);
    }

    /**
     * Whether an embedding address is `https` and on a host of its service.
     */
    public function allows(string $service, string $url): bool
    {
        return 1 === preg_match('~^https://([^/?#:@]+)(?:[/?#]|$)~i', $url, $match)
            && \in_array(strtolower($match[1]), $this->services[$service] ?? [], true);
    }
}
