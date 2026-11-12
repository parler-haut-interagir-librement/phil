<!-- phil-dev source-sha256=66a3993dab4fdf8251de07992af243981d40fdf2e07aa517c359f7da22aa905f -->
# phil/social-cards

**Ce qu'une page dit d'elle-même quand on la partage : les balises Open Graph, X et du fédivers, une carte de 1200×630
dessinée à partir d'un gabarit, et les balises des pages liées lues et vérifiées.**

phil/social-cards modélise les métadonnées de partage d'une page comme une valeur : son titre et sa description, son
adresse canonique, son image et le texte qui la remplace, son type Open Graph, sa locale et les locales dans lesquelles
elle est aussi écrite, son site, son auteur et ses dates, et les comptes du site et de l'auteur sur X et sur le
fédivers. `MetaTags` les écrit sous forme de lien canonique, de description, de balises Open Graph, de balises
`article:` d'un article, de carte de X et de `fediverse:creator`, échappées pour le HTML.

Une page sans image propre reçoit une carte : `GdCardRenderer` dessine un PNG de 1200×630 à partir d'un `CardTemplate`
déclaratif (une couleur ou une image de fond, une bande d'accent, un logo, les polices et les couleurs du titre et du
sous-titre), en ajustant le titre par réduction entre deux tailles et en coupant ses lignes selon les règles de sa
langue, puis en le coupant avec des points de suspension quand il ne tient toujours pas. Les polices sont lues par
phil/fonts. Le même gabarit et le même contenu dessinent toujours les mêmes octets, et `CardGenerator` garde chaque
carte sous une empreinte de tout ce qu'elle montre.

Dans l'autre sens, `TagReader` lit les balises d'une page d'un autre site (bornée en octets, en balises et en
longueurs, analysée par l'analyseur HTML5 de PHP), `LinkPreview` en fait la carte d'un lien, `LinkFinder` trouve les
liens qu'un membre a collés dans un texte, et `TagCheck` dit ce qui manque ou ce qui est faux dans les balises d'une
page.

Les cartes sont dessinées par GD, sans mise en forme du texte : le latin, le grec, le cyrillique et les écritures de la
Chine, du Japon et de la Corée se dessinent bien ; l'arabe, l'hébreu et les écritures de l'Inde, non, comme
l'expliquent [les cartes](doc/fr/cards.md#ce-que-gd-dessine-et-ce-quil-ne-dessine-pas).

C'est une simple bibliothèque PHP : PHP 8.5 avec les extensions ctype, DOM, GD, intl, libxml, mbstring et URI, et
phil/fonts. Dans une application Symfony, phil/social-cards-bundle définit les balises de chaque page depuis une
fonction Twig, sert les cartes, les dessine dans un *worker*, récupère les pages liées en toute sécurité et vérifie une
page depuis la ligne de commande.

## État

| Partie | État |
|---|---|
| `ShareMetadata`, `ShareImage`, `ShareType`, `TwitterCard`, `MetaTags` | Implémenté et testé |
| `CardTemplate`, `CardFont`, `CardImage`, `Color`, `CardContent` | Implémenté et testé |
| `LineBreaker`, `TextFitter`, `GdCardRenderer`, `CardGenerator`, les stockages | Implémenté et testé |
| `TagReader`, `PageTags`, `LinkPreview`, `TagCheck` | Implémenté, testé et soumis au *fuzzing* |
| `LinkFinder` | Implémenté, testé et soumis au *fuzzing* |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et les extensions employées. |
| [Balises de partage](doc/fr/tags.md) | Les métadonnées de partage, leurs règles et les balises écrites. |
| [Cartes](doc/fr/cards.md) | Les gabarits, les polices, l'ajustement du titre, le dessin, les empreintes et les stockages. |
| [Aperçus et vérifications](doc/fr/previews.md) | La lecture des balises d'une page, les aperçus de lien, les liens d'un texte, la vérification des balises d'une page. |
| [Exceptions](doc/fr/exceptions.md) | Ce qui est lancé, et quand. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente ou remplace. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque. |
| [Tests](doc/fr/testing.md) | Comment la bibliothèque est testée, mesurée et soumise au *fuzzing*. |

## Démarrage rapide

```php
use Phil\SocialCards\Card\{CardContent, CardGenerator, CardTemplate, DirectoryCardStore, GdCardRenderer};
use Phil\SocialCards\{MetaTags, ShareMetadata};

$template = CardTemplate::fromArray(['title_font' => '/fonts/Inter-Bold.ttf', 'accent' => '#e11d48']);
$cards = new CardGenerator(new GdCardRenderer(), new DirectoryCardStore('/var/cards'));
$card = $cards->generate($template, new CardContent('Release notes', 'What changed this month', 'en'));

echo MetaTags::html(MetaTags::of(ShareMetadata::fromArray([
    'title' => 'Release notes',
    'url' => 'https://example.com/releases',
    'description' => 'What changed this month',
    'image' => ['url' => "https://example.com/cards/{$card->fingerprint}.png", 'alt' => 'Release notes', 'width' => 1200, 'height' => 630],
    'site_name' => 'Example',
    'fediverse_creator' => '@team@example.social',
])));
```

## Prérequis

PHP 8.5 ou plus récent, avec les extensions ctype, DOM, GD (avec FreeType), intl, libxml, mbstring et URI, et
`phil/fonts`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
