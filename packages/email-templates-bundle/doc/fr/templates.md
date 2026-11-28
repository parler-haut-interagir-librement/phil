<!-- phil-dev source-sha256=64d748bf1245d5d298bf1065c6ef97f2f0fe1dd6f800a671e7f2d9ea30b99488 -->
# Gabarits

Un gabarit est nommé par une **clé** — 1 à 64 lettres ASCII minuscules, chiffres, `_`, `.` ou `-`, en commençant par une
lettre, comme `ticket` ou `order.confirmation` — et existe dans une ou plusieurs **locales** : `en`, `fr`, `fr_CA`
(`fr-CA` est lu comme `fr_CA`). Un site ne peut enregistrer qu'un gabarit dont un fichier par défaut déclare la clé,
afin qu'un site n'envoie jamais un courriel que l'application n'envoie pas.

## Les fichiers par défaut

Un fichier par défaut est `<key>.<locale>.yaml`, dans l'un des répertoires de `defaults_paths`, puis dans `defaults/`
du bundle, le premier répertoire qui a le fichier l'emportant : `email_templates/ticket.fr.yaml` remplace le
`ticket.fr.yaml` du bundle, et `email_templates/welcome.en.yaml` ajoute un gabarit. Un fichier fait 1 Mio au plus ; il
est lu et vérifié la première fois que son gabarit est demandé, et `bin/console phil:email-templates:check` les vérifie
tous, comme le ferait un enregistrement.

Le bundle fournit trois gabarits, en anglais et en français, assez génériques pour n'importe quel site :

| Clé | Pour | Variables | Segments |
|---|---|---|---|
| `confirmation` | une inscription, une réservation ou une commande confirmée | `recipient.name`, `item.title`, `item.starts_at`, `item.location`, `reference`, `manage_url` | `first-time` |
| `ticket` | un billet à présenter à l'entrée | `recipient.name`, `event.title`, `event.starts_at`, `event.venue`, `ticket.number`, `ticket.category`, `ticket.seat`, `ticket_url` | |
| `cancellation` | une date, une réservation ou une commande annulée | `recipient.name`, `item.title`, `item.starts_at`, `reason`, `contact_url` | `refunded` |

## Les champs d'un gabarit

```yaml
subject: 'Your ticket for {{ event.title }}'
preheader: 'Ticket {{ ticket.number }}: show it at the door.'
format: markdown                 # html, the default, or markdown
body: |
    Hello {{ recipient.name }},

    {{ button('Show my ticket', ticket_url) }}
text: null                       # the plain-text part; written from the HTML when null or empty
segments: [vip, first-time]
embeds: { logo: logo.png }       # shown as cid:logo, read from embeds.directory
variables:
    recipient:
        type: object
        required: true
        fields:
            name: { type: string, required: true, max_length: 200, sample: 'Ada' }
    ticket_url: { type: url, required: true, sample: 'https://example.com/tickets/42' }
```

| Champ | Est |
|---|---|
| `subject` | Une ligne de 1 à 2000 caractères de Twig isolé, dont le rendu est du texte. |
| `preheader` | 2000 caractères au plus, rendus comme du texte : la ligne que la plupart des boîtes de réception montrent après l'objet. |
| `format` | `html`, un corps de HTML et de composants, ou `markdown`, du Markdown transformé en HTML une fois les variables en place. |
| `body` | De 1 octet à 256 Kio de Twig isolé, échappé comme du HTML. |
| `text` | 256 Kio au plus, rendus comme du texte ; vide, la partie en texte brut est écrite à partir du HTML. |
| `segments` | 32 noms au plus, une lettre minuscule suivie d'au plus 31 lettres minuscules, chiffres, `_` ou `-`. |
| `embeds` | 16 images au plus, chacune avec un identifiant de contenu de 1 à 64 lettres, chiffres, `_` ou `-`, et un chemin relatif de lettres, chiffres, `_`, `.`, `-` et `/`, jamais `..`. |
| `variables` | Le schéma des variables, ci-dessous. |

