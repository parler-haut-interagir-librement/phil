<!-- phil-dev source-sha256=f9f606b9cdacc582c2d45e2f7eb95367fc61271415cac08b2a4fef42043f6e30 -->
# phil/form-logic

**Logique conditionnelle typée pour les formulaires, évaluée de la même façon sur le serveur et dans le navigateur, le serveur faisant foi.**

phil/form-logic décide quelles questions d'un formulaire sont posées et calcule des valeurs à partir des réponses : un
champ affiché seulement à ceux qui amènent un invité, une section pour les mineurs, un total de places, une tranche de
prix. La logique est une structure d'objets PHP immuables — comparaisons, `and`, `or`, `not`, appartenance,
intervalles, présence et arithmétique simple —, vérifiée contre les types des champs quand le formulaire est défini,
de sorte qu'une condition qui compare un choix à une valeur qu'il ne prend jamais est refusée à ce moment plutôt que
trouvée fausse plus tard.

Elle est stockée et échangée comme un sous-ensemble strict de JsonLogic, que d'autres outils lisent : `{"in": [{"var":
"team"}, ["Blue", "Green"]]}`. Tout opérateur hors du sous-ensemble est refusé à la lecture de la règle, et la taille,
la profondeur et le nombre de nœuds d'une règle sont bornés, de sorte que son évaluation coûte un temps proportionnel à
sa taille.

La même logique s'exécute deux fois. Dans le navigateur, un contrôleur Stimulus fourni avec le paquet affiche et
masque les champs et affiche les valeurs calculées à mesure que le formulaire se remplit. Sur le serveur, le `Judge`
évalue de nouveau la réponse, quoi que le navigateur ait envoyé : il retire les valeurs des champs masqués, calcule
les variables et rend le verdict que l'application valide et stocke, les variables calculées avec la réponse. Une
suite commune de cas passe par les deux évaluateurs.

C'est une bibliothèque PHP ordinaire, avec un adaptateur pour les conditions de `phil/forms-bundle` et un contrôleur
servi par AssetMapper, sans étape de construction.

## État

| Partie | État |
|---|---|
| Les expressions : comparaisons, intervalles, `and`, `or`, `!`, `!!`, `if`, `in`, `missing`, `missing_some`, arithmétique | Implémenté et testé |
| Le lecteur JsonLogic, strict et borné, et son écrivain | Implémenté, testé et soumis au fuzzing |
| L'évaluateur, avec les conversions de JsonLogic | Implémenté et testé contre les cas communs et les cas de test que publie JsonLogic |
| Le vérificateur de types des variables qu'une règle lit | Implémenté et testé |
| La logique d'un formulaire : conditions de champs et variables calculées dans l'ordre, le `Judge` et son `Verdict` | Implémenté et testé |
| Le contrôleur Stimulus `logic` et son évaluateur | Implémenté ; testé sous Node contre les mêmes cas, et dans Chrome dans une application avec AssetMapper |
| `JsonLogicConditions`, le `ConditionEvaluator` de `phil/forms-bundle` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, la bibliothèque, le contrôleur Stimulus, `phil/forms-bundle`. |
| [Les règles](doc/fr/rules.md) | Le sous-ensemble de JsonLogic : chaque opérateur, les valeurs, les conversions, les limites, ce qui est refusé. |
| [Logique de formulaire](doc/fr/form-logic.md) | Les expressions en PHP, les types, les conditions de champs, les variables calculées, le juge et son verdict. |
| [JavaScript](doc/fr/javascript.md) | Le contrôleur, le balisage qu'il lit, la réponse qu'il collecte, ses événements, son évaluateur. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou sur quoi elle s'appuie. |
| [Tests](doc/fr/testing.md) | Les tests PHP et Node, les cas communs et les cas publiés, la suite du navigateur, la cible de fuzzing, les bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type et module public du paquet, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/form-logic
```

```php
use Phil\FormLogic\Logic\FormLogic;
use Phil\FormLogic\Logic\Judge;

$logic = FormLogic::fromArray([
    ['field' => 'guest_name', 'when' => ['==' => [['var' => 'bringing_guest'], true]]],
    ['compute' => 'seats', 'type' => 'number', 'value' => ['+' => [1, ['if' => [['var' => 'bringing_guest'], 1, 0]]]]],
]);

$verdict = new Judge()->judge($logic, $request->request->all('entry'));
$verdict->answer;   // the answer, without the values of hidden fields
$verdict->computed; // ['seats' => 2], to store with the answer
```

```twig
<form data-controller="phil--form-logic--logic"
      data-phil--form-logic--logic-steps-value="{{ logic.toArray()|json_encode }}">
    <div data-form-logic-field="guest_name">…</div>
    <output data-form-logic-output="seats"></output>
</form>
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`. Pour le contrôleur : Stimulus 3 dans le navigateur, servi par
AssetMapper et StimulusBundle. Pour l'adaptateur : `phil/forms-bundle`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
