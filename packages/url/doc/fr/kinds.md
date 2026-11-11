<!-- phil-dev source-sha256=c2b93d2eb53e6296ad4bdb83dab49c590fa6d6851f195ba30f16a1535c3626b5 -->
# Genres d'adresse

## `UrlType`

Un genre est un cas d'une enum qui implémente `UrlType` : `accepts(Url $url): bool` dit si une adresse est de ce
genre. Chaque cas porte sa propre règle.

## Les genres internationaux

`KnownUrlType` contient les genres qui ont le même sens partout :

| Genre | Domaines |
|---|---|
| `Facebook`, `Instagram`, `X`, `Threads`, `Bluesky`, `LinkedIn`, `TikTok` | `facebook.com`, `fb.com`, `fb.me` ; `instagram.com`, `instagr.am` ; `x.com`, `twitter.com` ; `threads.net`, `threads.com` ; `bsky.app` ; `linkedin.com`, `lnkd.in` ; `tiktok.com` |
| `YouTube`, `Vimeo`, `Twitch` | `youtube.com`, `youtu.be`, `youtube-nocookie.com` ; `vimeo.com` ; `twitch.tv` |
| `Pinterest`, `Reddit`, `Tumblr`, `Flickr`, `Discord`, `GitHub` | `pin.it` et les sites nationaux que détient Pinterest, comme `pinterest.com`, `pinterest.ca` ou `pinterest.co.uk`, jamais un autre `pinterest.<tld>` ; `reddit.com`, `redd.it` ; `tumblr.com` ; `flickr.com`, `flic.kr` ; `discord.gg`, `discord.com` ; `github.com` |
| `Spotify`, `SoundCloud`, `Bandcamp`, `Patreon`, `Kickstarter`, `Etsy`, `Linktree`, `Wikipedia` | `spotify.com`, `spotify.link` ; `soundcloud.com` ; `bandcamp.com` ; `patreon.com` ; `kickstarter.com`, `kck.st` ; `etsy.com`, `etsy.me` ; `linktr.ee` ; `wikipedia.org` |
| `Mastodon` | N'importe quel serveur : un chemin d'un seul segment, `/@name` ou `/@name@server`, sur aucun domaine des autres genres ni de `medium.com`, `substack.com`, `figma.com`, `vsco.co`, `behance.net`, `ko-fi.com`, `buymeacoffee.com` |
| `Website` | N'importe quelle adresse |

Un sous-domaine compte : `fr.wikipedia.org`, `shop.bandcamp.com`. `label()` donne le nom propre de la marque,
`X (Twitter)`, `YouTube`.

Un serveur Mastodon peut être n'importe quel hôte : `Mastodon` lit donc le chemin, où `/@name` est un profil. YouTube,
TikTok, Threads et Medium écrivent aussi leurs profils ainsi ; leurs adresses ne sont jamais `Mastodon`, même dans un
ensemble sans leur propre genre, où `https://www.youtube.com/@ada` est un `Website`. Un autre site dont les profils
sont en `/@name` est pris pour un serveur Mastodon : quand l'application connaît de tels sites, une enum de ses propres
genres, placée avant `KnownUrlType` dans les ensembles qu'un champ accepte, les revendique en premier.

## Les genres propres à l'application

Les genres d'une région (son registre des entreprises, le portail de son gouvernement) appartiennent à l'application,
dans une enum à elle :

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

**Déclarez les cas les plus précis en premier et les cas fourre-tout en dernier** : la détection prend le premier cas
qui accepte.

## Détecter le genre

```php
$kind = UrlTypes::detect($url, EnumSet::all(RegistryUrlType::class), EnumSet::all(KnownUrlType::class));
$typed = UrlTypes::type('https://registry.example/listing/42', $own, $known);   // or InvalidUrl
```

Les ensembles sont essayés dans l'ordre donné, et chacun dans l'ordre de déclaration de son enum : placez les ensembles
de l'application avant `KnownUrlType` quand ses genres doivent l'emporter sur `Website`. Un champ qui n'accepte aucun
fourre-tout refuse une adresse d'aucun genre énuméré.

`TypedUrl::of($kind, $url)` construit une adresse d'un genre connu, et refuse celle que son genre n'accepte pas.

## Lecteurs vidéo sans témoins

`CookielessVideo::fromUrl($url)` donne le lecteur d'une vidéo YouTube ou Vimeo dans sa version qui n'écrit aucun
témoin de connexion, pour un cadre dans une page ; il prend un `Url` ou un texte, que lit `Url::fromString()`. La plateforme se
reconnaît à la règle de `KnownUrlType::YouTube` ou de `KnownUrlType::Vimeo`, la vidéo à son chemin :

| Genre | Adresses d'une vidéo | Lecteur |
|---|---|---|
| `YouTube` | `watch?v=`, `youtu.be/<id>`, `embed/`, `shorts/`, `live/`, `v/`, sur n'importe quel hôte du genre | `https://www.youtube-nocookie.com/embed/<id>`, avec `start` tiré de `start` ou de `t` (`90`, `90s`, `1h2m3s`) |
| `Vimeo` | `vimeo.com/<id>`, `vimeo.com/<id>/<hash>`, `player.vimeo.com/video/<id>` | `https://player.vimeo.com/video/<id>?dnt=1`, le hash d'une vidéo non répertoriée gardé dans `h` |

```php
$video = CookielessVideo::fromUrl('https://youtu.be/dQw4w9WgXcQ?t=1m30s');
$video->type;             // KnownUrlType::YouTube
$video->url->toString();  // "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90"
```

Toute autre adresse, y compris une chaîne ou un profil sur ces plateformes, donne null. Le lecteur YouTube sans témoins
n'écrit rien avant que la vidéo soit lue ; le `dnt=1` de Vimeo retire ses données de session. Aucun des deux ne se passe
de la plateforme : le cadre la joint quand même, avec l'adresse du visiteur, et une page le garde donc derrière le
consentement du visiteur.
