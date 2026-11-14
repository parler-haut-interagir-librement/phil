<!-- phil-dev source-sha256=df0f717d73f9e96717ea9911e2ce494ff066725c01a5c5000ba8a1b4194e027a -->
# Formulaires

## Définir un formulaire

```php
$form = Form::create('registration', 'Tournament registration', $fields, retentionDays: 90);
$form->rename('Registration');
$form->define($fields);        // the fields, replaced
$form->keepFor(30);            // 1 to 3650 days
$form->identifyBy('email');    // the email field that names the person an entry is about; null for none
$form->close();                // its page is a 404, it takes no entry
$form->open();
$repository->save($form);
```

Une clé compte de 1 à 64 lettres minuscules, chiffres ou `-`, et elle est unique : c'est l'adresse du formulaire,
`/forms/{key}`. Un formulaire a au moins un champ, et chaque nom n'apparaît qu'une fois.

## Champs

```php
Field::of('message', 'Your message', FieldType::Textarea, required: true, maxLength: 2000);
Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']);
Field::of('born', 'Date of birth', FieldType::BirthDate, required: true);
```

| Type | Accepte | Longueur maximale par défaut |
|---|---|---|
| `Text` | Une ligne | 255 |
| `Textarea` | Plusieurs lignes | 5000 |
| `Email` | Une adresse courriel | 255 |
| `Url` | Une URL `http` ou `https` | 255 |
| `Date` | Une date existante, `YYYY-MM-DD` | — |
| `BirthDate` | Une date existante, qui ne tombe pas après le jour de l'arrivée de l'entrée ni plus de 130 ans avant ; avec des âges, une personne dans leurs bornes | — |
| `Choice` | L'un de ses choix | — |
| `Checkbox` | Cochée ou non ; requise signifie qu'elle doit être cochée, pour un consentement | — |

Un nom compte de 1 à 64 lettres minuscules, chiffres ou `_`, et commence par une lettre ; un libellé, de 1 à 255
caractères. Une longueur, de 1 à 10000, ne s'applique qu'aux champs de texte ; des choix, de 1 à 500 de 1 à 255
caractères chacun, donnés une seule fois, ne s'appliquent qu'aux champs de choix.

Le bundle s'en tient volontairement à ces types : les nombres, les échelles, les évaluations, les téléversements de
fichiers, les adresses et les autres champs avancés relèvent d'un bundle de sondage construit par-dessus, pas de
celui-ci.

## Âges d'une date de naissance

Une date de naissance peut ne prendre que certains âges, comptés en années complètes :

```php
Field::of('born', 'Date of birth', FieldType::BirthDate, required: true, minAge: 18);
Field::of('born', 'Date of birth', FieldType::BirthDate, minAge: 8, maxAge: 12, referenceDate: '2027-07-01');
```

| Option | Clé dans la définition | Prend |
|---|---|---|
| `minAge` | `min_age` | L'âge le plus jeune accepté, de 0 à 130 |
| `maxAge` | `max_age` | L'âge le plus vieux accepté, de 0 à 130, pas sous `minAge` |
| `referenceDate` | `reference_date` | Le jour où l'âge est compté, `YYYY-MM-DD`, avec `minAge` ou `maxAge` seulement ; aucune : le jour où l'entrée arrive |

Les deux bornes sont incluses. Avec `minAge: 18`, une personne dont le dix-huitième anniversaire tombe le jour de
référence est acceptée, et celle dont l'anniversaire tombe le lendemain est refusée avec `too_young`. Avec
`maxAge: 12`, une personne de 12 ans est acceptée jusqu'à la veille de son treizième anniversaire ; ce jour-là, elle est
refusée avec `too_old`. Une année est complète à l'anniversaire ; une personne née un 29 février la complète le 1er mars
dans une année sans 29 février. `minAge` et `maxAge` peuvent être égaux, pour un seul âge, et l'un ou l'autre peut
exister seul.

