<!-- phil-dev source-sha256=372489802a549371f0dc9f9d516060e54de3d826a8e48c7e87aa71272e2e8ffd -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-intl`, `ext-mbstring` et `ext-sodium`.
- Symfony 8.2 ou une version plus récente, avec la protection CSRF de FrameworkBundle activée : les votes et les
  recherches comptées sont vérifiés par son jeton sans état `phil_faq`, qui n'exige ni session ni témoin.
- Doctrine DBAL 4.5 et ORM 3.7, par DoctrineBundle.
- Twig et Symfony UX Twig Component 3.7 ou une version plus récente.
- Facultatifs : AssetMapper et StimulusBundle pour le contrôleur ; Symfony Security pour l'administration ; la console
  et le composant YAML pour les commandes ; EasyAdmin pour les liens d'un tableau de bord.

## Le bundle

```sh
composer require phil/faq-bundle
```

Symfony Flex enregistre le bundle, copie `config/packages/phil_faq.yaml` et `config/routes/phil_faq.yaml`, et affiche
les étapes ci-dessous. Sans Flex :

```php
// config/bundles.php
Phil\FaqBundle\PhilFaqBundle::class => ['all' => true],
```

## Les tables

Le bundle associe ses entités à DoctrineBundle. Créez les tables avec une migration :

```sh
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

| Table | Ce qu'elle contient |
|---|---|
| `phil_faq_section` | Une section : sa clé, sa place |
| `phil_faq_section_translation` | Le titre d'une section par langue |
| `phil_faq_question` | Une question : sa section, son ancre, sa place, ses totaux de réponses utiles et inutiles |
| `phil_faq_question_translation` | Par langue : la formulation, la réponse telle qu'écrite et en HTML, la publication, le moment du changement |
| `phil_faq_unanswered_search` | Une recherche sans réponse : sa langue, ses mots, son total, son dernier jour |
| `phil_faq_spent_vote` | L'empreinte d'un jeton de vote utilisé pour une question, jusqu'à l'expiration du jeton |

Les moments sont conservés en UTC (`datetime_utc_immutable`), quel que soit le fuseau horaire du serveur ou de la
connexion.

## Les routes

```yaml
# config/routes/phil_faq.yaml
phil_faq:
    resource: '@PhilFaqBundle/config/routes.php'
    prefix: /faq
```

| Route | Chemin | Ce qu'elle fait |
|---|---|---|
| `phil_faq_page` | `GET /` | La page des questions, filtrée par son `q` |
| `phil_faq_vote` | `POST /vote/{anchor}` | Un vote, par un formulaire simple ou en JSON |
| `phil_faq_unanswered` | `POST /unanswered` | Le décompte d'une recherche sans réponse, depuis le contrôleur |

L'administration a ses propres routes, montées derrière le pare-feu :

```yaml
phil_faq_admin:
    resource: '@PhilFaqBundle/config/admin_routes.php'
    prefix: /admin/faq
```

## Les recettes du kit

Le composant utilise trois recettes du kit PHIL UX, que le Symfony UX Toolkit copie dans l'application :

```sh
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install input --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install copy-button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Les noms suivent le sous-répertoire des composants du Toolkit : avec `ui`, le composant appelle `ui:Button`. Une
application sans la configuration du Toolkit règle `phil_faq.kit_subdirectory`.

## Le contrôleur Stimulus

Avec AssetMapper et StimulusBundle, Flex active le contrôleur dans `assets/controllers.json` ; il est chargé à la
demande, à la première page qui contient des questions. Sans lui, la page fonctionne de la même façon : la recherche va
au serveur, les votes reviennent à la page.
