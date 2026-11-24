<!-- phil-dev source-sha256=893be4c62556fdf90883c4378b200c4dc9b9e25b2c4d75789a46314fc5409297 -->
# Référence

Chaque type public du paquet, avec son rôle. L'espace de noms est `Phil\MailAuthCheck`.

## Vérifier un profil

| Type | Rôle |
|---|---|
| `SenderProfile` | Ce qu'un expéditeur attend : domaine d'envoi, chemin de retour, sélecteurs DKIM, inclusions SPF, politique DMARC minimale. Noms en ASCII minuscule, doublons retirés, au plus 16 sélecteurs et 16 inclusions. |
| `MailAuthCheck` | Exécute dans l'ordre les vérifications d'un profil et réunit leurs constats ; `defaultChecks()` donne SPF, DKIM, DMARC et chemin de retour. |
| `Report` | Les constats d'un profil : `canSend()`, `failures()`, `warnings()`, `forCheck()`. |
| `Finding` | Un constat : sa vérification, son `Status`, son code stable et son message. |
| `Status` | `Pass`, `Warning` ou `Fail` ; un échec empêche l'envoi. |
| `DmarcPolicy` | `None`, `Quarantine`, `Reject` ; `isAtLeast()` compare leur rigueur. |
| `Alignment` | `Relaxed` ou `Strict`, pour l'alignement SPF et DKIM. |

## Vérifications

| Type | Rôle |
|---|---|
| `Contract\Check` | Le port d'une vérification : `run(SenderProfile, TxtResolver): list<Finding>`. |
| `Check\SpfCheck` | L'enregistrement SPF du domaine d'envoi (`spf`). |
| `Check\DkimCheck` | La clé DKIM de chaque sélecteur attendu (`dkim`). |
| `Check\DmarcCheck` | L'enregistrement DMARC qui s'applique au domaine d'envoi, sa politique et l'alignement du chemin de retour (`dmarc`). |
| `Check\ReturnPathCheck` | L'enregistrement SPF du chemin de retour quand il diffère du domaine d'envoi (`return-path`). |

## DNS

| Type | Rôle |
|---|---|
| `Contract\TxtResolver` | Le port qui lit les enregistrements TXT d'un nom. |
| `Dns\DnsGetRecordResolver` | Interroge le résolveur du système par `dns_get_record()` ; au plus 32 enregistrements pour un nom. |
| `Dns\StaticTxtResolver` | Répond à partir d'une table de noms, et échoue pour les noms qu'on lui indique. |

## Enregistrements

| Type | Rôle |
|---|---|
| `Spf\SpfParser` | Lit un enregistrement SPF : au plus 4096 octets et 64 termes ; `isSpf()` distingue un enregistrement SPF des autres enregistrements TXT. |
| `Spf\SpfRecord`, `Spf\SpfMechanism`, `Spf\Qualifier` | Un enregistrement SPF lu : ses mécanismes dans l'ordre, `redirect` et `exp`. |
| `Spf\SpfAnalyser` | Suit les inclusions et la redirection d'un enregistrement et compte ses requêtes DNS, en s'arrêtant à la onzième. |
| `Spf\SpfAnalysis`, `Spf\SpfState` | Ce que l'analyse a trouvé : état, requêtes, inclusions atteintes, boucles, inclusions brisées, macros, résultat de `all`. |
| `Dkim\DkimKeyParser` | Lit un enregistrement de clé DKIM et mesure sa clé. |
| `Dkim\DkimKey`, `Dkim\DkimKeyType` | Une clé DKIM lue : RSA ou Ed25519, sa taille, révoquée, en essai. |
| `Dmarc\DmarcParser` | Lit un enregistrement DMARC ; `isDmarc()` distingue un enregistrement DMARC des autres enregistrements TXT. |
| `Dmarc\DmarcRecord` | Un enregistrement DMARC lu : `p`, `sp`, `np`, `aspf`, `adkim`, `t`, `psd`, `rua` ; `policyFor()` donne la politique du domaine ou de ses sous-domaines. |
| `Dmarc\DmarcTreeWalk` | La marche dans l'arbre DNS de la RFC 9989 : l'enregistrement qui s'applique à un domaine, et son domaine organisationnel, en huit requêtes au plus. |
| `Dmarc\DmarcDiscovery` | Ce que la marche a trouvé : l'enregistrement, où, les enregistrements écartés en chemin, une requête échouée. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\MailAuthCheckException` | L'interface que toute exception du paquet implémente. |
| `Exception\InvalidDomain` | Un nom de profil qui n'est pas un nom d'hôte valide, ou un nom internationalisé sans ext-intl. |
| `Exception\InvalidProfile` | Un profil de plus de 16 sélecteurs ou inclusions. |
| `Exception\InvalidRecord` | Un enregistrement que les analyseurs ne peuvent pas lire ; le message dit pourquoi. |
| `Exception\DnsLookupFailed` | Un résolveur qui n'a pas pu répondre. |

