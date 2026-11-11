<!-- phil-dev source-sha256=9afab28e75e8f6a7d33df9661d9b43abbd51205a162779c98f27e9492bf56c63 -->
# Points d'extension

Ce qu'une application ou un autre bundle implémente, configure, remplace ou utilise. Voir [Documents](documents.md) et
[WebFinger](webfinger.md).

## Contrats

| Interface | Comment elle est utilisée |
|---|---|
| `Phil\WellKnownBundle\Application\Contract\WellKnownDocument` | Un document à soi, servi à son chemin : `#[AsWellKnownDocument('matrix/client')]` sur la classe avec l'autoconfiguration, ou l'étiquette `phil_well_known.document` avec `path` et un `route` facultatif |
| `…\Application\Contract\WebFingerResolver` | Ajoute des ressources et des liens à WebFinger ; autoconfiguré avec l'étiquette `phil_well_known.webfinger_resolver`, après les ressources de la configuration |
| `…\Application\Contract\NodeInfoProvider` | Dit le logiciel, l'utilisation, les inscriptions et les métadonnées de NodeInfo ; nommé dans `nodeinfo.provider` |
| `…\Application\Contract\SecurityTxtSigner` | Signe le security.txt ; nommé dans `security_txt.signer` |

Le `path` d'un document est un nom sous `/.well-known/` (des segments de lettres, chiffres, `.`, `_`, `~` et `-`,
chacun commençant par une lettre ou un chiffre) ou un chemin absolu pour un document vers lequel pointe un document
bien connu. Sa route se nomme `phil_well_known_` suivi du chemin, ses autres caractères devenant `_`, sauf si `route`
la nomme. Deux documents au même chemin ou avec le même nom de route empêchent la construction du conteneur. Le
contrôleur ajoute les en-têtes de [Documents](documents.md#en-têtes) ; un document qui veut une autre durée de vie
fixe son propre `max-age`, ou `no-store`.

## Étiquettes

| Étiquette | Attributs | Rôle |
|---|---|---|
| `phil_well_known.document` | `path`, `route` | Un document et l'endroit où il est servi |
| `phil_well_known.webfinger_resolver` | `before`, `after` | Un résolveur WebFinger et son ordre |

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `cache_max_age` | Combien de temps les réponses réussies peuvent être mises en cache |
| Une section par document | Les documents servis, et ce qu'ils contiennent ; voir [Configuration](configuration.md) |
| `documents` | Des documents fixes à d'autres chemins bien connus |

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Phil\WellKnownBundle\Infrastructure\Http\WellKnownController` | Le contrôleur de chaque route ; public |
| `Phil\WellKnownBundle\Application\WebFinger` | Avec `webfinger` : injecté pour décrire une ressource dans le code |
| Le type de route `phil_well_known` et `config/routes.php` | Importés là où l'application veut que les documents soient servis ; voir [Installation](installation.md) |
| Le paramètre `phil_well_known.routes` | Le chemin de chaque document, indexé par son nom de route |
