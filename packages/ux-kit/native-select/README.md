# NativeSelect

The `select` element of the browser, dressed by phil-ux: the list of the system, on every device, without JavaScript.

```twig {"preview":true}
<twig:NativeSelect name="country" aria-label="Country">
    <twig:NativeSelect:Option value="">Choose a country</twig:NativeSelect:Option>
    <twig:NativeSelect:OptGroup label="North America">
        <twig:NativeSelect:Option value="ca">Canada</twig:NativeSelect:Option>
        <twig:NativeSelect:Option value="mx">Mexico</twig:NativeSelect:Option>
    </twig:NativeSelect:OptGroup>
</twig:NativeSelect>
```

## Installation

::: installation

## Usage

```twig
<twig:NativeSelect name="country" aria-label="Country">
    <twig:NativeSelect:Option value="">Choose a country</twig:NativeSelect:Option>
    <twig:NativeSelect:OptGroup label="North America">
        <twig:NativeSelect:Option value="ca">Canada</twig:NativeSelect:Option>
        <twig:NativeSelect:Option value="mx">Mexico</twig:NativeSelect:Option>
    </twig:NativeSelect:OptGroup>
</twig:NativeSelect>
```

## Accessibility

The browser gives the list its role, its keyboard and its announcements; name it with a `Label` or `aria-label`.
