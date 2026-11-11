<!-- phil-dev source-sha256=bb2be46c705a9006912ca940d3cc0e9413858fa94f85c15f274a3bc214fa693b -->
# L'éditeur

L'éditeur présente un document comme une colonne de blocs. Au survol ou au focus, chaque bloc montre une marge avec deux
boutons : **+** ajoute un paragraphe dessous et y ouvre le menu `/`, **⋮⋮** est la poignée qui déplace le bloc et ouvre
son menu.

## Ajouter et transformer des blocs

| Moyen | Effet |
|---|---|
| `/` au début d'un bloc ou après une espace | Ouvre le menu `/` ; les lettres tapées ensuite filtrent les entrées, dans les libellés et dans des mots comme `h1`, `todo`, `callout`, `divider`, en anglais et en français. ↑ ↓ choisissent, Entrée ou Tab insèrent, Échap ferme. Dans un paragraphe vide, le bloc choisi prend sa place ; sinon, il vient après |
| Marques tapées au début d'un paragraphe | `#` à `######` et une espace pour un titre, `-`, `*` ou `+` pour une liste à puces, `1.` pour une liste numérotée, `[]` ou `[x]` pour une liste de tâches, `>` pour une citation, `>>` pour un dépliant, `!` pour un encadré, ` ``` ` puis un langage et une espace pour du code, `---` pour un séparateur |
| Le menu de bloc, depuis la poignée | **Transformer en** un autre type qui contient du texte ; les actions du type (niveau de titre, style de liste, type d'encadré, alignement de la citation, bordure, fond et largeur de l'image, ligne d'en-tête, lignes et colonnes du tableau, nombre de colonnes) ; **Dupliquer**, **Monter**, **Descendre**, **Supprimer** |
| Raccourcis | Voir plus bas |

Une liste transformée en paragraphes donne un paragraphe par élément. Un dépliant transformé en un autre type laisse ses
blocs après lui. Du texte transformé en liste donne un élément par ligne.

## Déplacer des blocs

Glissez un bloc par sa poignée, avant ou après un autre bloc, dans un dépliant, dans une colonne, ou sur le bouton d'un
dépliant ou d'une colonne vide. Un bloc ne se déplace jamais dans lui-même. Au clavier, ⌘⇧↑ et ⌘⇧↓ (Ctrl+Maj sur les
autres systèmes) déplacent le bloc dans sa liste.

**Échap** sélectionne le bloc où se trouve le curseur. Un bloc sélectionné est encadré. ↑ ↓ sélectionnent le bloc
d'avant ou d'après, ⌘⇧↑ ↓ le déplacent, ⌘D le duplique, Retour arrière ou Suppr l'enlève, Entrée revient à son texte,
Échap le relâche.

## Écrire

| Touche | Dans un texte | Dans un élément de liste | Dans une cellule de tableau |
|---|---|---|---|
| Entrée | Scinde le bloc : ce qui suit le curseur devient un nouveau paragraphe ; dans un dépliant ouvert, son premier bloc | Scinde l'élément ; dans un élément vide, quitte la liste ou un niveau imbriqué | Passe à la cellule du dessous et ajoute une ligne à la fin |
| Maj+Entrée | Un saut de ligne, quand le jeu d'outils a `lineBreak` | de même | de même |
| Retour arrière au début | Joint le paragraphe au texte qui précède, enlève un paragraphe vide, transforme un titre, une citation, un encadré ou un dépliant en paragraphe | Joint l'élément au précédent, sort un élément imbriqué, transforme un élément seul en paragraphe | — |
| Suppr à la fin | Ramène le paragraphe suivant | — | — |
| Tab, Maj+Tab | — | Imbrique l'élément sous le précédent, ou l'en sort | — |
| ↑ ↓ sur la première ou la dernière ligne, ← → aux extrémités | Passe au texte d'avant ou d'après | de même | de même |

Dans un bloc de code, Entrée crée une nouvelle ligne, ⌘Entrée ajoute un paragraphe après le bloc, et Retour arrière dans
un bloc vide l'enlève.

## Mise en forme

Sélectionner du texte affiche la barre d'outils des outils en ligne du champ : gras, italique, souligné, barré, code en
ligne, lien, surlignage, couleur, exposant et indice. Un lien demande son adresse, qui doit être `http`, `https`,
`mailto`, `tel` ou relative ; le même bouton la modifie ou l'enlève. Les couleurs sont les jetons de la configuration,
et **Couleur par défaut** en enlève une.

