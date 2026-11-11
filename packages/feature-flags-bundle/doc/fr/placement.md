<!-- phil-dev source-sha256=3bbc39f847f9970517aaaafce93e988601cde5c23cf9f3447dc13bfd9e98d6c5 -->
# Placer un indicateur

Un indicateur se lit à quatre endroits, tous au moyen du même `FlagEvaluator` : dans une requête, le contrôleur, le
gabarit et le cœur lisent donc la même évaluation mémoïsée pour le même contexte.

## En bordure : l'attribut de contrôleur

```php
use Phil\FeatureFlagsBundle\Infrastructure\Http\IsFeatureEnabled;

#[IsFeatureEnabled('new-checkout')]                                   // 404 while the flag is off
final class CheckoutController
{
    #[IsFeatureEnabled('checkout-layout', variant: 'two-columns')]    // 404 outside that arm
    public function twoColumns(): Response { /* … */ }
}
```

`IsFeatureEnabled(string $flag, ?string $variant = null)` cible une classe ou une méthode et peut être répété.
`FeatureGateListener` écoute `kernel.controller` et, pour chaque `IsFeatureEnabled` sur la classe et la méthode du
contrôleur, évalue l'indicateur :

- sans `variant`, le contrôleur ne s'exécute que tant que l'indicateur est activé ;
- avec `variant`, seulement pour les contextes que l'expérience place dans cette variante : chaque branche peut donc
  avoir ses propres contrôleurs.

Sinon, il lance `NotFoundHttpException`. **Un indicateur désactivé répond 404, pas 403** : une fonctionnalité qui n'est
pas sortie n'existe pas encore, et dire qu'elle est interdite reviendrait à dire qu'elle existe.

Un nom d'indicateur invalide dans l'attribut lance `InvalidValue` à l'appel du contrôleur (une erreur 500), pas à la
construction.

## Dans le routage : la condition de route

`FlagRoutingCondition` est enregistré comme service de condition de routage sous l'alias `feature_flags`, avec deux
méthodes, `enabled(string $flag): bool` et `variant(string $flag): ?string` :

```yaml
# A route that matches only while its flag is on; the next route with the same path answers otherwise.
checkout_v2:
    path: /checkout
    controller: App\Controller\CheckoutV2Controller
    condition: "service('feature_flags').enabled('new-checkout')"

checkout:
    path: /checkout
    controller: App\Controller\CheckoutController
```

```php
#[Route('/checkout', condition: "service('feature_flags').variant('checkout-layout') == 'two-columns'")]
```

Déclarez la route protégée par un indicateur **avant** la route actuelle. Les conditions de route exigent
`symfony/expression-language`, et elles sont évaluées au moment de la correspondance, à chaque requête : le routeur
appelle donc l'évaluateur.

**Une condition de route ne lit qu'un indicateur qui ne cible ni sujets ni locataires.** Le routeur fait correspondre la
requête dans `RouterListener` (`kernel.request`, priorité 32), avant que le pare-feu (priorité 8) charge l'utilisateur
et avant les écouteurs qui fixent habituellement le locataire : le contexte selon lequel il évaluerait ne sait pas qui
fait la demande. Un indicateur avec une règle d'appartenance ou de pourcentage, ou une expérience, sur le sujet ou le
locataire est donc refusé. La condition lance `FlagNotRoutable` la première fois qu'elle évalue un tel indicateur, avec
un message qui nomme l'indicateur, et la requête répond 500. La vérification s'exécute à chaque évaluation : un
indicateur modifié dans l'administration pour cibler des locataires est donc refusé dès sa requête suivante. Un
indicateur activé ou désactivé pour tout le monde convient, tout comme un indicateur qui cible des projets, pourvu que
le résolveur puisse déterminer le projet à partir de la seule requête (son hôte, par exemple) avant le routage.

**Un indicateur qui cible des sujets ou des locataires protège un contrôleur avec `#[IsFeatureEnabled]`**, qui s'exécute
sur `kernel.controller`, après le pare-feu, et voit donc l'utilisateur authentifié. Pour servir un nouveau contrôleur
sur le chemin de l'actuel pour certains utilisateurs seulement, acheminez le chemin vers un seul contrôleur et faites
bifurquer le code à l'intérieur, ou protégez le nouveau contrôleur avec l'attribut sur un chemin qui lui est propre.

## Dans les gabarits : les fonctions Twig

`FeatureFlagsExtension` déclare deux fonctions avec `#[AsTwigFunction]` :

| Fonction | Retourne |
|---|---|
| `feature_enabled(flag)` | `bool` |
| `feature_variant(flag)` | le nom de la variante sous forme de chaîne, ou `null` |

```twig
{% if feature_enabled('new-checkout') %}…{% endif %}
{% if feature_variant('checkout-layout') == 'two-columns' %}…{% endif %}
```

Elles exigent TwigBundle (l'extension est enregistrée au moyen des étiquettes `twig.attribute_extension` et
`twig.runtime`).

## Dans le cœur

Un service interroge `FlagEvaluator::isEnabled()` ou `variant()` **une seule fois**, au point où il choisit entre deux
implémentations, et une méthode nommée de l'application garde le nom de l'indicateur à un seul endroit :

```php
final readonly class OrderMailer
{
    public function __construct(private FlagEvaluator $flags) {}

    private function includeOrderCancellationInEmail(): bool
    {
        return $this->flags->isEnabled(FlagName::of('order-cancellation-in-email'));
    }
}
```

Lorsque l'indicateur est retiré, seuls cette méthode et ses appelants changent. Évitez de disperser des appels à
`isEnabled()` sur le même indicateur à travers les couches : chacun est une branche à retirer plus tard.

Un indicateur peut aussi choisir une implémentation au niveau des services, avec un petit répartiteur qui contient les
deux implémentations et interroge l'évaluateur à chaque appel : la décision reste prise par requête, contrairement à une
fabrique résolue une seule fois lorsque le conteneur construit le service.

## Choisir l'endroit

| Endroit | À utiliser pour |
|---|---|
| Attribut de contrôleur | Une page ou un point de terminaison entièrement nouveau qui ne doit pas exister tant que l'indicateur est désactivé. |
| Condition de route | Un nouveau contrôleur qui remplace l'actuel sur le même chemin, pour un indicateur activé ou désactivé pour tout le monde (ou par projet). |
| Attribut de contrôleur, pour un indicateur ciblé | Une fonctionnalité pour certains sujets ou locataires : l'attribut s'exécute après le pare-feu. |
| Twig | Un bloc de page affiché ou masqué, une variante de mise en page. |
| Cœur | Un changement de comportement dans un service (un nouveau calcul, une nouvelle section de courriel). |
