<!-- phil-dev source-sha256=ecda5480325b17ce00a3914498bc9c1e7d52674586d923568329cf1e31dcb211 -->
# Licences

Un fichier de police est un logiciel sous licence. Le registre inscrit, pour chaque fichier, son SHA-256 et ce que
chacune de ses faces dit de sa licence (description, URL, identifiant SPDX, permissions d'intégration) le jour où
quelqu'un l'a vérifié. Le catalogue est ensuite comparé au registre : un fichier ajouté, retiré ou modifié depuis est
une licence à vérifier de nouveau.

`Licences` tient le registre à l'emplacement `licence_register` :

| Méthode | Retourne |
|---|---|
| `kept()` | Le registre tel qu'il a été écrit la dernière fois ; vide si le fichier n'existe pas |
| `current()` | Le registre actuel du catalogue, où les fichiers illisibles gardent leur fiche (voir [Le catalogue](catalogue.md#fiches-de-licence)) |
| `changedFiles()` | Les fichiers dont la licence doit être vérifiée, triés |
| `write()` | Écrit `current()` dans le fichier, crée son répertoire et le retourne |
| `path()` | Le chemin du registre |

Un registre qui n'est pas du JSON valide lance `FontUnreadable`, avec `InvalidLicenceRegister` de la bibliothèque comme
exception précédente.

## Dans une construction

```sh
bin/console phil:fonts:licences
```

Chaque fichier à vérifier est listé avec ce qui lui est arrivé (ajouté, retiré, modifié) et la commande se termine avec
le code 1 : une étape d'intégration continue s'arrête donc. Une fois les licences lues, `--write` inscrit les fichiers
dans leur état actuel ; faites un commit du registre avec eux.
