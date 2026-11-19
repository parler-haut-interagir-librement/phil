<!-- phil-dev source-sha256=9d1bb225a03db87189ae6eb177f897cd39d49677ce00f557516e4931bf35126c -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\DocumentImport\Exception\DocumentImportException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidDocument` | `RuntimeException` | Le fichier ne peut pas être lu ; les octets ne forment pas une archive ZIP ; une entrée est corrompue ; une entrée n'est pas du XML bien formé ou déclare un type de document ; un DOCX n'a ni partie de document principale ni corps ; un ODT n'a pas de texte dans `content.xml` ; aucun fichier temporaire ne peut être créé ; un RTF ne commence pas par `{\\rtfN` ; un PDF ne commence pas par `%PDF-` ; un ODT plat dont la racine n'est pas l'`office:document` d'un texte |
| `UnsupportedFormat` | `InvalidArgumentException` | Le format n'est pas donné, et ni le nom ni les octets ne le montrent ; le fichier est un document Word 97-2003 ou un document Office protégé par un mot de passe ; le PDF est chiffré |
| `LimitExceeded` | `RuntimeException` | Une entrée, une archive, un fichier ou un flux PDF est plus grand que ne le permet `Limits` ; des groupes RTF ou des objets PDF sont imbriqués plus profondément ; un PDF déclare plus d'objets, ou demande plus d'opérations pour lire ses pages |
| `ExportFailed` | `RuntimeException` | Un document est exporté dans un autre format que DOCX et ODT, ou son archive ne peut pas être écrite |

Chaque message nomme le fichier ou l'entrée, par exemple `The entry "word/document.xml" is not well-formed XML.`

Deux exceptions des paquets voisins peuvent aussi passer : `Phil\Markup\Exception\InvalidSource`, quand un lecteur
Markdown ou HTML donné à l'importateur refuse sa source (les lecteurs par défaut ne le font jamais), et les exceptions
de `phil/ujo`, quand le magasin de médias répond une adresse que refuse un bloc d'image ou qu'un normaliseur donné ne
connaît pas un type de bloc qu'écrit l'importation.

Ce que contient un document et que l'UJO ne peut pas garder n'est pas une exception : c'est laissé de côté et signalé.
