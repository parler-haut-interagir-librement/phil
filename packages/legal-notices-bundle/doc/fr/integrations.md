<!-- phil-dev source-sha256=e841e0f190946c5ba858d7b420c840c09ac0e0792c33fdbb51d47c3437e5fcac -->
# Intégrations

## phil/markup

Avec phil/markup installé, les corps sont rendus par son `Markup` (Markdown, UJO (Universal JSON Output), HTML, texte
brut et chaque format qu'il lit), puis assainis à son niveau `all`. `markup` nomme plutôt un service `Markup` de
l'application, avec ses propres modules. Sans phil/markup, seul le format `text` est rendu ; avec un autre format,
`phil:legal-notices:check` signale le corps.

## phil/privacy-bundle

```yaml
phil_legal_notices:
    retention:
        source: App\Privacy\RetentionSchedule     # the RetentionScheduleSource the privacy bundle reads
        labels:
            R-001: { en: Your account, fr: Votre compte }
```

Chaque règle du calendrier qui contient des renseignements personnels (ou toutes les règles, avec `personal_only: false`)
devient une ligne de la politique de confidentialité : ce qu'elle garde, nommé par son libellé dans la locale, sinon
par son magasin, et sa durée en toutes lettres. Une durée fixe s'écrit « 2 years », « 2 ans » ; une durée jusqu'à un
événement montre l'événement que nomme le calendrier ; la note d'une durée pas encore fixée reste interne. Le corps
nomme une durée avec `${retention.R-001}`. Une autre source de durées est un service de l'application, aliasé à
`Phil\LegalNotices\Contract\RetentionDurations`.

## Catégories de consentement

Les catégories sous `consent.categories` sont listées sous la politique de confidentialité, nécessaires ou activées
seulement avec consentement, avec leurs témoins ; `${consent.<key>}` en nomme une dans le corps. Un bandeau de
consentement qui garde ses propres catégories les donne par un service `ConsentCategories` nommé par `consent.source`.

## phil/terms-bundle

`footer.terms_policies` ajoute les politiques de phil/terms-bundle aux liens du pied de page, par sa route
`phil_terms_policy` et les libellés de son domaine `phil_terms`. Les pages des conditions montrent les mêmes liens quand
la mise en page qu'elles étendent, celle de son option `pages.layout`, inclut `@PhilLegalNotices/_links.html.twig`.

## phil/sitemap-bundle

Avec phil/sitemap installé, `LegalUrlProvider` ajoute la page de chaque document en vigueur à la section
`sitemap_section`, avec comme date de modification l'entrée en vigueur de sa version. Les routes localisées donnent la
page de la locale dans laquelle le plan du site est construit.

## phil/structured-data-bundle

Avec phil/structured-data-bundle installé, `LegalPagesStructuredData` décrit la page d'un document en vigueur et la page
de chacune de ses versions comme une `WebPage`, au `#webpage` de la page pour qu'elle se fusionne avec le nœud du site :
le nom traduit du document, la langue, les dates de publication et d'entrée en vigueur, son numéro de version, et
l'éditeur nommé par l'identité, avec son adresse de contact et son adresse postale. L'historique et toutes les autres
pages ne donnent aucun nœud.

## phil/accessibility-statement-bundle

Avec phil/accessibility-statement-bundle installé, le lien vers la déclaration vient après les autres liens du pied de
page, par `FooterLinkProvider`.

## PHIL UX

Les pages dessinent leurs badges et leur tableau avec les recettes `badge` et `table` du kit PHIL UX, que `ux:install`
copie dans l'application ([Pages](pages.md#les-recettes-du-kit)) ; le reste est du HTML sémantique que la feuille de
style de phil/ux-css habille sans classe. Une application qui change une page copie son gabarit sous
`templates/bundles/PhilLegalNoticesBundle/` et appelle les mêmes recettes, ou d'autres du kit, par les noms que donne
`phil_legal_notices_kit()`.

## EasyAdmin

Le bundle n'a aucune page d'administration : les versions sont publiées en les écrivant dans la configuration, ou par
le `DocumentSource` propre à l'application. Une application qui les publie depuis un tableau de bord EasyAdmin écrit
cette page dans son tableau de bord, où phil/ux-easyadmin-bundle lui donne les couleurs de PHIL UX comme à toute autre
page de l'administration.
