<!-- phil-dev source-sha256=b482369cc5bb7747bbbb5b0636403a9d673c2ff998050478bcea772d777b6d69 -->
# Console

## `phil:outbox:relay`

Relaie l'outbox lot après lot.

| Option | Valeur par défaut | Effet |
|---|---|---|
| `--batch-size` | `relay.batch_size` | Le plus grand nombre d'enregistrements d'un lot, de 1 à 1 000 |
| `--limit` | 0 | Le plus grand nombre de lots ; aucune limite à 0 |
| `--stop-when-empty` | désactivée | Termine l'exécution au premier lot qui ne trouve rien |
| `--time-limit` | 0 | Les secondes après lesquelles l'exécution se termine ; aucune limite à 0 |
| `--sleep` | 1 | Les secondes d'attente après un lot qui n'a rien trouvé, de 0 à 3 600 |

Sans `--stop-when-empty`, `--limit` ni `--time-limit`, l'exécution se poursuit jusqu'à ce qu'on l'arrête. SIGINT et
SIGTERM la terminent après le lot en cours. Une exécution qui trouve le verrou détenu par une autre exécution s'efface
aussitôt et se termine avec succès. Avec `-v`, chaque lot qui a réservé des enregistrements est rapporté ; l'exécution
se termine par la somme :

```text
[OK] Claimed 3: 3 delivered, 0 to try again, 0 dead, 0 left to their lease, 0 taken over.
```

## `phil:outbox:dead:show`

Liste les enregistrements morts, les plus anciens d'abord, avec le compte des enregistrements par état : `--limit`
(50) et `--offset` (0). Avec un identifiant, montre cet enregistrement : son type, son état, sa charge utile, ses clés,
son occurrence, ses tentatives, son règlement et sa dernière erreur, la classe et le code de l'exception de sa dernière tentative.

## `phil:outbox:dead:retry`

Remet en attente les enregistrements morts nommés, relayés aussitôt avec leurs tentatives remises à 0, ou les plus
anciens avec `--all`, jusqu'à `--limit` (100). Elle échoue quand un enregistrement nommé n'est pas mort.

## `phil:outbox:dead:remove`

Retire pour de bon les enregistrements morts nommés, ou les plus anciens avec `--all`, jusqu'à `--limit` (100), ce qui
laisse passer les enregistrements suivants de leurs clés d'ordre. Rien n'est retiré sans `--force`.

## `phil:outbox:purge`

Retire les enregistrements livrés réglés il y a plus de `retention` secondes, les plus anciens d'abord, jusqu'à
`--limit` (1 000) ; `--retention` choisit un autre nombre de secondes. Les enregistrements en attente et morts restent.
Exécutez-la selon un horaire, par exemple une fois l'heure.

## `phil:outbox:errors:clear`

Retire le message des erreurs que la table conserve, et en garde la classe : `RuntimeException: 550 …` devient
`RuntimeException`. Une erreur garde la classe et le code d'une exception, et le message des exceptions propres à
l'outbox seulement ; cette commande réécrit les erreurs écrites avec le message de n'importe quelle exception, qui peut
citer des renseignements personnels, comme une adresse qu'un serveur de courriel a refusée. Les erreurs des exceptions
propres à l'outbox restent telles quelles, et la relancer ne réécrit rien. Enregistrée avec l'entrepôt `dbal`.

## `phil:outbox:schema`

Montre le SQL qui crée la table quand sa connexion en est dépourvue, et l'exécute avec `--force`. Une table déjà là est
laissée telle quelle. Enregistrée avec l'entrepôt `dbal`.
