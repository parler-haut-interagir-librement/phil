<!-- phil-dev source-sha256=6943d7e32ed528b4ae511687dd4b03ca0f0a923209e794d08403d1b5159a623d -->
# Points d'extension

La bibliothèque en est à ses versions `0.x` : le port, les objets-valeurs et les exceptions peuvent changer dans une
version mineure, ce que dit le journal des modifications. Ils sont destinés à être stables à partir de `1.0`.

## Un autre fournisseur

Un adaptateur implémente `Contract\VirtualClassroom` :

- `capabilities()` dit ce qu'il sait faire ; chaque appel qu'il ne sait pas faire lève `Exception\Unsupported` ;
- `open()` est idempotent pour une salle en cours, et donne la clé de la séance quand le serveur en a une ;
- `joinUrl()` n'appelle aucun serveur, et signe ce qu'il faut au serveur pour faire confiance au rôle ;
- `receive()` vérifie la signature propre au fournisseur avant toute chose, et donne les événements dans l'ordre où ils
  se sont produits ;
- toute défaillance est une `VirtualClassroomException`, son message exempt de participants et d'adresses d'appel ;
- l'adaptateur ne garde aucun état entre les appels, pour qu'une seule instance serve chaque requête d'un *worker* ;
- un appel à un serveur passe par les bornes de `Http\HttpBudget`.

Un adaptateur Zoom ou Microsoft Teams, les prochains prévus, s'écrit ainsi : un client OAuth du fournisseur, ses
réunions comme salles, ses webhooks comme notifications.

## Événements

`Contract\RoomEvent` a `room` et `at` ; les quatre événements de la bibliothèque l'implémentent. Un adaptateur qui lit
d'autres événements de son serveur les rapporte à ceux-ci, ou les écarte.
