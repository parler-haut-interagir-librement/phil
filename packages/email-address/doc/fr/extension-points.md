<!-- phil-dev source-sha256=c3c47aca224928cd803204c8c24c4a819874fe70e5932a999c7d71828de30952 -->
# Points d'extension

La bibliothèque est du simple PHP et ne déclare aucun service. Ce qu'une application choisit passe par un port, des
arguments et des données.

## `Contract\MailDomainResolver`

| Méthode | Contrat |
|---|---|
| `resolve(string $asciiDomain): MailDomain` | Prend un domaine en ASCII sans point final ; retourne ce que dit le DNS, `MailDomain::Unknown` quand il ne peut pas le dire ; ne garde aucun état entre les appels, sauf s'il s'agit d'un cache qui appartient à l'application |

`Dns\DnsGetRecordResolver` interroge le résolveur du système, `Dns\StaticResolver` répond d'après une table. Un
adaptateur de l'application peut mettre en cache, utiliser un autre résolveur ou répondre d'après une liste : voir
[DNS](dns.md).

## Arguments

| Point | Comment |
|---|---|
| Les formes d'adresse acceptées | `SyntaxPolicy` et ses méthodes `with` : voir [Syntaxe](syntax.md) |
| Les domaines refusés, ou les seuls acceptés | `DomainRules::of()`, `withDenied()`, `withAllowedOnly()` : voir [Domaines](domains.md) |
| Les domaines de premier niveau | `TopLevelDomains::bundled()`, `of()` : voir [Domaines](domains.md) |
| Les domaines jetables | `DisposableDomains::bundled()`, `of()`, `merge()` |
| Les domaines suggérés et les fautes remplacées | Les `$domains` et `$slips` de `TypoSuggester` : voir [Suggestions](suggestions.md) |
| Les étapes exécutées | Les arguments d'`EmailValidator` : une étape dont l'argument est `null` est sautée |
| Les options, les messages et les codes de la contrainte | Les arguments de `Validator\DeliverableEmailAddress` : voir [Validator de Symfony](symfony-validator.md) |
| Les listes et le résolveur de la contrainte | Les arguments de `Validator\DeliverableEmailAddressValidator`, déclaré comme service |
| Attraper les erreurs de la bibliothèque | Chaque exception implémente `Exception\EmailAddressException` |

## Données

La liste fournie des domaines jetables est importée par `Data\DisposableDomainsImporter` au moyen de
`bin/import-disposable-domains.php`, et la liste des domaines de premier niveau par `Data\TopLevelDomainsImporter` au
moyen de `bin/import-top-level-domains.php` ; aucune n'est modifiée à la main.
