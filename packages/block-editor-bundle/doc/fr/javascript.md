<!-- phil-dev source-sha256=d602178df8f2cd71ec03b0f2ef67098622797d5f3f5a2cef3e987e79d352cd1a -->
# JavaScript

## Le contrôleur

`assets/src/editor_controller.js` est un contrôleur Stimulus, enregistré sous le nom
`phil--block-editor-bundle--editor`. Le thème de formulaire écrit son balisage ; une page peut l'écrire à la main, par
exemple pour afficher un document en lecture seule hors d'un formulaire :

```html
<div class="phil-block-editor" data-controller="phil--block-editor-bundle--editor"
     data-phil--block-editor-bundle--editor-config-value='{"readOnly": true, "locale": "fr"}'>
    <input type="hidden" value="{{ article.body }}" data-phil--block-editor-bundle--editor-target="input">
    <div data-phil--block-editor-bundle--editor-target="canvas"></div>
    <div class="phil-block-editor__status" data-phil--block-editor-bundle--editor-target="status" role="status"></div>
</div>
```

| Cible | Rôle |
|---|---|
| `input` | Le champ qui contient le JSON UJO, lu quand l'éditeur se connecte et écrit après chaque changement |
| `canvas` | L'endroit où les blocs sont dessinés ; créé s'il manque |
| `status` | Une zone dynamique qui annonce les déplacements, les suppressions et les téléversements |

La valeur `config` est un seul objet :

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `tools` | tous les outils de bloc | Les types de blocs offerts |
| `inlineTools` | tous les outils en ligne | Les outils en ligne offerts |
| `readOnly` | `false` | Afficher sans modifier |
| `placeholder` | le libellé `placeholder` | L'indication d'un paragraphe vide |
| `locale` | le `lang` de la page | La langue des libellés |
| `messages` | `{}` | Libellés remplacés, par clé |
| `colors` | `[]` | Les jetons de couleur de l'outil de couleur du texte |
| `uploadUrl`, `mentionsUrl`, `linksUrl` | `null` | Les points de terminaison ; `null` désactive la fonctionnalité |
| `csrfCookie` | `null` | Le nom du témoin et de l'en-tête du jeton CSRF à double soumission ; `null` n'en envoie aucun, et le téléversement est refusé |

L'éditeur ne refuse jamais de s'ouvrir : un JSON qu'il ne peut pas lire donne un document vide, et le serveur juge ce
qu'il reçoit.

## Événements

| Événement | Sur | Détail |
|---|---|---|
| `change` | le champ | — déclenché chaque fois que l'éditeur écrit une nouvelle valeur, pour l'enregistrement automatique ou un avertissement de formulaire modifié |
| `phil--block-editor-bundle--editor:change` | l'élément de l'éditeur | `{json}`, la valeur écrite |
| `phil--block-editor-bundle--editor:focus` | l'élément de l'éditeur | `{id}`, le bloc où le rédacteur est entré ; `null` quand le focus quitte l'éditeur, ses menus et sa barre d'outils |

## Écrire à plusieurs

L'éditeur écoute sur son élément les événements qu'émet un contrôleur de collaboration — celui de
`phil/edit-lock-bundle`. Aucun d'eux n'entre dans l'historique d'annulation : un bloc remplacé par un autre rédacteur ne
peut pas être annulé localement, et l'historique repart donc à zéro après chacun.

| Événement | Détail | Ce que fait l'éditeur |
|---|---|---|
| `phil-block-editor:apply` | `{block, list, after}` | Met le bloc à la place du bloc de même identifiant, quelle que soit son imbrication ; sinon, l'insère dans la liste `list` (`root`, l'identifiant d'un bloc, ou l'identifiant d'un bloc et une colonne, `id:0`) après le bloc `after`, en premier quand `after` est `null`, à la fin du document quand la liste n'existe nulle part. Seul ce bloc est redessiné : le curseur reste donc où se trouve le rédacteur |
| `phil-block-editor:remove` | `{id}` | Enlève le bloc |
| `phil-block-editor:lock` | `{blocks: {id: name}}` | Ferme les blocs nommés — `inert`, encadrés, le nom affiché au-dessus — et ouvre les autres |

Le document est écrit dans le champ après `apply` et `remove`, hors du mode lecture seule, comme après tout changement.

## Libellés

Les libellés sont en anglais et en français dans `assets/src/lib/i18n.js`. Une locale comme `fr_CA` prend le français,
une locale inconnue l'anglais. Tout libellé peut être remplacé par l'option `messages` du champ ou par la clé
`messages`, selon sa clé — `placeholder`, `delete`, `turnInto`, `color.accent` et les autres du fichier. `{type}` dans
`foreign` et `converted` est remplacé par le type.

## Modules

Le contrôleur est construit sur des modules sans DOM, chacun testé sous Node, et sur deux modules qui exigent un
navigateur :

| Module | Rôle |
|---|---|
| `lib/inline.js` | L'assainisseur de texte en ligne : les outils du profil `rich` de `phil/ujo`, sa forme canonique, ses règles d'adresses et d'attributs, les styles lus comme des marques |
| `lib/paste.js` | Le HTML ou le texte collé transformé en blocs du jeu d'outils |
| `lib/model.js` | UJO lu et écrit, les blocs trouvés, déplacés et placés dans les listes imbriquées, les éléments de liste, les lignes et colonnes de tableau, l'historique d'annulation |
| `lib/tools.js` | Les données de chaque type de bloc, son texte et ses transformations, le menu `/`, les actions du menu de bloc |
| `lib/menu.js` | La correspondance et la sélection dans les menus, les déclencheurs `/`, `@` et `[[`, le HTML d'une suggestion choisie |
| `lib/keys.js` | Les raccourcis et les marques à la Markdown |
| `lib/i18n.js` | Les libellés |
| `lib/csrf.js` | Un nouveau jeton aléatoire pour chaque requête, son témoin, son en-tête et le champ d'un formulaire |
| `lib/caret.js` | Le curseur et la sélection dans un champ, la scission, l'insertion, les marques ; exige un navigateur |
| `lib/view.js` | Les blocs dessinés en éléments ; exige un navigateur |

Tout HTML que dessine l'éditeur passe d'abord par `lib/inline.js`, et les adresses des liens et des images sont
vérifiées selon les mêmes règles que sur le serveur.

## La feuille de style

`assets/styles/block-editor.css` met en forme l'éditeur sous `.phil-block-editor` avec des propriétés personnalisées :
`--pbe-text`, `--pbe-muted`, `--pbe-surface`, `--pbe-sunken`, `--pbe-border`, `--pbe-accent`, `--pbe-accent-soft`,
`--pbe-focus`, `--pbe-danger`, `--pbe-info`, `--pbe-success`, `--pbe-warning`, `--pbe-mark`, `--pbe-radius`,
`--pbe-mono`, `--pbe-gutter` et `--pbe-shadow`. Un bloc tenu par un autre rédacteur, `.pbe-locked`, est encadré en
`--pbe-info` avec le nom tiré de son attribut `data-locked-by`. Chaque propriété prend le jeton de PHIL UX de même rôle
quand cette feuille de style est chargée, et sinon une valeur tirée de `Canvas` et `CanvasText` de la page : elle suit
donc le mode clair ou sombre de la page. Redéfinissez-les sur `.phil-block-editor` pour changer l'apparence. Les cinq
jetons de couleur par défaut y sont aussi mis en forme, et les jetons propres à un site demandent chacun une règle.
