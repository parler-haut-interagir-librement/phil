<!-- phil-dev source-sha256=4d0f79c84562624f481cf1088f81b3d31d5c11033023eaea6601cb67a99cf23a -->
# Stockage

Un `UsageStore` garde les compteurs : un par sujet, compteur et début de compteur (une période, ou un compartiment
d'une fenêtre glissante), avec le moment où il cesse de compter dans toute fenêtre. Un stockage fait trois promesses :
une incrémentation sous un plafond est atomique, et deux incrémentations au même moment sont donc toutes deux comptées
ou l'une est refusée, jamais perdue ; une clé notée une fois n'est jamais comptée deux fois ; une incrémentation
refusée ne laisse derrière elle ni sa quantité ni sa clé. Un remboursement rend au plus ce que sa clé a compté, peu
importe combien de fois on le demande.

## En mémoire

`InMemoryUsageStore` garde les compteurs dans un tableau, pour les tests et pour un seul processus : une commande, un
*worker* qui compte ce qu'il fait en une exécution. Ils disparaissent avec l'objet.

## Dans une base de données

`Dbal\DbalUsageStore` les garde dans deux tables d'une connexion Doctrine DBAL, sur PostgreSQL, MySQL, MariaDB ou
SQLite ; toute autre plateforme est refusée à la première incrémentation.

| Table | Clé | Colonnes |
|---|---|---|
| `phil_usage_counter` | `subject`, `meter`, `starts_at` | `used`, `expires_at` (indexée) |
| `phil_usage_key` | `subject`, `meter`, `idempotency_key` | `starts_at`, `amount`, `refunded`, `expires_at` (indexée) |

Les instants sont des horodatages Unix dans des colonnes `BIGINT` : aucun fuseau horaire n'est stocké, et les
comparaisons sont des comparaisons d'entiers. Les noms sont les deuxième et troisième arguments du constructeur :
jusqu'à 63 lettres minuscules, chiffres ou `_`, sans commencer par un chiffre.

**Une incrémentation est une transaction** : la ligne de la clé est insérée si elle n'est pas là, puis la ligne du
compteur à zéro si elle n'est pas là (`ON CONFLICT DO NOTHING` sur PostgreSQL, `INSERT IGNORE` sur MySQL et MariaDB,
`INSERT OR IGNORE` sur SQLite, pour qu'un conflit n'interrompe jamais la transaction), puis une seule instruction
ajoute la quantité :

```sql
UPDATE phil_usage_counter SET used = used + ?
 WHERE subject = ? AND meter = ? AND starts_at = ? AND used + ? <= ?
```

Cette instruction est l'étape atomique : la base de données verrouille la ligne pendant qu'elle la vérifie et l'écrit,
et une seconde requête attend donc la première et voit son compte. Aucune ligne modifiée signifie un refus ; la
transaction est annulée. Une clé déjà présente signifie un doublon ; la transaction est annulée avant que rien ne soit
compté. Dans une transaction de l'application, l'incrémentation s'y imbrique et est annulée avec elle.

**Un remboursement est une transaction aussi.** La ligne de la clé, qui garde le début du compteur où elle est allée, la
quantité qu'elle a comptée et ce qui en a été rendu, est lue et verrouillée : `SELECT … FOR UPDATE` sur PostgreSQL,
MySQL et MariaDB ; SQLite n'a qu'un seul écrivain et prend le verrou de la base à la première écriture. Ce qui reste à
rendre est calculé à partir de cette ligne, puis le compteur baisse de cette quantité là où il en contient encore autant,
et le `refunded` de la clé monte d'autant. Un second remboursement de la même clé attend le premier et ne trouve plus
rien à rendre ; rien à rendre, ou un compteur déjà purgé, annule la transaction. Dans une transaction de l'application,
le remboursement s'y imbrique.

**Les fenêtres glissantes** retirent de la limite ce que contiennent les compartiments précédents avant la mise à jour
conditionnelle du compartiment courant. Les compartiments précédents ne changent plus : la vérification reste donc
exacte, sauf pour deux requêtes qui chevauchent une frontière de compartiment dans le même instant. La fenêtre peut
alors dépasser sa limite de la quantité de l'une d'elles.

## Créer les tables

`configureSchema()` ajoute les deux tables à un schéma qui ne les a pas. Avec l'ORM, `Dbal\UsageSchemaListener` le
fait sur l'événement `postGenerateSchema` pour la connexion du stockage, pour que `doctrine:migrations:diff` écrive la
migration et que `doctrine:schema:validate` garde les tables :

```php
$store = new DbalUsageStore($connection);
$eventManager->addEventListener('postGenerateSchema', new UsageSchemaListener($connection, $store));
```

Sans l'ORM, une migration appelle `$store->configureSchema($schema)` sur le schéma qu'elle construit.

## La purge

`UsageMeter::purge()`, ou le `purge($now)` du stockage, oublie les compteurs, et leurs clés, qui ont cessé de compter :
la fin d'une période, ou la fin de la fenêtre d'un compartiment. Il retourne le nombre de lignes retirées. Rien ne
casse sans elle, mais les tables grossissent d'une ligne par sujet, compteur et période ; une tâche quotidienne les
garde petites.
