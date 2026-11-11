<!-- phil-dev source-sha256=3759986aa56b2b01c3dbd8e76a685fc43188a01c555785ec14e0ea530621071f -->
# Règles

Une règle est la réponse de l'application à un message qu'aucune norme ne décrit : un motif sur un champ du message, le
genre de rebond qu'il signifie, et l'action à laquelle il mène.

```php
use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\Rule\Field;
use Phil\BounceParser\Rule\Rule;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Rule\RuleScope;

$rules = new Rules(
    Rule::containing('quota', Field::Body, 'over quota', BounceKind::Soft),
    Rule::matching('gone', Field::Body, '/account (has been )?(disabled|closed)/i', BounceKind::Hard, BounceAction::Suppress),
    Rule::containing('ticket', Field::Header, 'helpdesk', BounceKind::AutoReply, header: 'X-Mailer'),
    Rule::containing('policy is gone', Field::Diagnostic, 'user does not exist', BounceKind::Hard, scope: RuleScope::Always),
);
```

## Déclarer

| Constructeur | Motif |
|---|---|
| `Rule::matching($name, $field, $pattern, $kind, $action, $scope, $header)` | Une expression régulière PCRE avec ses délimiteurs et ses options |
| `Rule::containing($name, $field, $text, $kind, $action, $scope, $header)` | Un texte que contient le champ, sans égard à la casse, en UTF-8 |

L'action vaut par défaut l'action du genre (`BounceAction::for()`). Un motif qui n'est pas une expression régulière
valide, un texte vide, ou une règle sur `Field::Header` sans nom de champ lève `Exception\InvalidRule` au moment de sa
déclaration, et non à l'arrivée d'un message. Un motif qui échoue sur un message — trop de retours arrière, UTF-8 brisé
— ne lui correspond pas.

## Champs

| `Field` | Le texte |
|---|---|
| `Subject` | L'objet, ses mots encodés décodés |
| `From` | Le champ `From` tel qu'écrit |
| `Body` | Les parties texte du message, et non celles du message qu'il retourne, l'une après l'autre, jusqu'à la limite de texte |
| `Diagnostic` | Les diagnostics des destinataires d'une notification d'état de remise, un par ligne |
| `Header` | Chaque valeur du champ nommé, une par ligne |

## Portées

| `RuleScope` | La règle est essayée sur |
|---|---|
| `Unrecognised` (par défaut) | Les messages dont le genre est inconnu : non reconnus, un rebond écrit en texte sans code, une notification sans destinataire |
| `Always` | Chaque message, pour qu'elle puisse corriger ce qu'a décidé l'analyseur |

## Ordre

Les règles sont essayées dans leur ordre, et la première qui correspond décide : le rapport garde son format et ses
destinataires, et prend le genre, l'action et le nom de la règle. `Rules::with($rule)` ajoute une règle à la fin ;
`with($rule, before: 'name')` et `with($rule, after: 'name')` la placent à côté d'une règle désignée par son nom —
`before` l'emporte quand les deux sont donnés. Rien n'est ordonné par numéro. Deux règles du même nom, ou un nom de
placement qui n'existe pas, lèvent `Exception\InvalidRule`.

## Écrire une règle à partir d'un message

Un message laissé en révision contient les mots dont une règle a besoin : la phrase du serveur dans son texte, son
objet, ou un champ qu'il ajoute. Écrivez le texte le plus étroit qui nomme le cas, essayez la règle sur les messages
gardés en révision, et gardez peu de règles : chacune coûte une comparaison sur chaque message de sa portée.
