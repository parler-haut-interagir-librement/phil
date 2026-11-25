<!-- phil-dev source-sha256=c182ff24f859d26b6ed6165714b0fff9f38b0e670c3019d39be98a72aae1a127 -->
# Pages

## Les routes

| Route | Chemin | Page |
|---|---|---|
| `phil_legal_notices_legal_notice` | `/legal` | Les mentions légales en vigueur |
| `phil_legal_notices_legal_notice_history` | `/legal/history` | Leurs versions |
| `phil_legal_notices_legal_notice_version` | `/legal/v{number}` | Une version |
| `phil_legal_notices_privacy_policy` | `/privacy` | La politique de confidentialité en vigueur |
| `phil_legal_notices_privacy_policy_history` | `/privacy/history` | Ses versions |
| `phil_legal_notices_privacy_policy_version` | `/privacy/v{number}` | Une version |

`DocumentController::route(DocumentType $type, string $page = '')` donne ces noms. Un document sans version en vigueur,
un brouillon, une version qui n'existe pas et un historique sans version publique reçoivent une réponse 404.

## Ce que montre une page

La page d'une version montre son titre, son numéro et son statut (en vigueur depuis une date, en vigueur à partir d'une
date, remplacée, retirée), un lien vers l'historique, le résumé de ses changements, un lien vers la prochaine version
annoncée, puis son corps. La politique de confidentialité liste aussi les durées de conservation, sous forme de
tableau, et les catégories de consentement avec leurs témoins, sauf si `pages.retention_section` ou
`pages.consent_section` est désactivé, pour un corps qui les place lui-même avec `${retention.<id>}` et
`${consent.<key>}`.

L'historique liste les versions publiées et retirées, la plus récente d'abord, avec leurs dates d'entrée en vigueur et
leurs résumés ; la version en vigueur est marquée comme page courante.

Le corps est affiché dans la locale de la requête, sinon dans sa langue, sinon dans `default_locale` ; chaque texte des
pages, ponctuation comprise, est dans le domaine `phil_legal_notices`, en anglais et en français, et un gabarit n'écrit aucun
mot de lui-même : un site change un texte, ou ajoute une langue, avec un fichier de traduction de ce domaine. Chaque
réponse porte un `Content-Language` ; la page en vigueur et l'historique sont mis en cache cinq minutes, une version une
heure.

## Les recettes du kit

Les gabarits dessinent leurs parties avec des recettes du kit PHIL UX, installées dans l'application par le Symfony UX
Toolkit ; rien n'est rendu depuis `vendor/` :

```sh
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

| Gabarit | Recettes |
|---|---|
| `document.html.twig` | `Badge` pour l'état de la version — `default` en vigueur, `outline` bientôt en vigueur, `secondary` remplacée, `destructive` retirée — ; `Table` avec `Table:Header`, `Table:Body`, `Table:Row`, `Table:Head` et `Table:Cell` pour les durées de conservation |
| `history.html.twig` | `Badge` pour la version en vigueur et les versions retirées |

Ces recettes ont les noms, les propriétés et les blocs du kit Shadcn du Toolkit : un site sur Tailwind les installe donc
depuis le kit Shadcn, avec l'extension HTML de Twig et les filtres Tailwind qu'appellent ses recettes ; les pages sont
testées avec les deux kits. Les composants se trouvent sous le sous-répertoire des composants de la configuration
`ux_toolkit`, comme les nomme `phil_legal_notices_kit('Table:Row')` ; dans un environnement où le Toolkit n'est pas
activé, comme l'est souvent la production, répétez-le dans l'option `pages.kit_subdirectory`. Un gabarit propre à
l'application appelle une recette de la même façon : `{% component (phil_legal_notices_kit('Badge')) with {variant: 'secondary'} %}`.

Les témoins d'une catégorie de consentement s'écrivent comme une liste dans la langue de la page — `_pk_id.*, _pk_ses.*, and
_pk_ref.*`, `_pk_id.*, _pk_ses.* et _pk_ref.*` — par le filtre `format_list` de twig/intl-extra. Le bundle enregistre
cette extension, et `IntlExtensionPass` la retire quand l'application enregistre la sienne : TwigExtraBundle avec sa
partie intl activée, ou une extension Twig de la classe `Twig\Extra\Intl\IntlExtension`. Twig en a toujours une.

## Gabarits

| Gabarit | Contexte |
|---|---|
| `@PhilLegalNotices/document.html.twig` | `type`, `version`, `html`, `summary`, `retention`, `consent`, `now`, `in_force`, `upcoming`, `layout` |
| `@PhilLegalNotices/history.html.twig` | `type`, `versions`, `now`, `in_force`, `upcoming`, `layout` |
| `@PhilLegalNotices/_links.html.twig` | aucun : il appelle `phil_legal_notices_links()` |
| `@PhilLegalNotices/layout.html.twig` | les blocs `title` et `content`, et les liens du pied de page |

La mise en page du bundle est une page HTML nue ; la mise en page de l'application charge la feuille de style de
phil/ux-css, qui donne leur apparence aux recettes et habille le reste de la page — titres, listes, liens — sans classe.

`pages.layout` nomme la mise en page qu'étendent les pages : celle de l'application, qui définit `title` et `content`.

## Liens du pied de page

```twig
{{ include('@PhilLegalNotices/_links.html.twig') }}
```

`phil_legal_notices_links()` retourne, pour chaque document qui a une version en vigueur, puis pour chaque politique de
`footer.terms_policies`, une `url`, une clé de traduction `label` et son `domain`. Les pages des conditions de
phil/terms-bundle renvoient vers les documents quand leur mise en page inclut le même gabarit.
