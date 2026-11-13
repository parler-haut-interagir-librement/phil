<!-- phil-dev source-sha256=426efb90e410cd687c2899ebf1d8c103008bc66be7bb432c502e3967c9935ff4 -->
# phil/embed-bundle

**Des fonctions d'un site Symfony que d'autres sites intègrent, encadrées par les origines qu'il autorise et par personne d'autre.**

phil/embed-bundle transforme une page du site — un calendrier, un formulaire, une liste d'événements — en quelque chose
qu'un autre site peut coller dans ses propres pages. Le site donne à cet autre site un code : un élément `<phil-embed>`
qui contient un simple lien, et un script. L'élément place la page dans un cadre, à la hauteur que son contenu demande,
et continue de la suivre ; un lecteur sans JavaScript suit le lien. L'adresse du cadre porte un jeton, signé par le site
pour cette intégration et cette origine : la page est servie dans le thème que nomme le jeton, et elle ne peut être
encadrée que par cette origine — un jeton copié sur un autre site n'y montre rien.

La hauteur voyage par un petit protocole `postMessage` : le cadre envoie qu'il est prêt et sa hauteur à l'origine de son
jeton, jamais à `*` ; l'élément ne lit que les messages de son propre cadre, et seulement ceux de l'origine de son
adresse ; le thème que demande l'autre site fait le chemin inverse, sous les mêmes vérifications. Le bundle écrit
`frame-ancestors` par `phil/browser-security-bundle` lorsqu'il est installé, ou dans un en-tête qui lui est propre.

Il convient à tout site Symfony dont les partenaires montrent le contenu : le calendrier d'une salle sur les sites de ses
artistes, un formulaire d'inscription sur la page d'un partenaire, une liste d'événements sur le portail d'une ville.

## État

| Partie | État |
|---|---|
| `Origin`, `SigningKey`, `EmbedClaims` : une origine lue par `phil/url`, une clé, ce que dit un jeton | Implémenté et testé |
| `EmbedTokenCodec` : des jetons `pe1.hs256.<key>.<claims>.<signature>`, une liste ordonnée de clés | Implémenté, testé et soumis au fuzzing |
| `Embed`, `Embeds`, `EmbedTokens` : les intégrations de la configuration, des jetons émis et relus dans un contexte | Implémenté et testé |
| `EmbedRequestListener` : la page d'une intégration par le `_embed` de sa route ou par `#[Embeddable]` | Implémenté et testé |
| `BrowserSecurityFraming`, `HeaderFraming` : `frame-ancestors` pour l'origine du jeton, ou aucune | Implémenté et testé, avec et sans `phil/browser-security-bundle` |
| `EmbedMessage` : la référence du protocole du cadre, partagée avec le JavaScript par ses jeux de données d'essai | Implémenté, testé et soumis au fuzzing |
| `<phil-embed>` : le cadre à la hauteur de son contenu, le thème, le lien conservé sans JavaScript ou en cas de refus | Implémenté ; piloté dans Chrome sur deux origines par la suite de navigateur |
| Le contrôleur `frame` : disponibilité, hauteur et thème dans le cadre | Implémenté ; piloté dans Chrome par la suite de navigateur |
| `EmbedCodes`, `phil_embed_code()`, `phil:embed:token` : le code qu'un partenaire colle, un jeton depuis la console | Implémenté et testé |
| `EmbedScriptController` : le script à une adresse stable, lisible d'une origine à l'autre | Implémenté et testé |
| `RemoteCommentThreads` : les commentaires d'une page portés par le forum d'un autre site | Pas commencé : le port est déclaré, sans adaptateur tant que le forum n'existe pas |
| La recette Flex : le bundle, une clé tirée de l'environnement, la route du script | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le bundle, la clé, la route, le contrôleur. |
| [Configuration](doc/fr/configuration.md) | Chaque option, et les services enregistrés. |
| [Intégration](doc/fr/embedding.md) | Déclarer une intégration, sa page, le code qu'un partenaire colle, l'élément et son repli. |
| [Jetons](doc/fr/tokens.md) | Le format, les clés et leur rotation, les revendications, ce que peut faire un jeton copié. |
| [Protocole](doc/fr/protocol.md) | Les messages `postMessage` et les vérifications des deux côtés. |
| [Encadrement](doc/fr/framing.md) | `frame-ancestors`, avec et sans `phil/browser-security-bundle`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests PHP et Node, les cibles de fuzzing et les vérifications dans un navigateur. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque module du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/embed-bundle
```

```yaml
# config/packages/phil_embed.yaml
phil_embed:
    keys:
        - { id: '1', secret: '%env(EMBED_KEY)%' }
    embeds:
        calendar:
            origins: ['https://partner.example']
            themes: [light, dark]
            default_theme: light
```

```php
#[Route('/embed/calendar', defaults: ['_embed' => 'calendar'])]
public function calendar(): Response { /* the page, with phil_embed_frame_attributes() on <html> */ }
```

```twig
<pre>{{ phil_embed_code('calendar', 'https://partner.example', 'calendar_embed', {}, url('calendar'), 'Our calendar') }}</pre>
```

## Prérequis

PHP 8.5 ou une version plus récente avec l'extension sodium, Symfony 8.2 et `phil/url`. Pour le contrôleur du cadre,
Stimulus 3 et AssetMapper ; pour la politique écrite dans celle du site, `phil/browser-security-bundle`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
