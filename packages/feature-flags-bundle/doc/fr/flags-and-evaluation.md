<!-- phil-dev source-sha256=3f0df719e541f9ba09bcd06c340b670eecdff45368cc86ddda1d2eb5d8534032 -->
# Indicateurs et évaluation

Un indicateur retire une **nouvelle** fonctionnalité sans déploiement. Leur ensemble grandit avec les fonctionnalités,
et chaque indicateur est retiré une fois sa fonctionnalité activée pour tout le monde. Un indicateur **n'est pas un
interrupteur d'urgence opérationnel** pour un sous-système établi : un tel interrupteur est une liste courte et énumérée
qui doit continuer de fonctionner lorsque le problème vient de la base de données ou du cache. Il se lit donc sans
passer par les fournisseurs de ce bundle.

## Vocabulaire

| Terme | Type | Signification |
|---|---|---|
| Nom d'indicateur | `FlagName` | Des segments ASCII en minuscules séparés par `.`, `_` ou `-`, au plus 100 octets (`new-checkout`, `search.v2`). Le nom sale les compartiments stables : renommer un indicateur rebrasse donc son déploiement progressif. |
| Définition | `FlagDefinition` | Un nom, un état actif, une liste de règles, une expérience facultative et des métadonnées. |
| Contexte | `EvaluationContext` | Le `tenant`, le `project` et le `subject` de la requête courante, chacun étant un identifiant opaque ou `null`. |
| Évaluation | `Evaluation` | Le résultat pour un indicateur et un contexte : `enabled`, une `reason`, un `errorCode` lorsque la raison est `ERROR`, et une `variant` lorsqu'une expérience a placé le contexte. |
| Fournisseur | `FlagProvider` | Une source de définitions : la configuration, le magasin en base de données, un service distant. |
| Magasin d'états | `FlagStateSource` | Une source d'états actifs seulement, lue par-dessus les définitions des fournisseurs. |

## Comment une définition s'évalue

`FlagDefinition::evaluate()` applique ces étapes dans l'ordre :

1. **L'inactivité l'emporte.** Un indicateur inactif est désactivé pour tous les contextes, avec la raison `DISABLED`,
   quoi qu'en disent ses règles. Ses règles restent en place : la désactivation est donc un retour arrière, et la
   réactivation rétablit exactement le déploiement progressif.
2. **Règles.** Actif et sans règle, l'indicateur est activé avec la raison `STATIC`. Actif et avec des règles, la
   première règle qui correspond donne la raison (`TARGETING_MATCH` pour une règle d'appartenance, `SPLIT` pour un
   déploiement progressif par pourcentage) ; lorsqu'aucune règle ne correspond, l'indicateur est désactivé avec la
   raison `DEFAULT`. Voyez [targeting.md](targeting.md).
3. **Expérience.** Lorsque l'indicateur est activé et a une expérience, l'expérience place le contexte dans une
   variante : activé, avec la raison `SPLIT` et la variante ; ou, lorsque le contexte n'a pas l'attribut de
   l'expérience, désactivé avec la raison `DEFAULT`. Voyez [experiments.md](experiments.md).

Les métadonnées (description, responsable, genre, expiration) ne changent rien à une évaluation.

## Raisons

Les raisons et le code d'erreur suivent le vocabulaire de la spécification OpenFeature (`EvaluationReason`,
`EvaluationErrorCode`) :

| Raison | Activé | Quand |
|---|---|---|
| `STATIC` | oui | Actif, aucune règle, aucune expérience. |
| `TARGETING_MATCH` | oui | Actif et une règle d'appartenance a correspondu (aucune expérience). |
| `SPLIT` | oui | Actif et un déploiement progressif par pourcentage a admis le contexte (aucune expérience), ou l'expérience a placé le contexte dans une variante. |
| `DEFAULT` | non | Actif avec des règles dont aucune n'a correspondu, ou l'expérience n'a pas pu placer le contexte. |
| `DISABLED` | non | Inactif. |
| `ERROR` | non | Non évalué ; le code d'erreur dit pourquoi (tableau ci-dessous). |

| Code d'erreur | Quand |
|---|---|
| `FLAG_NOT_FOUND` | Aucun fournisseur ne connaît l'indicateur. |
| `PARSE_ERROR` | Un fournisseur a levé `InvalidValue` : il tient l'indicateur, mais sa définition ne se lit plus comme telle. |
| `GENERAL` | Un fournisseur ou le magasin d'états a levé une autre exception, par exemple une base de données injoignable. |

`Evaluation` fait respecter ses propres invariants : un code d'erreur exactement lorsque la raison est `ERROR`, une
évaluation `ERROR` toujours désactivée, et seule une évaluation activée porte une variante.

