<!-- phil-dev source-sha256=8aed0c3d376c86119ed6e39b70e798b4f7d8efbfa4a86a39ca8cde462557eae6 -->
# Installation

```sh
composer require phil/scheduling-poll-bundle
```
Enregistrez ensuite le bundle dans `config/bundles.php` :

```php
Phil\SchedulingPollBundle\PhilSchedulingPollBundle::class => ['all' => true],
```
Le paquet exige PHP 8.5 ou une version plus récente avec `ext-intl` et `ext-mbstring`, Symfony 8.2 ou une version plus
récente, Doctrine ORM 3.7 et DBAL 4.5, et les composants Messenger, Mailer et Scheduler. Les pages exigent TwigBundle,
`twig/intl-extra` pour les dates écrites dans la langue de la page, et les recettes du kit PHIL UX.

## Les clés et la page des liens

Les liens sont hachés sous des clés de l'application, de 32 octets ou plus chacune, et mènent à la page de réponse :

```yaml
# config/packages/phil_scheduling_poll.yaml
phil_scheduling_poll:
    links:
        keys: ['%env(SCHEDULING_POLL_LINK_KEY)%']
        page: 'https://example.org/scheduling-poll/answer'
```
```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'   # a key for SCHEDULING_POLL_LINK_KEY
```
Gardez la clé dans un secret de Symfony, jamais dans le dépôt. Pour la renouveler, mettez la nouvelle clé en premier et
gardez l'ancienne après elle jusqu'à ce que tous les liens envoyés sous elle aient expiré ; voir
[Vie privée](privacy.md#les-liens).

## Les tables

Le bundle fait correspondre ses entités pour Doctrine — `phil_scheduling_poll`, `phil_scheduling_poll_slot` et
`phil_scheduling_poll_respondent` — et ajoute les deux tables des liens, `phil_access_link_invitation` et
`phil_access_link`, au schéma que l'ORM génère. Une migration les écrit toutes :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```
## Les routes

```yaml
# config/routes/phil_scheduling_poll.yaml
phil_scheduling_poll:
    resource: '@PhilSchedulingPollBundle/config/routes.php'
    prefix: /scheduling-poll
```
La page des liens, `links.page`, est l'adresse de la route `phil_scheduling_poll_answer` sous ce préfixe.

## Le planificateur et le *worker*

Les échéances sont clôturées et les noms effacés par le planificateur `phil_scheduling_poll`, et les courriels sont
envoyés par les *handlers* des messages du bundle. Lancez un *worker* pour les deux :

```sh
php bin/console messenger:consume scheduler_phil_scheduling_poll async
```
Acheminez `Phil\SchedulingPollBundle\Application\Message\AnnounceChosenDate` et `SendChosenDate` vers un transport
asynchrone, afin qu'une clôture n'attende pas les courriels.

## Les recettes du kit

Les gabarits dessinent leurs parties avec les recettes `Table`, `Field`, `Input`, `NativeSelect` et `Button` du kit PHIL
UX, installées par le Symfony UX Toolkit :

```sh
for recipe in table field input native-select button; do
    php bin/console ux:install $recipe --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```
Le bundle les nomme d'après le sous-répertoire des composants de la configuration du Toolkit ; `kit_subdirectory` le lui
dit là où le Toolkit n'est pas installé. La recette Celebration n'est nécessaire que pour remplir le bloc `celebration`,
comme le montre [Pages et gabarits](pages.md#la-célébration).
