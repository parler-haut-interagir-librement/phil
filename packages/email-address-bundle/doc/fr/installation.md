<!-- phil-dev source-sha256=5970080c8485c6877b1d67d214eca71070d7eb573a0e8638891c46c8bd06508e -->
# Installation

```sh
composer require phil/email-address-bundle
```

Enregistrez ensuite le bundle dans `config/bundles.php`, ce que fait la recette Flex :

```php
Phil\EmailAddressBundle\PhilEmailAddressBundle::class => ['all' => true],
```

Le paquet exige PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente.

## Les parties et leurs paquets

Le bundle n'enregistre chaque partie que lorsque le paquet dont elle a besoin est installé :

| Partie | Paquet |
|---|---|
| Les contraintes `DeliverableEmailAddress` et `AcceptedEmailAddress`, `EmailAddresses`, les règles de rebond | toujours |
| `EmailAddressType` | `symfony/form` |
| La route des domaines connus | `symfony/routing` |
| `phil:email-address:read-bounce` et `phil:email-address:poll-mailbox` | `symfony/console` |
| Le consommateur du webhook d'un fournisseur | `symfony/remote-event`, avec `symfony/webhook` et le pont Mailer du fournisseur |
| `BounceDecidedMessage` transmis à un worker | `symfony/messenger` |

## La route

Le contrôleur Stimulus lit les domaines connus, les fautes de domaine de premier niveau et les adresses de rôle sur une
route du bundle. Importez-la, comme le fait la recette, et donnez son URL à la configuration :

```yaml
# config/routes/phil_email_address.yaml
phil_email_address:
    resource: '@PhilEmailAddressBundle/config/routes.php'
    prefix: /email-address
```

```yaml
# config/packages/phil_email_address.yaml
phil_email_address:
    suggestions:
        url: /email-address/domains.json
```

La réponse est la même pour tout le monde : publique, gardée un jour, et répondue par `304 Not Modified` quand le
navigateur renvoie son ETag.

## Le contrôleur Stimulus

Le contrôleur est fourni dans `assets/` avec un `package.json` que lit StimulusBundle : avec AssetMapper, on l'active
dans `assets/controllers.json` sous le nom `phil--email-address-bundle--email-address`, comme pour tout contrôleur d'un
bundle. `EmailAddressType` écrit ses attributs sur le champ quand `suggestions.url` est défini.

La région vivante où écrit le contrôleur a la classe `phil-email-address-hint` ; stylez-la comme les autres indices du
site. Sans JavaScript, la même suggestion ou le même avertissement devient le texte d'aide du champ une fois le
formulaire soumis.

## Les entrées de rebond

- Le webhook d'un fournisseur : configurez le composant Webhook pour le pont Mailer du fournisseur, puis nommez le
  webhook dans `bounces.remote_event_consumers`.
- Un message brut : faites envoyer par le serveur de courriel l'adresse des rebonds à
  `bin/console phil:email-address:read-bounce`.
- La boîte des rebonds : donnez un Maildir dans `bounces.maildir`, ou un service qui implémente `BounceMailbox` dans
  `bounces.mailbox`, et lancez `phil:email-address:poll-mailbox` depuis le planificateur.

[Rebonds](bounces.md) décrit chacune.
