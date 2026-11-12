<!-- phil-dev source-sha256=d2f7d7963cafd6e5c9234563eace36084554b5d8748249fbdad409091850af19 -->
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
| Les props d'une image pour un objet de l'application | Un service qui implémente `Phil\UxKit\Application\Contract\PicturePropsProvider`, balisé `phil_ux_kit.picture_props` par l'autoconfiguration, que `picture_props()` interroge |
| Le graphe de la page | Un `NodeCollector` de `phil/structured-data`, que rejoint l'`ImageObject` d'une image structurée au lieu d'un script à elle |
| La suite navigateur d'un autre kit | Étendez `Phil\UxKit\Test\BrowserTestCase` et donnez-lui une `RecipesPage` : voir [Tests](testing.md#pour-un-autre-kit) |
