<!-- phil-dev source-sha256=5d91883f9dc99dbbe842c65f818a06776305ddfce2841bd8377cb3d330c73e69 -->
# phil/well-known-bundle

**Les adresses `/.well-known/` d'un site Symfony, chacune activée dans sa configuration.**

phil/well-known-bundle sert les documents que d'autres logiciels cherchent à des adresses fixes d'un domaine
(RFC 8615) : comment signaler une vulnérabilité (`security.txt`), où changer un mot de passe, ce que le site dit d'un
compte (WebFinger), quel logiciel il fait tourner (NodeInfo), le document DID d'un identifiant `did:web` et les
attestations qui lient le domaine à ses DID, le DID vers lequel se résout un identifiant AT Protocol, et les liens
d'applications des applications iOS et Android. Des documents fixes de la configuration et des documents d'autres
bundles s'y ajoutent, chacun à sa propre route exacte.

Utilisez-le dans un site Symfony qui veut ces adresses justes sans écrire un contrôleur pour chacune : les types de
média, l'en-tête CORS des documents lus par d'autres sites, des en-têtes de cache et un `ETag`,
`X-Robots-Tag: noindex`, aucune session et aucun témoin. Un document qui n'est pas activé n'a pas de route, et donne
un `404`. Une valeur qu'un document ne pourrait pas servir, comme un security.txt expiré ou un DID qui n'en est pas un,
empêche la construction du conteneur.

Il se branche sur le routeur de Symfony, par un chargeur de routes, et sur le conteneur : les résolveurs WebFinger sont
autoconfigurés. Un bundle ActivityPub ajoute ainsi les liens de ses comptes en implémentant `WebFingerResolver`, et
n'importe quel bundle ajoute un document en implémentant `WellKnownDocument` avec `#[AsWellKnownDocument]`.

## État

| Partie | État |
|---|---|
| `security.txt` (RFC 9116), vérifié à la construction du conteneur, signé par un port | Implémenté et testé |
| `change-password` | Implémenté et testé |
| WebFinger (RFC 7033) : ressources `acct:` et `https:`, `rel`, résolveurs | Implémenté, testé et soumis au fuzzing |
| NodeInfo 2.1 et son document de découverte, avec un port fournisseur | Implémenté et testé |
| `did.json`, `did-configuration.json`, `atproto-did` | Implémenté et testé |
| `apple-app-site-association`, `assetlinks.json` | Implémenté et testé |
| Documents fixes, documents d'autres bundles | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement, les routes. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec sa valeur par défaut. |
| [Documents](doc/fr/documents.md) | Ce que sert chaque document, et avec quels en-têtes. |
| [WebFinger](doc/fr/webfinger.md) | Les requêtes, les ressources, les résolveurs et ce que WebFinger révèle. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé, soumis au fuzzing et mesuré. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```yaml
# config/routes/phil_well_known.yaml
phil_well_known:
    resource: '@PhilWellKnownBundle/config/routes.php'
```

```yaml
# config/packages/phil_well_known.yaml
phil_well_known:
    security_txt:
        contact: ['mailto:security@example.com']
        expires: '2027-06-30T00:00:00Z'
    change_password:
        route: account_password
    webfinger: true
```

## Prérequis

PHP 8.5 ou plus récent et Symfony 8.2 ou plus récent : HttpKernel, Routing, Config et DependencyInjection.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
