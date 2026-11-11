<!-- phil-dev source-sha256=b7a19e522bff640b12a438ea1871354976700b47be5953c9563bddbecb2f598d -->
# phil/sitemap

**Le plan de site XML des moteurs de recherche, construit à partir de pages vérifiées et coupé aux limites du
protocole.**

phil/sitemap modélise un plan de site sous forme de valeurs. Un `Url` est une page, avec ce que le protocole des plans
de site permet à un site d'en dire : quand elle a changé pour la dernière fois, à quelle fréquence elle change, son
rang, la même page dans d'autres langues et les images qu'elle montre. Des fournisseurs ajoutent des pages à un
`Sitemap` par section, et le plan de site coupe chaque section en fichiers d'au plus 50 000 pages. `XmlWriter` écrit
le `urlset` de chaque fichier, avec les variantes `xhtml:link` et l'extension des images, ainsi que le `sitemapindex`
qui liste les fichiers. Il ne sert ni ne stocke les fichiers.

Il s'adresse à tout site PHP qui veut que les moteurs de recherche trouvent ses pages, dans une langue ou plusieurs.
Chaque valeur vérifie ce qu'exige le protocole : des adresses HTTP ou HTTPS absolues d'au plus 2 048 caractères, lues
par phil/url comme un navigateur les lit et gardées sous leur forme canonique, une priorité par pas de 0,1, une
fréquence de changement connue, un `hreflang` valide. Une erreur échoue là où la page est construite, avant qu'aucun
XML invalide ne soit écrit. Dans une application Symfony, phil/sitemap-bundle lit les
pages dans les routes et les fournisseurs, sert `/sitemap.xml` et écrit les fichiers ; phil/content-bundle et
phil/directory-bundle lui donnent leurs pages publiées.

C'est une bibliothèque PHP simple, sans dépendance au-delà de `phil/url`, qui lit les adresses. `UrlProvider` est le point d'extension :
chaque fournisseur connaît une partie d'un site et, quand on lui demande une section, il peut éviter de charger les
autres.

## État

| Partie | État |
|---|---|
| `Url`, `ChangeFrequency`, `Alternate`, `Image` | Implémenté et testé |
| `Sitemap`, `UrlContainer`, `UrlProvider` | Implémenté et testé |
| `XmlWriter` : `urlset` et `sitemapindex` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Référence](doc/fr/reference.md) | Les types, leurs règles, les sections et les fichiers, le XML écrit. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Comment la bibliothèque est testée. |

## Démarrage rapide

```php
$sitemap = new Sitemap();
$sitemap->add(new Url(
    'https://example.com/fr/calendrier',
    new DateTimeImmutable('2026-11-04 12:00'),
    ChangeFrequency::Weekly,
    0.8,
    [new Alternate('fr', 'https://example.com/fr/calendrier'), new Alternate('en', 'https://example.com/en/calendar')],
    [new Image('https://example.com/calendar.png')],
), 'pages');

foreach ($sitemap->files() as $name => $urls) {
    file_put_contents("public/sitemap.$name.xml", XmlWriter::urlset($urls));
}
```

Un `UrlProvider` ajoute les pages d'une partie du site ; dans une application Symfony, `phil/sitemap-bundle`
rassemble les fournisseurs, sert le plan de site et l'écrit dans des fichiers.

## Prérequis

PHP 8.5 ou plus récent ; `phil/url` 0.1.3, qui requiert les extensions intl, mbstring et URI.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
