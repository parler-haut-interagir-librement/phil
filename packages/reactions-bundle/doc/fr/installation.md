<!-- phil-dev source-sha256=9f02da6a6e4f032e1824b913abf2a5106eb684f0b35eb476570841d0ba476379 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl`, Symfony 8.2 ou plus récent, Live Component 3.8.1 et Twig Component 3.8 de Symfony UX, ou
plus récent, Doctrine DBAL 4.5 et ORM 3.7, et twig/intl-extra, dont `format_list` écrit les noms de ceux qui ont réagi
dans la langue de la page. Le bundle enregistre lui-même l'extension de twig/intl-extra, sauf si TwigExtraBundle est là
pour l'enregistrer. La protection CSRF de FrameworkBundle doit être activée : sinon, le conteneur s'arrête avec
un message qui le dit. Le jeton est sans état et n'a besoin d'aucune session.

## La recette Flex

La recette Flex du bundle l'enregistre dans `config/bundles.php`, écrit `config/packages/phil_reactions.yaml`, qui nomme
`App\Reactions\CurrentMember` comme membre, copie cette classe dans `src/Reactions/CurrentMember.php`, et écrit la route du
simple formulaire dans `config/routes/phil_reactions.yaml`. Tant que l'application ne fait pas lire le compte connecté
par la classe, elle ne désigne personne : chaque visiteur voit ce qui est compté, et l'application démarre aussi avec
Symfony Security. La recette vit dans le dépôt de recettes de PHIL, lu une fois que l'application liste ce dépôt dans
`extra.symfony.endpoint` ; sans lui, enregistrez le bundle et écrivez la configuration à la main, comme ci-dessous.

## Le bundle

```php
// config/bundles.php
Phil\ReactionsBundle\PhilReactionsBundle::class => ['all' => true],
```

## La table

Le bundle associe la table `phil_reaction` pour Doctrine : générez une migration, ou laissez les outils de schéma la
créer. L'instant d'une réaction, `reacted_at`, est une colonne `datetime_utc_immutable` : écrit et relu en UTC, quel que soit
le fuseau horaire du serveur ou de la connexion.

```sh
php bin/console doctrine:migrations:diff
```

## Les routes

Le simple formulaire envoie vers une seule route, dont le composant a besoin pour écrire son adresse :

```yaml
# config/routes/phil_reactions.yaml
phil_reactions:
    resource: '@PhilReactionsBundle/config/routes.php'
    prefix: /_reactions
```

## Les recettes

Les gabarits du bundle dessinent les boutons avec deux recettes du kit, installées dans l'application par le Symfony UX
Toolkit, sans rien rendre depuis `vendor/` :

```sh
php bin/console ux:install reaction-button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install reaction-picker --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Le kit `github.com/parler-haut-interagir-librement/ux-kit-shadcn` a les mêmes recettes pour Tailwind. Les composants
sont trouvés sous le sous-répertoire des composants de la configuration `ux_toolkit`. Dans un environnement où le
Toolkit n'est pas activé, comme l'est souvent la production, répétez-le dans l'option `kit_subdirectory`.

## Les contrôleurs Stimulus et la feuille de style

Avec AssetMapperBundle enregistré, le bundle ajoute ses contrôleurs et sa feuille de style aux chemins de l'extension
`asset_mapper`. Symfony Flex active les contrôleurs `reactions` et `picker` de `@phil/reactions-bundle` dans
`assets/controllers.json` à partir du `package.json` du bundle, chargés paresseusement : StimulusBundle charge chacun la
première fois qu'un élément le nomme. Importez la feuille de style :

```js
import 'phil-reactions/reactions.css';
```

Sans rien de tout cela, les réactions fonctionnent tout de même, par leurs simples formulaires.

## Le membre

Le site nomme le service qui donne l'identifiant stable d'un membre, jamais une adresse courriel :

```yaml
# config/packages/phil_reactions.yaml
phil_reactions:
    current_member: App\Reactions\AccountMember
```

Avec Symfony Security, le conteneur n'est pas construit tant que `current_member` n'est pas défini ; `TokenCurrentMember`
sert un site dont les identifiants d'utilisateur sont stables. Un identifiant qui contient `@` est refusé : voyez
[réactions](reactions.md#le-membre).

## Le voter

Chaque réaction est refusée jusqu'à ce qu'un *voter* réponde à l'attribut `phil_reactions.react` : voir
[réactions](reactions.md#qui-peut-réagir). Sans Symfony Security, nommez un `ReactionAccess` et un `CurrentMember`.
