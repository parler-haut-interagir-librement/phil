<!-- phil-dev source-sha256=ff15deedead83013194bf92d2aae655702a596b03f5914a8e5c01eb58c7ecf97 -->
# Sécurité

## XML

`SafeXml` lit chaque document venu de l'extérieur — une réponse, un message de déconnexion, des métadonnées, une
assertion déchiffrée :

- le texte est en UTF-8, sans octet NUL, et une déclaration XML ne nomme aucun autre encodage, de sorte que les
  contrôles qui suivent lisent ce que l'analyseur lit ;
- un DOCTYPE ou une déclaration d'entité est refusé avant l'analyse : aucune entité externe n'est résolue, aucune
  entité n'est développée ; le réseau n'est jamais atteint (`LIBXML_NONET`) ;
- une instruction de traitement ou une référence d'entité est refusée après l'analyse ;
- les octets, la profondeur des éléments et le nombre de nœuds sont bornés par un `XmlBudget` : 256 Kio, 32 niveaux et
  10 000 nœuds pour un message, 4 Mio, 48 niveaux et 200 000 nœuds pour des métadonnées ;
- un message sur HTTP-Redirect est décompressé dans les limites des octets du budget, de sorte qu'une petite requête ne
  puisse se développer en un grand document.

Un commentaire à l'intérieur d'une valeur ne cache rien : le texte d'un élément est l'ensemble de ses nœuds de texte,
le commentaire omis, ce qui est aussi ce que contient la forme canonique que couvre la signature.

## Signatures

`SignatureVerifier` vérifie une signature enveloppée sur un élément et n'accepte rien de plus souple :

- exactement un enfant `ds:Signature`, avec un `SignedInfo`, une `SignatureValue` et exactement une `Reference` ;
- l'`URI` de la référence est `#` suivi de l'`ID` de cet élément, et cet `ID` mène, dans tout le document, à cet élément
  même ;
- canonicalisation par C14N exclusive sans commentaires, avec sa liste de préfixes `InclusiveNamespaces` ; les
  transformations sont la signature enveloppée et la C14N exclusive, une fois chacune — aucun XPath, XSLT ou autre
  transformation ne s'exécute ;
- RSA avec SHA-256, SHA-384 ou SHA-512, des empreintes de la même famille ; SHA-1 refusé sauf `SignaturePolicy::$allowSha1` ;
- les certificats de confiance du fournisseur d'identité vérifient la signature, jamais une clé ou un certificat que la
  signature porte dans son `KeyInfo` ; un certificat dont la clé RSA est plus courte que 2048 bits est refusé ;
- l'empreinte est comparée en temps constant.

Le document est laissé tel qu'il a été trouvé : la signature retirée pour calculer l'empreinte est remise à sa place.

## Enveloppement de signature

L'enveloppement de signature déplace l'élément signé ailleurs dans le document et plante un élément falsifié là où le
lecteur regarde. `ResponseProcessor` ne lui laisse rien à exploiter :

- chaque `ID` du document est unique, de sorte qu'une référence ne puisse mener à une copie ;
- le document contient exactement une assertion, en clair ou chiffrée, et c'est un enfant direct de la réponse : une
  assertion cachée dans une extension, dans `Advice`, dans l'`Object` d'une signature ou ailleurs est refusée, même non
  signée ;
- la signature vérifiée est celle que l'assertion, ou la réponse, tient comme son propre enfant, en se référençant
  elle-même ;
- ce qui est lu l'est dans l'élément vérifié, par ses enfants directs, jamais par une recherche dans le document.

Les tests rejouent chacune de ces attaques à partir de jeux de données d'essai ; voir [Tests](testing.md).

## Les contrôles du profil

Statut, émetteur (avec le format `entity`, quand l'un est nommé), destination, destinataire, audience, la requête à
laquelle répondent la réponse et la confirmation, `NotBefore`, `NotOnOrAfter`, les instants d'émission, la fin de la
session : voir [Connexion](sign-in.md#la-réponse). Les adresses sont comparées sous la forme normalisée de phil/url, de
sorte qu'une différence de casse dans l'hôte ou un port par défaut ne fasse pas refuser une réponse, et que rien
d'autre ne passe.

## Rejeu

`ReplayGuard::firstUse()` retient l'ID de chaque assertion acceptée, sous son émetteur, jusqu'à la dernière de ses
expirations plus le décalage d'horloge : une assertion présentée de nouveau est refusée avec `ReplayedAssertion`.
`DbalReplayGuard` la garde dans une table de base de données dont la clé primaire fait de la première utilisation une
insertion atomique, sur un nombre quelconque de serveurs ; `CacheReplayGuard` la garde dans une réserve PSR-6 partagée
par chaque *worker* et chaque serveur, où une réserve vidée au déploiement oublie les assertions encore valides, et où
une réserve locale à chaque serveur laisse un autre serveur accepter de nouveau une assertion. Voir
[Le magasin de rejeu](replay-store.md).
`PendingRequests::take()` oublie une requête en la retournant, de
sorte qu'une réponse ne réponde à une requête qu'une fois.

## Chiffrement

`Decrypter` lit les transports de clé RSA-OAEP avec MGF1 SHA-1 (`rsa-oaep-mgf1p`) et `xmlenc11#rsa-oaep` avec la même
empreinte pour OAEP et MGF1 — jamais RSA PKCS#1 v1.5, exposé à l'attaque de Bleichenbacher — et les données en AES-128,
192 ou 256, en GCM ou en CBC. Le CBC, que Microsoft Entra ID et Okta envoient par défaut, est exposé aux attaques par
oracle de remplissage quand la réponse distingue un mauvais remplissage d'un autre échec : chaque échec de déchiffrement
au-delà des algorithmes se lit de la même façon, l'assertion à l'intérieur doit être signée (une signature sur la
réponse seule couvre les données chiffrées, ce qui suffit à arrêter un texte chiffré modifié), et GCM est le choix à
faire là où le fournisseur d'identité l'offre.

## Clés

`PrivateKey` garde son PEM et sa phrase secrète hors de `var_dump()` et des traces de pile. `KeyPair` vérifie que la clé
est celle du certificat. La bibliothèque ne signe qu'avec RSA-SHA256 et plus, jamais SHA-1.

## Messages et journaux

Le message de chaque `SamlException` nomme ce qui a été refusé — jamais un identifiant de nom, une valeur d'attribut ou
une clé — de sorte qu'on puisse le journaliser.

## Ce qui revient à l'application

- Garder les clés privées hors du dépôt et hors de la racine Web ; les renouveler, le certificat suivant publié dans les
  métadonnées avant qu'il signe.
- Une garde de rejeu partagée par chaque serveur — la table de `DbalReplayGuard`, ou une réserve de cache partagée —, et
  une réserve de cache partagée pour les requêtes en attente.
- Lier une requête au navigateur qui l'a envoyée, ce que le bundle fait avec un témoin.
- Décider de ce qu'un compte peut voir à sa première connexion : les attributs viennent du fournisseur d'identité d'une
  organisation, et une revendication de groupe ne vaut que ce que vaut sa configuration là-bas.
