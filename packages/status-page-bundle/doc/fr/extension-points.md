<!-- phil-dev source-sha256=41c800ee98dfbe7a9e07548712c62df375f6ca34a8c1346198f3c7c6a36c9d70 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Tirer les états des composants d'ailleurs | Implémentez `Application\Contract\ComponentStatuses` et nommez le service dans `source_service`. |
| Conserver ailleurs les incidents et les maintenances — une table, une interface d'administration | Implémentez `Application\Contract\StatusRecords` et nommez le service dans `records.service`. |
| Publier l'instantané vers un service d'hébergement | Implémentez `Application\Contract\SnapshotPublisher` et déclarez une cible `{ type: service, service: <id> }`. |
| Dessiner autrement la page | Remplacez `Application\Contract\PageRenderer`, ou remplacez les gabarits `@PhilStatusPage/page.html.twig` et ses partiels dans `templates/bundles/PhilStatusPageBundle/`. |
| Mettre la page dans la mise en page du site | Réglez `layout` sur un gabarit avec les blocs `title` et `content`. |
| Publier depuis l'application | Appelez `Application\SnapshotPush::push()` (public), ou envoyez `Application\Message\PushStatusSnapshot`. |
| Construire la page pour un autre usage | Appelez `Application\StatusPageBuilder::build()` (public), puis `JsonDocument` ou `AtomFeed`. |
| Ajouter une langue | Ajoutez `translations/phil_status_page.<locale>.php` avec les clés du fichier anglais. |

Un `ComponentStatuses` retourne l'état des composants qu'il connaît, par identifiant : ceux qu'il omet sont
opérationnels. Un `StatusRecords` retourne tous les enregistrements qu'il détient ; la page les trie et les filtre selon
l'horloge. Un `SnapshotPublisher` écrit chaque fichier de l'instantané ou lance une exception : `SnapshotPush` signale et
journalise l'échec, et poursuit avec les autres cibles. Aucun d'eux ne garde d'état d'une requête à l'autre.
