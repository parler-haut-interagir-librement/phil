<!-- phil-dev source-sha256=15ae1e6d44fce2cc3c79d2cd5e721ecafeb3c8f1b51cb9ce25a1888b17d6f06b -->
# phil/mail-auth-check

**Savoir avant d'envoyer si les destinataires feront confiance à votre courriel : les enregistrements SPF, DKIM et
DMARC d'un profil d'expéditeur, vérifiés selon ce qu'il attend.**

phil/mail-auth-check est une bibliothèque PHP. Une application décrit chaque expéditeur par un **`SenderProfile`** : le
domaine de son adresse `From`, le domaine de son chemin de retour, les sélecteurs DKIM avec lesquels ses fournisseurs
signent, les inclusions SPF de ces fournisseurs et la politique DMARC la plus faible qu'il accepte. **`MailAuthCheck`**
lit le DNS de ce profil et retourne un **`Report`** de constats : réussite, avertissement ou échec, chacun avec un code
stable et un message qu'un propriétaire de domaine peut mettre en pratique. Le profil **peut envoyer** quand aucun
constat n'a échoué.

- **SPF** (RFC 7208) : un seul enregistrement, lisible, ses inclusions suivies et ses **requêtes DNS comptées** par
  rapport à la limite de dix, les boucles signalées, les fournisseurs attendus inclus, et une fin qui n'est pas `+all`.
- **DKIM** : une clé à `selector._domainkey` pour chaque sélecteur attendu, non révoquée, RSA d'au moins 1024 bits
  (un avertissement sous 2048) ou Ed25519.
- **DMARC** (RFC 9989) : l'enregistrement du domaine ou, par la **marche dans l'arbre DNS**, celui d'un parent ; une
  politique au moins aussi stricte que celle que le profil attend ; des rapports demandés ; le chemin de retour aligné
  sur le domaine d'envoi.
- **Chemin de retour** : son propre enregistrement SPF autorise les fournisseurs attendus.

Le DNS est lu par un **`TxtResolver`** : `DnsGetRecordResolver` interroge le résolveur du système, `StaticTxtResolver`
répond à partir d'une table, pour les tests ou pour des enregistrements pas encore publiés. Chaque nom est interrogé
une seule fois par vérification. Les analyseurs bornent ce qu'ils lisent (longueur, termes, étiquettes, profondeur de
la marche SPF) : des enregistrements hostiles coûtent donc peu.

## État

| Partie | État |
|---|---|
| `SenderProfile`, `MailAuthCheck`, `Report`, `Finding`, `Status` : profils vérifiés, constats réunis | Implémenté et testé |
| `TxtResolver`, `DnsGetRecordResolver`, `StaticTxtResolver` : le DNS lu par un port | Implémenté et testé |
| `SpfParser`, `SpfAnalyser`, `SpfCheck`, `ReturnPathCheck` : SPF lu, parcouru et compté | Implémenté et testé |
| `DkimKeyParser`, `DkimCheck` : clés DKIM lues et mesurées | Implémenté et testé |
| `DmarcParser`, `DmarcTreeWalk`, `DmarcCheck` : DMARC trouvé par la marche dans l'arbre, politique et alignement | Implémenté et testé |
| Le port `Check`, pour les vérifications propres à une application | Implémenté et testé |
| Un bundle Symfony : profils enregistrés, vérifications planifiées et à la demande | Pas écrit |
| Vérifications MTA-STS, TLS-RPT et BIMI ; lecture des rapports DMARC | Pas écrit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et une première vérification. |
| [Points d'extension](doc/fr/extension-points.md) | Le résolveur et les vérifications qu'une application ajoute, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests, le résolveur statique dans les tests d'une application, et la cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public et chaque code de constat, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/mail-auth-check
```

```php
use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\MailAuthCheck;
use Phil\MailAuthCheck\SenderProfile;

$profile = new SenderProfile(
    sendingDomain: 'example.org',
    returnPathDomain: 'bounce.example.org',
    dkimSelectors: ['s1'],
    spfIncludes: ['_spf.provider.example'],
    minimumPolicy: DmarcPolicy::Quarantine,
);

$report = new MailAuthCheck()->check($profile);

if (!$report->canSend()) {
    foreach ($report->failures() as $finding) {
        echo $finding->code, ': ', $finding->message, "\n";
    }
}
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-filter` et `ext-sodium`. `ext-intl` convertit les noms de domaine
internationalisés ; sans elle, donnez leur forme ASCII (`xn--`).

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
