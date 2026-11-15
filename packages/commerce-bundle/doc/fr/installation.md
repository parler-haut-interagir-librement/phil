<!-- phil-dev source-sha256=b129af2114ab2946e03aba859c5b670626bbeaf748fe79c86108a59e24c0dab4 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le bundle déclare et sur lequel il est testé |
| `ext-filter` | toute version | `PaymentAttemptKey::forClientIp()` valide les adresses avec `filter_var()` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue`, que lèvent les types du commerce |
| `ext-json` | toute version | Les colonnes JSON des lignes et des ajustements d'une commande |
| `ext-mbstring` | toute version | `ReceiptPdf` écrit le texte en Windows-1252 |
| `ext-sodium` | toute version | Les jetons des liens de paiement et des listes de souhaits partagées, encodés par `sodium_bin2base64()` |
| `doctrine/orm` | ^3.7 | Les attributs de mappage d'`Order` et l'adaptateur du dépôt |
| `doctrine/dbal` | ^4.5 | Les types de colonnes des lignes et des ajustements d'une commande |
| `phil/money` | ^0.1@dev | `Money` et `Currency`, dans lesquels chaque montant d'une commande est compté |
| `phil/tax` | ^0.1@dev | `TaxRate` et le calculateur à plusieurs taux, avec lesquels chaque ligne est taxée |
| `phil/paged-media` | ^0.1@dev | La mise en page du PDF de la facture |
| `psr/clock` | ^1.0 | Le moment où un paiement notifié est annoncé, d'après l'horloge de l'application |
| `symfony/event-dispatcher-contracts` | ^3.6 | Les événements du domaine et ceux du workflow, envoyés quand l'application a un répartiteur |
| `symfony/workflow` | ^8.2 | La machine à états `phil_commerce_order` |
| `symfony/dependency-injection` | ^8.2 | La classe du bundle est un `AbstractBundle` ; les services qu'il enregistre |
| `symfony/config` | ^8.2 | L'arbre de configuration `tax_rates` |
| `symfony/http-foundation` | ^8.2 | La requête et la réponse du contrôleur de *webhook* |
| `symfony/uid` | ^8.2 | L'UUID ordonné dans le temps qui nomme un paiement |

Les commandes sont stockées par DoctrineBundle quand l'application l'enregistre ; aucune bibliothèque de processeur
de paiement ni de client HTTP n'est requise.

## Installer

Le bundle n'est pas encore publié sur Packagist. Installez-le, ainsi que `phil/ddd-bundle`, depuis le dépôt qui les
contient, par des dépôts Composer de type `path` ou `vcs` :

```json
{
    "repositories": [
        { "type": "path", "url": "../path/to/packages/*" }
    ],
    "require": {
        "phil/commerce-bundle": "^0.1@dev"
    }
}
```

Une fois publié :

```bash
composer require phil/commerce-bundle
```

## La recette

La recette Flex enregistre le bundle, écrit `config/packages/phil_commerce.yaml` sans taxe, sans méthode hors ligne et
avec des remboursements en deux temps, et `config/routes/phil_commerce.yaml` avec la route du point de terminaison de
*webhook* ([payments.md](payments.md#le-point-de-terminaison-des-webhooks)). Sans Flex, enregistrez le bundle et écrivez
les deux fichiers à la main.

## Enregistrer le bundle

```php
// config/bundles.php
return [
    // …
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
];
```

L'enregistrer lit `phil_commerce.tax_rates`, enregistre `OrderModifier` et le processeur de taxes, étiquette les
services qui implémentent ses ports et, avec DoctrineBundle, mappe et stocke les commandes (voyez
[configuration.md](configuration.md)). Générez une migration pour les tables `phil_commerce_order`,
`phil_commerce_channel`, `phil_commerce_payment`, `phil_commerce_receipt`, `phil_commerce_quote`,
`phil_commerce_deferred_sale`, `phil_commerce_wish_list`, `phil_commerce_payment_link`, `phil_commerce_order_change`,
`phil_commerce_order_state_change`, `phil_commerce_refund` et `phil_commerce_document_sequence` comme pour les entités
propres à l'application. La migration peut aussi insérer une rangée par série dans `phil_commerce_document_sequence`
(`series` `receipt` et `credit_note`, `last_number` à 0, ou au dernier numéro qu'a émis un système précédent) : le
bundle crée lui-même une rangée absente, mais une rangée créée d'avance n'est jamais l'objet d'une course. Routez le point de terminaison de *webhook*
([payments.md](payments.md#le-point-de-terminaison-des-webhooks)), et enregistrez à côté les bundles des modules avec
lesquels vous vendez ([modules.md](modules.md)).

`PhilDddBundle` n'a pas besoin d'être enregistré pour le commerce : seules ses classes de domaine sont utilisées.

## Déclarer les services que vous utilisez

```yaml
# config/services.yaml
parameters:
    app.payment_attempts.budget: 5
    app.payment_attempts.penalty_step: 30
    app.payment_attempts.window: 900

services:
    app.payment_attempt_policy:
        class: Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptPolicy
        factory: [Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptPolicy, of]
        arguments:
            - '%app.payment_attempts.budget%'
            - '%app.payment_attempts.penalty_step%'
            - '%app.payment_attempts.window%'

    Phil\CommerceBundle\Domain\Service\PaymentAttemptLimiter:
        arguments: ['@app.payment_attempt_policy']

    Phil\CommerceBundle\Application\Port\PaymentAttemptStateStore: '@App\Commerce\CachePaymentAttemptStateStore'
```

Les valeurs ci-dessus sont des exemples, pas des recommandations : le budget, le pas et la fenêtre sont la décision
de l'application (voyez [payment-attempts.md](payment-attempts.md)). Les contrats des adaptateurs sont dans
[extension-points.md](extension-points.md).
