<!-- phil-dev source-sha256=8e3309818eab68003cccfd842e0633f13466c0f079a49097c11c87cf81197e9f -->
# Stockage

## Tables

| Table | Clé | Colonnes |
|---|---|---|
| `phil_translation_segment` | `document`, `target_language`, `segment_key` | `source_language`, `position`, `source`, `fingerprint`, `state`, `target`, `translated_source`, `updated_at`, `translated_by`, `reviewed_by`, `machine`, `flags` |
| `phil_translation_memory` | `source_language`, `target_language`, `fingerprint` | `length`, `source`, `target`, `origin`, `updated_at` ; index sur `source_language`, `target_language`, `length` |
| `phil_translation_term` | `source_language`, `target_language`, `term` | `translations`, `forbidden`, `forms` et `translation_forms` (listes JSON), `case_sensitive`, `note` |
| `phil_translation_comment` | `id`, généré | `document`, `target_language`, `segment_key`, `author`, `body`, `created_at` ; index sur `document`, `target_language`, `segment_key` |

Chaque clé, sauf celle d'un commentaire, est naturelle : un segment, c'est son document, sa langue et sa clé ; une unité
de mémoire, ses langues et l'empreinte de sa source ; un terme, ses langues et son texte. `translated_by`,
`reviewed_by` et `author` contiennent les identifiants que donne le service `Translators` de l'application, d'au plus
255 octets, jamais un nom ; `flags` contient les indicateurs des contrôles sous forme de texte, d'au plus 1024 octets.
La mémoire est consultée par sa clé pour les correspondances exactes, et par son index de longueur pour les candidats
aux correspondances approximatives. L'horloge écrit les moments en secondes entières.

## Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Un segment, une unité de mémoire, un terme et un
commentaire se trouvent par leurs clés telles qu'elles ont été écrites : le segment `Title` d'un document n'est jamais
`title`, le glossaire contient `Apple` et `apple` comme deux termes, et le travail du traducteur `Ann` n'est jamais
listé pour `ann`. Un terme marqué insensible à la casse correspond toujours au texte dans n'importe quelle casse : c'est
la correspondance du glossaire, non celle de la table.
`BinaryKeysListener` donne à ces champs la collation binaire quand l'ORM charge leurs métadonnées sur MySQL ou
MariaDB ; l'association elle-même reste la même sur toutes les plateformes.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_translation_segment` | `document`, `source_language`, `target_language`, `segment_key`, `translated_by`, `reviewed_by` |
| `phil_translation_memory` | `source_language`, `target_language`, `fingerprint` |
| `phil_translation_term` | `source_language`, `target_language`, `term` |
| `phil_translation_comment` | `document`, `target_language`, `segment_key`, `author` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ne distingue ni la casse ni les
accents. Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et
SQLite compare les octets : leurs colonnes gardent la valeur par défaut.

### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Sur PostgreSQL et SQLite, le diff est vide.

Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne brise donc une clé unique.
Il sépare ce que la collation insensible à la casse réunissait : les lignes dont les clés ne diffèrent que par la
casse, qu'une recherche par l'une ou l'autre graphie retournait ensemble, se trouvent chacune par leur propre graphie
dès la migration, et une clé que l'application cherche dans une autre casse que celle où elle l'a écrite cesse de
correspondre. Avant de migrer, listez les clés écrites en plus d'une casse :

```sql
SELECT c.id FROM phil_translation_comment c
WHERE NOT EXISTS (SELECT 1 FROM phil_translation_segment s WHERE CAST(s.document AS BINARY) = CAST(c.document AS BINARY)
    AND CAST(s.target_language AS BINARY) = CAST(c.target_language AS BINARY) AND CAST(s.segment_key AS BINARY) = CAST(c.segment_key AS BINARY));
```

Chaque ligne est une clé sous plusieurs graphies. Gardez-les distinctes, ou ramenez les lignes à la graphie
qu'utilise l'application avec un `UPDATE` avant la migration. Une clé unique ne peut contenir deux telles graphies sous
l'ancienne collation, donc les colonnes d'une clé unique n'ont pas besoin de vérification.

## Stockages

| Port | Doctrine | Mémoire |
|---|---|---|
| `SegmentRepository` | `DoctrineSegmentRepository` | `InMemorySegmentRepository` |
| `TermRepository` | `DoctrineTermRepository` | `InMemoryTermRepository` |
| `Phil\TranslationMemory\Memory\MemoryStore` | `DoctrineMemoryStore` | l'`InMemoryStore` de la bibliothèque |
| `Contract\CommentRepository` | `DoctrineCommentRepository` | `InMemoryCommentRepository` |

Les stockages Doctrine appellent `flush` à chaque enregistrement et à chaque retrait ; l'enregistrement de plusieurs
segments n'appelle `flush` qu'une fois. Les stockages en mémoire gardent leurs lignes pendant la vie du service : pour
les tests, et pour les aperçus qui ne doivent rien écrire.
