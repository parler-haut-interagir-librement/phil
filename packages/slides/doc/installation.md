# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with `ext-mbstring` | Readonly classes; the length of directive texts |
| `phil/ujo` | `^0.1` | The document, its blocks, the `Ujo` service's class |
| `phil/ujo-bundle` | `^0.1` | The `Ujo` service that renders each slide, as configured by `phil_ujo` |
| `phil/markup` | `^0.2` | Markdown and the other formats read into UJO, the front matter |
| `phil/paged-media` | `^0.1` | The `@page` rule of a printed deck |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | The bundle and its configuration |
| `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing` | `^8.2` | The presenter routes |
| `symfony/ux-twig-component` | `^3.7` | The recipe `Button` the templates draw their buttons and links with |
| `twig/twig` | `^3.21` | The templates and the Twig functions |
| `symfony/twig-bundle` | optional | The templates under `@PhilSlides`, `DeckPrint` |
| `symfony/asset-mapper`, `symfony/stimulus-bundle` | optional | The controllers and the stylesheet, without a build step |
| `symfony/translation` | optional | The labels in English and French |
| `phil/paged-media-bundle` | optional | `DeckPdf`, the PDF of a deck through the configured print engine |
| `symfony/security-bundle` | optional | The role the presenter routes ask for; without it, they are refused to everyone |
| `phil/ux-kit`, or the Shadcn kit of `symfony/ux-toolkit` | one of them | The recipe `Button`, installed by the Symfony UX Toolkit |

## Enabling the bundle

```sh
composer require phil/slides
```

Symfony Flex registers the bundle and copies its configuration from the recipe. Without Flex:

```php
// config/bundles.php
return [
    // …
    Phil\UjoBundle\PhilUjoBundle::class => ['all' => true],
    Phil\Slides\PhilSlidesBundle::class => ['all' => true],
];
```

`phil/ujo-bundle` must be enabled too: without its `Ujo` service, the container stops with a message that says so.

## The recipe

`recipe/0.1` registers the bundle and copies two files:

- `config/packages/phil_slides.yaml`: documents cut at their separators, or, without any, at their headings of levels
  1 and 2; slides in 16:9; the presenter routes for `ROLE_SLIDES_PRESENTER`. Every other option keeps its default;
  see [Configuration](configuration.md);
- `config/routes/phil_slides.yaml`: the import of the presenter routes under `/presenter`, commented. Uncomment it once
  a service implements `PresentedDecks`; see [Presenter view](presenter.md#the-presenter-routes).

## The kit

The templates draw every button and link of their controls with the recipe `Button` of the kit the application
installed, through the Symfony UX Toolkit:

```sh
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
# or, with the Shadcn kit on Tailwind
php bin/console ux:install button --kit=shadcn
```

The recipe is called by the name the Toolkit gives it: with a components subdirectory `ui` in the `ux_toolkit`
configuration, `ui:Button`. Without the Toolkit, name the subdirectory with `phil_slides.kit_subdirectory`. Each button
keeps its label, its Stimulus action, its target and its `aria-pressed` state in either kit; the kit gives its look.

## The controllers and the stylesheet

The controllers `phil--slides--deck` and `phil--slides--presenter` and the stylesheet `styles/slides.css` ship in
`assets/`, declared in its `package.json`. With AssetMapper, the bundle maps that directory under `@phil/slides`;
Symfony UX's Flex recipe adds both controllers to `assets/controllers.json`, with the stylesheet imported, or the
application registers them by hand. They need `@hotwired/stimulus` 3.

The stylesheet dresses the slides over the PHIL UX stylesheet, `phil/ux-css`: it uses its `--phil-*` tokens, else the
tokens of the Shadcn kit — `--background`, `--foreground`, `--border` —, else system colours, and its classless
styling of links and headings. Its states are classes in the `isX` and `hasX` form of PHIL UX, and every colour it mixes
is mixed in OKLCH.
