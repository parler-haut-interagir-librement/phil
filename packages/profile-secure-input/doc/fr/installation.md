<!-- phil-dev source-sha256=7cc316019657fd458b34cd534446fca89b98278af2afbb16639a6c90fb271714 -->
# Installation

## Stabilité

Les membres sont des versions alpha, publiées sous la forme `0.x.y-alpha`. Composer les installe dans un projet qui
accepte cette stabilité ; `prefer-stable` garde tous les autres paquets à leur version stable quand il y en a une :

```sh
composer config minimum-stability alpha
composer config prefer-stable true
composer require phil/profile-secure-input
```

Les membres exigent Symfony 8.2 ou une version plus récente : tant que la 8.2 n'a pas de version stable, les mêmes
réglages permettent à Composer d'installer sa bêta ou sa version candidate.

Un indicateur de stabilité sur la seule exigence, `phil/profile-secure-input:@alpha`, ne suffit pas : Composer applique
les indicateurs aux paquets que le projet nomme, jamais aux membres qu'un métapaquet exige.

## Enregistrer les bundles

Avec Symfony Flex, chaque bundle est ajouté à `config/bundles.php` à son installation. Sans Flex, ajoutez-les :

```php
// config/bundles.php
return [
    // …
    Phil\UrlBundle\PhilUrlBundle::class => ['all' => true],
    Phil\RateLimitBundle\PhilRateLimitBundle::class => ['all' => true],
    Phil\BotDetectionBundle\PhilBotDetectionBundle::class => ['all' => true],
    Phil\SpamProtectionBundle\PhilSpamProtectionBundle::class => ['all' => true],
    Phil\UploadScanBundle\PhilUploadScanBundle::class => ['all' => true],
];
```

`phil/safe-http` est une bibliothèque : son client se construit là où l'application en a besoin, sans bundle.

## Ce qu'apporte l'application

| Paquet ou service | Pourquoi |
|---|---|
| Un démon ClamAV | `phil/upload-scan-bundle` analyse par clamd, sur `tcp://127.0.0.1:3310` sauf configuration contraire. |
| StimulusBundle avec AssetMapper | Les parties côté navigateur de `phil/spam-protection-bundle` et de `phil/bot-detection-bundle` sont des contrôleurs Stimulus. |

Ni `doctrine/dbal` ni les sessions ne sont nécessaires. `phil/url-bundle` n'enregistre son type de colonne Doctrine que
lorsque `doctrine/dbal` et DoctrineBundle sont installés. Quand Twig et `symfony/security-csrf` sont installés,
`phil/upload-scan-bundle` enregistre la page de sa quarantaine, dont FrameworkBundle vérifie le jeton d'après l'origine
de la requête : la protection CSRF est activée par défaut, et aucune session n'est démarrée pour elle. Une application
qui a désactivé `framework.csrf_protection` met `phil_upload_scan.admin.enabled` à `false`, ou la réactive.

## Ce qu'il faut configurer ensuite

Chaque membre fonctionne avec ses valeurs par défaut une fois enregistré. Les étapes habituelles :

1. Déclarez les limites de l'application dans `phil_rate_limit`, et placez `#[RateLimit]` sur les contrôleurs qu'elle
   protège.
2. Ajoutez l'option `spam_protection` aux formulaires publics.
3. Placez `#[CleanFile]` sur les champs de téléversement, et importez les routes de la page de quarantaine si des
   administrateurs s'en servent.
4. Employez `TypedUrlType` et `#[UrlOfType]` pour les liens que donnent les gens, et `SafeHttpClient` partout où
   l'application va en chercher un.

La page des [membres](members.md) renvoie à la documentation de chacun.