## Codes de constat

Les codes du chemin de retour sont ceux de SPF avec `return-path.` à la place de `spf.`, plus `return-path.same-domain`.

| Code | État | Signification |
|---|---|---|
| `spf.lookup-failed` | Échec | L'enregistrement SPF n'a pas pu être lu. |
| `spf.missing` | Échec | Aucun enregistrement TXT ne commence par `v=spf1`. |
| `spf.multiple` | Échec | Plus d'un enregistrement SPF. |
| `spf.invalid` | Échec | L'enregistrement ne peut pas être lu. |
| `spf.too-many-lookups` | Échec | Plus de dix requêtes DNS. |
| `spf.lookups-near-limit` | Avertissement | Neuf ou dix requêtes. |
| `spf.lookups` | Réussite | Les requêtes dont l'enregistrement a besoin, huit ou moins. |
| `spf.include-loop` | Échec | Un domaine est inclus de nouveau à l'intérieur de lui-même. |
| `spf.include-broken` | Échec | Un domaine inclus ou redirigé n'a aucun enregistrement utilisable. |
| `spf.macro` | Avertissement | Un domaine à macro, compté mais pas suivi. |
| `spf.include-missing` | Échec | Une inclusion attendue n'est atteinte nulle part dans l'enregistrement. |
| `spf.include` | Réussite | Une inclusion attendue est atteinte. |
| `spf.all-pass` | Échec | L'enregistrement se termine par `+all`. |
| `spf.all-neutral` | Avertissement | L'enregistrement se termine par `?all`. |
| `spf.no-all` | Avertissement | L'enregistrement n'a ni `all` ni redirection qui en donne un. |
| `spf.all-softfail` | Réussite | L'enregistrement se termine par `~all`. |
| `spf.all-fail` | Réussite | L'enregistrement se termine par `-all`. |
| `return-path.same-domain` | Réussite | Le chemin de retour est le domaine d'envoi. |
| `dkim.no-selector` | Avertissement | Le profil n'attend aucun sélecteur DKIM. |
| `dkim.lookup-failed` | Échec | La clé n'a pas pu être lue. |
| `dkim.missing` | Échec | Aucun enregistrement au sélecteur. |
| `dkim.multiple` | Échec | Plus d'un enregistrement TXT au sélecteur. |
| `dkim.invalid` | Échec | L'enregistrement de clé ne peut pas être lu. |
| `dkim.revoked` | Échec | La clé est vide. |
| `dkim.testing` | Avertissement | La clé dit `t=y`. |
| `dkim.key-too-short` | Échec | RSA sous 1024 bits. |
| `dkim.key-short` | Avertissement | RSA sous 2048 bits. |
| `dkim.ok` | Réussite | RSA de 2048 bits ou plus, ou Ed25519. |
| `dkim.ed25519-only` | Avertissement | Toutes les clés attendues sont Ed25519. |
| `dmarc.lookup-failed` | Échec | Une requête de la marche a échoué. |
| `dmarc.invalid` | Échec | L'enregistrement du domaine d'envoi est écarté. |
| `dmarc.parent-invalid` | Avertissement | L'enregistrement d'un parent est écarté. |
| `dmarc.missing` | Échec | Aucun enregistrement ne s'applique. |
| `dmarc.inherited` | Réussite | L'enregistrement d'un parent s'applique. |
| `dmarc.no-policy` | Avertissement | L'enregistrement n'a pas de `p`. |
| `dmarc.policy-too-weak` | Échec | La politique est plus faible que celle que le profil attend. |
| `dmarc.policy-none` | Avertissement | La politique est `none`, que le profil accepte. |
| `dmarc.policy` | Réussite | La politique est `quarantine` ou `reject`, aussi stricte que prévu. |
| `dmarc.testing` | Avertissement | L'enregistrement dit `t=y`. |
| `dmarc.no-reports` | Avertissement | L'enregistrement ne demande aucun rapport agrégé. |
| `dmarc.return-path-aligned` | Réussite | Le chemin de retour s'aligne selon l'alignement SPF de l'enregistrement. |
| `dmarc.return-path-unaligned` | Avertissement, ou échec sans sélecteur DKIM | Le chemin de retour ne s'aligne pas. |
| `dmarc.dkim-alignment` | Réussite | L'alignement DKIM que demande l'enregistrement. |
