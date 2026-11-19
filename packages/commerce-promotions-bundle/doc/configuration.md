# Configuration

The module's configuration lives under `phil_commerce_promotions`. Without one, every promotion is shown in its own
label.

```yaml
# config/packages/phil_commerce_promotions.yaml
phil_commerce_promotions:
    translations:
        locales:
            fr_CA: [fr]
            fr: [en]
            en: []
```

| Option | Default | What it does |
|---|---|---|
| `translations.locales` | `{}` | The locales labels are shown in, each with the locales tried after it. A locale `phil/locale-dimensions` cannot read fails the container build. |

With locales declared, `PromotionTexts` reads each label in the request's locale — the translator's, read at each
call, so a worker serves each request in its own —, and the discounts of an order carry the label in that locale. A
locale not declared, or a translation whose label is not written yet, falls back to the promotion's own label.