Les noms sont ceux d'OpenFeature, pour qu'une ligne de journal, le panneau du profileur ou un tableau de bord se lisent
comme dans tout outil bâti sur cette spécification ; le bundle ne livre aucun fournisseur OpenFeature et ne dépend pas
de son SDK. Les raisons d'OpenFeature que donnerait un fournisseur distant ou en cache (`CACHED`, `STALE`, `UNKNOWN`)
et les codes d'erreur de l'évaluation typée ou distante (`TYPE_MISMATCH`, `PROVIDER_NOT_READY`,
`TARGETING_KEY_MISSING`, `INVALID_CONTEXT`, `PROVIDER_FATAL`) ne servent pas : un indicateur est un booléen, évalué
dans le processus, et un contexte sans l'attribut qu'une règle lit ne correspond simplement pas à cette règle.

### Une évaluation ne lève jamais d'exception

`FlagEvaluator::evaluate()`, `isEnabled()` et `variant()` ne lèvent jamais d'exception parce qu'une source a échoué.
Un fournisseur ou le magasin d'états qui lève une exception donne une évaluation désactivée, raison `ERROR`, code
`PARSE_ERROR` pour une `InvalidValue` et `GENERAL` pour toute autre ; l'échec est journalisé comme erreur sur le canal
`feature_flags`, avec l'indicateur, le code, le message et l'exception, et mémoïsé comme toute évaluation, de sorte
qu'une requête n'interroge qu'une fois la source en échec. Une `Error` de PHP est un bogue plutôt qu'un échec de
source et n'est pas attrapée. `FlagEvaluator::definition()`, que lisent le catalogue et l'administration, lève
toujours : ces écrans montrent l'échec.

## L'évaluateur

`FlagEvaluator` est le service qu'interroge l'application :

```php
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

final readonly class CheckoutMailer
{
    public function __construct(private FlagEvaluator $flags) {}

    public function includeOrderCancellationInEmail(): bool
    {
        return $this->flags->isEnabled(FlagName::of('order-cancellation-in-email'));
    }
}
```

| Méthode | Retourne |
|---|---|
| `isEnabled(FlagName $flag): bool` | Si l'indicateur est activé pour le contexte courant. |
| `variant(FlagName $flag): ?VariantName` | La variante de l'expérience de l'indicateur, ou `null` lorsque l'indicateur est désactivé ou n'a pas d'expérience. |
| `evaluate(FlagName $flag): Evaluation` | Le résultat complet, avec sa raison. |
| `definition(FlagName): ?FlagDefinition` | La définition que déclare le premier fournisseur qui connaît l'indicateur, avant tout état stocké ; ne lit aucun contexte. |
| `evaluated(): array<string, Evaluation>` | Les indicateurs évalués pendant la requête courante, par nom, chacun avec sa dernière évaluation, sans le contexte. |
| `reset(): void` | Termine la requête : oublie chaque évaluation mémoïsée. |

### Une fois par requête et par contexte

Le contexte est résolu à **chaque** évaluation (un appel à `EvaluationContextResolver::current()`), et chaque indicateur
est évalué une fois par contexte, puis mémoïsé. Un indicateur basculé au milieu d'une requête ne change pas de valeur en
cours de route : une page n'affiche donc jamais la moitié d'une fonctionnalité, et l'attribut de contrôleur, Twig et le
cœur lisent la même réponse pour le même contexte.

