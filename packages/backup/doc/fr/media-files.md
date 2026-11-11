<!-- phil-dev source-sha256=b600a422f32eb64d074aa3c35fe2220d0d8e09469662eaf9a7d08b28a97378c0 -->
# Fichiers médias

## Répertoires nommés

`MediaArchive` reçoit les répertoires de médias de l'application, chacun sous un nom :

```php
new MediaArchive([
    'uploads' => '/srv/shop/public/uploads',
    'invoices' => '/srv/shop/var/invoices',
]);
```

Un nom est formé de lettres, de chiffres, de `-` et de `_` ; tout autre nom est refusé avec `InvalidBackupValue`.
L'archive contient les fichiers de chaque répertoire sous son nom — `uploads/2026/11/poster.jpg` — et jamais sous son
chemin : une sauvegarde faite sur une machine se restaure ainsi sur une autre dont les répertoires sont ailleurs, pourvu
que les noms soient les mêmes.

## Ce qui est archivé

`write($file)` écrit une archive POSIX ustar, lisible par son seul propriétaire, et retourne le nombre de fichiers :

- chaque répertoire et chaque fichier ordinaire sous chaque répertoire, triés par chemin pour que les mêmes fichiers
  donnent la même archive, avec leurs bits de permission et leurs heures de modification ;
- **aucun lien symbolique**, qui pourrait pointer n'importe où sur la machine, ni aucun socket, tube ou périphérique ;
- un répertoire qui n'existe pas encore, sous forme de répertoire vide : une nouvelle installation sans téléversements
  se sauvegarde.

Un chemin de plus de 100 octets est coupé à une barre oblique entre le préfixe de 155 octets de l'archive et son nom ;
un chemin qui ne peut pas être coupé, ou un fichier de 8 Gio ou plus, est refusé, tout comme un fichier dont la taille
change pendant l'archivage. Un répertoire qui ne peut pas être lu en entier est nommé dans l'erreur.

## Réécriture

`extract($file)` lit d'abord toute l'archive — chaque somme de contrôle d'en-tête, chaque chemin — et la refuse, avant
d'écrire quoi que ce soit, lorsqu'un chemin nomme un répertoire qui n'a pas été donné à l'archive, ou contient un
segment vide, `.` ou `..`. Chaque fichier est ensuite écrit dans son répertoire :

- par-dessus le fichier de même chemin, avec les bits de permission et l'heure de modification qu'il avait à
  l'archivage ;
- un lien symbolique qui se trouve à la place d'un fichier est supprimé, jamais suivi pour écrire ;
- un répertoire qui sort de son répertoire de médias par un lien est refusé ;
- les répertoires manquants sont créés.

Les fichiers que l'archive ne contient pas sont laissés : une restauration ramène ce qui a été sauvegardé et laisse ce
qui a été ajouté depuis.

`prune($file)` les supprime, après `extract()`, pour que chaque répertoire soit laissé tel qu'il a été sauvegardé. Il
lit d'abord toute l'archive — un chemin qui ne nomme aucun répertoire configuré est refusé avant toute suppression.
Ensuite, dans chaque répertoire que nomme l'archive, il supprime chaque fichier, lien symbolique et fichier spécial que
l'archive ne contient pas — le lien lui-même et jamais ce vers quoi il pointe —, puis les répertoires laissés vides, les
plus profonds d'abord. Un répertoire que contient l'archive est conservé même vide, et un répertoire configuré que
l'archive ne nomme pas est laissé en entier. Il retourne le nombre d'entrées supprimées, répertoires exclus. L'élagage
est désactivé sauf s'il est demandé : un fichier téléversé après la sauvegarde disparaît dès qu'il s'exécute, alors
faites d'abord une sauvegarde du contenu actuel.

`verify($file)` lit toute l'archive, comme la première étape de `extract()`, sans rien écrire, et retourne le nombre de
fichiers.

## Le format tar

`TarWriter` et `TarReader` sont écrits à partir de la description du format : un en-tête de 512 octets par entrée, le
contenu complété à 512 octets, deux blocs vides à la fin. Le lecteur accepte les répertoires et les fichiers ordinaires
— un type NUL est un fichier ordinaire, comme dans les anciennes archives — et refuse les liens et toute autre sorte
d'entrée, une archive sans son bloc de fin, un en-tête dont la somme de contrôle ou la signature `ustar` est erronée, et
une archive tronquée. Le contenu que le visiteur du lecteur laisse non lu est sauté sans être lu.
