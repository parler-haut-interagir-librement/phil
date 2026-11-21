<!-- phil-dev source-sha256=4e9885e77297126701afd6166b7a050aa7182a3de81db942107a0b10f57cf9c9 -->
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
`400` et ne met fin à rien. Une revendication que la réserve n'a pas gardée — son `save()` répond `false` — est refusée
de la même façon, de sorte que le même jeton ne puisse pas être accepté deux fois ; le fournisseur le livre de nouveau
plus tard. Une revendication dont le verrou a expiré pendant la lecture de la réserve est refusée aussi
([Durée de vie des verrous](token-refresh.md#durée-de-vie-des-verrous)).

La requête vient du fournisseur : elle ne peut donc pas toucher la session du navigateur. Le `sid` terminé est
enregistré, et l'écouteur natif désauthentifie la requête suivante de cette session. `EndedSessionListener` invalide
alors toute la session locale et distribue `LocalSessionEnded`.

Un fournisseur qui renvoie une livraison dont il a perdu la réponse reçoit un `400` à la deuxième tentative, la session
étant déjà terminée.

## Le plafond de session

Un jeton de déconnexion peut se perdre. `session.max_lifetime` borne la session quoi qu'il arrive au canal arrière :
12 heures par défaut, à compter du moment où la session a commencé chez le fournisseur.

Ce moment est la revendication `session.start_claim` du jeton d'identité, `session_start` par défaut, sinon
`auth_time`, sinon la connexion elle-même. Une date dans le futur, ou une valeur qui n'est pas un nombre, est lue comme
la connexion, et une élévation dans la même session du fournisseur ne le repousse jamais.

```yaml
phil_identity_client:
    session:
        max_lifetime: 43200          # 0 or null for none
        start_claim: session_start
```

## Le plancher de démarrage à froid

La réserve de rejeu et les sessions terminées vivent dans le pool `back_channel_logout.cache`. Un pool vidé, évincé ou
remplacé les oublie : un jeton de déconnexion déjà accepté pourrait l'être de nouveau, et une session que le fournisseur
a terminée reviendrait. `back_channel_logout.cold_start_floor` ferme cette fenêtre :

```yaml
phil_identity_client:
    back_channel_logout:
        cache: cache.state           # shared by every server, and never evicting
        cold_start_floor:
            enabled: true
            not_before: ~            # seconds since the Unix epoch
```

`CacheReplayFloor` garde dans ce pool une entrée sans expiration, sa sentinelle, qui enregistre le plancher. Quand
l'entrée manque, le pool a été vidé : le plancher devient cet instant, et l'entrée est écrite de nouveau. Alors :

- un jeton de déconnexion dont le `iat` précède le plancher est refusé avec un `400`, avant la recherche de son `jti` ;
- une session locale ouverte avant le plancher prend fin à sa requête suivante, et `LocalSessionEnded` le signale avec
  `LocalSessionEndReason::ReplayFloor`. L'utilisateur se reconnecte.

Chaque connexion lit le plancher avant d'être datée, de sorte qu'une session commencée sur un pool vidé ne soit jamais
refusée par le plancher que ce pool enregistre ensuite.

`not_before` relève le plancher à un instant à lui, comme un changement de clés chez le fournisseur, ou le plancher de
la réserve de rejeu du fournisseur lui-même. Il est en vigueur une fois atteint, de sorte qu'on puisse le fixer
d'avance. Le plancher est désactivé par défaut, et il exige la déconnexion par canal arrière.

Le pool doit tout de même garder ses entrées pendant `replay_ttl` et ne jamais les évincer : le plancher distingue une
réserve qui a été vidée d'une qui ne l'a pas été, pas une qui a perdu quelques entrées.

## Déconnexion locale

La déconnexion locale est le `logout` du pare-feu. Avec `end_session.enabled`, l'utilisateur est aussi envoyé au point
de terminaison de fin de session du fournisseur. Utilisez un `POST` avec un jeton CSRF, comme pour toute déconnexion.
