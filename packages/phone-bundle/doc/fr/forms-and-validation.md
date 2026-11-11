<!-- phil-dev source-sha256=0226b795673fbc583ea7346fec146e8271709e112783b89f0f1ab34aa3b4a6e5 -->
# Formulaires et validation

## `PhoneType`

Un champ `tel`, vers un `PhoneNumber` et à partir de celui-ci. Un numéro saisi sans indicatif de pays est lu dans le
`region` du champ : le `default_region` du bundle, sauf si le champ nomme le sien. Un numéro qui ne figure dans aucun
plan de numérotation rend le champ invalide avec *Cette valeur n’est pas un numéro de téléphone valide.* Un numéro du
pays du champ s'affiche à la forme nationale, `(514) 555-0123`, tout autre à la forme internationale,
`+33 6 12 34 56 78`. Le `maxlength` du champ est `PhoneNumber::MAX_INPUT`, 100 caractères, l'entrée la plus longue que
`parse()` lit.

```php
$builder->add('phone', PhoneType::class, ['region' => 'FR', 'required' => false]);
```

## `ValidPhoneNumber`

```php
#[ValidPhoneNumber(types: [PhoneNumberType::Mobile, PhoneNumberType::FixedLineOrMobile], regions: ['CA', 'US'])]
public ?PhoneNumber $mobile = null;
```

Un `PhoneNumber` ou un texte — lu dans `region`, ou dans le `default_region` du bundle — est valide quand il figure
dans son plan, est de l'un des `types` et vient de l'une des `regions` que nomme la contrainte ; les deux valent tous
par défaut. `null` et le texte vide sont valides.

| Code | Message |
|---|---|
| `INVALID_ERROR` | *Cette valeur n’est pas un numéro de téléphone valide.* |
| `TYPE_ERROR` | *Ce numéro de téléphone n’est pas d’un type accepté.* |
| `REGION_ERROR` | *Ce numéro de téléphone n’est d’aucun des pays acceptés : {{ regions }}.* |

Un numéro d'un mauvais type et d'un mauvais pays reçoit les deux violations.
