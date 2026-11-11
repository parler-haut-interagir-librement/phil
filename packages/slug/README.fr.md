<!-- phil-dev source-sha256=ed9157c971eb5a018eb400f70985aaae3e4130500345a63576ec8b9e8126a779 -->
# phil/slug

**Des slugs d'URL faits à partir de titres et de noms, sous forme d'un type qui n'accepte rien d'autre.**

phil/slug donne aux slugs un type valeur. `Slug` est un segment d'URL fait de lettres ASCII minuscules et de
chiffres, en mots joints par des traits d'union simples, d'au plus 255 caractères : `my-first-article`. Il refuse un
texte qui n'est pas déjà un slug et ne le corrige jamais, parce que `My-Article` à côté de `my-article` ferait deux
adresses pour une même page. Les générateurs de slugs font des slugs à partir d'un titre ou d'un nom.
`TransliteratingSlugger` translittère en ASCII, dans une locale quand on en donne une, et coupe un slug trop long au
dernier trait d'union qui tient, pour qu'aucun mot ne soit coupé en deux. Un décorateur change ce que donne un
générateur : `LeadingWordsRemoved` retire un article en tête, pour que les noms d'un répertoire se trient par leur
premier mot significatif. Aucun mot n'est retiré si l'application ne le liste pas.

Il s'adresse à toute application PHP qui construit des adresses à partir de ce que les gens tapent : titres
d'articles, noms de produits, entrées de répertoire. Une application nomme ses générateurs, un par usage, et
`Sluggers` en choisit un par son nom ou donne celui par défaut. Dans une application Symfony, phil/slug-bundle
construit les générateurs nommés à partir de la configuration, les autoconnecte et ajoute le filtre Twig `phil_slug`.

C'est une petite bibliothèque PHP construite sur le `AsciiSlugger` de `symfony/string` ; avec `ext-intl`, la
translittération couvre toutes les écritures, pas seulement les latines. La conception repose sur une interface,
`Slugger`, sur le type valeur vérifié qu'elle retourne, et sur la décoration pour changer un générateur sans en
hériter.

## État

| Partie | État |
|---|---|
| `Slug`, `InvalidSlug` | Implémenté et testé |
| `Slugger`, `TransliteratingSlugger`, `LeadingWordsRemoved` | Implémenté et testé |
| `Sluggers`, `UnknownSlugger` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Référence](doc/fr/reference.md) | Les types, les règles d'un slug, les générateurs et leur décoration. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Comment la bibliothèque est testée. |

## Démarrage rapide

```php
$slugger = new TransliteratingSlugger();
$slugger->slug('Jeux de rôle')->value;                                    // 'jeux-de-role'

$names = new LeadingWordsRemoved($slugger, ['le', 'la', 'les', 'l', 'the']);
$names->slug("L'Ordre du Corbeau")->value;                                // 'ordre-du-corbeau'

$sluggers = new Sluggers(['default' => $slugger, 'names' => $names], 'default');
$sluggers->slug('The Guild', 'names')->value;                             // 'guild'

Slug::fromString('my-first-article');                                     // checked, never fixed
```

Dans une application Symfony, `phil/slug-bundle` déclare les générateurs dans la configuration.

## Prérequis

PHP 8.5 ou plus récent, `symfony/string` et `symfony/translation-contracts` ; `ext-intl` pour la translittération de
toutes les écritures.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
