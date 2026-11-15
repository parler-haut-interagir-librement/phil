<!-- phil-dev source-sha256=483156f013a128051eeb6c590654db6cb0f6ed4c5419b95e9f2716528c13427b -->
# phil/legal-notices-bundle

**Les mentions légales et la politique de confidentialité d'un site Symfony, versionnées, dans chaque locale, à
`/legal` et `/privacy`.**

phil/legal-notices-bundle apporte phil/legal-notices à une application Symfony. L'identité du site et les versions de
ses deux documents sont écrites dans la configuration (chaque corps en ligne ou dans un fichier, en Markdown, en UJO
(Universal JSON Output), en HTML ou en texte brut), ou lues dans une source de l'application. La construction du
conteneur vérifie chaque version et refuse un document publié dont l'identité n'a pas un champ qu'il exige.

Les pages montrent la version en vigueur à `/legal` et `/privacy`, chaque version que voit le public à une adresse qui
ne change jamais, `/legal/v2`, et l'historique des versions, en anglais et en français et dans chaque locale pour
laquelle les routes sont montées. La politique de confidentialité liste les durées de conservation du calendrier de
phil/privacy-bundle et les catégories de consentement des témoins ; son corps peut aussi les nommer par des espaces
réservés. La fonction Twig `phil_legal_notices_links()` donne les liens d'un pied de page, dont les politiques de
phil/terms-bundle ; `phil:legal-notices:check` signale ce qui manque à un document public, et
`phil:legal-notices:announce` distribue un événement pour chaque version publiée récemment.

Il s'adresse à un site qui doit publier qui il est et comment il traite les renseignements personnels, garder chaque
version qu'il a publiée, et montrer les durées et les témoins qu'applique son code. Les corps sont assainis par
phil/markup, chaque valeur insérée est échappée, et les services ne gardent rien entre deux requêtes.

## État

| Partie | État |
|---|---|
| Configuration : identité, versions en ligne ou dans des fichiers, sources, conservation, consentement, pages, pied de page | Implémentée et testée |
| Pages : la version en vigueur, chaque version, l'historique, dans chaque locale | Implémentées et testées |
| Corps phil/markup, conservation de phil/privacy-bundle, catégories de consentement, liens de pied de page de phil/terms-bundle, pages de phil/sitemap-bundle, nœuds de phil/structured-data-bundle | Implémentés et testés |
| `phil:legal-notices:check`, `phil:legal-notices:announce` et `DocumentPublished` | Implémentés et testés |
| Les pages dessinées par les recettes `badge` et `table` du kit PHIL UX ou du kit Shadcn, chaque mot tiré des traductions, des listes dans la langue de la page | Implémentées et testées |
| La recette Flex : le bundle enregistré, ses routes, une configuration avec deux brouillons et leurs corps | Implémentée et testée |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les routes et un premier document. |
| [Configuration](doc/fr/configuration.md) | Chaque option, les versions et les vérifications de la construction du conteneur. |
| [Pages](doc/fr/pages.md) | Les routes, les pages, les recettes du kit, leurs gabarits et leur cache, les liens du pied de page. |
| [Intégrations](doc/fr/integrations.md) | phil/markup, phil/privacy-bundle, consentement, phil/terms-bundle, phil/sitemap-bundle, phil/structured-data-bundle, phil/accessibility-statement-bundle, PHIL UX, EasyAdmin. |
| [Console](doc/fr/console.md) | La vérification et les annonces. |
| [Points d'extension](doc/fr/extension-points.md) | Les services, ports et gabarits qu'une application remplace. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |

## Démarrage rapide

```sh
composer require phil/legal-notices-bundle
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

La recette du bundle l'enregistre, monte ses routes et écrit sa configuration, avec les deux documents en brouillon et
leurs corps dans `legal/`. Remplissez l'identité, puis publiez la première version :

```yaml
# config/packages/phil_legal_notices.yaml
phil_legal_notices:
    identity:
        publisher_name: Example Inc.
        publisher_address: "1 Main Street\nMontréal QC"
        publication_director: Jo Smith
        contact_email: hello@example.com
        host_name: Example Hosting
        host_address: 2 Server Road, Toronto ON
        host_contact: support@hosting.example
    documents:
        legal_notice:
            - number: 1
              format: markdown
              published_at: '2026-12-01'
              files:
                  en: '%kernel.project_dir%/legal/notice.en.md'
                  fr: '%kernel.project_dir%/legal/notice.fr.md'
```

Un corps nomme les champs de l'identité comme des espaces réservés entre `${` et `}`, un marqueur qu'aucun des formats
de phil/markup ne lit, les formats wiki compris :

```markdown
# Legal notice

This site is published by **${publisher_name}**, ${publisher_address}.
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl`, Symfony 8.2 ou plus récent, Twig avec twig/intl-extra, Symfony UX Twig
Components et les recettes `badge` et `table` du kit PHIL UX, ou du kit Shadcn sur Tailwind ; phil/markup, phil/privacy-bundle,
phil/terms-bundle et phil/sitemap-bundle sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
