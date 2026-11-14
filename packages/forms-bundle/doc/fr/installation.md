<!-- phil-dev source-sha256=3eebb3bf8b862920644f2ed0b4ae064db9816b400c5a4cf5249e47f72b97c09c -->
# Installation

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-filter`, `ext-mbstring` | toute | Les adresses courriel ; les longueurs en caractères |
| `doctrine/orm` | ^3.7 | Les formulaires et les entrées |
| `phil/spam-protection-bundle` | ^0.1 | La protection de chaque formulaire |
| `phil/standard-webhooks` | ^0.1 | La signature des webhooks |
| `symfony/form`, `symfony/http-foundation`, `symfony/http-kernel` | ^8.2 | La page d'un formulaire |
| `symfony/dependency-injection`, `symfony/config`, `symfony/uid` | ^8.2 | Le bundle ; les identifiants |
| `psr/clock` | ^1.0 | La réception et l'expiration d'une entrée, et l'horodatage du webhook ; le service `clock` du conteneur, que FrameworkBundle enregistre une fois `symfony/clock` installé |
| `twig/twig` | ^3.21 | La page et le courriel |
| `symfony/mailer`, `symfony/http-client`, `symfony/console` | facultatif | Le courriel ; le webhook ; `phil:forms:purge` |
| `symfony/routing` | facultatif | L'importation de `config/routes.php` |
| `phil/privacy-bundle` | facultatif | Les demandes d'accès et d'effacement trouvent les entrées qui concernent une personne |
| `symfony/security-core` | facultatif | La personne connectée est conservée avec ses entrées |
| `symfony/key-management` | pour les champs sensibles | Les valeurs des champs sensibles scellées sous une clé maîtresse d'un KMS |
| `phil/form-logic` | facultatif | Les conditions appliquées aussi dans le navigateur, et lues comme tout son sous-ensemble de JsonLogic |

```sh
composer require phil/forms-bundle
```

## La recette

La recette Flex du bundle l'enregistre dans `config/bundles.php`, écrit `config/packages/phil_forms.yaml` avec les
valeurs par défaut ci-dessous — pas de courriel, pas de webhook, champs sensibles refusés — et
`config/routes/phil_forms.yaml`, qui importe la page de chaque formulaire sous `/forms`. Flex enregistre
`PhilSpamProtectionBundle` avec son paquet. La recette se trouve dans le dépôt de recettes de PHIL, lu une fois que
l'application liste ce dépôt dans `extra.symfony.endpoint` ; sans cela, enregistrez les bundles et écrivez la
configuration à la main, comme ci-dessous.

## À la main

```php
// config/bundles.php
return [
    // ...
    Phil\SpamProtectionBundle\PhilSpamProtectionBundle::class => ['all' => true],
    Phil\FormsBundle\PhilFormsBundle::class => ['all' => true],
];
```

Sans `PhilSpamProtectionBundle`, le conteneur refuse de se construire.

```yaml
# config/packages/phil_forms.yaml
phil_forms:
    sender: site@example.com                    # null: no email
    subject_prefix: '[Site] '
    webhook_secret: '%env(FORMS_WEBHOOK_SECRET)%'  # whsec_…; null: no webhook
    template: '@PhilForms/form.html.twig'       # the page of a form
    digest_key: '%kernel.secret%'               # the key of the addresses' digests
    allow_unsealed_sensitive: false             # true keeps sensitive values as sent when no sealer is configured
    sealing:
        key: null                               # a master key of the KMS; null: no sealer, sensitive fields refused
        kms: null                               # a client such as key_management.vault; null: the default client
```

```yaml
# config/routes/phil_forms.yaml
phil_forms:
    resource: '@PhilFormsBundle/config/routes.php'
    prefix: /forms
```

## Tables

Avec DoctrineBundle, le bundle mappe lui-même ses entités. Les migrations de l'application créent trois tables :

| Table | Contenu |
|---|---|
| `phil_form` | Un formulaire : sa clé, son titre, ses champs (JSON), ses destinataires (JSON), ses autres notifications (`notifications`, JSON), son webhook, sa conservation, s'il est ouvert, le champ de courriel qui nomme la personne (`subject_field`) |
| `phil_form_entry` | Une entrée : son formulaire, ses valeurs (JSON), sa date d'arrivée et sa date de suppression, sa langue, l'identifiant de la personne (`subject`) et l'empreinte de son adresse (`email_digest`), chacun indexé, et les champs dont les valeurs sont scellées (`sealed_fields`, JSON) |
| `phil_form_choice_count` | Les places occupées dans un choix : son formulaire, son champ (`field_name`) et son choix, la clé, et le décompte (`taken`) |

## Configuration

| Clé | Valeur par défaut | Rôle |
|---|---|---|
| `sender` | `null` | L'adresse depuis laquelle les entrées sont envoyées par courriel ; null n'envoie aucun courriel |
| `subject_prefix` | `''` | Placé avant le titre du formulaire dans le sujet |
| `webhook_secret` | `null` | Le secret de Standard Webhooks, `whsec_…` ; null ne poste à aucun webhook |
| `template` | `@PhilForms/form.html.twig` | La page d'un formulaire |
| `digest_key` | `%kernel.secret%` | La clé des empreintes d'adresses qui nomment la personne que concerne une entrée |
| `allow_unsealed_sensitive` | `false` | Conserve les valeurs sensibles telles qu'envoyées quand aucun scelleur n'est configuré, au lieu de refuser un formulaire qui a un champ sensible ; voir l'avertissement dans [Entrées et conservation](entries.md#conserver-les-valeurs-sensibles-telles-quenvoyees) |
| `sealing.key` | `null` | La clé maîtresse du KMS qui scelle les valeurs sensibles ; null ne configure aucun scelleur |
| `sealing.kms` | `null` | Le service d'un client du composant KeyManagement ; null pour son client par défaut |

## Avec phil/form-logic

Avec `phil/form-logic` installé, la page écrit le contrôleur Stimulus de la bibliothèque sur un formulaire dont des
champs ont des conditions, et le navigateur les affiche et les masque à mesure que le formulaire se remplit ; le
contrôleur est servi par AssetMapper et StimulusBundle, comme la bibliothèque le décrit dans son `doc/installation.md`,
et la page de l'application charge l'import map — voir [Formulaires](forms.md#dans-le-navigateur). Un alias du port
`ConditionEvaluator` vers son `JsonLogicConditions` lit en outre les conditions comme tout le sous-ensemble de
JsonLogic de la bibliothèque ; voir [Formulaires](forms.md#conditions-plus-riches-avec-philform-logic).

## Champs sensibles

Un formulaire qui a un champ sensible a besoin d'un scelleur : installez `symfony/key-management`, enregistrez son
`KeyManagementBundle` avec un client, et nommez une clé maîtresse de ce client dans `sealing.key`, comme le montre
[Entrées et conservation](entries.md#valeurs-sensibles). Sans scelleur, un tel formulaire est refusé, sauf si
`allow_unsealed_sensitive` est vrai.
