<!-- phil-dev source-sha256=677030d01c0e3fe3ab11adf160442679019b5ca725db832bf76e1729d2aebc5c -->
# phil/print-preflight-bundle

**Ce qu'un imprimeur refuserait, vérifié dans une application Symfony par rapport aux imprimeurs à qui elle envoie ses
fichiers.**

phil/print-preflight-bundle intègre `phil/print-preflight` à une application Symfony. Les règles des imprimeurs —
formats rognés, fond perdu, marge de sécurité, résolution, nombres de pages, flux de couleur, couverture d'encre,
épaisseur des filets, surimpression, transparence, gravités — sont des profils nommés de la configuration, écrits en
millimètres, en pouces, en points ou en pixels CSS. Un seul service, `Preflighter`, exécute les vérifications de la
bibliothèque et celles de l'application, enregistrées par un attribut. Un document mis en page dans un navigateur est
mesuré par un script et envoyé en JSON, que lit `MeasuredDocument`. Un document dessiné avec le moteur PDF natif de
`phil/paged-media` est enregistré par `PdfLayout` à mesure qu'il est dessiné. Une image de `phil/media-bundle` est
placée avec la taille en pixels qu'a enregistrée la bibliothèque. Une police nommée par famille, graisse et style est
trouvée, avec ses permissions d'intégration, dans le catalogue de `phil/fonts-bundle`. Une commande vérifie un document
mesuré et fait échouer une construction sur une erreur.

Il s'adresse à toute application Symfony qui envoie des fichiers à l'impression : un éditeur de livres ou de zines qui
montre les problèmes page par page, une boutique d'impression à la demande, une chaîne de traitement qui produit des
PDF.

C'est un bundle bâti sur `symfony/config`, `symfony/dependency-injection`, `symfony/console` et
`symfony/filesystem`. `phil/fonts-bundle`, `phil/paged-media` et `phil/media-bundle` sont facultatifs : chaque
intégration est enregistrée quand son paquet est installé.

## État

| Partie | État |
|---|---|
| Configuration : profils d'imprimeur par nom, longueurs et formats en texte, un profil par défaut | Implémenté et testé |
| `Preflighter` et `#[AsPreflightCheck]` | Implémenté et testé |
| `MeasuredDocument` : un document mesuré dans un navigateur, lu à partir de JSON | Implémenté et testé |
| `phil:print-preflight:check` : constats en texte ou en JSON, échec sur une erreur | Implémenté et testé |
| `PdfLayout` pour `phil/paged-media`, `MediaImages` pour `phil/media-bundle`, `CatalogueFontResolver` pour `phil/fonts-bundle` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation et activation du bundle. |
| [Configuration](doc/fr/configuration.md) | Profils, longueurs et formats, et les services qu'ils donnent. |
| [Vérification](doc/fr/checking.md) | `Preflighter`, les vérifications de l'application et la commande. |
| [Documents mesurés](doc/fr/measured-documents.md) | Le JSON qu'envoie un script sur la page rendue, et `MeasuredDocument`. |
| [Intégrations](doc/fr/integrations.md) | `PdfLayout`, `MediaImages` et les polices de `phil/fonts-bundle`. |
| [Points d'extension](doc/fr/extension-points.md) | Les services et les ports que l'application utilise ou remplace. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lève le bundle, en plus des exceptions de la bibliothèque. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/print-preflight-bundle
```

```yaml
# config/packages/phil_print_preflight.yaml
phil_print_preflight:
    profiles:
        booklet: { trim_sizes: [a5], bleed: 3mm, minimum_pages: 8, page_multiple: 4 }
        poster: { trim_sizes: [a4, 297x210mm], bleed: 5mm, colour_workflow: rgb }
```

```php
$report = $preflighter->check($measuredDocument->fromJson($request->getContent()), 'booklet');

foreach ($report->forPage(1) as $finding) {
    // show $finding->message beside the page
}
```

```sh
bin/console phil:print-preflight:check var/measured/zine.json --profile=booklet
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, `phil/print-preflight` et `phil/fonts` ; `phil/fonts-bundle` pour nommer les
polices par famille ; `phil/paged-media` pour `PdfLayout` ; `phil/media-bundle` pour `MediaImages`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
