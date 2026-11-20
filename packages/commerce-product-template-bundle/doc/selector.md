# Choosing a product

The products of a group are shown as one page with a selector per axis. The module's Stimulus controller,
`variant-selector`, picks the member of the values chosen, disables the values the group does not sell with the
others, and shows the member's mockups. With AssetMapper and `symfony/stimulus-bundle`, the module serves it under the
name Symfony UX gives the controllers of a package: no build step, and no Webpack.

```twig
{% set page = groups.page('NAQ001', 'unisex', app.request.locale) %}

<form {{ stimulus_controller('phil/commerce-product-template-bundle/variant-selector', {
    members: page.members,
    language: 'F',
    images: asset('shop-images/'),
}) }} data-action="change->phil--commerce-product-template-bundle--variant-selector#update">
    {% for axis in page.axes %}
        <fieldset>
            <legend>{{ axis.label }}</legend>
            {% for value in axis.values %}
                <label>
                    <input type="radio" name="{{ axis.code }}" value="{{ value.code }}" data-axis="{{ axis.code }}">
                    {{ value.label }}
                </label>
            {% endfor %}
        </fieldset>
    {% endfor %}

    <img data-phil--commerce-product-template-bundle--variant-selector-target="mockup" data-face="front" alt="" hidden>
    <p data-phil--commerce-product-template-bundle--variant-selector-target="unavailable" hidden>Not made in this combination.</p>
    <input type="hidden" name="sku" data-phil--commerce-product-template-bundle--variant-selector-target="sku">
    <button>Add to cart</button>
</form>
```

| Part | Role |
|---|---|
| `members` value | The members of the group, as `GroupPage::$members` gives them: SKU, values by axis, language version, mockups by face |
| `language` value | The code of the language version shown; empty for a design without one |
| `images` value | The base URL the mockup paths are read under |
| An element marked `data-axis` | A radio button or a `<select>` of an axis, its values the codes of the axis |
| `sku` target | Receives the SKU of the member chosen, or nothing |
| `mockup` targets | Images marked `data-face`: each shows the member's mockup of its face, or is hidden |
| `unavailable` target | Shown when every axis has a value and no member has them |

The members of a page are the products the group sells: a combination the template excludes — a colour not made in
a size — has no member, so the controller disables that colour once the size is chosen, and that size once the colour
is. The empty option of a `<select>` is never disabled.

Each update dispatches `phil--commerce-product-template-bundle--variant-selector:change`, its detail giving `sku`, or
`null`, and `values`, the codes chosen by axis.

The controller changes properties and the `hidden` and `disabled` attributes, never a `style` attribute, so a page
keeps a content security policy without `style-src 'unsafe-inline'`. The server checks the SKU it receives like any
other: the controller only helps the buyer choose.
