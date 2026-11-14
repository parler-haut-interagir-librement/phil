<!-- phil-dev source-sha256=885314646c9347c970a384d062fc89764c79e5a32a27c4977ded37ada34039dc -->
# Déplacer un locataire

```sh
bin/console phil:tenancy:database:move retail tenant_retail      # to a database of its own
bin/console phil:tenancy:database:move retail shared             # back to the shared database
bin/console phil:tenancy:database:move retail tenant_retail --keep-source
```

Un locataire passe de la base partagée à une base à lui, revient, ou passe entre deux bases dédiées. La cible doit
exister et être migrée au préalable : `phil:tenancy:database:create` crée et migre la base d'un locataire déjà placé
là, de sorte qu'une base qu'aucun locataire n'utilise encore se prépare avec les outils du serveur et les migrations de
l'application. Le service d'emplacement doit implémenter `PlacementStore`, par lequel le déplacement enregistre le
nouvel emplacement : `placement: dbal` en donne un, sur une table de la base partagée ([Emplacements et suspensions
dans la base de données](stores.md)). Avec les emplacements de `dedicated`, que la configuration fixe, le déplacement
est refusé avant que rien ne soit copié.

## Les étapes

1. **Le locataire est suspendu.** Ses requêtes reçoivent un `503`, et ses messages attendent la nouvelle tentative.
2. **Ses lignes sont copiées.** Dans une transaction sur la cible, les lignes du locataire qu'un déplacement antérieur
   y a laissées sont supprimées, puis chaque table du locataire est copiée, dans l'ordre du plan de copie. Sur
   PostgreSQL, la séquence de chaque clé générée est avancée jusqu'à la plus grande clé, jamais reculée.
3. **La copie est vérifiée.** Chaque table est relue, dans la source et dans la cible, ligne par ligne dans l'ordre de
   sa clé, et les deux sont comparées par un SHA-256 de chaque colonne de chaque ligne.
4. **L'emplacement est enregistré** dans le `PlacementStore`.
5. **La source est vidée** des lignes du locataire, sauf avec `--keep-source`. Si cela échoue, le locataire est déjà
   servi depuis sa cible : les lignes restées dans la source sont seulement inutilisées, et un avertissement est
   journalisé.
6. **Le locataire est repris.**

Tant que l'emplacement n'est pas enregistré, tout échec annule le déplacement : l'ancien emplacement est enregistré de
nouveau, les lignes copiées dans la cible sont supprimées, et le locataire est repris dans sa source, inchangé. La
commande affiche pourquoi et se termine par `1`.

La source et la cible sont lues et écrites par deux connexions à elles, ouvertes avec les paramètres de la connexion
gérée fixés sur chaque base, dans le locataire, et fermées à la fin.

## Le plan de copie

`CopyPlan` lit les tables d'un locataire dans le mappage de `entity_manager` :

1. la table de chaque entité racine marquée `#[TenantScoped]`, après les tables limitées au locataire que sa propre
   table référence, les égalités départagées par l'ordre de leurs noms ;
2. la table de chaque sous-classe d'une hiérarchie jointe, après celle de son parent, atteinte par la clé de la racine ;
3. la table de jointure de chaque association many-to-many qu'une entité limitée au locataire possède, atteinte par la
   clé du propriétaire.

La suppression se fait dans l'autre sens.

## Limites

- **Des clés uniques d'une base à l'autre.** Une ligne garde sa clé. Un locataire déplacé dans une base où un autre
  locataire détient déjà la même clé — des clés entières générées par chaque base — échoue sur la contrainte d'unicité,
  et le déplacement est annulé. Des clés uniques partout, comme des UUID ou des ULID, se déplacent librement.
- **Les références entre tables limitées au locataire doivent être acycliques.** Des tables qui se référencent
  mutuellement ne peuvent pas être ordonnées : le plan les refuse avec `TenantNotCopyable`. Une table qui se référence
  elle-même est ordonnée par sa clé, ce qui place généralement un parent en premier.
- **Une colonne pour atteindre un locataire.** Une sous-classe jointe ou une table de jointure est atteinte par une clé
  d'une seule colonne ; une clé de plusieurs colonnes est refusée.
- **Seulement ce que le mappage connaît.** Les tables qui ne sont pas limitées au locataire ne sont pas copiées :
  chaque base contient ses propres données de référence. Une table hors du mappage, ou une association many-to-many
  possédée par une entité qui n'est pas limitée au locataire, n'est pas copiée non plus.
- **Un serveur par déplacement.** La source et la cible sont lues avec la plateforme de la connexion partagée.

## Le cache, Messenger et les migrations

Le cache du locataire reste valide : il est limité par la référence du locataire, et les données n'ont pas changé. Les
messages qui ont attendu pendant le déplacement sont traités, une fois le locataire repris, dans sa nouvelle base. La
prochaine exécution de `phil:tenancy:database:migrate` migre la base où se trouve le locataire maintenant.
