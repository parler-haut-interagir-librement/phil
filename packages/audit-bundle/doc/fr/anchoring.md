<!-- phil-dev source-sha256=551fd4d2fa4cd3b7695cc5ab4044362f751207e01a4957df88b987f04cb86c2a -->
# L'ancrage

**État : implémenté.** `AnchorWriter` et `AnchorReader` sont des ports; `Anchoring` et `phil:audit:anchor` écrivent
l'ancre de chaque segment; `JsonLinesAnchorStore` est un adaptateur pour le développement et les tests.

## Pourquoi des ancres

Le chaînage seul peut être défait par quiconque est capable de réécrire toute la chaîne. Une **ancre** périodique (les
empreintes de bornes d'une période, écrites hors de la base de données) rend cette réécriture détectable, **et
seulement si sa destination est en ajout seul** : une ancre écrite avec un identifiant de connexion qui peut aussi la
supprimer est réécrite par le même administrateur qui réécrit la chaîne.

Les destinations acceptables, de la plus forte à la plus faible :

1. un stockage d'objets sous **verrouillage d'objets en mode conformité** (une rétention que personne ne peut raccourcir
   ni retirer, pas même le compte racine);
2. un autre stockage WORM;
3. à tout le moins, un identifiant de connexion qui a l'accès en écriture **sans** suppression ni écrasement, sur un
   stockage qui versionne les objets.

Gardez l'identifiant de connexion des ancres séparé de celui qui écrit les sauvegardes et de tout ce qui est stocké sur
l'hôte de l'application.

## Le port

```php
namespace Phil\AuditBundle\Domain\Contract;

interface AnchorWriter
{
    public function write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void;
}
```

| Paramètre | Signification |
|---|---|
| `$segment` | L'identifiant du segment ancré : une partition, ou un segment entre deux discontinuités |
| `$periodEnd` | La fin de la période ancrée |
| `$firstHash` | L'empreinte de la première ligne de la période, pour vérifier les bornes |
| `$headHash` | L'empreinte de la dernière ligne de la période |

La méthode ne retourne rien : une écriture qui n'a pas atteint le stockage en ajout seul doit lever une exception.

L'encodage des empreintes (octets bruts ou hexadécimal) n'est pas fixé par le port. La chaîne de l'application le
décide, et l'adaptateur doit le stocker sans perte.

## Quand ancrer

- périodiquement (par exemple chaque jour), par segment;
- **immédiatement après une ligne de discontinuité**, écrite après chaque restauration ([hash-chain.md](hash-chain.md)).

```bash
bin/console phil:audit:anchor
```

`Anchoring::anchor($periodEnd)` écrit, pour chaque segment qui a une ligne, ses empreintes de début et de tête
(SHA-256 hexadécimal) et les affiche. Planifiez la commande chaque jour, et exécutez-la sur-le-champ après
`recordDiscontinuity()`.

## Le stockage par fichier

`JsonLinesAnchorStore` ajoute une ligne JSON par ancre à un fichier, et les relit : définissez `anchor_file`, et il
sert à la fois d'auteur et de lecteur. **Il n'est en ajout seul que si le fichier se trouve sur un stockage que
l'administrateur de la base de données ne peut pas réécrire** (un montage sous verrouillage d'objets, un volume WORM).
Sur l'hôte de l'application, il ne rend pas une réécriture complète plus difficile. Une écriture qui n'atteint pas le
fichier lève une exception.

Voir [extension-points.md](extension-points.md) pour les invariants que respecte un adaptateur et un exemple.

## Sans ancres

La chaîne détecte encore la modification d'une ligne par quelqu'un qui ne recalcule pas toutes les empreintes
suivantes. Elle ne détecte **pas** une réécriture complète. Ne présentez pas une piste non ancrée comme infalsifiable.
