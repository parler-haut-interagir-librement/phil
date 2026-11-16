<!-- phil-dev source-sha256=c8b9b95c74fca58b735b85fd0bc0b9499eb33cafa54b3bbde0bc7e670c92688c -->
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
3. Sinon, elle publie un résultat en attente, renouvelle, et publie le résultat avant de libérer le verrou. Quand la
   réserve ne garde pas le résultat en attente — son `save()` répond `false` —, le fournisseur n'est pas appelé et les
   jetons restent tels quels.

| Résultat | Effet sur chaque requête qui détient le jeton |
|---|---|
| Remplacé | Les nouveaux jetons sont appliqués à la session |
| Refusé par le fournisseur (`invalid_grant`) | Toute la session locale est invalidée, et `RefreshTokenRejected` le signale |
| Fournisseur injoignable | Les jetons restent, et la prochaine tentative vient après 5 secondes |
| En attente : la réserve a perdu le résultat, ou la requête qui renouvelait est morte | Les jetons restent, et l'ancien jeton de renouvellement n'est jamais présenté de nouveau ; le résultat en attente dure `refresh.outcome_ttl` secondes |

Une requête dont l'attente expire garde ses jetons et réessaie à sa requête suivante.

`TokensRefreshed` et `RefreshTokenRejected` sont distribués en dernier, une fois le résultat publié, appliqué à la
session et le verrou libéré. Un abonné qui lève une exception ne peut pas faire perdre des jetons que le fournisseur a
déjà remplacés : son exception parvient à l'appelant, comme pour tout autre événement du bundle, mais chaque requête qui
détient l'ancien jeton trouve toujours le résultat.

Les résultats vivent dans une réserve de cache pendant `refresh.outcome_ttl` secondes, chiffrés avec une clé dérivée du
jeton de renouvellement auquel ils répondent. La réserve seule ne révèle rien, et il n'y a aucune clé à gérer.

## Réglages

Gardez `lock_ttl` au-dessus du délai d'expiration du client HTTP, pour qu'un verrou ne puisse pas expirer pendant un
appel au fournisseur. Gardez `outcome_ttl` au-dessus de la plus longue requête que sert l'application : une requête
commencée avant le remplacement, qui écrit sa session après, laisse l'ancien jeton derrière elle, et la requête suivante
doit encore trouver le résultat.