**Un contexte qui change pendant la requête reçoit ses propres évaluations.** Un indicateur lu avant que le pare-feu ait
chargé l'utilisateur, par exemple par un écouteur précoce, est évalué pour le contexte anonyme. Une fois l'utilisateur
connu, le résolveur retourne le contexte de l'utilisateur et l'indicateur est évalué de nouveau pour celui-ci : une
évaluation faite avant l'authentification ne répond jamais pour l'utilisateur authentifié. Le résolveur est donc appelé
à chaque évaluation et doit rester peu coûteux ([extension-points.md](extension-points.md#evaluationcontextresolver)).

### Réinitialisation dans les workers

`FlagEvaluator` porte l'étiquette `kernel.reset` : Symfony appelle donc `reset()` entre deux requêtes principales d'un
environnement d'exécution persistant (mode worker de FrankenPHP, RoadRunner) et entre deux messages d'un worker de
Messenger (le `services_resetter`). Les magasins en base de données portent aussi cette étiquette
([database-store.md](database-store.md)). Une boucle personnalisée qui traite plusieurs unités de travail hors de ces
mécanismes appelle elle-même `reset()`, ou injecte `services_resetter`.

Dans un consommateur de messages, le résolveur de contexte lit le message, pas une requête : l'application décide de
quel champ du message viennent le locataire, le projet et le sujet.

### Fournisseurs et priorité

Les fournisseurs sont interrogés dans leur ordre configuré et **le premier qui connaît l'indicateur donne sa
définition**, règles comprises ; un fournisseur suivant qui connaît le même nom est masqué. Un fournisseur qui ne
connaît pas un indicateur retourne `null` plutôt qu'une définition inactive, qui cacherait celle du fournisseur suivant.
**Un indicateur qu'aucun fournisseur ne connaît est désactivé**, avec la raison `ERROR` et le code `FLAG_NOT_FOUND`.
Un fournisseur qui lève une exception arrête la recherche : l'indicateur est désactivé, raison `ERROR`, code
`PARSE_ERROR` ou `GENERAL`, plutôt que de recevoir la réponse d'un fournisseur suivant qu'il masquerait peut-être.

L'ordre par défaut est la configuration, puis le magasin en base de données : un indicateur déclaré dans le code
l'emporte sur un indicateur du même nom dans la base de données (et l'éditeur refuse de créer un nom qu'un autre
fournisseur déclare).

### Le magasin d'états

L'état actif a sa propre source. Lorsqu'un magasin d'états est configuré, l'état qu'il contient pour un indicateur
**remplace** l'état actif de la définition du fournisseur, et les règles, l'expérience et les métadonnées restent celles
du fournisseur :

| Le fournisseur déclare | Le magasin contient | Évalué comme |
|---|---|---|
| actif, 20 % par locataire | rien | actif, 20 % par locataire |
| actif, 20 % par locataire | `false` | inactif (`DISABLED`) |
| actif, 20 % par locataire | `false`, puis `true` | de nouveau actif, 20 % par locataire |
| inactif | `true` | actif avec les règles du fournisseur |
| rien | `true` | introuvable (`ERROR`) : un état seul ne déclare aucune règle |

C'est ce qui permet à un opérateur de désactiver sans déploiement un indicateur déclaré en YAML, puis de le réactiver
avec exactement le même déploiement progressif.

## Le contexte

`EvaluationContext::of(?string $tenant = null, ?string $project = null, ?string $subject = null)` construit un
contexte ; `EvaluationContext::anonymous()` n'a aucun attribut. Un attribut absent vaut `null` ; une chaîne vide est
refusée, pour qu'une règle puisse distinguer « absent » de « le locataire dont l'identifiant est vide ».

Le contexte ne porte **que des identifiants**, jamais un nom ni une adresse : il atteint chaque fournisseur. **Ne
journalisez pas une évaluation avec son contexte** : `subject` identifie une personne, et un journal de quelle personne
a vu quelle fonctionnalité est un dossier sur cette personne. `FlagEvaluator::evaluated()` ne conserve délibérément
aucun contexte.

## Le catalogue

`FlagCatalog` lit la configuration actuelle sans rien évaluer et sans contexte : chaque indicateur avec le fournisseur
qui le déclare (par identifiant de service), l'état que le magasin contient pour lui, et la définition qu'applique
l'évaluateur, selon la même priorité. C'est ce que montrent la commande de liste et l'administration.

| Méthode | Retourne |
|---|---|
| `entries(): list<CatalogEntry>` | Chaque indicateur que connaissent et peuvent lire les fournisseurs **énumérables**, la première déclaration l'emportant, trié par nom. Un fournisseur qui implémente seulement `FlagProvider` n'en apporte aucun. |
| `unreadable(): list<UnreadableEntry>` | Chaque indicateur qu'un `DiagnosableFlagProvider` contient sans pouvoir le lire, à sa place dans la préséance, trié par nom. |
| `lookup(FlagName $flag): CatalogEntry\|UnreadableEntry\|null` | L'entrée d'un indicateur, de n'importe quel fournisseur, une `UnreadableEntry` lorsque le fournisseur qui le déclare lance une `InvalidValue`, ou `null` lorsqu'aucun fournisseur ne le connaît. |
| `find(FlagName $flag): ?CatalogEntry` | L'entrée d'un indicateur, de n'importe quel fournisseur, ou `null` ; lance une `InvalidValue` lorsque le fournisseur qui le déclare ne peut pas le lire. |

`CatalogEntry` a `declared` (la définition du fournisseur), `source` (l'identifiant de service du fournisseur), `stored`
(`?bool`) et `effective()` (la définition déclarée, avec l'état stocké appliqué). `UnreadableEntry` a `name` (tel que le
fournisseur le stocke), `reason` et `source`.

**L'absent et l'illisible sont distingués**, comme l'évaluateur distingue `FLAG_NOT_FOUND` de `PARSE_ERROR`. Un
indicateur illisible prend la place que lui donne l'évaluateur : un indicateur qu'un fournisseur antérieur déclare
masque un indicateur illisible ultérieur, et un indicateur illisible masque la définition du même nom d'un fournisseur
ultérieur, puisque l'évaluateur s'arrête au fournisseur qui lance l'exception.
