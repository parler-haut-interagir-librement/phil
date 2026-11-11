<!-- phil-dev source-sha256=57bad5ca5a66a289932ea256e2d6bdf85237d38a3df395728f254f6c5c256df2 -->
# Préférences

## Le port

`Phil\ContentWarningsBundle\Application\Contract\WarningPreferencesStore` donne les préférences du lecteur courant et
les enregistre :

```php
interface WarningPreferencesStore
{
    public function current(): WarningPreferences;

    public function save(WarningPreferences $preferences): void;
}
```

`current()` donne les valeurs par défaut — le `default_display` de la configuration — lorsque le lecteur n'a rien
choisi. `save()` lève `PreferencesNotSaved` lorsqu'il n'y a personne pour qui les enregistrer. Une implémentation ne
conserve aucun lecteur entre les requêtes : elle lit le lecteur courant chaque fois, et elle est donc sûre dans un
*worker*.

## Les magasins fournis

| Magasin | Où | Quand |
|---|---|---|
| `SessionWarningPreferences` | La session HTTP, sous `phil_content_warnings.preferences` | Par défaut, avec HttpFoundation |
| `InMemoryWarningPreferences` | La mémoire, réinitialisée aux valeurs par défaut entre les requêtes | Sans HttpFoundation, dans les tests et les commandes |

Le magasin en session ne démarre jamais de session pour lire : un visiteur qui n'en a pas reçoit les valeurs par défaut.
Des préférences que la session contient mais qu'il est impossible de relire — une valeur trafiquée — donnent aussi les
valeurs par défaut. Il enregistre par la session de la requête principale et lève `PreferencesNotSaved` sans elle.

## Membres

Une application qui a des réglages de membre implémente le port par-dessus ceux-ci, en stockant
`WarningPreferences::toArray()` et en le relisant avec `fromArray()`, puis nomme son service :

```yaml
phil_content_warnings:
    preferences_store: App\ContentWarnings\MemberWarningPreferences
```
