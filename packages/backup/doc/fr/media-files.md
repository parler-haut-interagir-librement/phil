<!-- phil-dev source-sha256=434cd4d54d2736435f9ff90202a907d1d5bf0a7dedd1ff6f7433f81394f4443c -->
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

`write($file)` écrit une archive POSIX ustar, lisible par son seul propriétaire dès son premier octet, et retourne le
nombre de fichiers. L'archive est écrite sous forme de fichier temporaire à côté de `$file`, `.phil-tar-` suivi de
caractères aléatoires, créé en `0600`, et prend son chemin en une seule étape une fois complète ; un échec supprime le
fichier temporaire et laisse `$file` tel qu'il était. `TarWriter` fait de même seul : `close()` donne son chemin à
l'archive, `discard()` y renonce, et un écrivain ni fermé ni abandonné supprime son fichier temporaire quand il est
détruit. L'archive contient :

- chaque répertoire et chaque fichier ordinaire sous chaque répertoire, chaque répertoire suivi de ses entrées triées
  par nom, dans l'ordre des octets, pour que les mêmes fichiers donnent la même archive, avec leurs bits de permission
  et leurs heures de modification ;
- **aucun lien symbolique**, qui pourrait pointer n'importe où sur la machine, ni aucun socket, tube ou périphérique ;
- un répertoire qui n'existe pas encore, sous forme de répertoire vide : une nouvelle installation sans téléversements
  se sauvegarde.

Un chemin de plus de 100 octets est coupé à une barre oblique entre le préfixe de 155 octets de l'archive et son nom ;
un chemin qui ne peut pas être coupé, ou un fichier de 8 Gio ou plus, est refusé, tout comme un fichier dont la taille
change pendant l'archivage. Un répertoire qui ne peut pas être lu en entier est nommé dans l'erreur.

L'arborescence est parcourue un répertoire à la fois, à mesure que l'archive s'écrit : seuls les noms des répertoires
en cours de parcours sont gardés en mémoire, jamais toute l'arborescence, si bien que la mémoire que prend une
archive suit la taille des répertoires sur le chemin, et non le nombre de fichiers du stockage.

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
fichiers. Elle refuse un chemin que l'archive contient deux fois — deux fichiers, deux répertoires, un fichier et un
répertoire au même endroit, ou le même endroit sous deux noms qui mènent à un seul répertoire — : `TarWriter` n'en
écrit jamais, et une restauration garderait celui qui vient en dernier, de sorte qu'une telle archive a été écrite par
autre chose, ou falsifiée. Le message nomme le chemin : `The media archive holds uploads/2026/11/poster.jpg twice.`
Chaque chemin lu est gardé sous forme d'une empreinte de 16 octets jusqu'à la fin de l'archive, de sorte que la
mémoire qu'une vérification prend croît avec le nombre d'entrées — environ cent octets chacune, quelque 100 Mo pour un
million de fichiers —, jamais avec la longueur de leurs noms.

## Le format tar

`TarWriter` et `TarReader` sont écrits à partir de la description du format : un en-tête de 512 octets par entrée, le
contenu complété à 512 octets, deux blocs vides à la fin. Le lecteur accepte les répertoires et les fichiers ordinaires
— un type NUL est un fichier ordinaire, comme dans les anciennes archives — et refuse les liens et toute autre sorte
d'entrée, une archive sans son bloc de fin, un en-tête dont la somme de contrôle ou la signature `ustar` est erronée, et
une archive tronquée. Le contenu que le visiteur du lecteur laisse non lu est sauté sans être lu.

Le bloc de fin est fait de deux blocs vides. Une archive qui ne contient qu'un bloc vide, suivi de rien, d'une partie
de bloc ou de quoi que ce soit d'autre qu'un second bloc vide, est endommagée, et `verify()` et `extract()` la
refusent. Les octets NUL après le bloc de fin, jusqu'à l'enregistrement de 10 240 octets auquel `tar` complète ses
archives, sont ignorés ; tout autre octet après lui, ou plus de remplissage que cela, est refusé.
