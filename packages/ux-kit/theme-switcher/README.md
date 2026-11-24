# ThemeSwitcher

The choice of the colour scheme, light, dark or the system's, kept in the browser and written on the page before it is first painted.

```twig {"preview":true}
<twig:ThemeSwitcher />
```

## Installation

::: installation

## Usage

```twig
{# in the head, before the stylesheets #}
<twig:ThemeSwitcher:Script />

{# where the reader chooses #}
<twig:ThemeSwitcher />
```

## Accessibility

Three radio buttons in a group named by its legend, each with its visible name, used with the keys of the browser. The group stays hidden without a script, which it needs. The script of the head writes the stored scheme before the first paint, so the page never flashes in the other one.
