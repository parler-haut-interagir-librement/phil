<!-- phil-dev source-sha256=7650a241f73396cb85f33af4a1fe58c55db0e6532481e9d2b891bb22c44a448f -->
# Essai de ProseMirror

**Essai archivé.** L'éditeur garde ses propres champs riches. L'option `richEngine` de l'essai, ses modules et la
dépendance à `prosemirror-*` ne font plus partie du bundle depuis 0.1.1-alpha. Cette version corrige aussi les deux
échecs de `lib/caret.js` que l'essai a trouvés, et donne à chaque champ modifiable un rôle `textbox` et un nom
accessible. L'essai serait rouvert si les claviers Android ou un lecteur d'écran comme VoiceOver montraient un écart
que l'éditeur ne peut pas combler.

L'essai plaçait une vue [ProseMirror](https://prosemirror.net/) dans chaque champ riche, à la place de la gestion de
`contenteditable` propre à l'éditeur. Il s'activait champ par champ et laissait tout le reste au contrôleur :
l'enveloppe Stimulus, les blocs et leur coordination, les menus `/`, `@` et `[[`, la barre d'outils, le collage de
blocs, l'historique d'annulation et l'UJO (Universal JSON Output) écrit dans l'entrée. Cette page dit comment l'essai
était câblé, comment il a été mesuré contre les champs propres de l'éditeur et quels étaient les chiffres.

## Son activation

```html
<div data-controller="phil--block-editor-bundle--editor"
     data-phil--block-editor-bundle--editor-config-value='{"richEngine": "prosemirror"}'> … </div>
```

| Clé | Valeurs | Sens |
|---|---|---|
| `richEngine` | `"dom"` (valeur par défaut), `"prosemirror"` | Qui modifie le texte et les marques d'un champ riche ; le mode lecture seule dessine toujours du HTML simple |

La table d'importation de la page exigeait `prosemirror-model`, `prosemirror-state`, `prosemirror-transform` et
`prosemirror-view` (et `orderedmap`, que `prosemirror-model` importe). `assets/package.json` énumérait les quatre, de
sorte que `importmap:require` les ajoutait, avec le module dont ils dépendent, quand le contrôleur du bundle était
installé. Le module de l'essai, `lib/prosemirror.js`, était importé dynamiquement : un champ sans
`richEngine: "prosemirror"` ne chargeait jamais ProseMirror. Webpack Encore n'intervenait pas.

## Le câblage

| Module | Rôle |
|---|---|
| `lib/rich-schema.js` | Le schéma en simples spécifications, sans le DOM et sans ProseMirror : une marque par outil en ligne du profil `rich`, lue depuis chaque élément que l'outil accepte par le `match` de l'assainisseur lui-même et écrite sous sa forme canonique ; un saut de ligne comme seul nœud en dehors du texte ; des liens de tout genre qui s'excluent entre eux et qui ne s'étendent pas à leur fin ; les styles en ligne écrits par Word et Google Docs lus comme des marques, l'enveloppe en gras de poids normal de Google Docs refusée. Testé sous Node |
| `lib/prosemirror.js` | La vue montée sur le champ lui-même (`{mount: field}`), si bien que le champ garde son élément, sa classe et ses attributs de données ; le document lu à travers un `<template>` inerte, écrit par `DOMSerializer` puis par l'assainisseur ; marques basculées, HTML et sauts de ligne insérés, champ scindé au curseur, le tout par des transactions |
| `editor_controller.js` | `loadEngine()` importe le module après le premier dessin, `mountRich()` donne une vue à chaque champ riche après chaque dessin et détruit les vues des champs retirés du dessin ; `deleteBefore`, `insertHtml`, `insertLineBreak`, `splitAtCaret` et `toggleMark` passent à la vue quand le champ en a une, à `lib/caret.js` sinon ; `html(field)` lit le document de la vue ; une vue signale ses changements par `input()` une fois le DOM lu, et l'écouteur `input` du contrôleur laisse ses champs tranquilles |
| `lib/caret.js` | Le saut de ligne que ProseMirror dessine à la fin d'une ligne vide compte pour rien dans `atStart` et `atEnd` |
| `block-editor.css` | Le vide de la vue sous la forme de `.pbe-rich-empty`, qui affiche le texte indicatif par-dessus la ligne |

L'historique, la table des touches et les commandes de ProseMirror ne sont pas utilisés. Entrée, Retour arrière,
Suppr, les flèches, Tab et les raccourcis parviennent au contrôleur comme avant ; un collage est laissé au contrôleur
(`handleDOMEvents.paste`), qui le transforme en blocs et insère le HTML en ligne par la vue.

## Méthode

`tests/Browser/measure.mjs` exécute les mêmes 26 scénarios sur deux pages qui ne diffèrent que par `richEngine`, dans un
Chrome 158 sans interface piloté par le protocole DevTools, chaque scénario sur une page neuve :

