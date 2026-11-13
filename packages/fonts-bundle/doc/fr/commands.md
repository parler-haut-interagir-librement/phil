<!-- phil-dev source-sha256=7e9fbe852d476b9c96bf414357b8980d5ef88c67add1f069a7577e21d7e03d02 -->
# Commandes

| Commande | Rôle | Code de sortie |
|---|---|---|
| `phil:fonts:list` | Une ligne par face de chaque fichier : fichier, format, famille, style, graisse, permission d'intégration, identifiant SPDX de la licence (`?` si aucune n'est reconnue) ; un fichier illisible indique pourquoi | 1 quand un fichier est illisible pour une autre raison que l'absence d'`ext-brotli`, sinon 0 |
| `phil:fonts:licences [--write]` | Liste les fichiers dont la licence doit être vérifiée ; `--write` les inscrit | 1 tant qu'un fichier reste à vérifier et que `--write` n'est pas passé, sinon 0 |
| `phil:fonts:css [--output=FILE]` | Affiche les règles `@font-face` ou les écrit dans un fichier | 0 |

`phil:fonts:list` écrit « Not read: ext-brotli is not loaded » pour un fichier WOFF2 lu sans l'extension, et une note
sous le tableau indique que les fichiers WOFF, TrueType ou OpenType sont lus à la place. Un répertoire vide ou absent
est signalé en une phrase.
