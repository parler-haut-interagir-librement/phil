<!-- phil-dev source-sha256=bc1afbdccf6c0a604ebccaf5e0ff5d32bd43c03d8b775f231e5185d4b85cd10f -->
# Réglages

## Déclarer un réglage

Un réglage a une clé faite de mots en minuscules séparés par des points, un type, une valeur par défaut, des règles
que ses valeurs respectent, une portée, une section et les clés de traduction de son libellé et de son aide.
L'application déclare les siens dans la configuration, ou par des attributs sur une classe qui ne contient rien
d'autre, que le conteneur configure ensuite automatiquement :

```php
use Phil\SettingsBundle\Domain\Attribute\AsSetting;
use Phil\SettingsBundle\Domain\Attribute\AsSettingSection;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SettingType;

#[AsSettingSection('author', before: ['notifications'])]
#[AsSetting('author.signature', SettingType::Text, section: 'author', maxLength: 120)]
#[AsSetting('author.columns', SettingType::Integer, 2, section: 'author', min: 1, max: 4)]
#[AsSetting('site.registrations_open', SettingType::Boolean, true, Scope::Site, 'general')]
final class AuthorSettings
{
}
```

Un paquet déclare ses réglages en ajoutant au début la configuration de `phil_settings` dans son propre bundle :

```php
public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
{
    $builder->prependExtensionConfig('phil_settings', ['settings' => [
        'gallery.columns' => ['type' => 'integer', 'default' => 3, 'min' => 1, 'max' => 6, 'translation_domain' => 'gallery'],
    ]]);
}
```

## Types et règles

| Type | Valeur | Règles | Champ |
|---|---|---|---|
| `boolean` | `true` ou `false` | | Une case à cocher |
| `integer` | un entier | `min`, `max` | Un champ numérique |
| `text` | une ligne de texte UTF-8 | `max_length` (255, jusqu'à 2 000), `pattern` | Un champ texte |
| `choice` | l'un des `choices` | au plus 100 choix faits de lettres minuscules, de chiffres, de `_` et de `-` | Des boutons radio |
| `choices` | une liste de `choices`, dans leur ordre, chacun une fois | les mêmes | Des cases à cocher |

Un texte refuse les caractères de contrôle, y compris le saut de ligne. La valeur par défaut doit respecter les règles
du réglage. Un réglage du site peut nommer le `role` qui le change. Un réglage d'utilisateur peut être conservé pour
les visiteurs, `anonymous`, quand c'est un booléen ou un choix. Un choix peut nommer un `html_attribute` ; voir
[Préférences d'affichage](display.md).

## Ordre

Les sections et les réglages viennent dans l'ordre de leur déclaration (d'abord les sections du bundle, puis celles de
la configuration, puis celles des attributs), sauf s'ils demandent à venir `before` ou `after` d'autres : un tel
élément vient juste avant le premier qui l'attend. Un nom qui n'est pas déclaré est ignoré, ce qui permet à un paquet
de se placer à côté d'un autre qui n'est peut-être pas installé. Un cycle arrête le conteneur.

## Lire

`Settings` lit une valeur typée. Il se rabat sur la valeur par défaut quand rien n'est enregistré, ou quand ce qui est
enregistré ne respecte plus les règles (un choix retiré, un intervalle rétréci) :

```php
$settings->get('author.columns');           // the current user's, or a visitor's default
$settings->int('author.columns', 'u-42');   // a user's, by identifier
$settings->bool('site.registrations_open'); // the site's, the same for everybody
$settings->string('display.motion');        // a text or a choice
$settings->list('author.genres');           // a list of choices
```

Une méthode typée refuse un réglage d'un autre type avec `InvalidSetting`, et une clé inconnue avec `UnknownSetting`.
Un visiteur a les valeurs par défaut, et la valeur de son témoin pour un réglage conservé pour les visiteurs. Dans
Twig :

```twig
{% if phil_setting('site.registrations_open') %}…{% endif %}
```

## Écrire

```php
use Phil\SettingsBundle\Domain\ValueObject\Subject;

$settings->save(Subject::user('u-42'), ['author.columns' => 3, 'author.signature' => 'Ada']);
$settings->save(Subject::site(), ['site.registrations_open' => false]);
$settings->restoreDefaults(Subject::user('u-42'), ['author.columns']);
$settings->forget(Subject::user('u-42'));
```

Chaque valeur est vérifiée avant qu'aucune ne soit écrite. Une valeur refusée lance `InvalidSettingValue`, dont la
`reason` est une clé de traduction du domaine `phil_settings`, et rien ne change. Seul ce qui diffère de la valeur par
défaut est enregistré : une valeur par défaut changée dans le code atteint donc tous ceux qui n'ont jamais choisi.
`save()` et `restoreDefaults()` retournent les clés dont la valeur a changé, `forget()` les clés effacées.

## Événements

Chaque changement répartit `Application\Event\SettingsChanged` sur le répartiteur d'événements : la portée (`user` ou
`site`), le sujet (l'identifiant de l'utilisateur ou `site`) et les clés. Il ne porte aucune valeur. On peut donc le
transmettre à un bus de messages ou à un journal sans ce qu'une personne a choisi. Un écouteur lit les nouvelles
valeurs par `Settings`.

```php
#[AsEventListener]
public function __invoke(SettingsChanged $event): void
{
    if ('user' === $event->scope && \in_array('author.signature', $event->keys, true)) {
        $this->bus->dispatch(new RefreshSignature($event->subject));
    }
}
```
