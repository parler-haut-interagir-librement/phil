<!-- phil-dev source-sha256=c52ecd470c02679b62839ed3019eff617b95cafd0769cb76c90f2f4118d766cc -->
# Chiffrement

## Clés

Une `BackupKey` est formée de 32 octets aléatoires. `BackupKey::generate()` en tire une, et `toBase64()` l'écrit pour
l'endroit où l'application conserve ses secrets. `BackupKey::fromBase64($value)` la relit, sans tenir compte des blancs
qui l'entourent. Une valeur qui n'est pas formée de 32 octets en base64 standard est refusée avec `InvalidBackupValue` :

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

**Qui détient la clé lit toutes les sauvegardes ; qui la perd n'en lit aucune.** Conservez-la à l'écart des sauvegardes
— dans un magasin de secrets, pas dans le même compartiment de stockage — et gardez-en une copie là où un sinistre qui
emporte le serveur n'emporte pas la clé. Pour changer de clé, gardez l'ancienne jusqu'à ce que le dernier jeu chiffré
avec elle soit sorti de la rotation.

## Le format

`BackupCipher::encrypt($source, $target)` écrit :

| Octets | Contenu |
|---|---|
| 7 | la signature `PHILBAK` |
| 1 | la version du format, `1` |
| 24 | l'en-tête du flux secret de libsodium (XChaCha20-Poly1305) |
| pour chaque bloc : 4 | la longueur du texte chiffré du bloc, gros-boutiste |
| pour chaque bloc : longueur | le texte chiffré d'au plus 64 Kio du fichier, avec ses 17 octets d'étiquette et d'authentification |

Chaque bloc est authentifié avec la signature et la version comme données supplémentaires, et le dernier porte
l'étiquette finale du flux ; un fichier vide est un seul bloc final vide. Un fichier de toute taille passe par une
quantité fixe de mémoire.

## Ce qui est refusé

`decrypt($source, $target)` refuse, avec `BackupFailed` :

- un fichier qui ne commence pas par la signature : `… is not an encrypted backup.` ;
- une autre version : `… is encrypted in a format this version does not read.` ;
- une autre clé, un octet modifié, des blocs réordonnés, un fichier tronqué — même à la frontière d'un bloc, puisque
  l'étiquette finale manque alors —, ou des octets après le bloc final :
  `… cannot be decrypted: the key is not the one it was encrypted with, or the file is damaged.`

La cible reçoit les blocs à mesure qu'ils sont authentifiés : en cas d'échec, elle contient un début qui ne doit pas
servir. `Backups` déchiffre dans un fichier temporaire qu'il supprime quoi qu'il arrive, et ne restaure rien à partir
d'un fichier en échec.

## Jeux chiffrés

Avec un chiffreur, `Backups::create()` écrit chaque fichier du jeu dans un fichier temporaire en clair, le chiffre à sa
place avec l'extension `.enc` et supprime la copie en clair. Un jeu se lit d'après l'extension de ses fichiers : sans
clé, un jeu en clair se restaure, et un jeu chiffré est refusé avec
`The backup … is encrypted, and no backup key is given.`

**Avec une clé, un fichier en clair est refusé**, que `find()` le reçoive ou que `restore()` ou `verify()` le lise, avec
`The backup … is in clear, and a backup key is given: sets in clear are refused unless they are allowed.` Quiconque
peut écrire dans le stockage des sauvegardes aurait pu l'y déposer, et rien ne l'authentifie : un vidage daté dans le
futur deviendrait sinon le jeu le plus récent, et une archive en clair à côté d'un vrai `.sql.enc` écrirait des
fichiers choisis dans les répertoires de médias. `find(null)` ne regarde que les jeux chiffrés, et un fichier de base de
données n'est apparié qu'à une archive des médias de même chiffrement. Le sixième argument de `Backups`,
`$allowUnencrypted`, accepte de nouveau les jeux en clair, pour ceux écrits avant que la clé soit donnée.
