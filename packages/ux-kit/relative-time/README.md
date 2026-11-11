# RelativeTime

A moment written by the server as a date, then by a script as the time from now, which it keeps right.

```twig {"preview":true}
<twig:RelativeTime datetime="2026-11-09 12:00" />
```

## Installation

::: installation

## Usage

```twig
<twig:RelativeTime datetime="2026-11-09 12:00" />
```

## Accessibility

A `time` element with its machine-readable date and the date in its title. It is no live region: the text changes without interrupting anyone. Without a script, or past `limit`, the date stays.
