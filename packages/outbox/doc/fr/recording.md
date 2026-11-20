<!-- phil-dev source-sha256=2e85fc0450cba87ba50c9ea9c03f6e9b2f232795b2ebb70050963eb32b902c84 -->
# Enregistrement

Une bibliothèque enregistre un événement avec `Outbox::record()`, dans la transaction de l'écriture que l'événement
décrit :

```php
$connection->transactional(function () use ($outbox, $holder, $set): void {
    $this->completions->add($holder, $set);
    $outbox->record('sets.set_completed', ['set' => $set->code, 'holder' => $holder->id], orderingKey: 'holder:'.$holder->id);
});
```

L'enregistrement est validé avec l'écriture, ou annulé avec elle : aucun événement n'est communiqué pour une écriture
qui n'a jamais eu lieu, et aucune écriture ne reste sans son événement. Un enregistrement fait hors d'une transaction
est écrit seul, comme n'importe quelle instruction.

`record()` retourne le `RecordId` de l'enregistrement, que les écouteurs reçoivent et qui reste le même d'une
livraison à l'autre.

## Le type d'événement

Le type nomme l'événement, comme `sets.set_completed` : de 1 à 120 lettres minuscules et chiffres, en mots joints par
`.`, `_` ou `-`. L'outbox n'enregistre que les types de sa liste d'autorisation, c'est-à-dire l'ensemble des types que
ses [codecs](codecs.md) reconstruisent : `record()` lance `UnknownEventType` pour tout autre, avant que rien ne soit
écrit. Un type fait partie des données stockées ; renommer un type modifie les enregistrements stockés, et le codec de
l'ancien nom reste jusqu'à ce qu'ils soient relayés.

## La charge utile

La charge utile contient les quelques identifiants à partir desquels le codec reconstruit l'événement, par nom :

| Borne | Valeur |
|---|---|
| Valeurs | chaînes, entiers, booléens ou null ; jamais un nombre à virgule, une liste, un objet ni une valeur imbriquée |
| Noms | de 1 à 64 lettres, chiffres ou `_`, commençant par une lettre minuscule |
| Une chaîne | UTF-8 valide, d'au plus 255 octets |
| La charge utile | d'au plus 16 valeurs, d'au plus 4 096 octets de JSON |

Une charge utile ne porte que des identifiants, jamais de renseignements personnels ni de données volumineuses : le
codec relit le reste là où la bibliothèque le conserve, au moment de reconstruire l'événement. Un enregistrement livré
est conservé jusqu'à la fin de sa rétention, et les renseignements personnels qu'il contiendrait survivraient à leur
effacement des tables de la bibliothèque. `record()` lance `InvalidRecord` pour une charge utile hors de ses bornes.

## La clé d'ordre

Une clé d'ordre nomme l'agrégat dont les enregistrements sont relayés dans l'ordre où ils ont été enregistrés, l'un
après l'autre, comme `holder:7` : un enregistrement attend tant qu'un enregistrement antérieur de sa clé n'est pas
livré. Les enregistrements sans clé sont relayés dans n'importe quel ordre, côte à côte. Voyez
[Relais](relaying.md#lordre-dune-clé).

## La clé d'idempotence

Une clé d'idempotence enregistre un événement une seule fois pour son type : enregistrer de nouveau un type et une clé
déjà conservés n'ajoute rien et retourne l'identifiant du premier enregistrement. Employez-la quand l'écriture
elle-même peut s'exécuter deux fois, comme un achèvement vérifié de nouveau après une nouvelle tentative. La clé est
conservée avec l'enregistrement, et oubliée quand l'enregistrement est purgé après sa rétention.

Une clé, d'ordre ou d'idempotence, compte de 1 à 190 caractères ASCII visibles, comparés octet par octet.

Une clé est aussi un identifiant, comme la charge utile : construisez-la avec des identifiants, comme `completion:42:7`,
jamais avec une adresse, un nom ou d'autres renseignements personnels. Une clé est conservée avec son enregistrement
jusqu'à ce que celui-ci soit purgé ou écarté, citée dans le message d'une `InvalidRecord`, et montrée par les commandes
des enregistrements morts.

## L'horloge

L'outbox date l'enregistrement par son horloge PSR-20, `SystemClock` en UTC, sauf si l'application fournit la sienne.
Les instants sont conservés à la seconde près.
