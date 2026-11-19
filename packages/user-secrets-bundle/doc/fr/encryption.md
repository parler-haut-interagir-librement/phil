<!-- phil-dev source-sha256=c10655ebc20c5e59f076af2806f7278f4faaeb25755910423a8631298e69f7f0 -->
# Chiffrement et rotation des clés

## Ce qui est scellé

Ce qui est scellé est un document JSON : le propriétaire, le nom, le service et la nature de la ligne, et l'identifiant
de connexion. À l'ouverture, ces quatre champs sont comparés à la ligne. Le chiffrement authentifie le texte chiffré,
pas l'endroit où il se trouve. Sans cette liaison, quiconque peut écrire dans la table pourrait copier le texte chiffré
d'une personne dans la ligne d'une autre, et les tâches de la seconde utiliseraient la clé de la première. Avec elle,
la copie échoue avec `UnreadableSecret`.

Le reste de la ligne — le service, la nature, les dates, l'expiration — est en clair : c'est ce que le propriétaire
voit dans la liste de ses secrets, et ce dont un administrateur a besoin pour répondre à une question sans rien
déchiffrer.

## Le format scellé

La colonne `ciphertext` contient des octets en base64 standard. Leur premier octet est la version du format, qui indique
à un lecteur comment les ouvrir. La colonne `format` le répète quand il nomme un format connu, pour que la rotation des
clés trouve en SQL les valeurs d'un autre format. La colonne `key_id` nomme la clé maîtresse qui a scellé la valeur.

| Premier octet | Format | Disposition après l'octet | Écrit |
|---|---|---|---|
| `3` | `SealedFormat::Algorithm` | l'octet de l'algorithme, puis ce que l'algorithme a écrit | chaque nouvelle valeur |
| `2` | `SealedFormat::KeyManagement` | une enveloppe autonome du composant KeyManagement de Symfony, AES-256-GCM sous-entendu | jamais ; lu |

Dans le format courant, le deuxième octet nomme l'algorithme qui a scellé la valeur :

| Deuxième octet | Algorithme | Ce qui suit |
|---|---|---|
| `1` | `SealedAlgorithm::KeyManagementAes256Gcm` | une enveloppe autonome du composant KeyManagement de Symfony : la clé de données enveloppée par le KMS, la valeur chiffrée avec AES-256-GCM |

Les deux octets de l'en-tête font partie des données authentifiées supplémentaires : un en-tête changé pour l'ancien
format ou pour un autre algorithme ne s'ouvre pas. Une valeur dont le premier octet ne nomme aucun format connu, ou dont
le deuxième ne nomme aucun algorithme connu, échoue avec `UnreadableSecret`. Les valeurs de l'ancien format s'ouvrent
telles quelles, et la rotation des clés les scelle de nouveau dans le format courant.

## Le chiffrement par enveloppe avec KeyManagement

Le scellement et l'ouverture passent par `Application\Contract\EnvelopeCipher`, dont l'unique implémentation,
`Infrastructure\KeyManagement\KeyManagementCipher`, est la seule classe qui appelle le composant KeyManagement de
Symfony. Le composant est expérimental dans Symfony 8.2 : son API peut changer dans une version mineure, hors de la
promesse de rétrocompatibilité de Symfony. Ses changements restent dans cet adaptateur, et l'en-tête du format nomme
l'algorithme : un chiffrement ultérieur peut donc se placer à côté.

Chaque nouvelle valeur est scellée par `Symfony\Component\KeyManagement\EnvelopeEncrypter`. Une nouvelle clé de
données de 32 octets est créée pour chaque valeur et enveloppée par le KMS sous la clé maîtresse que nomme
`current_key`. La valeur est chiffrée localement avec AES-256-GCM sous la clé de données. L'enveloppe conserve
l'identifiant de la clé maîtresse, la clé de données enveloppée, le *nonce*, l'étiquette et le texte chiffré. La clé
maîtresse ne quitte jamais le KMS ; seule la clé de données enveloppée lui est envoyée.

L'en-tête et la ligne — l'octet du format, l'octet de l'algorithme, puis propriétaire, nom, service et nature, sous
forme de liste JSON — constituent les données authentifiées supplémentaires de l'enveloppe : une valeur copiée dans une
autre ligne ne s'ouvre pas, avant même que son contenu soit lu. Dans l'ancien format, seule la ligne l'était.

`key_id` conserve l'identifiant de la clé maîtresse tel que l'écrit la configuration — et non un identifiant que le
KMS pourrait renvoyer pour elle, comme un alias résolu. C'est celui que la rotation des clés compare à `current_key`.

## Le KMS

Le bundle scelle par un client du composant : le client par défaut de KeyManagementBundle — l'alias
`Symfony\Component\KeyManagement\DataKeyGeneratorInterface`, le seul client ou celui que nomme
`key_management.default_client` —, ou le service que nomme l'option `kms`, toute `DataKeyGeneratorInterface` comme
`key_management.<client>`. Un client se déclare sous `key_management.clients` par un DSN : AWS KMS, Azure Key Vault,
Google Cloud KMS, HashiCorp Vault Transit, Scaleway, KMIP par leurs ponts, ou un KMS local. `current_key` est une clé
maîtresse de ce client. Sans client, ou avec un `kms` qui ne nomme aucun service, le conteneur s'arrête avec un message
qui dit quoi changer.

### Un KMS local : `sodium://`

