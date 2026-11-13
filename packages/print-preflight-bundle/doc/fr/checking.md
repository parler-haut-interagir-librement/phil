<!-- phil-dev source-sha256=71122a75da6ec1a84e31f87b8b33493c276133afbe2f74d3a7d05a67f0f76b0a -->
# Vérification

## `Preflighter`

```php
$report = $preflighter->check($document);             // the default profile
$report = $preflighter->check($document, 'poster');   // a profile by name
```

`check(Document $document, ?string $profile = null)` exécute les vérifications de la bibliothèque, puis celles de
l'application, et renvoie le `Report` de la bibliothèque : `passes()` quand aucun constat n'est une erreur,
`isClean()` quand il n'y a pas non plus d'avertissement, `forPage()`, `toArray()`. Un nom que la configuration ne
déclare pas lève `UnknownProfile`. `profiles()` donne les `PrintProfiles`, pour lister les noms dans un formulaire.

## Vérifications de l'application

Une classe qui implémente `Phil\PrintPreflight\Check\Check`, marquée `#[AsPreflightCheck]` et autoconfigurée,
s'exécute dans chaque contrôle préalable après les vérifications intégrées :

```php
#[AsPreflightCheck]
final readonly class TotalInkCoverage implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        // return Finding values with a code of the application, such as "house.ink-coverage"
    }
}
```

Ses constats prennent les gravités et les codes ignorés du profil comme les vérifications intégrées ; les services
reçoivent l'étiquette `phil_print_preflight.check`.

## La commande

```sh
bin/console phil:print-preflight:check measured.json [--profile=booklet] [--format=json]
```

| Option | Effet |
|---|---|
| `--profile`, `-p` | Le profil ; sinon, le profil par défaut |
| `--format`, `-f` | `text` (par défaut) : une ligne par constat — gravité, page ou `document`, code, sujet, message —, puis les totaux ; `json` : le `toArray()` du rapport |

| Code de sortie | Quand |
|---|---|
| 0 | Aucun constat n'est une erreur |
| 1 | Un constat est une erreur |
| 2 | Le fichier est illisible, n'est pas un document mesuré, ou nomme un profil inconnu ; la raison est affichée |

Les constats sont affichés tels quels : un identifiant qui contient `<` est ainsi montré, et non pris pour du balisage
de la console.