`TemplateDefinition::fromArray()` lit ces champs et liste d'un coup chaque raison de les refuser, dans
`InvalidTemplate::$errors` ; `toArray()` les réécrit.

## Le schéma des variables

Le schéma déclare les emplacements nommés qu'un gabarit lit. Chacun est une table associative avec son `type` et,
facultativement :

| Clé | Est |
|---|---|
| `required` | `true` quand un rendu doit la donner ; `false` par défaut. |
| `default` | La valeur prise quand aucune n'est donnée ; elle doit correspondre au type. |
| `sample` | La valeur avec laquelle l'aperçu et les vérifications produisent leur rendu ; à défaut, la valeur par défaut, sinon une valeur inventée pour le type. |
| `description` | Un texte d'au plus 500 caractères, pour les personnes qui modifient le gabarit. |
| `fields` | Pour un `object`, les variables qu'il contient. |
| `items` | Pour un `list`, la déclaration de ses valeurs. |
| `max_items` | Pour un `list`, de 1 à 1000 ; 100 par défaut. |
| `max_length` | Pour un `string`, de 1 à 100 000 caractères ; 10 000 par défaut. |

| Type | Accepte | Devient |
|---|---|---|
| `string` | un texte, un nombre ou un `Stringable` | un texte en UTF-8 valide |
| `integer` | un entier, ou un texte qui en représente un | un entier |
| `number` | un entier, un nombre à virgule flottante, ou un texte qui en représente un | un entier ou un nombre à virgule flottante |
| `boolean` | `true` ou `false` | la même valeur |
| `datetime` | un `DateTimeInterface`, ou un texte ISO 8601 — `2026-11-18`, `2026-11-18T19:30`, `2026-11-18T19:30:00-05:00` | un `DateTimeImmutable` |
| `url` | une adresse absolue `http` ou `https`, lue comme le fait un navigateur par `phil/url` | son texte normalisé |
| `email` | une adresse courriel, lue par `phil/email-address` | son texte normalisé |
| `list` | une liste de valeurs du type `items` | une liste |
| `object` | une table associative des variables `fields` | une table associative |

Un schéma contient 100 variables au plus, comptées à tous les niveaux, imbriquées sur 3 niveaux au plus. Un nom est une
lettre minuscule ou `_`, suivi d'au plus 63 lettres minuscules, chiffres ou `_` ; `email`, `loop` et les noms que Twig se
réserve sont réservés.

`VariableSchema::validate()` vérifie les valeurs d'un rendu : chaque variable déclarée est ensuite présente, donnée,
remplacée par sa valeur par défaut ou `null` ; une valeur non déclarée, manquante alors qu'elle est obligatoire, ou qui
n'est pas de son type est refusée par `InvalidVariables`, dont les `violations` nomment chaque chemin —
`order.lines.2.qty` — sans jamais répéter la valeur, qui peut être personnelle. `samples()` donne les valeurs d'exemple.

## Segments et attributs

Un bloc conditionnel teste les segments du destinataire avec `segment()`, ou n'importe quelle variable — un attribut —
avec `if` :

```twig
{% if segment('vip') %}{% apply panel %}Thank you for being a member.{% endapply %}{% endif %}
{% if order.total > 100 %}Shipping is free.{% endif %}
```

Un segment doit être l'un de ceux que le gabarit déclare, sans quoi le rendu échoue ; les segments donnés à un rendu que
le gabarit ne déclare pas sont ignorés. L'aperçu dessine chaque variante : aucun segment, chaque segment seul, puis tous.

## Les images intégrées

Une image que le gabarit déclare dans `embeds` est lue depuis `embeds.directory` et jointe en ligne au courriel ; le
corps l'affiche avec `image('cid:logo', 'Shop')`. Voir [Rendu et envoi](rendering.md#les-images-integrees).
