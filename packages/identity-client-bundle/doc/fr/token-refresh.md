<!-- phil-dev source-sha256=356a2b9d872c5f4a5788953bca38aac2751262c8da6cab959668e2d545916aff -->
# Renouvellement des jetons

`refresh.enabled` renouvelle le jeton d'accès avant son expiration, pour une application qui appelle une API au nom de
l'utilisateur. Demandez au fournisseur un jeton de renouvellement, d'habitude avec la portée `offline_access`.

## Le problème

Un fournisseur qui remplace les jetons de renouvellement considère qu'un jeton présenté deux fois a été volé, et révoque
la famille. Deux requêtes de la même session, envoyées ensemble à l'expiration du jeton d'accès, feraient exactement
cela. Un gestionnaire de session qui verrouille la session l'empêche, mais tous les gestionnaires ne verrouillent pas, et
une requête qui a lu la session avant le remplacement détient encore l'ancien jeton.

## Ce que fait le bundle

L'écouteur remplace l'écouteur natif sur le pare-feu. Quand une requête trouve son jeton d'accès à moins de
`refresh.leeway` secondes de son expiration :

1. Elle prend un verrou nommé d'après son jeton de renouvellement, en attendant jusqu'à `refresh.wait_timeout` secondes.
2. Si une autre requête a déjà utilisé ce jeton, elle applique le résultat publié et n'appelle rien.
3. Sinon, elle renouvelle et publie le résultat avant de libérer le verrou.

| Résultat | Effet sur chaque requête qui détient le jeton |
|---|---|
| Remplacé | Les nouveaux jetons sont appliqués à la session |
| Refusé par le fournisseur (`invalid_grant`) | Toute la session locale est invalidée, et `RefreshTokenRejected` le signale |
| Fournisseur injoignable | Les jetons restent, et la prochaine tentative vient après 5 secondes |

Une requête dont l'attente expire garde ses jetons et réessaie à sa requête suivante.

Les résultats vivent dans une réserve de cache pendant `refresh.outcome_ttl` secondes, chiffrés avec une clé dérivée du
jeton de renouvellement auquel ils répondent. La réserve seule ne révèle rien, et il n'y a aucune clé à gérer.

## Réglages

Gardez `lock_ttl` au-dessus du délai d'expiration du client HTTP, pour qu'un verrou ne puisse pas expirer pendant un
appel au fournisseur. Gardez `outcome_ttl` au-dessus de la plus longue requête que sert l'application : une requête
commencée avant le remplacement, qui écrit sa session après, laisse l'ancien jeton derrière elle, et la requête suivante
doit encore trouver le résultat.