Le jour où l'entrée arrive est lu sur l'horloge que reçoit `Submissions` — le service `clock` du conteneur, de
`symfony/clock` —, de sorte qu'un test la règle avec une horloge factice. Une date de référence convient à un
événement : les âges du jour d'un camp, quel que soit le jour où l'entrée arrive. Les messages de refus,
`phil_forms.error.too_young` et `phil_forms.error.too_old`, prennent l'âge comme `%age%`. Une date de naissance n'est
jamais après le jour où l'entrée arrive, quelle que soit la date de référence.

Seule une date de naissance a des âges ; un âge hors de 0 à 130, `minAge` au-dessus de `maxAge`, une date de référence
sans âge, ou qui n'est pas une date, est refusé avec `InvalidForm`.

## Questions posées sous une condition

Un champ peut n'être demandé que lorsqu'une condition portant sur les champs **qui le précèdent** tient :

```php
Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']),
Field::of('captain', 'Captain of Blue?', FieldType::Checkbox, shownWhen: ['==' => [['var' => 'team'], 'Blue']]),
Field::of('armband', 'Armband size', FieldType::Text, required: true, shownWhen: ['==' => [['var' => 'captain'], true]]),
```

Une condition est une donnée dont le JSON est un sous-ensemble de JsonLogic. `BasicConditions`, l'évaluateur du bundle,
lit trois formes, chacune portant sur un champ :

| Condition | Tient quand le champ |
|---|---|
| `{"==": [{"var": "team"}, "Blue"]}` | a cette valeur ; `true` ou `false` pour une case à cocher |
| `{"!=": [{"var": "team"}, "Blue"]}` | a une autre valeur, ou aucune : laissé vide |
| `{"in": [{"var": "team"}, ["Blue", "Green"]]}` | a l'une de 1 à 100 valeurs ; pas pour une case à cocher |

`Form::define()` vérifie chaque condition selon les types des champs qui la précèdent : un champ qui vient plus tard,
une valeur qu'un champ de choix ne prend jamais, ou une case à cocher comparée à un texte est refusé. Quand une entrée
arrive, un champ est demandé quand sa condition tient et que chaque champ qu'elle lit a lui-même été demandé, de sorte
que masquer une question masque les questions qui en découlent. **Un champ non demandé ne conserve aucune valeur**,
quoi que le visiteur ait envoyé, et n'est pas requis. Les valeurs sont comparées strictement. Un champ laissé vide n'a
aucune valeur : comme dans JsonLogic, il satisfait `!=` — l'absence de valeur n'est pas `"Blue"` —, et ni `==` ni
`in` ; un champ non demandé ne satisfait aucune condition, pas même `!=`.

