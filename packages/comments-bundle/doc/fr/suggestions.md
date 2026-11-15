<!-- phil-dev source-sha256=28bb990c8fe82a99088a3fc30b779ccdd69c9016e136b5614518c999b51c28f3 -->
# Suggestions

Un fil peut proposer un remplacement pour son passage : un texte, ou un texte vide pour proposer de le retirer. La
suggestion attend (`pending`) qu'une personne autorisée par la politique à décider l'accepte ou la rejette ; l'une ou
l'autre décision résout le fil.

Une seule décision est gardée. Le stockage ne la garde que tant que la suggestion stockée est encore en attente —
Doctrine par un `UPDATE … WHERE suggestion_state = 'pending'` —, si bien que de deux décisions prises en même temps, la
seconde lève `SuggestionDecided`, que les points de terminaison traduisent en `409`, et ne change rien.

## Appliquée par l'application

`Comments::applySuggestion($thread, $actor, $document)` remplace le passage, là où le fil est ancré, par la suggestion
dans un document UJO. Il accepte la suggestion et retourne le document modifié, que l'application enregistre :

```php
$document = $comments->applySuggestion($threadId, $editor, $ujo->decode($article->body()));
$article->setBody($ujo->encode($document));
```

Le passage doit toujours être à sa place, les mêmes caractères aux mêmes positions — réancrez d'abord si le document a
changé —, sinon `InvalidComment` est levée et rien ne change. Quand une autre décision est arrivée avant,
`SuggestionDecided` est levée et le document modifié n'est pas retourné.

## Appliquée dans l'éditeur

Le contrôleur accepte une suggestion dans l'éditeur par blocs lui-même. Il trouve le passage là où il est maintenant,
le remplace dans le bloc et demande au serveur d'enregistrer l'acceptation. Il remet ensuite le bloc à l'éditeur par
son événement `phil-block-editor:apply` ; l'auteur enregistre le formulaire comme d'habitude. Le bouton *Accept*
n'est offert que lorsque l'éditeur est modifiable et que le passage est trouvé. Un bloc qu'un autre auteur détient
par `phil/edit-lock-bundle` n'est pas touché.

Quand une autre décision est arrivée avant, le serveur répond `409` avec l'erreur `decided` : le contrôleur n'applique
rien à l'éditeur, dit *This suggestion has already been decided.* dans l'état du panneau et recharge les fils, pour
que le lecteur voie la décision qui a été gardée. Un rejet qui arrive en second est traité de la même façon.

## Comment le passage est remplacé

Dans un champ de HTML en ligne, les caractères du passage sont retirés et le remplacement, échappé, est écrit là où
était le premier d'entre eux. Chaque balise reste où elle était, vide au besoin, pour que les marques autour et à
l'intérieur du passage restent équilibrées. Remplacer `two three` dans `one <b>two</b> three` par `2 3` donne
`one <b>2 3</b>` : le remplacement est écrit là où était `t`, dans le gras, et la balise fermante est gardée. Dans la
source d'un bloc de code, de diagramme ou de formule, les caractères sont remplacés tels quels. Le remplacement est
toujours du texte : une suggestion n'apporte jamais de balisage.
