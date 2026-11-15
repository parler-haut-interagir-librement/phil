<!-- phil-dev source-sha256=6b6cd4138200b27362885a865ab172766d44dbcca17cb42a4014ed83379f8c59 -->
# La page

`config/routes.php` déclare deux routes localisées :

| Route | Anglais | Français |
|---|---|---|
| `phil_accessibility_statement` | `/accessibility` | `/accessibilite` |
| `phil_accessibility_statement_version` | `/accessibility/v{version}` | `/accessibilite/v{version}` |

La première montre la version en vigueur, la seconde chaque version à une adresse qui ne change jamais. Une route
répond à `GET` et à `HEAD`. Une version qui n'existe pas, ou un site qui n'en a aucune, répond 404. Un préfixe de
locale, un hôte ou d'autres chemins relèvent de l'application : importez le fichier avec un `prefix`, ou déclarez vos
propres routes vers `StatementController::current()` et `version()`.

## Ce que montre la page

1. L'engagement et ce que mesure la déclaration, dans le libellé choisi.
2. L'état de conformité, avec la cible et un lien vers la recommandation du W3C.
3. Le contenu non accessible, regroupé par motif (critères non satisfaits, charge disproportionnée, contenu hors du
   champ d'application), chacun dans une carte placée dans un élément de liste, coiffée du numéro, du titre et du niveau de son critère, avec sa
   description, les occurrences trouvées par un audit, la solution de rechange et la date où il sera satisfait.
4. Les solutions de rechange.
5. Les moyens de rétroaction : un bouton vers le formulaire de `feedback_form` (la page d'un formulaire de
   `phil/forms-bundle`) ou, à défaut, vers la page de `feedback_route`, puis la page de rétroaction, le courriel, le téléphone sous
   forme de lien `tel:`, l'adresse postale, et le nombre de jours dans lequel une réponse arrive.
6. La procédure de recours et l'autorité, liée à sa page.
7. La façon dont la déclaration a été préparée : sa version et sa publication, la dernière évaluation, sa méthode, les
   pages évaluées et son rapport.
8. Les versions, quand il y en a plusieurs, dans un tableau : celle qui est affichée porte `aria-current="page"` et la
   plus récente est marquée en vigueur par un badge.

Chaque section est une `<section>` étiquetée par son titre, et la liste des versions est un `<nav>`. La page ne porte
ni script ni style propre : les recettes du kit portent leurs classes, et la feuille de style du kit, chargée par la
mise en page, leur donne leur apparence.

## Les recettes du kit

Les gabarits dessinent leurs parties avec des recettes du kit PHIL UX, ou du kit Shadcn, installées dans l'application
par le Symfony UX Toolkit ; rien n'est rendu depuis `vendor/` :

```sh
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
# for the administration page
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

| Gabarit | Recettes |
|---|---|
| `statement.html.twig`, `_versions.html.twig` | `Card` avec `Card:Header` et `Card:Content` ; `Table` avec ses parties ; `Badge` ; `Button` |
| `admin.html.twig` | `Alert` avec `Alert:Title` et `Alert:Description` ; `Field` avec `Field:Label` et `Field:Description` ; `Textarea` ; `Button` ; le tableau ci-dessus |

Ces recettes ont les noms, les propriétés et les blocs du kit Shadcn du Toolkit : un site sous Tailwind les installe
donc plutôt depuis le kit Shadcn (`--kit=shadcn`), avec `label` et `separator`, que ses recettes appellent. Les gabarits
ne passent que les propriétés que les deux kits partagent : l'élément de liste autour de chaque carte, le titre d'un
critère et le `scope` des cellules d'en-tête sont les leurs, car la carte Shadcn est un `div` et son titre n'est pas un
titre de section ; un champ invalide le dit par la classe `isDanger` et par `data-invalid`, que chaque kit met en forme.
Les tests rendent la page et la page d'administration avec chaque kit, et vérifient qu'aucun gabarit n'écrit un mot de
sa main.

Les composants se trouvent sous le sous-répertoire des composants de la configuration `ux_toolkit`, comme les nomme
`phil_accessibility_kit('Card:Content')` ; dans un environnement où le Toolkit n'est pas
activé, comme l'est souvent la production, répétez-le dans l'option `kit_subdirectory`. Un gabarit propre à
l'application appelle une recette de la même façon :
`{% component (phil_accessibility_kit('Badge')) with {variant: 'secondary'} %}`.

## Les langues

La page est en anglais et en français, à partir du domaine de traduction `phil_accessibility_statement`. Les titres
des critères de succès sont ceux du W3C en anglais, et le bundle les fournit en français. Les textes de la déclaration
sont montrés dans la langue de la requête, sinon dans sa langue principale, sinon dans `default_locale`, sinon dans la
première fournie. Ajoutez une autre langue avec un fichier `translations/phil_accessibility_statement.<locale>.php`
(ou `.yaml`, `.xlf`) de l'application, qui reprend les clés du fichier anglais.

## Le libellé

| `wording` | Pour |
|---|---|
| `wcag` | Tout site : l'engagement et la mesure par rapport aux Règles pour l'accessibilité des contenus Web. |
| `en-301-549` | Un site soumis aux exigences européennes d'accessibilité : la norme harmonisée EN 301 549, qui applique les WCAG au contenu Web. |
| `aca` | Un site soumis à la Loi canadienne sur l'accessibilité : la norme CAN/ASC-EN 301 549, et la façon d'accuser réception de la rétroaction. |

Chaque libellé est un gabarit, `@PhilAccessibilityStatement/wording/<wording>.html.twig`, fait de phrases du domaine
de traduction. Le libellé ne remplace pas un avis juridique : une organisation lit les obligations qui s'appliquent à
elle et écrit sa procédure de recours dans la déclaration.

## Remplacer les gabarits

Remplacez la mise en page avec l'option `layout`. Remplacez la page elle-même, `statement.html.twig`, et les libellés
à la manière de Symfony, par un gabarit du même nom sous `templates/bundles/PhilAccessibilityStatementBundle/`. La
page reçoit `statement`, `groups` (les non-conformités par motif), `versions`, `wording`, `feedback_route`,
`feedback_form` et `layout`. Dans le bloc d'une recette, les variables de la boucle qui l'entoure ne sont pas visibles :
définissez ce dont le bloc a besoin avant la balise `{% component %}`.

## Le lien de pied de page

```twig
<footer>
    {{ phil_accessibility_statement_link() }}
    {{ phil_accessibility_statement_link('Accessibility statement', {class: 'footer-link'}) }}
    <a href="{{ phil_accessibility_statement_path() }}">…</a>
</footer>
```

`phil_accessibility_statement_link()` écrit le lien vers la page dans la langue de la requête. Son texte est traduit
(`Accessibility`, `Accessibilité`) à moins que vous en fournissiez un, et il est échappé. Le lien porte les attributs
fournis dont le nom est simple (`class`, `data-…`, `aria-…`) : jamais `href`, jamais un nom avec une espace ou une
majuscule, jamais une valeur qui n'est pas scalaire. `phil_accessibility_statement_path()` donne l'adresse seule, et
le filtre `|phil_accessibility_text` donne le texte d'un `LocalizedText` dans la langue de la requête.

Quand `phil/legal-notices-bundle` est installé, la déclaration figure aussi parmi les liens de
`phil_legal_notices_links()`, dès qu'une version est publiée et que la route est importée. Un pied de page qui montre
ces liens n'a pas besoin de son propre lien.
