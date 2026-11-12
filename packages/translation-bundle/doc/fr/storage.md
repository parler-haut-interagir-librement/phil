<!-- phil-dev source-sha256=a788c78d0cbe34dea2e3dba270e1cad9aac0b63148e18cd15f1e0aa56bf851b7 -->
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