- les touches sont envoyées par `Input.dispatchKeyEvent`, de sorte que la page reçoit `keydown`, `beforeinput` et
  `input` comme d'un clavier ; la touche de commande est ⌘ sur un Mac ;
- la saisie IME est envoyée par `Input.imeSetComposition` et `Input.insertText`, qui font lever à Chrome ses propres
  événements `compositionstart`, `compositionupdate`, `compositionend` et `input` de composition, comme le fait une
  méthode de saisie ;
- un collage est un `ClipboardEvent` portant un `DataTransfer` avec `text/html` et `text/plain`, tiré des jeux de
  données d'essai de `tests/Browser/paste-fixtures.mjs` (Word pour Windows, Google Docs, une page Web piégée), noté
  selon les marques conservées, le texte présent et l'absence de tout ce qui est interdit ;
- le résultat est la liste des textes de blocs que le contrôleur enregistre dans son entrée.

La même exécution lit le nœud d'accessibilité que Chrome donne à un lecteur d'écran pour le premier champ, compte les
octets et les requêtes que la page charge avec le cache désactivé, et chronomètre le dessin de 50 et de 500
paragraphes. Le poids servi par AssetMapper est mesuré sur les fichiers eux-mêmes : ceux du bundle tels qu'AssetMapper
les sert, sans minificateur, et ceux de ProseMirror tels que `importmap:require` les télécharge, les versions `+esm`
minifiées de jsDelivr. Les tailles sont additionnées fichier par fichier, puisque chacun est une requête distincte,
compressées avec `gzip -9` et `brotli -q 11`.

```bash
npm ci --ignore-scripts --prefix packages/block-editor-bundle/tests/Browser
node packages/block-editor-bundle/tests/Browser/measure.mjs
```

