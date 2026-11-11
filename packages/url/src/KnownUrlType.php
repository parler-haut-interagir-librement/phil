<?php

declare(strict_types=1);

namespace Phil\Url;

use Override;

/**
 * The international kinds of web address: social networks, video and music platforms, shops and the like, each on
 * its own domains; a Mastodon profile on any server; and a website, any address, last.
 *
 * A Mastodon profile is told by its path, `/@name`, which other platforms write too. The domains of the other kinds,
 * and those of the best-known platforms with such paths, are never Mastodon; another site that writes its profiles
 * so is taken for a Mastodon server, which an application avoids by putting its own kinds before `Mastodon`.
 */
enum KnownUrlType: string implements UrlType
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case X = 'x';
    case Threads = 'threads';
    case Bluesky = 'bluesky';
    case LinkedIn = 'linkedin';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case Vimeo = 'vimeo';
    case Twitch = 'twitch';
    case Pinterest = 'pinterest';
    case Reddit = 'reddit';
    case Tumblr = 'tumblr';
    case Flickr = 'flickr';
    case Discord = 'discord';
    case GitHub = 'github';
    case Spotify = 'spotify';
    case SoundCloud = 'soundcloud';
    case Bandcamp = 'bandcamp';
    case Patreon = 'patreon';
    case Kickstarter = 'kickstarter';
    case Etsy = 'etsy';
    case Linktree = 'linktree';
    case Wikipedia = 'wikipedia';
    case Mastodon = 'mastodon';
    case Website = 'website';

    #[Override]
    public function accepts(Url $url): bool
    {
        return match ($this) {
            self::Website => true,
            // A profile on any server: `/@name`, or `/@name@server` seen from another one.
            self::Mastodon => 1 === preg_match('/^@[A-Za-z0-9_]{1,30}(@[a-z0-9.-]+)?$/', $url->segments()[0] ?? '') && 1 === \count($url->segments())
                && !$url->isOn(...self::NOT_MASTODON, ...array_merge(...array_values(self::DOMAINS))),
            default => $url->isOn(...self::DOMAINS[$this->value]),
        };
    }

    /**
     * The brand's own name, which is not translated.
     */
    public function label(): string
    {
        return self::LABELS[$this->value] ?? $this->name;
    }

    private const array DOMAINS = [
        'facebook' => ['facebook.com', 'fb.com', 'fb.me'],
        'instagram' => ['instagram.com', 'instagr.am'],
        'x' => ['x.com', 'twitter.com'],
        'threads' => ['threads.net', 'threads.com'],
        'bluesky' => ['bsky.app'],
        'linkedin' => ['linkedin.com', 'lnkd.in'],
        'tiktok' => ['tiktok.com'],
        'youtube' => ['youtube.com', 'youtu.be', 'youtube-nocookie.com'],
        'vimeo' => ['vimeo.com'],
        'twitch' => ['twitch.tv'],
        // The country sites Pinterest, Inc. holds, after DuckDuckGo's Tracker Radar (entities/Pinterest, Inc..json),
        // without its images, mail and engineering blog: a `pinterest.<tld>` anyone may register is no Pinterest.
        'pinterest' => [
            'pinterest.com', 'pin.it', 'pinterest.at', 'pinterest.be', 'pinterest.ca', 'pinterest.ch', 'pinterest.cl',
            'pinterest.co', 'pinterest.co.at', 'pinterest.co.in', 'pinterest.co.kr', 'pinterest.co.nz', 'pinterest.co.uk',
            'pinterest.com.au', 'pinterest.com.bo', 'pinterest.com.ec', 'pinterest.com.mx', 'pinterest.com.pe',
            'pinterest.com.py', 'pinterest.com.uy', 'pinterest.com.vn', 'pinterest.de', 'pinterest.dk', 'pinterest.ec',
            'pinterest.es', 'pinterest.fr', 'pinterest.hu', 'pinterest.id', 'pinterest.ie', 'pinterest.in',
            'pinterest.info', 'pinterest.it', 'pinterest.jp', 'pinterest.kr', 'pinterest.mx', 'pinterest.nl',
            'pinterest.nz', 'pinterest.pe', 'pinterest.ph', 'pinterest.pt', 'pinterest.ru', 'pinterest.se',
            'pinterest.th', 'pinterest.tw', 'pinterest.uk', 'pinterest.vn',
        ],
        'reddit' => ['reddit.com', 'redd.it'],
        'tumblr' => ['tumblr.com'],
        'flickr' => ['flickr.com', 'flic.kr'],
        'discord' => ['discord.gg', 'discord.com'],
        'github' => ['github.com'],
        'spotify' => ['spotify.com', 'spotify.link'],
        'soundcloud' => ['soundcloud.com', 'on.soundcloud.com'],
        'bandcamp' => ['bandcamp.com'],
        'patreon' => ['patreon.com'],
        'kickstarter' => ['kickstarter.com', 'kck.st'],
        'etsy' => ['etsy.com', 'etsy.me'],
        'linktree' => ['linktr.ee'],
        'wikipedia' => ['wikipedia.org'],
    ];

    /**
     * Platforms outside this enum whose profiles are written `/@name`, and which are not Mastodon servers.
     */
    private const array NOT_MASTODON = ['medium.com', 'substack.com', 'figma.com', 'vsco.co', 'behance.net', 'ko-fi.com', 'buymeacoffee.com'];

    private const array LABELS = [
        'x' => 'X (Twitter)',
        'linkedin' => 'LinkedIn',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'github' => 'GitHub',
        'soundcloud' => 'SoundCloud',
    ];
}
