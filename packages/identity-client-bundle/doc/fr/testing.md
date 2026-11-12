<!-- phil-dev source-sha256=42b833d7a9f244d963fbd16b0dd187fe1396eba54ad32a0227134f5052d5a146 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `VerifiedIdentity`, `AssuranceLevels` | Revendications gardées, adresses non vérifiées, l'échelle |
| `DeclaredOriginsReturnToPolicy` | Cibles acceptées et chaque forme refusée, origines qu'une application peut déclarer |
| `CacheReplayStore`, `EncryptedRefreshOutcomes` | Revendications, durées de vie, rien de lisible dans la réserve, entrées altérées |
| `SerializedTokenRefreshListener` | La marge, une requête périmée, une attente, un délai dépassé, une révocation, un fournisseur injoignable |
| `ReplayGuardedBackChannelLogout` | Jetons acceptés, réutilisation, échecs de signature et de revendication |
| Les écouteurs et le voter | Chaque branche, le début de session, une élévation refusée |
| `ClientConfiguration`, `FirewallComposer` | Valeurs par défaut fixées, configurations refusées, le bloc ajouté en tête |

Une suite fonctionnelle démarre un noyau avec le bundle de sécurité et un faux fournisseur local derrière un client HTTP
*mock* : découverte, clés, et un point de terminaison de jetons qui remplace les jetons de renouvellement et révoque la
famille en cas de réutilisation. Elle couvre la connexion, un `state` ou un `nonce` falsifié, un jeton non signé, un
algorithme changé, une clé publique utilisée comme secret, une clé intégrée, un `jti` rejoué, un `return_to` externe,
le plafond, l'authentification renforcée et le renouvellement.

```sh
vendor/bin/phpunit packages/identity-client-bundle/tests
```

`tests/Benchmark` mesure les chemins critiques avec PHPBench, `tests/Worker/worker.php` une page en mode *worker* de
FrankenPHP, et `tests/Fuzz/return-to.php` soumet à la politique `return_to` des entrées que personne n'a écrites.
