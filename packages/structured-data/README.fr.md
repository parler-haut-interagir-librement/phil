<!-- phil-dev source-sha256=978be7ba328bbcff53d48497cb02c6bca81d13fd144782074f245d3eb92d8a2b -->
# phil/structured-data

**Le graphe schema.org d'une page sous forme de valeurs : des nœuds venus de toutes les parties d'un site, fusionnés
par `@id`, vérifiés, puis imprimés dans un seul élément de script JSON-LD.**

phil/structured-data représente les données structurées d'une page comme un graphe. Un `Node` est une chose
schema.org — un site, une organisation, une page, un événement, un livre, un paquet — avec ses types, son `@id` et ses
propriétés ; il ne change jamais. Des fabriques construisent les types courants avec des arguments nommés et typés.
`Schema` construit les types qu'un site fournit le plus et refuse celui auquel manque ce dont son type a besoin. Tout
autre type accepte des propriétés libres. Un `Graph` rassemble les nœuds que fournissent les parties d'un site :
deux nœuds au même `@id` n'en forment plus qu'un, un nœud imbriqué doté d'un `@id` remonte au premier niveau et laisse
une référence à sa place, et le graphe peut porter la langue de ses textes. `JsonLd` l'écrit dans un seul élément
`<script type="application/ld+json">` qu'aucune valeur ne peut fermer. Le `Validator` signale ce qui manque à chaque
nœud : comme erreur ce dont il a besoin, comme avertissement ce qu'il devrait avoir. `JsonLdReader` relit le JSON-LD
d'une page.

Il s'adresse à tout site PHP qui veut que les moteurs de recherche et les autres lecteurs comprennent ses pages : un
site dont l'organisation, le fil d'Ariane et le contenu viennent de paquets différents, un catalogue de paquets
logiciels, une boutique, un calendrier. Dans une application Symfony, phil/structured-data-bundle rassemble les nœuds
de chaque requête et les imprime avec une seule fonction Twig.

C'est une simple bibliothèque PHP, sans autre dépendance que les extensions DOM, JSON et libxml de PHP.
`NodeCollector` est le port où les parties d'une application versent leurs nœuds ; des `TypeRules` ajoutent au
validateur les règles d'autres types.

## État

| Partie | État |
|---|---|
| `Node`, `Reference`, `LanguageString`, `Id`, `LanguageTag` | Implémenté et testé |
| `Graph` : fusion par `@id`, nœuds imbriqués remontés, langue | Implémenté et testé |
| Fabriques de 19 types courants, et les énumérations de leurs valeurs | Implémenté et testé |
| `Schema` : fabriques vérifiées des 12 types qu'un site fournit le plus | Implémenté et testé |
| `JsonLd` : le JSON-LD et son élément de script, avec un *nonce* | Implémenté et testé |
| `Validator`, `Rules`, `TypeRules`, `Report` | Implémenté et testé |
| `JsonLdReader` : le JSON-LD d'une page relu | Implémenté, testé et soumis au fuzzing |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Référence](doc/fr/reference.md) | Les nœuds, les valeurs, les identifiants, les langues, le graphe et les fabriques. |
| [JSON-LD](doc/fr/json-ld.md) | Le document écrit, son élément de script et le lecteur. |
| [Validation](doc/fr/validation.md) | Les règles de chaque type, les vérifications des valeurs, le rapport. |
| [Points d'extension](doc/fr/extension-points.md) | Le port, les règles et les types qu'une application ajoute. |
| [Tests](doc/fr/testing.md) | Comment la bibliothèque est testée, soumise au fuzzing et mesurée. |

## Démarrage rapide

```php
use Phil\StructuredData\{Graph, Id, JsonLd};
use Phil\StructuredData\Type\{Event, Organization, Place, WebSite};

$base = 'https://example.com/';
$organization = Organization::of('Le Gnome', $base, Id::of($base, 'organization'), logo: $base.'logo.png');

$graph = Graph::of(
    WebSite::of('Le Gnome', $base, Id::of($base, 'website'), inLanguage: 'fr-CA', publisher: $organization->reference()),
    $organization,
    Event::of('Tournoi de printemps', '2027-04-10T10:00:00-04:00', Place::of('Salle Saint-Denis'), organizer: $organization->reference()),
);

echo JsonLd::script($graph);
```

## Prérequis

PHP 8.5 ou plus récent, avec les extensions DOM, JSON et libxml ; `phil/url` 0.1.2, qui requiert les extensions intl,
mbstring et URI.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