Le script ne fait plus tourner que les champs propres de l'éditeur, avec deux scénarios de plus sur une marque activée
ou désactivée au curseur ; le second moteur revient avec les modules de l'essai (voir
[Rouvrir l'essai](#rouvrir-lessai)).

Versions mesurées : `prosemirror-model` 1.25.12, `prosemirror-state` 1.4.4, `prosemirror-transform` 1.12.4,
`prosemirror-view` 1.42.9, `orderedmap` 2.1.1, toutes sous licence MIT.

## Résultats

### Poids

| Ce qui est servi | Brut | gzip | Brotli |
|---|---:|---:|---:|
| L'éditeur d'aujourd'hui : contrôleur, 10 modules, feuille de style | 173 057 o | 45 655 o | 39 020 o |
| Les mêmes fichiers avec l'interrupteur de l'essai, `richEngine: "dom"` | 177 017 o | 46 587 o | 39 838 o |
| Les modules propres de l'essai, `prosemirror.js` et `rich-schema.js` | 11 869 o | 4 321 o | 3 634 o |
| ProseMirror par `importmap:require` (model 45 263, view 99 174, transform 32 969, state 12 460, orderedmap 1 799) | 191 665 o | 62 149 o | 55 713 o |
| **L'éditeur avec `richEngine: "prosemirror"`** | **380 551 o** | **113 057 o** | **99 185 o** |

ProseMirror multiplie ce que l'éditeur envoie par 2,2 en brut et par 2,5 compressé : environ 60 Ko de plus en Brotli.
Sur la page de mesure, qui sert les versions non minifiées de npm sans compression, l'éditeur charge 15 requêtes et
272 848 o avec ses propres champs, 22 requêtes et 788 530 o avec ProseMirror.

### Comportement

| Scénario | Champs propres | ProseMirror |
|---|:---:|:---:|
| Frappe | réussi | réussi |
| Chacune des 8 marques par son raccourci sur une sélection (gras, italique, souligné, barré, code en ligne, surligneur, exposant, indice) | 8 / 8 | 8 / 8 |
| Une marque au curseur réduit, puis la frappe | **échec** : `plain bold`, le raccourci ne fait rien sans sélection | réussi |
| Une marque retirée en répétant son raccourci, la sélection finissant aux bords du texte | **échec** : `one <b>two</b><b></b> three`, une deuxième marque vide | réussi |
| Entrée scinde au curseur ; Entrée puis la frappe ; Retour arrière au début fusionne ; Maj+Entrée écrit un saut de ligne | 4 / 4 | 4 / 4 |
| Annulation de la dernière rafale de frappe ; rétablissement ; annulation d'un bloc scindé | 3 / 3 | 3 / 3 |
| IME : japonais composé puis validé ; composition dans un texte en gras ; une composition de `＃` pleine chasse a laissé un paragraphe | 3 / 3 | 3 / 3 |
| Touche morte : `´` puis `e` donne `é` | réussi | réussi |
| Émojis et caractères hors du plan multilingue de base | réussi | réussi |
| Collage depuis Word, Google Docs et une page Web, par le contrôleur | 3 / 3 | 3 / 3 |
| **Total** | **24 / 26** | **26 / 26** |

Les deux échecs sont dans `lib/caret.js` et n'ont pas besoin de ProseMirror pour être corrigés : une marque mémorisée
au curseur réduit, et la recherche de la marque autour d'une sélection dont les extrémités sont juste en dehors de son
élément. Les deux sont corrigés dans 0.1.1-alpha : les champs propres de l'éditeur réussissent les 26 scénarios, et
les deux ajoutés sur une marque au curseur.

### Collage

Dans les deux moteurs, un collage passe par le `lib/paste.js` du contrôleur et par l'assainisseur, de sorte que les
blocs sont les mêmes. Les jeux de données ont aussi été lus par l'analyseur de ProseMirror avec le schéma de l'essai,
comme la vue les lirait si elle prenait le collage en charge : 3 jeux sur 3 réussissent dans les deux, avec toutes les
marques conservées (Word 7 / 7, Google Docs 7 / 7, page Web 5 / 5), le texte complet, et aucune adresse, aucun
gestionnaire, script, image ni style qui fuit. Une différence : ProseMirror imbrique les marques dans l'ordre du
schéma, si bien que le lien souligné de Google Docs s'écrit `<u><a>…</a></u>` là où l'assainisseur conserve
`<a><u>…</u></a>` ; le serveur accepte les deux.

### Accessibilité

Le nœud que Chrome expose pour un champ est le même dans les deux moteurs : rôle `generic`, `editable: richtext`,
focalisable, sans nom accessible. ProseMirror ajoute `translate="no"` au champ, ce qui empêche les traducteurs de page
de réécrire le texte en cours de modification. Aucun des moteurs ne donnait à un champ un rôle `textbox` ni un nom : cette
lacune était celle de l'éditeur, non du moteur. Depuis 0.1.1-alpha, Chrome expose chaque champ modifiable avec le rôle
`textbox`, `multiline`, nommé d'après son bloc (*Texte* pour un paragraphe).

### Temps

| Document | Champs propres | ProseMirror |
|---|---:|---:|
| 50 paragraphes, du démarrage du contrôleur aux champs modifiables (médiane de 5, modules récupérés, cache désactivé) | 6 ms | 37 ms |
| 500 paragraphes, idem | 15 ms | 88 ms |
| 50 paragraphes, dessinés de nouveau (médiane de 9) | 0,9 ms | 3,9 ms |
| 500 paragraphes, dessinés de nouveau (médiane de 9) | 7,1 ms | 34,3 ms |

Le contrôleur redessine tout le document après chaque changement de structure (un bloc ajouté, scindé, fusionné,
déplacé, une annulation). Avec ProseMirror, chaque dessin détruit et crée chaque vue : 34 ms pour 500 paragraphes,
c'est deux images. Un dessin qui ne remplacerait que les blocs modifiés ferait disparaître ce coût pour les deux
moteurs.

## Ce qui n'est pas mesuré

| Quoi | Pourquoi |
|---|---|
| Les claviers Android (Gboard, Samsung), qui envoient des événements de composition et `input` sans `keydown` | Aucun appareil ni émulateur Android sur la machine de mesure ; ProseMirror documente et gère ces cas, les champs propres de l'éditeur n'y ont jamais été testés |
| La fenêtre de candidats d'une vraie méthode de saisie, et les touches mortes tapées sur un clavier physique | Les événements de composition de Chrome ont été produits par le protocole DevTools, non par les méthodes de saisie de macOS |
| VoiceOver, NVDA, JAWS | Aucun lecteur d'écran ne se pilote sans interface ; seul l'arbre d'accessibilité a été comparé |
| Safari et Firefox | Les mesures n'ont été faites que dans Chrome |
| Les presse-papiers écrits par les applications elles-mêmes | Les jeux de données reproduisent les formes qu'écrivent Word, Google Docs et les pages Web ; un collage depuis le presse-papiers du système n'a pas été fait |
| Les tests Panther du bundle | Le bundle n'en a pas : son contrôleur a été vérifié à la main dans un navigateur. Les suites Panther des autres bundles n'ont pas pu démarrer sur la machine de mesure, dont le chromedriver (156) ne pilote pas son Chrome (158) |
| La mémoire | Non lue ; 500 vues ont été créées sans erreur |

## Rouvrir l'essai

Les modules de l'essai, `lib/prosemirror.js` et `lib/rich-schema.js` avec ses tests Node, ainsi que l'option
`richEngine` du contrôleur, sont dans l'historique du paquet, dans le commit qui ajoute cette page. Rouvrir l'essai
consiste à les restaurer, à énumérer les quatre modules `prosemirror-*` dans `assets/package.json` et dans
`tests/Browser/package.json`, et à remettre `prosemirror` parmi les moteurs de `measure.mjs` et de sa page,
`router.php`. Le format du document ne change pas : les deux moteurs écrivent le même UJO.