| Raccourci | Action | Raccourci | Action |
|---|---|---|---|
| ⌘B | Gras | ⌘. | Exposant |
| ⌘I | Italique | ⌘, | Indice |
| ⌘U | Souligné | ⌘Z, ⌘⇧Z ou ⌘Y | Annuler, rétablir |
| ⌘⇧X | Barré | ⌘D | Dupliquer le bloc |
| ⌘E | Code en ligne | ⌘⌥0 | Transformer en paragraphe |
| ⌘K | Lien | ⌘⌥1, 2, 3 | Transformer en titre |
| ⌘⇧H | Surlignage | ⌘⌥4, 5, 6, 7, 8 | Liste de tâches, à puces, numérotée, dépliant, code |

Sur les systèmes autres que ceux d'Apple, Ctrl remplace ⌘ et Alt remplace ⌥. Annuler et rétablir appartiennent à
l'éditeur : ils couvrent la frappe, par pauses, et chaque changement de structure.

## Mentions et liens de page

Quand une recherche de mentions est configurée, `@` et les lettres qui suivent ouvrent un menu de suggestions venues de
l'application. La suggestion choisie devient `<a data-mention="user:42" href="…">@Ada</a>`. Avec une recherche de liens,
`[[` fait de même pour les pages : `<a data-ref="page:7" href="…">Page title</a>`. Voir
[integrations.md](integrations.md).

## Collage

| Contenu collé | Résultat |
|---|---|
| Du texte sur une ligne, ou du HTML de texte en ligne | Inséré au curseur, nettoyé |
| Du texte sur plusieurs lignes | Un paragraphe par partie séparée d'une ligne vide, les sauts de ligne simples étant conservés |
| Le HTML d'une page, de Word ou de Google Docs | Des blocs : paragraphes, titres, listes à puces, numérotées et de tâches avec leur imbrication — y compris les paragraphes de liste de Word, sans leurs puces —, citations, code, tableaux avec ligne d'en-tête, séparateurs et images dont l'adresse est `http`, `https` ou relative |
| Une adresse, sur du texte sélectionné | Un lien sur ce texte |
| Un fichier image | Un bloc image, téléversé, quand les téléversements sont configurés |

Le texte en ligne ne garde que les marques du jeu d'outils. Les styles deviennent des marques — graisses, italiques,
soulignés et barrés, texte surélevé ou abaissé — et l'enveloppe de graisse normale de Google Docs n'est pas prise pour
du gras. Les scripts, les styles, les commentaires et les parties conditionnelles de Word sont retirés. Un type absent
du jeu d'outils se replie sur des paragraphes. Dans un élément de liste, une cellule de tableau ou une légende, tout ce
qui est collé est inséré comme texte en ligne.

## Blocs imbriqués

Un dépliant contient des blocs sous son titre, affichés ou masqués par sa flèche ; son état ouvert ou fermé est
enregistré avec lui. Les colonnes contiennent chacune une liste de blocs, côte à côte, l'une sous l'autre sur les écrans
étroits. Les deux acceptent n'importe quel bloc, y compris imbriqué, jusqu'à la profondeur que permet `phil/ujo-bundle`.

## Images

Un bloc image vide offre **Choisir une image** — ou le dépôt d'un fichier — quand les téléversements sont configurés, et
un champ d'adresse dans tous les cas. Une fois l'image en place, sa légende se modifie sur place, et le menu de bloc
règle sa bordure, son fond et sa largeur, ou la remplace.

## Mode lecture seule

Avec `read_only` ou un champ désactivé, l'éditeur affiche le document sans marges, sans menus et sans texte modifiable.
Les dépliants s'ouvrent et se ferment encore, sans rien enregistrer. Un document vide affiche *Rien n’est encore écrit.*

## Accessibilité

L'éditeur est un groupe nommé par le libellé du champ. Les menus sont des listes de choix ou des menus pilotés au
clavier, dont l'entrée active est annoncée. La barre d'outils est une barre de boutons nommés et à état pressé. Les
boutons de la marge ont un nom. Les déplacements, les suppressions, les duplications et les transformations sont
annoncés dans une zone dynamique polie. Les animations s'arrêtent lorsque le mouvement réduit est demandé.

## Enregistrement

L'éditeur écrit le document dans le champ caché un instant après chaque changement, et aussitôt à la soumission du
formulaire. Les blocs vides sont laissés de côté : un paragraphe vide, une image sans adresse, un tableau de cellules
vides. Un document sans aucun bloc restant écrit le texte vide. Chaque écriture déclenche `change` sur le champ et
`phil--block-editor-bundle--editor:change` sur l'éditeur ([javascript.md](javascript.md)).
