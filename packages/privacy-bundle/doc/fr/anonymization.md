<!-- phil-dev source-sha256=336ae21f788f04c65388258bb2d541644258545ba2d73656382ae1e1634d2f3a -->
# Anonymisation

Une copie de la base de production est la meilleure donnée avec laquelle puisse travailler un développeur, un testeur
ou une personne du soutien, et la pire chose à laisser sur un ordinateur portable. `phil:privacy:anonymize` réécrit les
données personnelles d'une base avec des valeurs plausibles qui ne disent rien des personnes à qui elles appartenaient,
pour que la copie puisse être partagée avec l'équipe.

## Le plan

Ce qui est réécrit est un plan : une liste de colonnes, chacune avec un type d'anonymiseur et ses options. Il est tiré
de trois sources, dans cet ordre, une règle plus tardive remplaçant une plus ancienne sur la même colonne :

1. **`#[Anonymize]` sur une propriété Doctrine mappée.**

   ```php
   use Phil\PrivacyBundle\Domain\Attribute\Anonymize;
   use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

   #[ORM\Column]
   #[PersonalData('email', retention: 'R-002')]
   #[Anonymize('email', options: ['domain' => 'example.org'])]
   private string $email;
   ```

2. **La catégorie d'une propriété `#[PersonalData]` sans `#[Anonymize]`**, par
   `phil_privacy.anonymization.categories` :

   ```yaml
   phil_privacy:
       anonymization:
           categories: { email: email, person_name: name, phone: phone }
   ```

3. **Les tables configurées**, qui atteignent aussi les tables qu'aucune entité ne mappe :

   ```yaml
   phil_privacy:
       anonymization:
           tables:
               member:
                   email: hash
                   note: { type: lorem, options: { words: 6 } }
               legacy_log:
                   remote_ip: null
   ```

Un champ `#[PersonalData]` d'une colonne mappée qu'aucune des trois n'atteint est **laissé en clair**, et signalé :
`--dry-run` le liste, `--strict` refuse de s'exécuter tant qu'il en reste un. Les données personnelles que déclare le
bundle sont ainsi liées à leur anonymisation : ajouter un champ personnel sans dire comment il est anonymisé se voit
dans le plan.

Les entités sont lues dans l'ordre de leurs noms de classes. Un champ hérité d'une entité parente est lu sur le parent,
dont la table le porte dans l'héritage à table unique comme dans l'héritage joint ; une propriété d'une superclasse
mappée appartient à chaque entité qui l'étend. Les propriétés des objets intégrés ne sont pas lues : nommez leurs
colonnes sous `tables`.

## Anonymiseurs

| Type | Écrit | Options |
|---|---|---|
| `email` | `user.3f9a…@example.com` : une partie locale dérivée de l'adresse d'origine sous le sel de l'exécution, sans égard à la casse | `domain` (`example.com`), `prefix` (`user`) |
| `firstname`, `lastname`, `name` | Un prénom, un nom de famille, ou les deux, tirés du paquet de locale | — |
| `phone` | Un numéro dans l'une des plages fictives du paquet, comme `514 555-0142` | `pattern` |
| `address` | Une ligne de rue, une ville, une région, un code postal, le nom du pays, ou une adresse entière dans le format du paquet | `part` : `line` (par défaut), `city`, `region`, `postal_code`, `country`, `full` |
| `iban` | Un numéro de compte de la forme IBAN dont les chiffres de contrôle sont justes | `country` (celui du paquet), l'un de AT, BE, CH, DE, ES, FR, GB, IE, IT, LU, NL, PT |
| `date_shift` | La date déplacée de 1 à `days` jours, en avant ou en arrière, dans le format où elle a été lue | `days` (30), `format` quand ce n'est aucun de `Y-m-d`, `Y-m-d H:i:s`, `Y-m-d H:i:s.u`, `Y-m-d\TH:i:sP` |
| `constant` | La même valeur pour chaque ligne, avec son type | `value` (`''`) |
| `null` | Nul ; la colonne doit l'accepter | — |
| `hash` | Le HMAC hexadécimal de la valeur sous le sel de l'exécution | `algorithm` (`sha256`, l'un de `hash_hmac_algos()`), `length` |
| `lorem` | Une phrase de latin de remplissage | `words` (12) |
| `pattern` | Une valeur d'une forme connue : `#` un chiffre, `?` une lettre majuscule, `*` l'un ou l'autre, `\` un échappement | `pattern`, obligatoire |

Chaque anonymiseur lit aussi `locale`, qui remplace le paquet de locale de l'exécution pour cette colonne.

**Une valeur nulle reste nulle**, quel que soit l'anonymiseur : il n'y a rien de personnel en elle, et une colonne qui
était facultative le reste. Une ligne dont toutes les colonnes anonymisées sont nulles n'est pas écrite.

