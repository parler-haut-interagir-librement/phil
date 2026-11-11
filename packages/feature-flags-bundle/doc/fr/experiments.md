<!-- phil-dev source-sha256=c1a9d80b4627b6a17196218766fa4634e7eaacf933a4fa87fc5a1627b6d7053a -->
# Expériences (tests A/B)

Une expérience répartit entre des variantes pondérées les contextes pour lesquels un indicateur est activé, selon un
attribut du contexte.

```yaml
flags:
    checkout-layout:
        active: true
        experiment:
            attribute: tenant
            variants: { one-column: 50, two-columns: 50 }
        kind: experiment
```

## Déclarer

| Partie | Type | Contrainte |
|---|---|---|
| `attribute` | `TargetingAttribute` | `tenant`, `project` ou `subject`. |
| `variants` | table de nom de variante vers poids | Au moins deux variantes, chacune déclarée une seule fois. |
| Nom de variante | `VariantName` | L'alphabet des noms d'indicateur (segments ASCII en minuscules séparés par `.`, `_` ou `-`), au plus 50 octets. Une clé numérique comme `1` se lit comme le nom `"1"`. |
| Poids | `Variant` | Un nombre entier de 0 à 100 ; la somme des poids vaut exactement 100. |

`Experiment::of()` refuse tout le reste et, dans la configuration, la construction du conteneur échoue en indiquant le
chemin de l'indicateur.

## Qui voit l'expérience

L'expérience s'applique **après** l'état actif et les règles ([flags-and-evaluation.md](flags-and-evaluation.md)) :

| Situation | Résultat |
|---|---|
| Indicateur inactif | désactivé, `DISABLED`, aucune variante |
| Actif, règles présentes, aucune ne correspond | désactivé, `DEFAULT`, aucune variante |
| Actif, règles absentes ou l'une correspond, le contexte a l'attribut | **activé, `SPLIT`, avec sa variante** |
| Actif, règles absentes ou l'une correspond, le contexte n'a pas l'attribut | désactivé, `DEFAULT`, aucune variante |

Les règles choisissent donc la population et l'expérience la répartit : `rules: [{ attribute: tenant, percentage: 10 }]`
avec une expérience mène le test sur un dixième des locataires.

## Attribution

Une valeur de l'attribut tombe dans un compartiment qui lui est propre (`Bucket::forVariant()`) :

```text
bucket = murmur3a("<flag>:variant:<value>") mod 100
```

Les compartiments sont remis aux variantes **dans leur ordre de déclaration, par poids cumulé**, chaque borne étant
exclusive : avec `control: 50, treatment: 50`, les compartiments 0 à 49 voient `control` et 50 à 99 voient `treatment`.

- **Stable.** Le même locataire voit la même variante à chaque requête et sur chaque serveur.
- **Salé à part du déploiement progressif.** Le sel `:variant:` évite que les locataires admis en premier par un
  déploiement progressif de l'indicateur soient aussi ceux de sa première variante. Un nom d'indicateur ne contient
  aucun `:` : le sel ne peut donc pas entrer en collision avec l'entrée d'un autre indicateur.
- **Un poids de 0** garde une variante déclarée sans y envoyer personne. La somme des poids vaut toujours 100 : sa part
  va donc à une autre branche. Donnez-la à une branche voisine, et seuls les contextes de la branche fermée se
  déplacent ; chaque autre branche garde les siens.
- **Ne changez les poids qu'entre deux expériences.** Déplacer une borne fait passer des contextes d'une branche à
  l'autre au milieu d'une mesure.

### Une porte à sens unique

La fonction de compartiment des variantes est fixée par les tests (`ExperimentTest`), comme celle du déploiement
progressif :

| Entrée | Compartiment |
|---|---|
| `new-checkout:variant:tenant-1` | 48 |
| `new-checkout:variant:tenant-2` | 67 |
| `new-checkout:variant:42` | 98 |

La changer déplace les cohortes de chaque expérience en cours.

## Lire la variante

```php
$variant = $flags->variant(FlagName::of('checkout-layout'));   // ?VariantName
if ('two-columns' === $variant?->value) { /* … */ }
```

```twig
{% if feature_variant('checkout-layout') == 'two-columns' %}…{% endif %}
```

```php
#[IsFeatureEnabled('checkout-layout', variant: 'two-columns')]   // 404 outside that arm
```

```yaml
condition: "service('feature_flags').variant('checkout-layout') == 'two-columns'"
```

Voyez [placement.md](placement.md).

## Expositions

Lorsqu'un `exposure_recorder` est configuré, `FlagEvaluator` appelle
`ExposureRecorder::record(FlagName $flag, VariantName $variant)` à la **première** évaluation d'un indicateur qui donne
une variante pour un contexte dans une requête : le moment où l'application pose la question est celui où elle bifurque
selon la réponse. Les évaluations suivantes du même indicateur pour le même contexte dans la même requête sont mémoïsées
et n'enregistrent rien ; un contexte qui n'est placé dans aucune branche (désactivé, aucune variante) n'enregistre rien.

**Une exposition est un compte, jamais un enregistrement.** L'enregistreur reçoit l'indicateur et la variante, et rien
du contexte : aucun locataire, aucun projet, aucun sujet. Il ajoute un à un agrégat que tient l'application (« variante
`treatment` de `checkout-layout` vue une fois de plus aujourd'hui »), et l'application compare les branches selon ses
propres mesures agrégées (nombres de conversions, durées). Un journal d'expositions par personne serait un registre de
qui a vu quoi, soit exactement ce que l'évaluation refuse de produire ; une application qui a besoin d'une analyse par
sujet la conçoit et la justifie de son côté.

Toute évaluation compte comme une exposition, d'où qu'elle vienne : l'attribut de contrôleur, une condition de route,
une fonction Twig ou un service.

```php
use Phil\FeatureFlagsBundle\Application\Flag\ExposureRecorder;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\VariantName;

final readonly class DailyExposureCounter implements ExposureRecorder
{
    public function __construct(private \Doctrine\DBAL\Connection $db, private \Psr\Clock\ClockInterface $clock) {}

    public function record(FlagName $flag, VariantName $variant): void
    {
        $this->db->executeStatement(
            'INSERT INTO flag_exposure (day, flag, variant, seen) VALUES (?, ?, ?, 1)
             ON CONFLICT (day, flag, variant) DO UPDATE SET seen = flag_exposure.seen + 1',
            [$this->clock->now()->format('Y-m-d'), $flag->value, $variant->value],
        );
    }
}
```

(La table et l'insertion ou mise à jour appartiennent à l'application ; le bundle ne fournit aucun magasin
d'expositions.)

## Dans la liste et l'administration

La liste et l'administration affichent le ciblage d'une expérience sous la forme
`A/B by <attribute>: <variant> <weight>%, …` ([console.md](console.md), [admin.md](admin.md)). Dans le formulaire de
modification de l'administration, l'expérience s'écrit en JSON à côté des règles :
`"experiment": {"attribute": "tenant", "variants": {"control": 50, "treatment": 50}}`.
