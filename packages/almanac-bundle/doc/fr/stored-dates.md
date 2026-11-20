<!-- phil-dev source-sha256=1b87ee330482fc0b42a944a12c4fd8db45684e672dd254b2255d27ec50322005 -->
# Dates stockées et formulaire

## La table

Avec `database: true`, les dates de `phil_almanac_date` s'ajoutent à celles de la configuration, et **une date stockée
remplace la date configurée de sa clé** : un administrateur modifie une date sans déploiement. Une date stockée garde sa
clé : `StoredDate::change()` refuse une définition d'une autre clé avec une `InvalidRule`. Une ligne dont la règle ne se
lit plus — une colonne modifiée à la main — est laissée hors de l'almanach, pour qu'une seule ligne n'arrête pas toutes
les pages.

| Colonne | Type | |
|---|---|---|
| `id` | UUID v7 | |
| `date_key` | `VARCHAR(100)`, unique | La clé de la date |
| `title` | `VARCHAR(200)` | |
| `rule`, `until_rule` | `VARCHAR(200)`, la seconde pouvant être nulle | Le texte canonique des règles |
| `days` | `SMALLINT` | |
| `all_day` | booléen | |
| `tags` | JSON | |

### Collation de la clé

**La clé se compare octet par octet**, comme PHP la compare. Le bundle écrit les clés en ASCII minuscule ; une ligne
écrite en dehors de lui — à la main, par un autre outil — garde son orthographe : une ligne `Harvest` ou `harvést` n'est
jamais trouvée pour `harvest`, et n'empêche jamais d'enregistrer `harvest` sous la clé unique. `BinaryKeysListener`,
enregistré avec `database: true`, donne à `date_key` la collation binaire lorsque l'ORM charge ses métadonnées sur MySQL
ou MariaDB ; le mappage lui-même reste le même sur toutes les plateformes.

Sur MySQL et MariaDB, `date_key` prend le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la plateforme
de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ignore la casse et les accents. Les
collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et SQLite compare les
octets : la colonne garde la valeur par défaut.

#### Mise à niveau depuis 0.1

La table d'une installation 0.1 sur MySQL ou MariaDB gardait la collation de la connexion. `doctrine:migrations:diff`
écrit la migration qui amène `date_key` à `utf8mb4_bin` ; exécutez-la avec `doctrine:migrations:migrate`. À la main,
selon la plateforme :

```sql
-- MySQL and MariaDB
ALTER TABLE phil_almanac_date MODIFY date_key VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
-- PostgreSQL: nothing to run, the default collation is deterministic.
-- SQLite: nothing to run, the column compares bytes.
```

Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne brise la clé unique. Une clé que
l'application cherche dans une autre casse que celle de la ligne cesse de correspondre dès la migration ; les clés que
le bundle écrit sont en minuscules, et seules les lignes écrites en dehors de lui peuvent donc être concernées. Avant de
migrer sur MySQL ou MariaDB, listez-les :

```sql
SELECT date_key FROM phil_almanac_date WHERE CAST(date_key AS BINARY) <> CAST(LOWER(date_key) AS BINARY);
```

`StoredDate` se construit à partir d'une `Definition` et la rend avec `definition()`. `change()` prend le titre, les
règles, la durée et les étiquettes d'une autre définition, la clé restant la même. Le port `StoredDateRepository` —
`all()`, par clé, `ofKey()`, `save()`, `remove()` — est `DoctrineStoredDateRepository`, qui fait un *flush* à chaque
enregistrement et à chaque suppression. L'almanach se construit à partir des définitions chaque fois qu'on le demande :
une date enregistrée figure donc dans l'almanach suivant.

`DoctrineStoredDateRepository` lit la table une fois et garde ce qu'il a lu jusqu'à ce qu'une date soit enregistrée ou
supprimée par son intermédiaire, ou que le noyau réinitialise ses services. Le service est étiqueté `kernel.reset` :
chaque requête, et chaque message d'un *worker* de Messenger, lit donc la table une fois, quel que soit le nombre
d'appels à `almanac_dates()` dans une page. Une ligne écrite par un autre processus, ou par le gestionnaire d'entités
sans passer par le dépôt, est vue à la réinitialisation suivante. Un service de longue durée qui affiche l'almanach le
demande à chaque utilisation, avec `AlmanacFactory::almanac()` ou une fermeture de service, plutôt que de garder celui
qu'il a reçu.

```php
$dates->save(new StoredDate($draft->definition()));
$dates->ofKey('thanksgiving')?->change($draft->definition());
```

`Definitions::isConfigured($key)` indique si une clé vient de la configuration, qu'une date stockée de même clé remplace
— une page d'administration peut l'indiquer. `stored()` énumère les dates stockées.

## Le formulaire

`StoredDateType` remplit un `DateDraft` : clé, titre, règle, fin, jours, toute la journée, et étiquettes séparées par
des virgules. Avec `key_locked: true` — pour une date qui existe —, la clé ne peut pas être modifiée. Ses libellés sont
dans le domaine `phil_almanac`.

```php
$form = $this->createForm(StoredDateType::class, DateDraft::of($stored->definition()), ['key_locked' => true]);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $stored->change($form->getData()->definition());
    $dates->save($stored);
}
```

Un `DateDraft` est valide lorsque l'almanach peut le lire : chaque champ séparément — une clé de la bonne forme, un
titre, une règle d'au plus 200 caractères, de 1 à 366 jours —, chaque règle lue, puis la date dans son ensemble une fois
que chaque champ est valide. Une règle illisible est refusée sur son champ, *This rule cannot be read: …* ; une date
qui ne peut pas être calculée l'est sur le formulaire, *This date cannot be computed: …* ; les deux messages existent
aussi en français.
