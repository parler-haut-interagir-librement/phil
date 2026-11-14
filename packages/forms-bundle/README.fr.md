<!-- phil-dev source-sha256=bd9ef84044904c18f823236dd05fa658d024041317ab50bbf6647de3e22bc8ac -->
# phil/forms-bundle

**Des formulaires de contact et d'inscription définis dans la base de données, avec des entrées conservées seulement le
temps nécessaire.**

phil/forms-bundle donne à une application Symfony des formulaires qui vivent dans sa base de données. Un formulaire est
une clé, un titre et des champs : texte, paragraphes, courriel, URL, date, date de naissance, choix, case à cocher. Un
champ peut être demandé sous une condition portant sur les champs qui le précèdent, ou seulement pour certains paliers
— un type de billet, une adhésion — ; un choix peut disqualifier l'entrée avec un message, ou contenir un nombre de
places. Le bundle donne à chaque formulaire une page à `/forms/{key}`, protégée contre le pourriel par
`phil/spam-protection-bundle`, sans captcha ni tiers. Il conserve chaque entrée au plus le temps que le formulaire
indique, puis `phil:forms:purge` la supprime. Il avertit de chaque entrée les personnes qui doivent l'être : par
courriel depuis l'adresse du site, à ses destinataires et à d'autres adresses sous conditions —, par un webhook signé
selon Standard Webhooks, ou par les notificateurs propres à l'application. Les valeurs des champs sensibles ne partent
jamais par courriel ni par webhook, et sont scellées sous une clé maîtresse d'un KMS par le composant KeyManagement de
Symfony ; un formulaire qui a un champ sensible est refusé tant qu'aucun scelleur n'est configuré. Une date de naissance
peut ne prendre que certains âges, comptés le jour où l'entrée arrive ou à une date que choisit le formulaire. Ses
conditions lisent `==`, `!=` et `in` sur un champ ; avec `phil/form-logic`, elles s'appliquent aussi dans le
navigateur à mesure que le formulaire se remplit, et peuvent lire tout le sous-ensemble de JsonLogic de cette
bibliothèque, vérifié selon les types des champs.

Il ne conserve ni l'adresse IP du visiteur, ni son navigateur, ni ses témoins de connexion : une entrée contient les
valeurs, sa langue et ses dates. Il n'envoie jamais de copie au visiteur, ce qui ferait d'un formulaire un relais de
pourriel, et il n'affiche jamais de captcha. Pour répondre à une demande d'accès ou d'effacement, une entrée peut aussi
conserver l'identifiant de la personne connectée, ou une empreinte à clé d'une adresse courriel que le formulaire
désigne. Avec `phil/privacy-bundle`, ces entrées sont exportées ou effacées par personne.

Il s'adresse à un site qui recueille des inscriptions, des questions ou des commentaires, et dont les formulaires sont
des données que l'application crée et modifie en cours d'exécution. La durée de conservation par formulaire, de 1 à 3650
jours, sert la limitation des finalités de la Loi 25 du Québec et du RGPD. Symfony Mailer, le client HTTP, Console,
Routing et Security sont facultatifs, chacun pour la partie qui en a besoin. `Form::accept()` est le seul endroit où les
valeurs sont vérifiées, et chaque notificateur implémente `EntryNotifier` : l'entrée est enregistrée d'abord, donc un
notificateur qui échoue ne fait rien perdre.

## État

| Partie | État |
|---|---|
| `Form`, `Entry`, `Field`, `FieldType` | Implémenté et testé |
| Conditions (`ConditionEvaluator`, `BasicConditions`), paliers, choix disqualifiants, date de naissance et ses âges | Implémenté et testé |
| Conditions plus riches par l'adaptateur `JsonLogicConditions` de `phil/form-logic` | Implémenté et testé, dans cette bibliothèque |
| Places dans les choix (`ChoicePlaces`), autres notifications sous conditions (`Notification`) | Implémenté et testé |
| Champs sensibles scellés par le composant KeyManagement de Symfony (`FieldSealer`, `KeyManagementFieldSealer`, `SealedValues`), refusés sans scelleur (`UnsealedSensitiveField`, `allow_unsealed_sensitive`) | Implémenté et testé |
| Conditions appliquées dans le navigateur par le contrôleur Stimulus de `phil/form-logic` (`LogicSteps`), quand cette bibliothèque est installée | Implémenté et testé |
| `Submissions`, `Retention`, `EntryNotifier` | Implémenté et testé |
| `EntryType`, `FormController` et `config/routes.php` | Implémenté et testé |
| `EmailNotifier`, `WebhookNotifier` | Implémenté et testé |
| `DoctrineFormRepository`, `phil:forms:purge` | Implémenté et testé |
| Recette Flex : le bundle, sa configuration, ses routes | Implémenté et testé |
| Gabarits et traductions, en anglais et en français | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, la recette, l'enregistrement, les routes, les tables, la configuration. |
| [Formulaires](doc/fr/forms.md) | Définir un formulaire, ses champs, ce que chacun accepte, les conditions, les paliers, les places, les champs sensibles. |
| [Entrées et conservation](doc/fr/entries.md) | Ce qui est conservé, pendant combien de temps, et sa suppression. |
| [Notifications](doc/fr/notifications.md) | Le courriel, le webhook et les notificateurs propres à l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | La façon dont le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```php
$form = Form::create('registration', 'Tournament registration', [
    Field::of('name', 'Name', FieldType::Text, required: true),
    Field::of('email', 'Email', FieldType::Email, required: true),
    Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green'], capacities: ['Blue' => 20, 'Green' => 20]),
    Field::of('captain', 'I will captain my team', FieldType::Checkbox, shownWhen: ['in' => [['var' => 'team'], ['Blue', 'Green']]]),
    Field::of('consent', 'I accept the rules', FieldType::Checkbox, required: true),
], retentionDays: 90);
$form->notify(['games@example.com'], 'https://hooks.example.com/forms');
$form->notifyAlso([Notification::to(['blue@example.com'], 'Blue: {name}', ['==' => [['var' => 'team'], 'Blue']])]);
$repository->save($form);
// The page: /forms/registration
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-filter` et `ext-mbstring` ; Doctrine ORM 3.7 ; `phil/spam-protection-bundle` 0.1 et
`phil/standard-webhooks` 0.1 ; les composants Form, HttpFoundation et HttpKernel, Uid et DependencyInjection de Symfony
8.2 ; l'horloge de PSR-20 ; Twig 3. Symfony Mailer, le client HTTP, Console et Routing sont facultatifs, tout comme
`phil/privacy-bundle` et le cœur de Symfony Security ; `symfony/key-management` scelle les valeurs des champs sensibles,
et un formulaire qui en a un est refusé sans scelleur ; `phil/form-logic` applique les conditions dans le navigateur
et en lit de plus riches.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
