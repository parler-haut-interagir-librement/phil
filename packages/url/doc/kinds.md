# Kinds of address

## `UrlType`

A kind is a case of an enum implementing `UrlType`: `accepts(Url $url): bool` says whether an address is of that
kind. Each case carries its own rule.

## The international kinds

`KnownUrlType` holds the kinds that mean the same everywhere:

| Kind | Domains |
|---|---|
| `Facebook`, `Instagram`, `X`, `Threads`, `Bluesky`, `LinkedIn`, `TikTok` | `facebook.com`, `fb.com`, `fb.me`; `instagram.com`, `instagr.am`; `x.com`, `twitter.com`; `threads.net`, `threads.com`; `bsky.app`; `linkedin.com`, `lnkd.in`; `tiktok.com` |
| `YouTube`, `Vimeo`, `Twitch` | `youtube.com`, `youtu.be`, `youtube-nocookie.com`; `vimeo.com`; `twitch.tv` |
| `Pinterest`, `Reddit`, `Tumblr`, `Flickr`, `Discord`, `GitHub` | `pin.it` and the country sites Pinterest holds, such as `pinterest.com`, `pinterest.ca` or `pinterest.co.uk`, never another `pinterest.<tld>`; `reddit.com`, `redd.it`; `tumblr.com`; `flickr.com`, `flic.kr`; `discord.gg`, `discord.com`; `github.com` |
| `Spotify`, `SoundCloud`, `Bandcamp`, `Patreon`, `Kickstarter`, `Etsy`, `Linktree`, `Wikipedia` | `spotify.com`, `spotify.link`; `soundcloud.com`; `bandcamp.com`; `patreon.com`; `kickstarter.com`, `kck.st`; `etsy.com`, `etsy.me`; `linktr.ee`; `wikipedia.org` |
| `Mastodon` | Any server: a path of one segment, `/@name` or `/@name@server`, on no domain of the other kinds nor of `medium.com`, `substack.com`, `figma.com`, `vsco.co`, `behance.net`, `ko-fi.com`, `buymeacoffee.com` |
| `Website` | Any address |

A subdomain counts — `fr.wikipedia.org`, `shop.bandcamp.com`. `label()` gives the brand's own name, `X (Twitter)`,
`YouTube`.

A Mastodon server is any host, so `Mastodon` reads the path: `/@name` is a profile. YouTube, TikTok, Threads and
Medium write their profiles so too; their addresses are never `Mastodon`, even in a set without their own kind,
where `https://www.youtube.com/@ada` is a `Website`. Another site whose profiles are `/@name` is taken for a Mastodon
server: when the application knows such sites, an enum of its own kinds, placed before `KnownUrlType` in the sets a
field accepts, claims them first.

## An application's own kinds

The kinds of a region — its business registry, its government's portal — belong to the application, as an enum of
its own:

```php
enum RegistryUrlType: string implements UrlType
{
    case Listing = 'listing';
    case Registry = 'registry';

    public function accepts(Url $url): bool
    {
        return match ($this) {
            self::Listing => $url->isOn('registry.example') && 'listing' === ($url->segments()[0] ?? null),
            self::Registry => $url->isOn('registry.example'),
        };
    }
}
```

**Declare the most specific cases first and the catch-all ones last**: detection takes the first case that
accepts.

## Detecting the kind

```php
$kind = UrlTypes::detect($url, EnumSet::all(RegistryUrlType::class), EnumSet::all(KnownUrlType::class));
$typed = UrlTypes::type('https://registry.example/listing/42', $own, $known);   // or InvalidUrl
```

The sets are tried in the order given, and each in its enum's declaration order: put the application's sets before
`KnownUrlType` when its kinds should win over `Website`. A field that accepts no catch-all refuses an address of no
listed kind.

`TypedUrl::of($kind, $url)` builds an address of a known kind, and refuses one its kind does not accept.

## Video players without cookies

`CookielessVideo::fromUrl($url)` gives the player of a YouTube or Vimeo video in its version that writes no cookies,
for a frame on a page; it takes a `Url` or a text, which `Url::fromString()` reads. The platform is told by the rule
of `KnownUrlType::YouTube` or `KnownUrlType::Vimeo`, the video by its path:

| Kind | Addresses of a video | Player |
|---|---|---|
| `YouTube` | `watch?v=`, `youtu.be/<id>`, `embed/`, `shorts/`, `live/`, `v/`, on any host of the kind | `https://www.youtube-nocookie.com/embed/<id>`, with `start` from `start` or `t` (`90`, `90s`, `1h2m3s`) |
| `Vimeo` | `vimeo.com/<id>`, `vimeo.com/<id>/<hash>`, `player.vimeo.com/video/<id>` | `https://player.vimeo.com/video/<id>?dnt=1`, the hash of an unlisted video kept as `h` |

```php
$video = CookielessVideo::fromUrl('https://youtu.be/dQw4w9WgXcQ?t=1m30s');
$video->type;             // KnownUrlType::YouTube
$video->url->toString();  // "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90"
```

Any other address, a channel or a profile on these platforms included, gives null. The YouTube player without
cookies writes nothing until the video is played; Vimeo's `dnt=1` drops its session data. Neither is free of the
platform: the frame still reaches it, with the visitor's address, so a page holds it behind the visitor's consent.
