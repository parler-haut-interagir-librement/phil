<!-- phil-dev source-sha256=13bb42275fc254c1ca5926f54184b5a7270c396415b4619f6afcd505760e1825 -->
# Métadonnées et expiration

Chaque indicateur peut dire ce qu'il protège, qui en est responsable, de quel genre il est et le jour où il devrait
avoir disparu (`FlagMetadata`). **Rien de cela ne change une évaluation.**

```yaml
flags:
    new-checkout:
        active: true
        description: The one-page checkout.
        owner: checkout-team
        kind: release
        expires_on: '2027-03-31'
```

## Champs

| Champ | Type | Contrainte |
|---|---|---|
| `description` | `?string` | UTF-8 valide, sans espaces aux extrémités, au plus 500 caractères (`FlagMetadata::DESCRIPTION_MAX_LENGTH`). Une valeur vide signifie aucune. |
| `owner` | `?string` | UTF-8 valide, sans espaces aux extrémités, au plus 100 caractères (`FlagMetadata::OWNER_MAX_LENGTH`). Une valeur vide signifie aucun. Texte libre : une équipe, une personne, un canal. |
| `kind` | `?FlagKind` | `release`, `experiment`, `ops` ou `permission`. |
| `expires_on` | `?DateTimeImmutable` | Écrite `YYYY-MM-DD`. Seule la date compte, lue en UTC (minuit UTC de ce jour). |

Les espaces aux extrémités de `description` et de `owner` sont retirés par le `trim()` de PHP (espaces ASCII),
contrairement aux raisons et aux acteurs, dont on retire tous les blancs Unicode.

## Genres

Les quatre catégories de l'article *Feature Toggles* de Pete Hodgson (martinfowler.com). Le genre dit combien de temps
un indicateur devrait vivre et qui en est responsable :

| Genre | Pour | Durée de vie |
|---|---|---|
| `release` | Livre une fonctionnalité avant sa sortie. | Retiré une fois la fonctionnalité activée pour tout le monde. |
| `experiment` | Répartit des contextes entre des variantes pour les comparer. | Retiré à la conclusion de l'expérience. |
| `ops` | Permet à un opérateur de désactiver une fonctionnalité en production. | La plupart sont retirés une fois la fonctionnalité jugée fiable. |
| `permission` | Ouvre une fonctionnalité à un ensemble de locataires, de projets ou de sujets, comme des bêta-testeurs. | Peut vivre longtemps. |

L'indicateur d'ouverture forcée que le bundle déclare pour chaque circuit de `phil/circuit-breaker-bundle` est de genre
`ops` ([circuit-breakers.md](circuit-breakers.md)).

## Expiration

`FlagMetadata::isExpiredAt(DateTimeImmutable $now): bool` est vrai à partir de minuit UTC du jour d'expiration : un
indicateur qui expire le 31 est expiré à partir du 31 à 00 h 00 UTC. Un indicateur sans date d'expiration n'expire
jamais.

**Un indicateur est un élément d'inventaire.** Chacun double les chemins dans le code qu'il protège, et la date
d'expiration permet à une construction d'échouer pour un indicateur qui a survécu à son objectif. L'expiration n'a aucun
effet sur l'évaluation : un indicateur expiré continue de fonctionner exactement comme avant, et seules la liste et
l'administration disent qu'il est expiré.

- `bin/console phil:feature-flags:list --fail-on-expired` termine en échec tant qu'un indicateur a dépassé sa date ;
  lancez-la en intégration continue ([console.md](console.md)) ;
- la liste de l'administration et la page de l'indicateur marquent un indicateur expiré ([admin.md](admin.md)) ;
- un test de l'application peut lire `FlagCatalog::entries()` et échouer sur `declared->metadata->isExpiredAt()`
  ([testing.md](testing.md)).

La liste et l'administration jugent l'expiration selon les métadonnées **déclarées** de la définition fournie, par
rapport à l'horloge configurée.

## Dates YAML

Un `expires_on: 2027-03-31` sans guillemets est analysé par YAML comme un horodatage ; l'arbre de configuration le
reconvertit en date UTC : les deux formes fonctionnent donc. Les guillemets rendent l'intention évidente.

## Où vivent les métadonnées

Les métadonnées appartiennent à la définition : elles viennent donc du fournisseur qui déclare l'indicateur, soit la
configuration, soit le magasin en base de données, où l'administration les modifie dans des champs qui leur sont
propres. Le magasin d'états ne contient que l'état actif ; basculer un indicateur ne touche jamais à ses métadonnées.
