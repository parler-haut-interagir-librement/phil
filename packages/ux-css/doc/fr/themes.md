<!-- phil-dev source-sha256=7568b98efd22ce9ab803e6db1889dc22166d0b2d533a6b5e2e9b744b2aaed0ba -->
# Thèmes

## Un thème, clair et sombre

phil-ux a un seul thème. Chaque rôle de couleur porte son ton clair et son ton sombre dans une seule déclaration,
`light-dark()`, que le navigateur résout d'après le `color-scheme` de l'élément qui emploie le rôle :

```css
:root {
    color-scheme: light dark;
    --phil-color-surface: light-dark(var(--phil-neutral-99), var(--phil-neutral-10));
}
```

Par défaut, la page suit le système, par `prefers-color-scheme`. `data-theme` choisit à sa place :

| `data-theme` | Schéma |
|---|---|
| aucun | Celui du système |
| `light` | Clair |
| `default` | Clair, comme `light` |
| `dark` | Sombre |

Il se met sur la racine ou sur n'importe quel élément, qui bascule alors son sous-arbre : une bande sombre dans une page
claire est une `<section data-theme="dark">`.

```twig
{% set theme = random(['default', 'dark']) %}
<html lang="{{ app.request.locale }}" data-theme="{{ theme }}">
```

## Les surcharges d'un site

Un site garde le thème et surcharge des jetons dans sa propre feuille de style, hors de toute couche, les couleurs et
les dimensions à part :

```css
/* assets/styles/colors.css: the hue of the brand */
:root {
    --phil-brand-40: oklch(0.40 0.09 300);
    --phil-brand-50: oklch(0.50 0.11 300);
    --phil-brand-70: oklch(0.70 0.12 300);
}

/* assets/styles/dimensions.css: a denser interface, rounder corners */
:root {
    --phil-scale-space: 0.875;
    --phil-scale-radius: 1.5;
}
```

Gardez chaque ton à la clarté que dit son numéro, et passez la [règle de contraste](contrast.md) sur les surcharges.
