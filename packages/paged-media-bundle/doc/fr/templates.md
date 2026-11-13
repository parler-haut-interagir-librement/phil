<!-- phil-dev source-sha256=0439e1bc015532890ab0ddc5c18452ab1753f7c6e1f1487f8414c2b01440d1e6 -->
# Gabarits d'imprimés

Le bundle enregistre les gabarits d'imprimés de `phil/paged-media` comme gabarits de documents :
`PagedMedia::renderTemplate()` les rend tout de suite, et `PagedMediaQueue::render()` dans un *worker*. Leurs paramètres
sont de type JSON — chaînes, nombres, booléens, listes et objets — et sont donc gardés tels quels dans le stockage des
tâches : les codes d'une carte-cadeau ou d'une carte de téléchargement, les noms sur les porte-noms et les adresses sur
les étiquettes ne voyagent jamais dans un message.

```php
$job = $queue->render('paged_media.gift_cards', [
    'card_title' => 'Gift card',
    'cards' => [['amount' => '$50.00', 'code' => 'GC-7F3K-9QPA', 'pin' => '4821', 'valid_until' => 'December 31, 2027']],
], 'gift-cards/batch-12.pdf');
```

Les valeurs sont imprimées telles qu'on les donne : l'application formate les montants, les prix et les dates dans la
langue du lecteur, et donne les mots qui les entourent. Les images — un logo, un code à barres, un code QR — ne peuvent
pas être passées en paramètres : une application qui en a besoin construit elle-même le gabarit de la bibliothèque dans
un `DocumentTemplate` à elle, qui lit les images dans son stockage.

## Gabarits et paramètres

Chaque gabarit prend `title`, le titre du document, au besoin ; les gabarits d'étiquettes prennent `sheet`, l'un de
`letter30`, `letter10`, `letter6`, `a4x21` et `a4x8`, quand la valeur par défaut ne convient pas.

| Nom | Paramètres | Planche par défaut |
|---|---|---|
| `paged_media.name_badges` | `event` ; `badges`, chacun `name`, `subtitle`, `organisation` ; `accent` (`#rrggbb`) | `letter6` |
| `paged_media.address_labels` | `labels`, chacune `lines` (une liste de chaînes) ; `size` | `letter30` |
| `paged_media.product_labels` | `labels`, chacune `name`, `price`, `sku`, `detail` | `letter30` |
| `paged_media.shelf_labels` | `labels`, chacune `name`, `price`, `unit_price`, `sku`, `location` | `letter10` |
| `paged_media.gift_cards` | `card_title` ; `cards`, chacune `amount`, `code`, `pin`, `recipient`, `message`, `valid_until` ; `code_label`, `pin_label`, `valid_until_label` (en anglais par défaut) ; `cut_lines` (true par défaut) | `letter10` |
| `paged_media.download_cards` | `instructions`, `address` ; `cards`, chacune `title`, `code`, `subtitle` ; `cut_lines` (true par défaut) | `letter10` |
| `paged_media.calendar` | `weekdays` (sept noms à partir du premier jour) ; `months`, chacun `year`, `month`, `title` et `events`, des listes de chaînes par jour du mois ; `first_day` (1 pour lundi, la valeur par défaut, à 7 pour dimanche) ; `accent` | Une page Letter à l'horizontale |
| `paged_media.large_print` | `blocks`, chacun un `heading` ou un `paragraph` ; `size` (18 par défaut), `heading_size` (24), `page_numbers` (true) | Une page Letter |

Un paramètre manquant ou d'un genre incorrect est refusé avec `InvalidTemplateParameters`, qui nomme le gabarit, le
chemin de la valeur — `cards.2.code` — et ce qui est attendu ; une valeur que la bibliothèque refuse, comme une
carte-cadeau sans code, lance son `InvalidDocument`. Dans un *worker*, les deux font échouer le message sans nouvelle
tentative.

## Polices

Les gabarits écrivent en Helvetica et en Helvetica Bold sauf si `templates.font` et `templates.bold_font` nomment des
fichiers TrueType ; avec `font` seul, il sert pour les deux. Les polices sont lues une fois, quand le conteneur construit
le service, si bien qu'un *worker* ne les relit pas pour chaque tâche.

```yaml
phil_paged_media:
    templates:
        font: '%kernel.project_dir%/assets/fonts/NotoSans-Regular.ttf'
        bold_font: '%kernel.project_dir%/assets/fonts/NotoSans-Bold.ttf'
```

`templates.enabled: false` écarte les gabarits, pour une application qui enregistre les siens sous d'autres noms.
