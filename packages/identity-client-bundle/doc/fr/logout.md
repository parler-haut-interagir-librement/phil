<!-- phil-dev source-sha256=2cbb73d032f1c8dbfc79b13400d175babd00dbadc4047cf303839ffc9fb6ba84 -->
# Déconnexion

## Déconnexion par canal arrière

Quand une session se termine chez le fournisseur, celui-ci envoie un jeton de déconnexion à `back_channel_logout.path`.
Déclarez cette URL chez le fournisseur comme `backchannel_logout_uri` du client.

Le jeton est vérifié avec l'algorithme fixé contre les clés du fournisseur. Ses revendications sont ensuite vérifiées
avec le `OidcLogoutToken` natif : émetteur, audience, dates, la revendication `events`, un `sid`, et aucun `nonce`. Un
jeton qui nomme un utilisateur sans nommer de session est refusé, plutôt que de mettre fin à toutes les sessions de cet
utilisateur.

Chaque `jti` n'est ensuite accepté qu'une fois. `CacheReplayStore` le garde pendant `replay_ttl` secondes, ou jusqu'à ce
que le jeton ne puisse plus passer sa vérification de `exp` si ce moment est plus tardif. Un jeton répété reçoit un
`400` et ne met fin à rien.

La requête vient du fournisseur : elle ne peut donc pas toucher la session du navigateur. Le `sid` terminé est
enregistré, et l'écouteur natif désauthentifie la requête suivante de cette session. `EndedSessionListener` invalide
alors toute la session locale et distribue `LocalSessionEnded`.

Un fournisseur qui renvoie une livraison dont il a perdu la réponse reçoit un `400` à la deuxième tentative, la session
étant déjà terminée.

## Le plafond de session

Un jeton de déconnexion peut se perdre. `session.max_lifetime` borne la session quoi qu'il arrive au canal arrière :
12 heures par défaut, à compter du moment où la session a commencé chez le fournisseur.

Ce moment est la revendication `session.start_claim` du jeton d'identité, sinon `auth_time`, sinon la connexion
elle-même. Une date dans le futur est lue comme la connexion, et une élévation dans la même session du fournisseur ne le
repousse jamais.

## Déconnexion locale

La déconnexion locale est le `logout` du pare-feu. Avec `end_session.enabled`, l'utilisateur est aussi envoyé au point
de terminaison de fin de session du fournisseur. Utilisez un `POST` avec un jeton CSRF, comme pour toute déconnexion.
