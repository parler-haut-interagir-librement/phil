# Logo

A logo: a `Picture` of the `image` recipe, with an AVIF source then a WebP one when it has them, each a URL or a map
of densities, linked to the home page when it has an `href`.
`{theme}` and `{locale}` in its sources are replaced, so one line serves the logo of every theme and language.

```twig {"preview":true}
<twig:Logo src="/images/logo.png" webp="/images/logo.webp" alt="Studio" href="/" />
```

## Installation

::: installation

## Usage

```twig
<twig:Logo src="{{ asset('images/logo.svg') }}" alt="Studio" />
```

## Examples

### By density

```twig
<twig:Logo
    src="/images/1x/logo.png"
    :srcset="{1: '/images/1x/logo.png', 2: '/images/2x/logo.png'}"
    :avif="{1: '/images/1x/logo.avif', 2: '/images/2x/logo.avif'}"
    :webp="{1: '/images/1x/logo.webp', 2: '/images/2x/logo.webp'}"
    alt="Studio"
    :width="160"
    :height="40"
/>
```

### By theme

The theme the page was given picks the logo drawn for its background.

```twig
<twig:Logo src="/images/logo-{theme}.svg" alt="Studio" :theme="theme" />
```

### By language

A logo that writes a translated name.

```twig
<twig:Logo src="/images/logo-{locale}.svg" alt="{{ 'site.name'|trans }}" :locale="app.request.locale" />
```

With an asset versioning strategy, resolve each variant with `asset()` instead, as the placeholders are replaced after
the URL is built.

## Accessibility

`alt` is required: the logo's text alternative is the name it writes. It is empty only when that name is written next
to the logo, so that screen readers do not read it twice. Give `width` and `height` to reserve the logo's space.
