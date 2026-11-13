<!-- phil-dev source-sha256=91e7a634aa7f6470b46a305315dced0ef5cefadd9c822788260e9b34e97514f0 -->
# Exceptions

Chaque exception implémente `Phil\Epub\Exception\EpubException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidBook` | `InvalidArgumentException` | Un titre ou un nom de créateur vide, une langue qui n'est pas une étiquette de langue, une date de publication sous une autre forme, un identifiant vide ou qui n'est pas l'UUID, l'ISBN-13 ou le DOI qu'il prétend être, un rôle MARC qui n'est pas fait de trois lettres minuscules, un nom de fichier de chapitre qui ne se termine pas par `.xhtml`, un chemin hors du paquet ou fait d'autres caractères, un chemin utilisé deux fois ou réservé, un type de média hors des types de médias de base d'EPUB, une image ou une police dont les octets ne sont pas reconnus, une couverture qui n'est pas une image ou qui n'a pas de texte de remplacement, une entrée de table des matières sans libellé, des déclarations d'accessibilité contradictoires, un livre sans chapitre, une profondeur de table des matières hors de 1 à 6 |
| `WriteFailed` | `RuntimeException` | L'archive ne peut pas être créée ou écrite, ou aucun fichier temporaire ne peut être créé |

Chaque message nomme ce qui ne va pas et ce qui a été donné, par exemple `The path "a.css" is used twice.` ou
`"english" is not a language tag such as "en" or "fr-CA".`

Du HTML qui ne peut pas entrer dans un livre n'est pas une exception : ce que refuse EPUB est laissé de côté, comme le
décrit [Documents de contenu](content.md).
