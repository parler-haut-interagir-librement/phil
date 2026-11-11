# Preferences

## The port

`Phil\ContentWarningsBundle\Application\Contract\WarningPreferencesStore` gives the current reader's preferences and
saves them:

```php
interface WarningPreferencesStore
{
    public function current(): WarningPreferences;

    public function save(WarningPreferences $preferences): void;
}
```

`current()` gives the defaults — the `default_display` of the configuration — when the reader chose nothing. `save()`
throws `PreferencesNotSaved` when there is no one to save them for. An implementation keeps no reader between
requests: it reads the current one each time, so it is safe in a worker.

## The stores shipped

| Store | Where | When |
|---|---|---|
| `SessionWarningPreferences` | The HTTP session, under `phil_content_warnings.preferences` | The default, with HttpFoundation |
| `InMemoryWarningPreferences` | Memory, reset to the defaults between requests | Without HttpFoundation, in tests and commands |

The session store never starts a session to read: a visitor without one gets the defaults. Preferences the session
holds but cannot read back — a value tampered with — give the defaults too. It saves through the main request's
session and throws `PreferencesNotSaved` without one.

## Members

An application with member settings implements the port over them, storing `WarningPreferences::toArray()` and
reading it back with `fromArray()`, and names its service:

```yaml
phil_content_warnings:
    preferences_store: App\ContentWarnings\MemberWarningPreferences
```
