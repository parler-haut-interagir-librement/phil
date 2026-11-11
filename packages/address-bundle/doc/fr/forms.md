<!-- phil-dev source-sha256=f23313f4c004c4866f75d6a8aa8128c8228c61b765e26d22a70eb2a18f51f4e6 -->
# Formulaires

## `AddressType`

Vers et depuis une `Address` : le pays, puis les champs du format de ce pays, dans son ordre, nommés comme il les nomme
— *État* pour les États-Unis, *Province* pour le Canada, *Préfecture* pour le Japon —, exigés comme il les exige.
Lorsque le pays a des États ou des provinces, la région administrative est un choix parmi eux, nommés dans la langue de
la requête : *Québec (QC)*. Masqués et désactivés, les champs des autres pays sont présents pour le contrôleur Stimulus.

À la soumission, les champs suivent le pays soumis, dont le code est mis en majuscules. L'adresse est ensuite vérifiée
par rapport à son pays, et chaque champ erroné reçoit son erreur, traduite dans le domaine `validators`.

```php
$builder->add('address', AddressType::class, ['countries' => ['CA', 'US', 'FR'], 'with_recipient' => true]);
```

| Option | Valeur par défaut | Signification |
|---|---|---|
| `default_country` | le `default_country` du bundle | Le pays d'une nouvelle adresse |
| `countries` | `null` : tous les pays | Les pays offerts |
| `with_recipient` | `false` | Si les noms et l'organisation du destinataire sont des champs |

Les libellés sont dans le domaine de traduction `phil_address`.

## Le contrôleur Stimulus

Lorsque `format_url` est configuré, le formulaire porte `phil--address-bundle--address`. Quand le pays change, le
contrôleur lit `/address-format/<country>` — les champs avec leurs libellés dans la langue de la requête et leur
caractère obligatoire, ainsi que les États ou les provinces. Il affiche ou masque ensuite chaque rangée, en désactivant
un champ masqué pour qu'il ne soit pas envoyé, écrit son libellé, le marque comme obligatoire et fait de la région
administrative un choix ou un champ texte. Il émet `changed` avec le format. Seule la réponse pour le pays encore choisi
est appliquée : une réponse lente pour un pays choisi auparavant n'écrase donc jamais les champs de celui choisi
ensuite. Une requête qui échoue émet `failed` avec l'erreur.

Le formulaire donne au contrôleur l'URL du point de terminaison avec la langue courante dans son paramètre de requête
`locale`, et `recipient=1` avec `with_recipient`. Une réponse pour une URL qui nomme sa langue est publique et mise en
cache une journée : un cache partagé garde ainsi une réponse par langue. Une réponse pour une URL qui ne la nomme pas
est dans la langue de la requête, qui peut venir d'une session, et elle est privée.

À la soumission, une adresse ne garde que ce qu'écrit le pays soumis. Lorsqu'une personne change le pays d'une adresse
en cours de modification, les champs masqués du pays précédent, que le navigateur n'envoie pas, sont abandonnés au lieu
de garder leurs anciennes valeurs. Un champ du destinataire que le formulaire n'affiche pas est conservé là où le pays
l'écrit. Une violation sur un champ que le formulaire n'affiche pas est rattachée au formulaire lui-même.
