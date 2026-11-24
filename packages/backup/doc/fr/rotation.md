<!-- phil-dev source-sha256=b9b8a54fd1d6ca46c0d470eacf320f4384616a7bc00213681d6be710d5ee3345 -->
# Rotation

## Les règles

`RotationPolicy(last, daily, weekly, monthly)` garde :

| Règle | Garde |
|---|---|
| `last: n` | les `n` jeux les plus récents |
| `daily: n` | le jeu le plus récent de chacun des `n` derniers jours qui en ont un |
| `weekly: n` | le jeu le plus récent de chacune des `n` dernières semaines ISO qui en ont un |
| `monthly: n` | le jeu le plus récent de chacun des `n` derniers mois qui en ont un |

Un jeu gardé par une règle quelconque est gardé ; les autres ont expiré. C'est le schéma grand-père-père-fils : avec
`last: 3, daily: 7, weekly: 4, monthly: 12` et une sauvegarde chaque nuit, le répertoire contient environ deux douzaines
de jeux qui remontent sur un an.

Chaque compte vaut zéro ou plus, et l'un d'eux au moins est supérieur à zéro ; une rotation qui ne garderait rien est
refusée avec `InvalidBackupValue`. Le jeu le plus récent est toujours gardé, puisque chaque règle en garde au moins un.

## Périodes

Les jours, les semaines et les mois sont ceux de l'UTC, l'heure des noms de fichiers : un changement de fuseau horaire
ou d'heure d'été du serveur ne fait donc passer aucune sauvegarde d'un jour à un autre. Les semaines sont des semaines
ISO, du lundi au dimanche : une sauvegarde du dimanche appartient à la semaine du lundi qui la précède. Une période sans
sauvegarde n'est pas comptée : après une semaine de panne, `daily: 7` garde encore sept jeux.

## Ce qui est en rotation

`expired($sets)` donne les jeux expirés, du plus ancien au plus récent ; `Backups::prune()` les supprime et les
retourne. Seuls les jeux **sans étiquette** sont en rotation : la copie faite avant une restauration, une copie
anonymisée ou une copie faite avant une migration reste jusqu'à ce qu'elle soit supprimée à la main, ou par
`Backups::keepLatest()` pour une étiquette. Les fichiers du répertoire qui ne sont pas des jeux ne sont jamais touchés.

## Les jeux étiquetés et leur conservation

Un jeu étiqueté n'a pas de période propre : il reste jusqu'à ce que quelque chose le supprime. Donnez une période à
chaque étiquette, notée à côté des autres périodes de conservation de l'application, et supprimez ses jeux quand elle
se termine :

- **par nombre** : `Backups::keepLatest($label, $keep)` supprime les jeux de l'étiquette sauf les `keep` plus
  récents, et les retourne, du plus ancien au plus récent ;
- **par âge** : listez les jeux de l'étiquette et supprimez ceux qui ont été faits avant la période :

```php
$cutoff = new DateTimeImmutable('-30 days');

foreach ($backups->directory()->labelled($backups->client()->extension(), 'anonymized') as $set) {
    if ($set->time < $cutoff) {
        $backups->directory()->remove($set);
    }
}
```

**Une copie étiquetée `anonymized` contient des renseignements personnels.** Une base de données dont des colonnes ont
été réécrites pour travailler sur une copie de la production garde ses clés primaires, les colonnes qu'aucune règle ne
nomme et son texte libre : on peut la relier aux personnes de la production. Selon la loi québécoise sur la protection
des renseignements personnels dans le secteur privé, elle est au mieux dépersonnalisée, non anonymisée, et d'autres lois
la voient de la même façon. Une telle copie demande une courte période — le temps du travail pour lequel elle a été
faite —, un chiffrement comme tout autre jeu, et sa fuite est un incident de confidentialité. La supprimer à la fin de
sa période est le comportement par défaut ; rien dans cette bibliothèque ne rend une copie anonyme.

## La rotation à côté d'une sauvegarde

`Backups` ne prend aucun verrou : `prune()` et `keepLatest()` suppriment des jeux pendant qu'un autre processus peut
en écrire un nouveau ou en tester un ancien. Un jeu en cours d'écriture n'est jamais supprimé — il n'a pas de nom avant
d'être complet, et le jeu le plus récent est toujours gardé —, mais un jeu en cours de test peut l'être. Une
application qui lance des sauvegardes, des rotations et des tests depuis plus d'un processus les sérialise avec un
verrou par répertoire ; phil/backup-bundle le fait avec Symfony Lock.
