# A coming soon page

The `ComingSoon` block rebuilds a whole "coming soon" page: a full-height split, the logo at the top of the left
column, the content centred below it — a muted eyebrow, the headline revealed through a clip, one or two paragraphs —
and a picture filling the right column. On a small screen, the columns stack.

## The base template

```twig
{# templates/base.html.twig #}
{% set theme = random(['default', 'dark']) %}
<!DOCTYPE html>
<html lang="{{ app.request.locale }}" data-theme="{{ theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}{% endblock %}</title>
    <link rel="stylesheet" href="{{ asset('phil-ux/phil-ux.css') }}">
    {% block importmap %}{{ importmap('app') }}{% endblock %}
</head>
<body>
    {% block body %}{% endblock %}
</body>
</html>
```

## The page

A logo drawn for each theme, an illustration drawn at random among several:

```twig
{# templates/static/comingsoon.html.twig #}
{% extends 'base.html.twig' %}

{% block body %}
    <twig:ComingSoon
        logoSrc="{{ asset('img/logo-' ~ theme ~ '.svg') }}"
        logoAlt="{{ 'site.name'|trans }}"
        eyebrow="{{ 'coming_soon'|trans }}"
        words="{{ 'site.name'|trans }}"
        :paragraphs="['site.info'|trans]"
        :illustrations="['knight', 'robot_gang', 'roadblock']|map(name => asset('img/' ~ name ~ '.svg'))"
        illustrationAlt=""
    />
{% endblock %}
```

A logo with a WebP source and a translated text that holds markup:

```twig
<twig:ComingSoon
    logoSrc="{{ asset('img/logo.png') }}"
    logoWebp="{{ asset('img/logo.webp') }}"
    logoAlt="{{ 'site.name'|trans }}"
    eyebrow="{{ 'coming_soon'|trans }}"
    words="{{ 'site.name'|trans }}"
    :paragraphs="['site.tech'|trans]"
    :illustrations="[{src: asset('img/main.png'), webp: asset('img/main.webp'), alt: 'site.illustration'|trans}]"
    illustrationAlt=""
>
    <p>{{ 'site.info.html'|trans|raw }}</p>
</twig:ComingSoon>
```

A logo per language, and one illustration of three drawn at random, each with its WebP source:

```twig
<twig:ComingSoon
    logoSrc="{{ asset('images/logo-' ~ app.request.locale ~ '.svg') }}"
    logoAlt="{{ 'site.name'|trans }}"
    eyebrow="{{ 'coming_soon'|trans }}"
    words="{{ 'site.title'|trans }}"
    :paragraphs="['site.info'|trans, 'site.tech'|trans]"
    :illustrations="[1, 2, 3]|map(n => {src: asset('images/main-' ~ n ~ '.png'), webp: asset('images/main-' ~ n ~ '.webp')})"
    illustrationAlt="{{ 'site.illustration'|trans }}"
/>
```

## What the page does for its readers

- The language of the page is the `lang` of `html`; the column is a `header` and a `main`.
- The logo's name is its `alt`; each illustration has its own text alternative, or an empty one when it only
  decorates.
- The headline is read whole by screen readers; under `prefers-reduced-motion`, nothing moves — neither the headline
  nor the illustration's entrance.
- Every colour pair reaches the contrast of WCAG 2.2 AA, in light and in dark.
