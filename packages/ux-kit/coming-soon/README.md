# ComingSoon

A "coming soon" page: the logo at the top of one column, a muted eyebrow, the animated headline and one or more
paragraphs centred below it, and an illustration filling the other column. The columns stack on small screens, the
illustration under the text.

```twig {"preview":true}
<twig:ComingSoon
    logoSrc="/images/logo.png"
    logoAlt="Studio"
    eyebrow="Coming soon"
    words="Studio"
    :paragraphs="['Our new site opens soon.']"
    :illustrations="['/images/main.png']"
    illustrationAlt=""
/>
```

## Installation

::: installation

## Usage

```twig
<twig:ComingSoon
    logoSrc="{{ asset('images/logo-{theme}.svg') }}"
    logoAlt="{{ 'site.name'|trans }}"
    :theme="theme"
    eyebrow="{{ 'coming_soon'|trans }}"
    words="{{ 'site.name'|trans }}"
    :paragraphs="['site.info'|trans]"
    :illustrations="[asset('images/knight.svg'), asset('images/robot.svg')]"
    illustrationAlt=""
/>
```

## Examples

### Markup in the text

The content block comes after the paragraphs, for a translated text that holds markup.

```twig
<twig:ComingSoon logoSrc="/images/logo.png" logoAlt="Studio" eyebrow="Coming soon" words="Studio">
    <p>{{ 'site.info.html'|trans|raw }}</p>
</twig:ComingSoon>
```

### Inside a layout

A layout that already writes `header` and `main` turns the landmarks off.

```twig
<twig:ComingSoon :landmarks="false" logoSrc="/images/logo.png" logoAlt="Studio" eyebrow="Coming soon" words="Studio" />
```

## Accessibility

The column is a `header` (the logo) and a `main` (the text), unless `landmarks` is false. The eyebrow and the headline
are an `hgroup`, so the eyebrow is not a heading of its own. The illustration enters with a slanted clip beside the
text, never under `prefers-reduced-motion`.
