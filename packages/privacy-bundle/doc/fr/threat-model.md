<!-- phil-dev source-sha256=3701a74c82c5a2cadc537548ae9aaa37a977e37c3197a252f7a055f39f66d0a8 -->
# Modèle de menaces

Ce que le bundle protège, de qui, comment, et ce qu'il laisse à l'application. Il garde des données personnelles peu
de temps (demandes sans compte, archives d'export), signe le consentement du visiteur et écrit les registres qu'un
organisme de réglementation peut demander (preuves de consentement, registre des incidents) : chacun est un actif
ci-dessous.

## Actifs

| Actif | Où il se trouve | Ce qui le protège |
|---|---|---|
| L'adresse d'une demande sans compte | `phil_privacy_guest_request` | Scellée par le `secretbox` de libsodium sous la clé `phil_privacy.guest_request_store` ; retirée avec la demande. |
| Le secret du lien d'une demande, du lien de progression d'un effacement | `phil_privacy_guest_request`, `phil_privacy_erasure_request` | 256 bits aléatoires, gardés sous forme de SHA-256, comparés en temps constant ; le lien fonctionne une fois et expire. |
| Un export des données de quelqu'un | `account_deletion.export_directory` | Un fichier `0600` d'un répertoire `0700`, scellé par `secretbox` sous la clé `phil_privacy.export_archive` ; refusé dès son expiration, avant d'être ouvert, et retiré par le balayage suivant. |
| Le choix de témoins du visiteur | Le témoin de consentement | Signé par HMAC-SHA-256 sous la clé `phil_privacy.consent` ; une valeur qui ne se vérifie pas se lit comme aucun choix. |
| Les adresses et les IP qui demandent des liens | Le réservoir de cache de `guest_requests.cache` | Jamais écrites : chaque limite a pour clé un HMAC-SHA-256 sous une clé à elle. |
| Les valeurs personnelles dans les journaux | Chaque enregistrement Monolog | Remplacées par `[redacted]` avant que tout handler les écrive ([Caviardage des journaux](log-redaction.md)). |
| Les preuves de consentement, le registre des incidents | `phil_privacy_consent_proof`, `phil_privacy_incident` et ses tables | Ne nomment personne au-delà de ce que la loi demande ; gardés pendant leur période légale ; les corrections sont gardées comme révisions. |

## Les clés

Chaque clé dont le bundle a besoin vient du secret de l'application (`kernel.secret`) par HKDF-SHA-256 (RFC 5869), par
`Domain\Secret\KeyDerivation`, avec une étiquette par usage :

| Étiquette | Usage |
|---|---|
| `phil_privacy.guest_request_store` | Scelle l'adresse d'une demande sans compte (`DbalGuestRequests`). |
| `phil_privacy.guest_requests.client` | Sert de clé à la limite par client des demandes sans compte (`GuestRequests`). |
| `phil_privacy.guest_requests.address` | Sert de clé à la limite par adresse des demandes sans compte (`GuestRequests`). |
| `phil_privacy.export_archive` | Scelle une archive d'export (`EncryptedExportArchive`). |
| `phil_privacy.consent` | Signe le témoin de consentement (`ConsentToken`). |

**Une étiquette par usage garde les clés sans lien entre elles.** Une clé qui fuit d'un usage, une signature que le
témoin de consentement montre à chaque visiteur, ou un condensé du stockage du limiteur, ne donne rien sur la clé qui
scelle les adresses ou les archives. HKDF est la dérivation faite pour cela : le secret est sa matière de clé en entrée
et l'étiquette son `info`, là où un HMAC dont la clé est une étiquette et le message le secret prend le secret comme
message et l'étiquette comme clé.

**Un secret vide est refusé.** `KeyDerivation::key()` lance `InvalidPrivacyValue` plutôt que de dériver des clés que
n'importe qui peut calculer ; les services qui ont besoin d'une clé échouent quand ils sont construits.

**Changer le secret change chaque clé.** Les demandes sans compte, les archives d'export et les témoins de consentement
de l'ancien secret se lisent comme absents ou comme aucun choix, et les limites repartent de zéro ; rien d'autre n'est
scellé avec ces clés. Le bundle ne lit aucun secret précédent : une rotation est une coupure nette, et ces actifs
vivent des heures ou des jours, pas des années.

## Frontières de confiance

1. **Le navigateur vers les pages du bundle.** Les formulaires des demandes sans compte, les pages de progression et les
   préférences lisent une entrée non fiable : une adresse passe par `phil/email-address`, un identifiant doit avoir sa
   forme, un secret est comparé en temps constant, chaque action est un `POST` avec un jeton CSRF, et ouvrir un lien
   montre seulement une page, de sorte qu'un analyseur de courriels qui le suit ne fait rien.
2. **Le navigateur vers le consentement.** Le témoin et `Sec-GPC` ne sont pas fiables. Un témoin plus long que
   `ConsentToken::MAX_LENGTH`, mal formé ou signé sous une autre clé se lit comme aucun choix, ce qui refuse chaque
   catégorie facultative ; rien de ce qu'envoie un navigateur ne lève d'erreur. Le décodage est soumis au fuzzing
   (`composer fuzz consent-token`).
3. **Le bundle vers la base de données.** Fiable pour l'intégrité, non pour le secret de ce qui est scellé : une copie
   de `phil_privacy_guest_request` ne donne aucune adresse sans le secret. Une ligne qui ne peut pas être ouverte se
   lit comme absente.
4. **Le bundle vers le système de fichiers.** Le répertoire d'export doit garder ses permissions ; un fichier qui ne
   s'ouvre pas, ou qui est plus court que son nonce, se lit comme absent.
5. **Les modules vers le bundle.** Les exporteurs et les effaceurs de l'application sont du code fiable : le bundle
   borne ce qu'ils renvoient dans un export et consigne ce que chacun rapporte, mais croit leur rapport.

## Menaces et défenses

| Menace | Défense |
|---|---|
| Savoir si une adresse est connue | Une demande reçoit la même réponse, que l'adresse soit connue ou non ; ce que trouve le lien est décidé une fois qu'il est ouvert. |
| Remplir la boîte aux lettres de quelqu'un de liens | La limite par adresse abandonne les liens en trop sans rien dire. |
| Un déluge de demandes | La limite par client répond `429` avec `Retry-After`, comptée avant que quoi que ce soit soit lu. |
| Réutiliser ou deviner un lien | 256 bits aléatoires, un seul usage, une vie courte, comparaison en temps constant. |
| Accorder une catégorie de témoins en écrivant le témoin | Le HMAC de la valeur ; un script ou un sous-domaine sans le secret ne peut pas signer. |
| Lire des adresses ou des exports dans une copie de la base de données ou d'une sauvegarde | `secretbox` sous des clés dérivées d'un secret que la copie ne contient pas. |
| Exécuter deux fois en même temps un export ou un effacement | Un verrou nommé d'après la demande, relu une fois obtenu, renouvelé avant chaque module. |
| Des données personnelles dans les journaux, les billets et les artefacts de CI | Le processeur de caviardage, enregistré dans chaque environnement. |

## Risques laissés à l'application

- **Le secret lui-même.** Qui détient `kernel.secret` peut ouvrir les demandes et les archives et signer un témoin de
  consentement. Gardez-le hors du dépôt (le coffre de secrets de Symfony ou une variable d'environnement), long et
  aléatoire.
- **Les données que gardent les modules.** Le bundle efface par les effaceurs que l'application écrit ; une table
  qu'aucun effaceur ne couvre garde ses données.
- **Le transport des liens.** Un lien vers une demande est envoyé par courriel : qui lit la boîte aux lettres peut
  l'ouvrir.
- **Le reste de la page.** Le bundle signe le consentement ; l'application ne charge un script tiers que par
  `privacy_consent()` ou `privacy_embed()`.
