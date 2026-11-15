<!-- phil-dev source-sha256=4affe70783d8c426f50636feae4347701aeca62aa068f263d5190f348fc6bfa5 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent avec `ext-intl`, Symfony 8.2 ou plus récent et Twig, avec le bundle Twig et le traducteur pour
  les pages.
- Symfony UX Twig Components et les recettes `badge` et `table` du kit PHIL UX, installées par le Symfony UX Toolkit,
  pour les pages ; twig/intl-extra écrit leurs listes dans la langue de la page.
- phil/markup pour des corps en Markdown, en UJO (Universal JSON Output) ou en HTML ; sans lui, les corps sont en texte
  brut.
- phil/privacy-bundle pour les durées de conservation de son calendrier, phil/terms-bundle pour ses politiques parmi
  les liens du pied de page, phil/sitemap-bundle pour les pages dans le plan du site, phil/structured-data-bundle pour
  les pages en JSON-LD : chacun est facultatif.

## Installer

```sh
composer require phil/legal-notices-bundle
```

La recette Flex du bundle l'enregistre et écrit :

| Fichier | Ce qu'il contient |
|---|---|
| `config/packages/phil_legal_notices.yaml` | Les champs de l'identité, laissés vides ; les mentions légales et la politique de confidentialité, chacune une version 1 en brouillon dont les corps sont des fichiers ; la catégorie de consentement du témoin de session |
| `config/routes/phil_legal_notices.yaml` | Les routes, en anglais à la racine et en français sous `/fr` |
| `legal/notice.en.md`, `legal/notice.fr.md`, `legal/privacy.en.md`, `legal/privacy.fr.md` | Les corps, en Markdown, qui nomment les champs de l'identité comme des espaces réservés |

Un brouillon n'est montré par aucune page et n'exige aucun champ de l'identité : l'application se construit donc dès
l'installation, et les pages répondent 404 tant qu'aucune version n'est publiée. Sans Flex, enregistrez le bundle et
écrivez ces fichiers vous-même :

```php
// config/bundles.php
return [
    // …
    Phil\LegalNoticesBundle\PhilLegalNoticesBundle::class => ['all' => true],
];
```

## Les recettes du kit

Les pages dessinent leurs badges et leur tableau avec des recettes du kit PHIL UX, copiées dans l'application par le
Symfony UX Toolkit ; `PhilLegalNoticesBundle::RECIPES` les liste :

```sh
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Un site sur Tailwind installe plutôt les mêmes recettes depuis le kit Shadcn du Toolkit ; les pages sont testées avec
les deux kits. Voir
[Pages](pages.md#les-recettes-du-kit).

## Les routes

```yaml
# config/routes/phil_legal_notices.yaml
phil_legal_notices:
    resource: '@PhilLegalNoticesBundle/config/routes.php'
    prefix: { en: '', fr: '/fr' }
```

Un préfixe par locale rend les routes localisées : `/legal` en anglais, `/fr/legal` en français, la locale de la
requête étant fixée par la route. Montées sans préfixe, les pages suivent la locale que l'application fixe sur la
requête.

## Un premier document

Remplissez l'identité dans `config/packages/phil_legal_notices.yaml`, adaptez le corps dans `legal/`, puis publiez la
version : donnez-lui un `published_at` et retirez son `state: draft` (voir [Configuration](configuration.md)). Un corps
nomme les champs de l'identité entre `${` et `}` :

```markdown
# Legal notice

This site is published by **${publisher_name}**, ${publisher_address}.
Director of publication: ${publication_director}. Contact: ${contact_email}.

It is hosted by ${host_name}, ${host_address} (${host_contact}).
```

Lancez ensuite `bin/console phil:legal-notices:check` : elle signale un espace réservé sans valeur ou un corps qui ne
peut pas être lu. La construction du conteneur refuse un document publié dont l'identité n'a pas un champ qu'il exige.
