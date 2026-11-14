# FileInput

A file field that also takes a drop, listing what it holds with the size of each.

```twig {"preview":true}
<twig:FileInput name="photos[]" label="Add photos" hint="PNG or JPEG, up to 2 MB" accept="image/png,image/jpeg" :maxSize="2097152" :multiple="true" />
```

## Installation

::: installation

## Usage

```twig
<twig:FileInput name="photos[]" label="Add photos" hint="PNG or JPEG, up to 2 MB" accept="image/png,image/jpeg" :maxSize="2097152" :multiple="true" />
```

## Accessibility

The input stays the field and its label the zone: Tab reaches it and Enter opens the dialog. What `accept`, `maxSize` or a single field leaves out is said in a polite live region. These limits spare the reader a useless upload; only the server can enforce them. File names are written as text.
