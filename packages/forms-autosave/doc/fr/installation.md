<!-- phil-dev source-sha256=f542d1a195b5c7d052995bcce6b7e35d680068673fd6748ceb7a0d9f4ec29005 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` et `ext-sodium`, Symfony 8.2 avec FrameworkBundle et le composant Form, et
Stimulus 3 dans le navigateur, par `symfony/stimulus-bundle`. Le stockage serveur demande aussi Doctrine DBAL 4 et ORM
3, les composants de routage et CSRF, un moyen de nommer l'utilisateur connecté et, sauf si le chiffrement est
désactivé, `symfony/key-management` avec un client de son `KeyManagementBundle` et une clé maîtresse.

## Activer le bundle

```sh
composer require phil/forms-autosave
```

Sans Flex, enregistrez-le :

```php
// config/bundles.php
return [
    // ...
    Phil\FormsAutosave\PhilFormsAutosaveBundle::class => ['all' => true],
];
```

Sans configuration, les brouillons restent dans le navigateur. Voir [Configuration](configuration.md) pour chaque
option.

## Le contrôleur

Avec AssetMapper, le bundle donne à AssetMapper le répertoire du contrôleur sous le nom `@phil/forms-autosave` et sa
feuille de style sous le nom `phil-forms-autosave`. Le StimulusBundle lit `assets/package.json` et enregistre le
contrôleur sous le nom `phil--forms-autosave--autosave` une fois le paquet listé dans `assets/controllers.json` :

```json
{
    "controllers": {
        "@phil/forms-autosave": {
            "autosave": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

La feuille de style est facultative ; la recette d'une trousse ou les règles propres à l'application peuvent la
remplacer :

```twig
<link rel="stylesheet" href="{{ asset('phil-forms-autosave/forms-autosave.css') }}">
```

## L'état

Le contrôleur écrit ce qu'il a fait dans une région active et affiche ses avis, à l'intérieur du formulaire. Incluez le
gabarit du bundle après `form_start()` :

```twig
{{ form_start(form) }}
    {{ include('@PhilFormsAutosave/status.html.twig') }}
    {{ form_widget(form) }}
{{ form_end(form) }}
```

Ou installez la recette `AutosaveStatus` de la trousse PHIL UX (`phil/ux-kit`) ou de sa version Tailwind
(`phil/ux-kit-shadcn`), et écrivez `<twig:AutosaveStatus />` à la place. Un formulaire sans l'un ni l'autre reçoit un
état et un avis propres au contrôleur, en haut.

## Le stockage serveur

```yaml
# config/packages/phil_forms_autosave.yaml
key_management:
    clients:
        local: 'sodium://?keys[drafts2026]=%env(DRAFTS_KEY)%'

phil_forms_autosave:
    server:
        enabled: true
        key: drafts2026
```

```yaml
# config/routes/phil_forms_autosave.yaml
phil_forms_autosave:
    resource: '@PhilFormsAutosaveBundle/config/routes.php'
    prefix: /_autosave
```

Les routes se trouvent derrière le pare-feu de l'application. La table `phil_form_draft` est mappée pour Doctrine :
créez-la avec les migrations de l'application (`doctrine:migrations:diff`). Planifiez la purge :

```sh
bin/console phil:forms-autosave:purge
```

Les brouillons sont scellés par le composant KeyManagement de Symfony sous la clé maîtresse que nomme `server.key`,
détenue par le client par défaut du composant ou par celui que nomme `server.kms`. La clé maîtresse d'un client
`sodium://` local est faite de 32 octets aléatoires en base64url, gardés dans une variable d'environnement ou dans le
coffre des secrets de Symfony ; une application qui a son propre KMS — AWS, Azure, Google Cloud, Vault — déclare plutôt
ce client. Sans le composant, la clé maîtresse ou son client, le conteneur s'arrête avec un message qui dit quoi
changer. Voir [Le stockage serveur](server-store.md).
