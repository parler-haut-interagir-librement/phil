<!-- phil-dev source-sha256=05d7ddaca4cfbb6c71dbe91c82deeed3a17c08faaa6c2eac955a3b24473f2135 -->
# Documents

## En-têtes

Chaque réponse porte `X-Robots-Tag: noindex` : les documents sont destinés aux logiciels, pas aux résultats de
recherche. Une réponse réussie dont le document ne fixe ni `max-age` ni `no-store` propre est `public` avec un
`max-age` de `cache_max_age`, et porte une empreinte de son corps comme `ETag` ; une requête qui la détient reçoit un
`304`. Les erreurs et les redirections gardent le `private, no-cache` de Symfony. Les routes sont sans état et aucun
document ne pose de témoin.

| Document | Type de média | CORS |
|---|---|---|
| `security.txt` | `text/plain; charset=utf-8` | non |
| `change-password` | un `302` | non |
| `webfinger` | `application/jrd+json` | `*`, erreurs comprises |
| `nodeinfo` | `application/json` | `*` |
| NodeInfo 2.1 | `application/json; profile="http://nodeinfo.diaspora.software/ns/schema/2.1#"` | `*` |
| `did.json` | `application/did+ld+json` avec un `@context`, sinon `application/did+json` | `*` |
| `did-configuration.json` | `application/json` | `*` |
| `atproto-did` | `text/plain; charset=utf-8` | `*` |
| `apple-app-site-association` | `application/json` | non |
| `assetlinks.json` | `application/json` | non |
| `documents` | `content_type` | `cors` |

## security.txt

RFC 9116. Les champs dans l'ordre de la RFC, un par ligne, `Contact` en premier, `Expires` en UTC,
`Preferred-Languages` sur une seule ligne :

```text
Contact: mailto:security@example.com
Expires: 2027-06-30T00:00:00Z
Preferred-Languages: fr, en
Canonical: https://example.com/.well-known/security.txt
```

Le texte est écrit à la construction du conteneur. La RFC 9116 recommande une signature OpenPGP en clair : le bundle
ne détient aucune clé, et l'application signe le texte avec le `SecurityTxtSigner` qu'elle nomme dans
`security_txt.signer`.

### L'expiration comme vérification de santé

Quand phil/monitoring-bundle est installé, le bundle enregistre `SecurityTxtExpiryCheck`, la vérification
`security_txt` de la santé du site, étiquetée `phil_monitoring.health_check`. Elle lit le champ `Expires` du fichier
sur le service `clock` :

| Temps restant | État | Résumé |
|---|---|---|
| Plus de `security_txt.expiry_warning_days` jours | en service | `Expires on 2027-06-30T00:00:00+00:00.` |
| Ce nombre de jours ou moins | dégradé | `The security.txt expires on 2027-06-30T00:00:00+00:00, in 12 days.` |
| Aucun | hors service | `The security.txt expired on 2027-06-30T00:00:00+00:00.` |

Les jours restants sont arrondis vers le haut. Un conteneur qui tourne encore après la date échoue donc à la
vérification, et la disponibilité du site avec elle, au lieu de servir un fichier expiré. Sans phil/monitoring-bundle,
aucune vérification n'est enregistrée.

## change-password

L'URL bien connue du W3C pour changer de mot de passe : un `302` vers la route ou l'URL de la configuration, pour que
les gestionnaires de mots de passe envoient leurs utilisateurs à la bonne page.

## WebFinger

RFC 7033 : voir [WebFinger](webfinger.md).

## NodeInfo

`/.well-known/nodeinfo` renvoie au document 2.1 par son URL absolue, générée par le routeur :

```json
{"links":[{"rel":"http://nodeinfo.diaspora.software/ns/schema/2.1","href":"https://example.com/nodeinfo/2.1"}]}
```

Le document 2.1 contient la version, le logiciel, les protocoles, les services, `openRegistrations`, l'utilisation
(les nombres que dit le fournisseur, aucun par défaut) et les métadonnées. Le fournisseur est interrogé à chaque
requête.

## did.json et did-configuration.json

`did.json` est le document DID de l'identifiant `did:web` du site, tel que donné. `did-configuration.json` suit la
DIF Well Known DID Configuration : son `@context` et ses `linked_dids`, les attestations de lien de domaine de la
configuration dans leur ordre, puis celles des fichiers. Le bundle sert les deux tels que donnés et ne vérifie aucune
signature.

## atproto-did

L'AT Protocol prouve un identifiant par son domaine : le DID, en texte brut, sans saut de ligne. La configuration nomme
le DID propre au site et le DID d'autres hôtes ; l'hôte de la requête, en minuscules, le choisit, sinon c'est celui du
site ; un hôte sans l'un ni l'autre donne un `404`.

## apple-app-site-association et assetlinks.json

Servis à leur adresse exacte, sans redirection et en `application/json`, comme l'exigent iOS et Android. Les
déclarations Android sont celles de `android_apps` (`relation` et une cible `android_app`), puis `statements` tel que
donné.

## Documents fixes

Chaque entrée de `documents` est servie à son chemin avec son type de média, à partir d'une chaîne ou d'un fichier lu à
la construction du conteneur.
