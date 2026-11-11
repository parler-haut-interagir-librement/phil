<!-- phil-dev source-sha256=3e1d49d4ae0334b9d89ebd6a9cdb9acdd233a0d58e4437e324331569ca8ddc77 -->
# Points d'extension

| Point | Comment |
|---|---|
| Le balisage d'une recette | Une fois installé, le composant est le code de l'application : changez-le dans `templates/components/` |
| La racine d'un composant | Tout attribut donné au composant va sur sa racine ; une `class` s'ajoute à la sienne |
| L'apparence | Les jetons et les couches de phil-ux, surchargés dans la feuille de style de l'application |
| Le rythme du titre | Les props `pause`, `duration` et `loop` ; le texte du bouton, `pauseLabel` et `playLabel` |
| Le choix de l'illustration | La prop `seed` : un jour de l'année, un identifiant, un nombre fixe dans les tests |
| Le contenu de la page « bientôt » | Le bloc `content` |
| Plus de champs dans un formulaire de connexion ou d'inscription | Le bloc `content` de `LoginForm` et de `SignupForm` ; leurs noms de champs et leurs identifiants en props |
| Les couleurs des séries d'un graphique | Les tonalités de phil-ux, `isPrimary` à `isNeutral`, dont la feuille de style de l'application surcharge les jetons `--phil-tone-*` ; les tirets sur `.isChartLine.isInfo` et les autres |
| Les noms des copies | La configuration `ux_toolkit` de l'application : dossiers et sous-dossier |
| Les textes d'une recette | Ses props et ses blocs : `closeLabel`, `label`, `text`, `pauseLabel`, `placeholder`… |
| Une recette du kit Common | Installée depuis le kit Common, avec `--kit=common` |
| Ce que le navigateur fait nativement | `dialog` et `sheet` portent `closedby="any"`, `AlertDialog` `closedby="closerequest"`, tous trois `command` et `commandfor` ; le contrôleur `dialog` ne fait que ce que le navigateur ne fait pas, comme le détectent `hasInvokers()` et `hasLightDismiss()` |
| La suite navigateur d'un autre kit | Étendez `Phil\UxKit\Test\BrowserTestCase` et donnez-lui une `RecipesPage` : voir [Tests](testing.md#pour-un-autre-kit) |
