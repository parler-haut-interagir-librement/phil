<!-- phil-dev source-sha256=a09188b81f17ba593dbb610a70c911ca565f616fd20788a3c670fc79bf23d53c -->
# Alertes CAP

`Cap\CapReader` lit un document du Common Alerting Protocol 1.2 (`urn:oasis:names:tc:emergency:cap:1.2`) et retourne
une `WeatherAlert` par bloc `<info>` :

| `WeatherAlert` | Provenance |
|---|---|
| `id` | L'`identifier` de l'alerte et la `language` du bloc |
| `event`, `headline`, `description`, `instruction` | Les éléments du bloc, tels qu'écrits |
| `severity` | La `severity` du bloc, `Unknown` si ce n'est pas l'une des cinq valeurs de CAP |
| `sender` | Le `senderName` du bloc, sinon le `sender` de l'alerte |
| `effective` | Le `onset` du bloc, sinon son `effective`, sinon le `sent` de l'alerte |
| `expires` | Le `expires` du bloc |
| `area` | L'`areaDesc` de chaque `<area>`, jointes par un point-virgule et une espace |
| `language` | La `language` du bloc, `en-US` s'il n'en a pas, comme le dit CAP |
| `web` | Le `web` du bloc quand c'est une adresse `http` ou `https` |

```php
use Phil\EventWeather\Cap\CapReader;

$alerts = new CapReader(language: 'fr')->read($xml); // the fr-CA blocks
```

La langue demandée prend ses formes régionales : `fr` lit `fr-CA` et `fr-FR`. Sans langue, tous les blocs sont lus. Les textes sont
ceux du bloc dans cette langue, tels qu'écrits : une page les montre tels quels, jamais résumés ni traduits
automatiquement — voir [Afficher les alertes](licences.md#afficher-les-alertes).

Seule une véritable alerte donne des alertes : un document dont le `status` est `Test`, `Exercise`, `System` ou `Draft`,
ou dont le `msgType` est `Cancel`, n'en retourne aucune. Un `Update` est lu comme l'alerte qu'il remplace.

Le lecteur refuse avec `Exception\InvalidCapDocument` un document qui n'est pas en UTF-8 ou qui contient un caractère de
contrôle, qui a un type de document, qui n'est pas du XML, qui n'est pas une alerte CAP 1.2, à qui manque son
`identifier`, son `sender`, son `sent`, son `status` ou son `msgType`, ou dont un `<info>` n'a pas son `event`. Il est
borné par `$maxBytes`, 1 Mio par défaut, et par `$maxInfos`, 64 blocs.