Une application à la logique plus riche crée un alias de `ConditionEvaluator` vers un autre évaluateur ([points
d'extension](extension-points.md#conditionevaluator)).

### Dans le navigateur

Sans `phil/form-logic`, la page affiche tous les champs et laisse la définition décider ; le libellé de la question dit
quand y répondre.

Avec `phil/form-logic` installé, les conditions s'appliquent aussi dans le navigateur, à mesure que le formulaire se
remplit. `EntryType` nomme alors la rangée de chaque champ posé sous une condition dans `data-form-logic-field`, et
`_form.html.twig` écrit le contrôleur Stimulus de la bibliothèque sur le formulaire, ses étapes tirées des conditions
des champs que la page affiche :

```html
<form name="phil_form" method="post" class="phil-form" novalidate="novalidate"
      data-controller="phil--form-logic--logic"
      data-phil--form-logic--logic-steps-value="[{&quot;field&quot;:&quot;captain&quot;,…}]">
    …
    <div data-form-logic-field="captain">…</div>
</form>
```

Le contrôleur masque la rangée d'un champ dont la condition ne tient pas et désactive ses contrôles, de sorte que le
navigateur ne les envoie pas, et l'affiche de nouveau avec sa valeur quand la condition tient. Il juge comme
`Form::accept()` : un champ est affiché quand sa condition tient et que chaque champ qu'elle lit est affiché, de sorte
qu'un champ laissé vide et un champ non demandé ne se lisent pas de la même façon ; une condition qui lit un champ que
la page n'affiche pas — d'un autre palier que ceux de l'entrée — masque son champ. Le serveur juge l'entrée de nouveau,
quoi que le navigateur ait envoyé.

Le contrôleur est servi par AssetMapper et StimulusBundle, comme `phil/form-logic` le décrit dans son
`doc/installation.md`, et la page charge l'import map : la page propre d'une application inclut `_form.html.twig` dans
une mise en page qui appelle `importmap()` ; la page nue du bundle, `form.html.twig`, n'en charge aucune. Une page qui
rend le formulaire autrement écrit les deux attributs du formulaire à partir de `form.vars.phil_form_logic_steps`, nul
quand aucun champ affiché n'a de condition ; les rangées portent les leurs par l'option `row_attr`. Un formulaire sans
conditions est rendu comme sans la bibliothèque.

### Conditions plus riches avec phil/form-logic

`phil/form-logic` lit les conditions comme tout son sous-ensemble de JsonLogic — `and`, `or`, `!`, ordres et
intervalles, `missing`, arithmétique —, par son adaptateur `Phil\FormLogic\FormsBundle\JsonLogicConditions` :

```yaml
# config/services.yaml
services:
    Phil\FormLogic\FormsBundle\JsonLogicConditions: ~
    Phil\FormsBundle\Domain\Contract\ConditionEvaluator: '@Phil\FormLogic\FormsBundle\JsonLogicConditions'
```

```php
Field::of('armband', 'Armband size', FieldType::Text, shownWhen: ['and' => [
    ['==' => [['var' => 'team'], 'Blue']],
    ['!' => [['var' => 'captain']]],
]]),
```

`Form::define()` vérifie alors chaque condition avec le vérificateur de types de la bibliothèque : un choix est typé
par ses choix, une case à cocher comme un booléen, une date ou une date de naissance comme un jour, tout autre champ
comme un texte. Les règles propres au bundle demeurent : une condition ne lit que les champs qui la précèdent, et celle
qui lit un champ non demandé ne tient pas.

Les trois formes que lit `BasicConditions` ont le même sens sous `JsonLogicConditions`, un champ laissé vide compris :
`!=` tient pour lui sous les deux. Le `==` de JsonLogic convertit les types comme le fait JavaScript, mais le
vérificateur de types refuse de comparer un champ à une valeur qu'il ne prend jamais — un texte à un nombre, une case à
cocher à un texte —, de sorte qu'une condition qu'il accepte compare des valeurs de même sorte. Une condition qui exige
un champ rempli le dit, comme `{"and": [{"!!": [{"var": "team"}]}, {"!=": [{"var": "team"}, "Blue"]}]}`.

La bibliothèque a aussi des variables calculées ; le bundle n'en conserve aucune. Son contrôleur applique les
conditions dans le navigateur, quel que soit l'évaluateur qui les lit sur le serveur ([Dans le
navigateur](#dans-le-navigateur)).

## Paliers

Un champ peut n'être demandé qu'aux entrées de certains paliers (un type de billet, une adhésion, un rôle), chacun de 1
à 64 lettres minuscules, chiffres, `_` ou `-` :

```php
Field::of('meal', 'Meal', FieldType::Choice, required: true, choices: ['Vegan', 'Other'], tiers: ['vip']),
```

Un champ sans paliers est demandé à toute entrée. L'application dit à quels paliers une entrée appartient :
`Submissions::submit($form, $values, tiers: ['vip'])`, et l'option `tiers` d'`EntryType` construit le formulaire de ces
champs. `Form::fieldsFor($tiers)` les liste. La page `/forms/{key}` ne correspond à aucun palier : elle ne demande que
les champs sans paliers. Une application qui prend plusieurs personnes dans une même commande soumet une entrée par
personne, chacune avec ses paliers.

## Choix qui disqualifient

Un choix peut refuser l'entrée avec un message de 1 à 500 caractères, affiché sur le champ :

```php
Field::of('age', 'Age', FieldType::Choice, choices: ['Under 18', '18 or over'], disqualifying: ['Under 18' => 'The event is for adults.']),
```

L'entrée est refusée avec `disqualified` sur ce champ, et rien n'est conservé. `Field::disqualification($value)` donne
le message.

## Places dans un choix

Un choix peut contenir un nombre de places, de 1 à 1000000 :

```php
Field::of('workshop', 'Workshop', FieldType::Choice, choices: ['Painting', 'Dice'], capacities: ['Painting' => 12]),
```

Chaque entrée qui retient le choix prend une place ; une fois les places prises, l'entrée est refusée avec `full`, et la
page offre le choix désactivé. Le décompte vit dans la table `phil_form_choice_count`, augmenté en une seule instruction
sous la capacité, de sorte que deux entrées envoyées en même temps ne prennent jamais toutes deux la dernière place.
Une entrée refusée pour une autre raison, ou qui ne peut pas être conservée, rend les places qu'elle a prises ; il en va
de même d'une entrée retirée, effacée ou purgée à la fin de sa conservation. Augmenter une capacité offre de nouveau
des places aussitôt.

## Champs sensibles

Un champ marqué `sensitive` n'est jamais envoyé par courriel ni par webhook, et il est conservé scellé par le scelleur
configuré — par défaut par le composant KeyManagement de Symfony, sous la clé maîtresse que nomme `sealing.key`
([entrées](entries.md#valeurs-sensibles)) :

```php
Field::of('health', 'Anything we should know about your health?', FieldType::Textarea, sensitive: true),
```

Une case à cocher, et un choix avec des places, ne peuvent pas être sensibles. Une définition ne conserve que ce qu'un
champ utilise : un champ sans condition, sans paliers, sans places ni âges ne stocke aucune de ces clés.

Sans scelleur, un formulaire qui a un champ sensible est refusé : `EntryType` ne construit pas sa page et
`Form::submit()` ne prend aucune entrée, les deux lançant `UnsealedSensitiveField`, dont le message dit quoi configurer.
Seul `allow_unsealed_sensitive: true` lève le refus ([entrées](entries.md#conserver-les-valeurs-sensibles-telles-quenvoyees)).

## Ce qui est accepté

`Form::accept()` est le seul endroit où les valeurs sont vérifiées. Elles sont débarrassées des espaces aux extrémités,
un texte vide équivaut à aucune valeur, et chaque champ refusé est signalé d'un coup dans `InvalidEntry::$errors`
(`required`, `too_long`, `email`, `url`, `date`, `birth_date`, `too_young`, `too_old`, `choice`, `disqualified`, `type`), ou `form` → `closed`.
`Submissions` ajoute `full` pour un choix dont les places sont prises. Les valeurs de champs que le formulaire n'a pas,
ou ne demande pas, sont abandonnées.

## La page

`FormController` construit le formulaire Symfony à partir de la définition, `EntryType`, protégé contre le pourriel avec
ses champs de texte inspectés et la portée `form-{key}`. Une entrée acceptée redirige vers `?sent=1` ; des valeurs
refusées reviennent avec un message sur chaque champ (le message propre au formulaire pour un choix disqualifiant), en
`422`. Les choix dont toutes les places sont prises sont offerts désactivés. La page est `@PhilForms/form.html.twig` ;
celle d'une application étend sa mise en page et inclut `@PhilForms/_form.html.twig`, qui reçoit `form`, `definition` et
`sent`.
