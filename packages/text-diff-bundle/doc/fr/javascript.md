<!-- phil-dev source-sha256=93f103ea27234d27c7232621c2e095a02f6efcdfbadcc3a49ece1efcc7efda91 -->
# JavaScript

## Le contrôleur

`assets/src/comparison_controller.js` est un contrôleur Stimulus, enregistré sous le nom
`phil--text-diff-bundle--comparison`. Il se place autour d'une différence écrite par l'un ou l'autre filtre :

```twig
<section data-controller="phil--text-diff-bundle--comparison"
         data-phil--text-diff-bundle--comparison-counter-text-value="Change {current} of {total}"
         data-phil--text-diff-bundle--comparison-total-text-value="{total} changes"
         data-phil--text-diff-bundle--comparison-none-text-value="No changes">
    <div role="group" aria-label="View">
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="inline" data-phil--text-diff-bundle--comparison-target="mode">Both</button>
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="old" data-phil--text-diff-bundle--comparison-target="mode">Before</button>
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="new" data-phil--text-diff-bundle--comparison-target="mode">After</button>
    </div>
    <button type="button" data-action="phil--text-diff-bundle--comparison#previous">Previous change</button>
    <button type="button" data-action="phil--text-diff-bundle--comparison#next">Next change</button>
    <p role="status" data-phil--text-diff-bundle--comparison-target="counter"></p>
    <div data-phil--text-diff-bundle--comparison-target="output">{{ old|text_diff(new) }}</div>
</section>
```

| Cible | Rôle |
|---|---|
| `output` | L'endroit où les marques sont cherchées ; l'élément du contrôleur en son absence |
| `counter` | Une région dynamique qui indique combien de changements la vue affiche, ou lequel est le changement courant |
| `mode` | Un bouton doté d'un paramètre `mode` ; `aria-pressed` indique si sa vue est affichée |

| Valeur | Valeur par défaut | Sens |
|---|---|---|
| `mode` | `inline` | La vue : `inline`, les deux textes marqués ; `old`, l'ancien texte seul ; `new`, le nouveau texte seul |
| `counter-text` | `{current} of {total}` | Le compteur quand un changement est courant |
| `total-text` | `{total} change(s)` | Le compteur quand aucun ne l'est |
| `none-text` | `No changes` | Le compteur quand la vue n'affiche aucun changement |

| Action | Ce qu'elle fait |
|---|---|
| `show` | Affiche la vue de son paramètre `mode` ; une vue inconnue affiche la vue en ligne |
| `next`, `previous` | Passe au changement suivant ou précédent qu'affiche la vue, en revenant au début après la fin et inversement |

## Ce qu'il fait

La vue est écrite sur l'élément sous la forme `data-text-diff-mode`, que lit la feuille de style pour cacher les
insertions de l'ancienne vue et les suppressions de la nouvelle. Changer de vue efface le changement courant.

Un changement est une marque qu'affiche la vue : chaque `<ins>` et `<del>` dans la vue en ligne, une suppression suivie
de son insertion — rien d'autre que des espaces entre les deux — comptant pour un seul remplacement ; les suppressions
seules dans l'ancienne vue ; les insertions seules dans la nouvelle vue. Le changement courant reçoit la classe
`text-diff-current`, sa première marque reçoit `aria-current="true"` et le focus, et il défile jusqu'au milieu de
l'écran — immédiatement quand le lecteur préfère réduire les animations, en douceur sinon.

Les libellés viennent des valeurs : une page les écrit donc dans sa propre langue. Les raccourcis clavier sont à
ajouter par la page, sous forme d'actions Stimulus sur l'élément :

```html
data-action="keydown.n->phil--text-diff-bundle--comparison#next keydown.p->phil--text-diff-bundle--comparison#previous"
```

## Ses tests

`tests/js` s'exécute avec l'exécuteur de tests de Node, Stimulus étant remplacé par un *stub* :

```sh
node --import ./tests/js/register.mjs --test tests/js
```
