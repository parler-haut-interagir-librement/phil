<!-- phil-dev source-sha256=bb7374ddf9eaf00dec5b44662e87416f78695ab480754da592260a161fa014e6 -->
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
| Combien une personne est célébrée | `data-celebrations="all"`, `discreet` ou `none` sur la racine, ou sur n'importe quel ancêtre d'une `Celebration` ; avec phil/settings-bundle, un choix de la section `display` dont l'`html_attribute` est `celebrations` : voir le README de la recette |
| Ce que dit une célébration | `message`, ou `messages` pour des mots à tour de rôle : ceux de `celebration_messages()`, ou ceux de l'application, traduits par son traducteur |
| Une célébration jouée par un bundle | `celebrate()` dans une réponse Turbo Stream, sur un sujet Mercure ou dans la page, ou `Phil\UxKit\Application\CelebrationStream` injecté pour produire le même flux en PHP |
| Les fêtes du décor de saison | `phil_ux_kit.seasonal.holidays` : chacune une règle de phil/almanac, ses jours ou son `until`, son décor et si elle est sobre ; `phil_ux_kit.seasonal.enabled` l'active : voir le README de `SeasonalDecor` |
| Les couleurs d'un décor de saison | `data-seasonal-decor-colors-value`, des propriétés personnalisées séparées par des espaces, à la place des couleurs du décor |
| Un jeu du décor de saison | `eggs`, et les éléments de la page marqués `data-egg-nest` où ils se cachent ; `code`, les touches du code secret, vide pour le désactiver |
| Ce qui suit un podium ou une carte à gratter | Les événements `podium:risen` et `scratch-card:revealed`, et `effect`, qui joue la `Celebration` de la page |
| La suite navigateur d'un autre kit | Étendez `Phil\UxKit\Test\BrowserTestCase` et donnez-lui une `RecipesPage` : voir [Tests](testing.md#pour-un-autre-kit) |
