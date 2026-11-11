<!-- phil-dev source-sha256=f3282c75567085e0afd496c9ba844707295090dd271871ac336d1e9d1b5db8e7 -->
# Une page « bientôt »

Le bloc `ComingSoon` refait une page « bientôt » entière : un partage sur toute la hauteur, le logo en haut de la colonne
de gauche, le contenu centré dessous — un surtitre atténué, le titre dévoilé par un découpage, un ou deux paragraphes —
et une image qui remplit la colonne de droite. Sur un petit écran, les colonnes s'empilent.

## Le gabarit de base

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

## La page

Un logo dessiné pour chaque thème, une illustration tirée au hasard parmi plusieurs :

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

Un logo avec une source WebP et un texte traduit qui contient du balisage :

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

Un logo par langue, et une illustration sur trois tirée au hasard, chacune avec sa source WebP :

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

## Ce que la page fait pour ses lecteurs

- La langue de la page est le `lang` de `html` ; la colonne est un `header` et un `main`.
- Le nom du logo est son `alt` ; chaque illustration a son propre texte de remplacement, ou un texte vide quand elle ne
  fait que décorer.
- Le titre est lu en entier par les lecteurs d'écran ; sous `prefers-reduced-motion`, rien ne bouge — ni le titre ni
  l'entrée de l'illustration.
- Chaque paire de couleurs atteint le contraste des WCAG 2.2 AA, en clair et en sombre.
