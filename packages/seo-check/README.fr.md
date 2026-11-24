<!-- phil-dev source-sha256=b5ad2ba91ac29050aa6edb5428ec2a13d4d16856b6e4c1824ce42b812fab7ee0 -->
# phil/seo-check

**Trouvez ce qui tient une page hors des résultats de recherche à sa publication : titre, description, langue, lien
canonique, indexation, hreflang, liens et images.**

phil/seo-check est une bibliothèque PHP simple. Elle lit une page telle que l'application la produit, au moment où
phil/publishing l'annonce par `PagePublished`, et dresse la liste des points à corriger, d'après les audits SEO de
Lighthouse :

- la réponse est un succès, et la page n'est pas plus grande que ce que la vérification lit ;
- un seul `<title>` et une seule méta-description, d'une longueur qu'une page de résultats affiche en entier ;
- le `lang` du document, et son en-tête `Content-Language`, sont la langue dans laquelle la page a été publiée ;
- un seul titre de premier niveau ;
- un seul lien canonique, absolu, qui nomme l'adresse où la page a été publiée — et tous les autres chemins qui mènent
  à la page, `/` et `/fr/` pour une page d'accueil, nomment la même ;
- les directives d'indexation, `<meta name="robots">` et `X-Robots-Tag`, concordent avec l'indicateur « indexable »,
  dans chaque vitrine ou canal où l'application sert la page ;
- les traductions `hreflang` sont valides et réciproques, `x-default` étant facultatif ;
- des liens que les moteurs de recherche peuvent suivre, avec un texte qui dit où ils mènent, et des images avec un
  `alt` ;
- aucun lien mort, d'après les statuts que le dernier vérificateur de liens a vus ;
- avec phil/structured-data, du JSON-LD valide ; avec phil/social-cards, des balises de partage complètes ; avec phil/link-checker, les
  statuts des liens de son vérificateur.

Le rapport est une liste, produite en texte, en JSON ou en fragment de HTML pour une administration. Il n'a pas de note
globale, et il n'arrête jamais une publication : la page est déjà en ligne quand elle est vérifiée. Il ne mesure ni la
densité de mots-clés ni un score de lisibilité.

Le HTML est lu par l'analyseur HTML5 de PHP, `Dom\HTMLDocument`, à un coût borné ; les adresses, par phil/url. La
vérification n'envoie aucune requête : l'application fournit le HTML de ses pages par le port `RenderedPages`, et les
statuts des liens par `LinkStatuses`.

## État

| Partie | État |
|---|---|
| `SeoChecker`, `Report`, les règles par défaut, le lecteur de balisage | Implémenté, testé et soumis au fuzzing |
| `PageCheckFactory` : la page à partir de `PagePublished` et de `RenderedPages`, l'indexation par canal | Implémenté et testé |
| Les liens morts, par `LinkStatuses` | Implémenté et testé |
| `Bridge\LinkChecker\LinkCheckerStatuses`, `LinkStatuses` par phil/link-checker | Implémenté et testé |
| Les règles de `Bridge\` : données structurées, balises de partage | Implémenté et testé |
| Les moteurs de rendu texte, JSON et HTML ; les messages en anglais et en français | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et la vérification de chaque page à sa publication. |
| [Règles](doc/fr/rules.md) | Chaque règle, ce qu'elle signale et pourquoi. |
| [Points d'extension](doc/fr/extension-points.md) | Écrire une règle, les ports, les messages, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, sa cible de fuzzing et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/seo-check
```

```php
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Render\TextRenderer;
use Phil\SeoCheck\RenderedPage;
use Phil\SeoCheck\SeoChecker;
use Phil\Url\Url;

$url = Url::fromString('https://example.org/fr/news/opening');
$page = CheckedPage::of($url, 'fr', true, [new RenderedPage($url, $html, 200, $headers)]);

echo new TextRenderer()->render(new SeoChecker()->check($page));
```

```text
2 point(s) to correct.
[error] canonical: The canonical link names https://example.org/fr/news, but the page was published at https://example.org/fr/news/opening.
[warning] image-alt: The image /logo.png has no alt attribute: describe it, or give it alt="" when it is only decoration.
```

`check($page, 'fr')` écrit le même rapport en français. [Installation](doc/fr/installation.md) montre un écouteur de
`PagePublished` qui vérifie chaque page à sa publication.

## Prérequis

PHP 8.5 ou une version plus récente, avec les extensions `dom`, `libxml`, `mbstring` et `uri` ; phil/publishing,
phil/url et symfony/translation 8.2. phil/structured-data, phil/social-cards et phil/link-checker sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