**Les valeurs dérivées se rejoignent.** `email` et `hash` dérivent leur résultat de la valeur d'origine sous un sel tiré
pour l'exécution et jamais gardé : au cours d'une exécution, la même adresse donne le même résultat dans chaque table,
si bien qu'une valeur répétée d'une table à l'autre correspond encore, et qu'une colonne unique reste unique ; sans le
sel, aucune valeur ne peut être retrouvée en hachant une liste de candidats.

**Les autres anonymiseurs tirent au hasard.** Chaque partie d'une adresse est tirée à part : la ville ne correspond pas
au code postal — une chose de plus que la copie ne dit pas.

## Paquets de locale

Les paquets donnent les noms, les rues, les villes, les régions, les motifs de code postal et les plages téléphoniques
fictives où puisent les anonymiseurs : `fr_CA`, `en_CA`, `fr_FR`, `en_US`, `en_GB`, `en_AU`, `de_DE` et `es_ES`. Le
paquet de l'exécution est `phil_privacy.anonymization.locale` (`en_US` par défaut).

**Les motifs téléphoniques sont des plages fictives** que chaque régulateur garde hors service : 555-01xx en Amérique du
Nord, 01 99 00 et ses voisines en France, 07700 900 et les plages réservées à la fiction au Royaume-Uni, 5550 et 7010 en
Australie. L'Allemagne et l'Espagne n'en réservent aucune : leurs paquets ne portent pas de motif téléphonique, et
l'anonymiseur `phone` demande une option `pattern` plutôt que d'inventer un numéro qui pourrait faire sonner quelqu'un.

Une application ajoute ou remplace un paquet par un service `LocalePack`, trouvé par l'autoconfiguration ; voir
[extension-points.md](extension-points.md).

## La commande

```bash
bin/console phil:privacy:anonymize --dry-run
bin/console phil:privacy:anonymize --confirm --seed=7
```

| Option | Effet |
|---|---|
| `--dry-run` | Affiche le plan, `table.column: type (origin)`, et chaque champ laissé en clair ; ne change rien |
| `--strict` | Échoue tant qu'un champ personnel est laissé en clair |
| `--seed=N` | Rend les tirages au hasard reproductibles : le même plan sur les mêmes données donne la même copie |
| `--confirm` | Anonymise ; sans elle, rien n'est fait |

L'exécution est **une seule transaction**. Tout ce qui peut être refusé l'est avant qu'une ligne soit lue : un type ou
une locale inconnus, une table sans clé primaire, une règle sur une colonne de clé (réécrire une clé casserait les
lignes qui y renvoient). Une option qu'un anonymiseur ne sait pas lire est trouvée à la première ligne, et la
transaction est annulée.

Les lignes sont lues par pages de `batch_size` (500), ordonnées par clé primaire, et écrites une à une par clé. Les noms
sont lus tels que Doctrine les écrit, sans guillemets — PostgreSQL ramène `firstName` à `firstname` —, et un schéma peut
préfixer une table, comme dans `billing.invoice`.

## La garde de production

L'anonymisation réécrit les données personnelles pour de bon : en production, elle détruit les dossiers de l'entreprise.
La garde refuse avant que quoi que ce soit soit écrit, l'essai à blanc excepté :

- quand le noyau s'exécute dans l'un des `phil_privacy.anonymization.guard.environments` (`[prod]`) ;
- quand l'hôte de la base est dans `guard.hosts`, ou son nom — le chemin d'un fichier SQLite — dans `guard.databases`.

La seconde vérification attrape la machine d'un développeur pointée vers le serveur de production par un `DATABASE_URL`
égaré. Il n'y a aucune option pour contourner la garde : sauvegardez la production, restaurez la sauvegarde ailleurs, et
anonymisez là.

## Une copie à partager avec l'équipe

Sauvegarder et restaurer n'est pas le travail de ce bundle. Avec `phil/backup-bundle`, une copie de la production à
remettre aux développeurs prend trois commandes, les deux dernières sur une machine qui n'est pas la production :

```bash
bin/console phil:backup:create                                                   # in production
bin/console phil:backup:restore var/backup/app-20261106-031500.sql.enc --confirm --database=app
bin/console phil:privacy:anonymize --confirm
bin/console phil:backup:create --label=anonymized --no-media
```

La restauration redemande le nom de la base et sauvegarde d'abord le contenu local ; la sauvegarde étiquetée est gardée
à l'écart de la rotation, prête à être partagée.

## Ce que l'anonymisation ne fait pas

Elle ne rend pas une copie sûre à publier. Les noms et les rues sont des noms et des rues ordinaires, et une combinaison
peut correspondre à une personne réelle ; le texte libre hors des colonnes du plan, les fichiers sur le disque, les
journaux et les index de recherche ne sont pas touchés. Gardez une copie anonymisée au sein de l'équipe, et supprimez-la
quand on n'en a plus besoin.
