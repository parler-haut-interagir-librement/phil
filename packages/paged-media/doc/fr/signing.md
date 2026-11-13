<!-- phil-dev source-sha256=f74572ffd0c5006ee3d7fd807120ff2e093c288063800f8bea2ddef44a2d9101 -->
# Signature

L'étape `sign` signe un PDF avec un certificat ; l'étape `timestamp` ajoute un horodatage de document provenant d'une
autorité RFC 3161. Les deux passent par deux ports : le certificat et l'autorité viennent de l'endroit où l'application
les conserve.

## Certificats

Un `Certificate` est un fichier PKCS#12 (`.p12`, `.pfx`) ouvert par son mot de passe, ou un certificat PEM avec sa clé
privée PEM :

```php
use Phil\PagedMedia\Gotenberg\Signing\Certificate;

Certificate::pkcs12(InputFile::fromPath('/run/secrets/signer.p12'), $password);
Certificate::pem(InputFile::fromPath('/run/secrets/cert.pem'), InputFile::fromPath('/run/secrets/key.pem'));
```

Une `CertificateSource` donne le certificat chaque fois qu'un PDF est signé : un certificat renouvelé est donc pris sans
redémarrage.

| Source | Emplacement du certificat |
|---|---|
| `FileCertificateSource($path, $keyPath, $password)` | Sur le disque : un fichier PKCS#12 et son mot de passe, ou un certificat PEM et le fichier de sa clé |
| `InlineCertificateSource($certificate, $key, $password)` | En mémoire : un certificat et une clé PEM, ou les octets d'un fichier PKCS#12, tirés d'une variable d'environnement ou du coffre de secrets de Symfony ; envoyés comme `signer.pem` et `signer.key`, ou `signer.p12` |
| `VaultCertificateSource($client, $address, $token, $path, $mount)` | Dans un moteur de secrets clé/valeur, version 2, de HashiCorp Vault ou d'OpenBao, lu à `<address>/v1/<mount>/data/<path>` ; le secret contient `certificate` et `key` en PEM |

Un coffre hors d'atteinte, ou un secret d'une autre forme, lève `CertificateUnavailable`. Une application qui a son
propre coffre implémente `CertificateSource`.

## Signatures

```php
use Phil\PagedMedia\Gotenberg\Stage\Sign;

$sign = Sign::with($source, reason: 'Approval', location: 'Montréal', name: 'Ada Lovelace', pades: true, timestampAuthority: $tsa);
$gotenberg->pipe($files, new Pipeline(new Remediate(Profile::PdfA3b), new Optimize(), $sign));
```

`reason`, `location`, `contactInfo` et `name` s'affichent dans le panneau des signatures du lecteur. `pades` produit une
signature PAdES, le format que reconnaît le règlement eIDAS de l'UE. Une autorité d'horodatage donnée à la signature
atteste de son heure de signature. Un certificat autosigné signe, mais les lecteurs le signalent comme non fiable :
signez avec un certificat d'une autorité en qui ils ont confiance.

Un changement après une signature la brise : seul un horodatage, une autre signature ou une étape terminale peut la
suivre, et une étape qui réécrit le fichier est refusée sur un PDF signé. Un PDF chiffré se signe en deux requêtes :
déchiffrez-le, puis signez le résultat.

## Horodatages

Une `TimestampAuthority` donne l'URL d'une autorité RFC 3161 ; `TimestampAuthorityUrl` en est une connue par son URL,
comme celle de la configuration. Cette URL est une adresse publique, lue par `phil/url` comme celle d'un téléchargement.

```php
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthorityUrl;
use Phil\PagedMedia\Gotenberg\Stage\Timestamp;

$gotenberg->run($files, new Timestamp(new TimestampAuthorityUrl('https://timestamp.example.com')));
```

Un horodatage de document ne nomme aucun signataire : il prouve que le PDF existait, inchangé, à ce moment. Gotenberg
appelle lui-même l'autorité, dans ce que permet son exploitant (`--pdf-allow-list`, `--pdf-deny-private-ips`).
