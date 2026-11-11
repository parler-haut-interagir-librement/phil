<!-- phil-dev source-sha256=d091a0eec5ebfc3e9f4497ee77ed735d69908ef63797d10556c94767502d32ee -->
# phil/sitemap-bundle

**`/sitemap.xml` pour un site Symfony, à partir d'options de route, de fournisseurs d'URL et d'un événement.**

phil/sitemap-bundle intègre phil/sitemap à une application Symfony. Trois sources produisent les pages : les routes
qui portent une option `sitemap`, chaque service qui implémente `UrlProvider`, et les écouteurs de l'événement
`PopulateSitemap`. Une route localisée donne une page par locale, chacune avec les autres comme variantes. Le bundle
sert l'index à `/sitemap.xml` et chaque fichier à `/sitemap.{name}.xml`, ou les écrit dans des fichiers avec
`phil:sitemap:dump`, compressés en gzip avec `--gzip` et sous une autre URL de base avec `--base-url`. Une mauvaise
option de route échoue tout de suite, en `InvalidRouteOption` qui nomme la route.

Utilisez-le dans un site Symfony qui veut ses routes fixes et ses pages en base de données dans un seul plan de site.
Un petit site le sert sur demande. Un gros site l'écrit en fichiers une fois une version déployée, puisque construire
l'index demande chaque page à chaque fournisseur. Une application qui vient de PrestaSitemapBundle garde la même option
de route, et retrouve ses clés de configuration sous `phil_sitemap`. Hors de Symfony, phil/sitemap écrit le XML à lui
seul.

Il se branche sur le routeur de Symfony, le répartiteur d'événements, la console et l'horloge. Les fournisseurs sont
autoconfigurés : une classe qui implémente `UrlProvider` suffit. Le bundle rassemble les pages, et la bibliothèque les
vérifie et les écrit.

## État

| Partie | État |
|---|---|
| L'option de route `sitemap`, avec valeurs par défaut et section par défaut, les routes localisées comme variantes | Implémenté et testé |
| Les fournisseurs, autoconfigurés, et l'événement `PopulateSitemap` | Implémenté et testé |
| `/sitemap.xml` et `/sitemap.{name}.xml` | Implémenté et testé |
| `phil:sitemap:dump`, avec `--gzip` et `--base-url` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement, les routes. |
| [Configuration](doc/fr/configuration.md) | Sections, valeurs par défaut, fichiers, le répertoire d'écriture. |
| [Pages](doc/fr/pages.md) | L'option de route, les fournisseurs et l'événement de remplissage. |
| [Servir et écrire](doc/fr/serving-and-dumping.md) | Le plan de site servi et les fichiers qu'écrit la commande. |
| [Venir de PrestaSitemapBundle](doc/fr/presta.md) | Ce que devient chaque partie de ce bundle. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```yaml
# config/routes/phil_sitemap.yaml
phil_sitemap:
    resource: '@PhilSitemapBundle/config/routes.php'
```

```php
#[Route('/contact', name: 'contact', options: ['sitemap' => ['priority' => 0.8, 'changefreq' => 'never', 'section' => 'pages']])]
```

```bash
bin/console phil:sitemap:dump --gzip
```

## Prérequis

PHP 8.5 ou plus récent avec zlib, Symfony 8.2 ou plus récent, et `phil/sitemap`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