Sans KMS à elle, une application déclare le KMS local libsodium du composant, qui enveloppe chaque clé de données avec
XChaCha20-Poly1305 sous une clé maîtresse de son DSN :

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[k2026]=%env(USER_SECRETS_KEY_2026)%'
```

Chaque clé maîtresse fait 32 octets aléatoires en base64url, comme les affiche
`bin/console phil:user-secrets:generate-key`, et se lit de préférence dans une variable d'environnement ou dans le
coffre des secrets de Symfony. Plusieurs clés tiennent dans un seul DSN — `sodium://?keys[k2026]=…&keys[k2027]=…` —,
et `sodium+dir:///etc/keys` lit plutôt chaque clé maîtresse dans un fichier de ce répertoire. Les clés sont lues à la
première utilisation du client, une fois les variables d'environnement résolues.

Un KMS injoignable ou qui refuse échoue avec `UnreadableSecret` à l'ouverture, et avec `SealingFailed` au scellement.
L'exception du composant ou de son client devient l'exception précédente, et son message est repris : une panne se lit
donc toujours comme une panne. Une valeur dont le KMS ne détient plus la clé maîtresse échoue aussi avec
`UnreadableSecret`, car le KMS ne distingue pas une clé inconnue d'une valeur altérée ; le scellement sous une clé
maîtresse que le KMS ne détient pas échoue avec `UnknownKey`.

## La rotation des clés

La rotation des clés scelle de nouveau sous la clé maîtresse courante, dans le format courant, chaque valeur scellée par
une autre clé maîtresse ou qui repose dans un ancien format.

1. Créez une nouvelle clé maîtresse : générez-en une avec `bin/console phil:user-secrets:generate-key` et ajoutez-la
   au DSN `sodium://` à côté de l'ancienne, ou créez une clé maîtresse dans le KMS de l'application.
2. Faites-en la `current_key`. Les nouvelles valeurs sont scellées sous elle aussitôt ; les anciennes s'ouvrent encore
   sous l'ancienne clé, que le KMS conserve.
3. Scellez de nouveau chaque valeur :

   ```sh
   bin/console phil:user-secrets:rotate --batch=500
   ```

   La commande lit les secrets lot par lot, en une seule passe par propriétaire et par nom, et écrit chaque valeur par
   une comparaison avant écriture sur la valeur scellée qu'elle a lue, comme le fait l'actualisation d'un jeton : un
   secret qu'un autre processus a stocké, actualisé ou révoqué entre la lecture et l'écriture reste tel que ce
   processus l'a laissé, et il est compté à part. La relancer ne scelle plus rien. Les valeurs de l'ancien format sont
   scellées de nouveau dans le format courant en chemin, sous la même clé ou non. Les secrets révoqués n'ont pas de
   valeur et sont sautés. Chaque secret scellé de nouveau produit un événement `rekeyed` pour les auditeurs.

   Une valeur qui ne s'ouvre pas — sa clé maîtresse absente du KMS, copiée d'une autre ligne, d'aucun format connu —
   reste telle quelle et est nommée par propriétaire et par nom ; la rotation continue avec les autres, et la commande
   se termine en échec. Seul son propriétaire peut stocker de nouveau un tel secret ; sinon, on le révoque.
4. Comptez ce qui reste :

   ```sh
   bin/console phil:user-secrets:rotate --dry-run
   ```

   L'essai à blanc n'écrit rien : il compte les secrets qui ne sont pas encore scellés sous la clé courante, ou pas
   encore dans le format courant, par clé qui les a scellés.
5. Retirez l'ancienne clé maîtresse — enlevez-la du DSN, ou désactivez-la dans le KMS — seulement quand l'essai à
   blanc ne compte plus aucun secret, et une fois réglée la durée de conservation des sauvegardes que l'ancienne clé
   ouvre encore : une sauvegarde prise avant la rotation contient des valeurs scellées sous l'ancienne clé, et la
   restaurer demande cette clé.

`KeyRotation::rotate($batchSize)` fait la même chose depuis le code et rend un `RotationReport` — les secrets scellés
de nouveau, ceux sautés parce qu'un autre processus les a changés, ceux qui ne se sont pas ouverts — ;
`KeyRotation::remaining()` compte ce qui reste, par clé.

La commande `key-management:rewrap-data-keys` du composant enveloppe de nouveau les clés de données de son magasin de
clés de données sous un autre client sans toucher aux charges utiles ; les enveloppes du bundle portent leur clé de
données enveloppée : leur rotation est donc la commande ci-dessus.

## Ce contre quoi il ne protège pas

Le chiffrement protège les valeurs dans la base de données, ses sauvegardes et ses répliques, contre quiconque les lit
sans les clés. Il ne les protège pas contre l'application elle-même, qui peut utiliser les clés : du code qui peut
appeler `UserSecrets` avec un `SecretAccess` peut lire les secrets du propriétaire que celui-ci nomme. Gardez les clés
hors du dépôt et à l'écart des sauvegardes de la base de données, et ne construisez des accès que là où le
propriétaire est connu.

Révoquer ou supprimer un secret l'efface de la table, pas des sauvegardes prises avant : il y reste scellé, et lisible
par quiconque détient ces sauvegardes et la clé maîtresse qui l'a scellé, tant que vit cette clé. Un secret n'a
disparu de toutes les copies qu'une fois expirées les sauvegardes qui le contiennent, ou détruite la clé maîtresse qui
l'a scellé.
