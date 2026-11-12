# Installation

## Requirements

PHP 8.5 or later with `ext-mbstring` and `ext-sodium`, Symfony 8.2 with FrameworkBundle and the Form component, and
Stimulus 3 in the browser, through `symfony/stimulus-bundle`. The server store also needs Doctrine DBAL 4 and ORM 3,
the routing and CSRF components, a way to name the signed-in user, and, unless encryption is turned off,
`symfony/key-management` with a client of its `KeyManagementBundle` and a master key.

## Enabling the bundle

```sh
composer require phil/forms-autosave
```

Without Flex, register it:

```php
// config/bundles.php
return [
    // ...
    Phil\FormsAutosave\PhilFormsAutosaveBundle::class => ['all' => true],
];
```

With no configuration, drafts stay in the browser. See [Configuration](configuration.md) for every option.

## The controller

With AssetMapper, the bundle gives the controller's directory to AssetMapper as `@phil/forms-autosave` and its
stylesheet as `phil-forms-autosave`. The StimulusBundle reads `assets/package.json` and registers the controller as
`phil--forms-autosave--autosave` once the package is listed in `assets/controllers.json`:

```json
{
    "controllers": {
        "@phil/forms-autosave": {
            "autosave": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

The stylesheet is optional; a kit's recipe or the application's own rules can take its place:

```twig
<link rel="stylesheet" href="{{ asset('phil-forms-autosave/forms-autosave.css') }}">
```

## The status

The controller writes what it did in a live region, and shows its notices, inside the form. Include the bundle's
template after `form_start()`:

```twig
{{ form_start(form) }}
    {{ include('@PhilFormsAutosave/status.html.twig') }}
    {{ form_widget(form) }}
{{ form_end(form) }}
```

Or install the recipe `AutosaveStatus` of the PHIL UX kit (`phil/ux-kit`) or of its Tailwind version
(`phil/ux-kit-shadcn`), and write `<twig:AutosaveStatus />` instead. A form without either gets a status and a notice
of the controller's own, at its top.

## The server store

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

The routes sit behind the application's firewall. The table `phil_form_draft` is mapped for Doctrine: create it with
the application's migrations (`doctrine:migrations:diff`). Schedule the purge:

```sh
bin/console phil:forms-autosave:purge
```

The drafts are sealed through Symfony's KeyManagement component under the master key `server.key` names, held by the
component's default client or the one `server.kms` names. A master key of a local `sodium://` client is 32 random
bytes in base64url, kept in an environment variable or the Symfony secrets vault; an application with a KMS of its own
— AWS, Azure, Google Cloud, Vault — declares that client instead. Without the component, the master key or its
client, the container stops with a message that says what to change. See [The server store](server-store.md).
