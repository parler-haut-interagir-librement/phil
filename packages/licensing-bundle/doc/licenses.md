# Licenses

`LicenseCatalog` holds the built-in licenses, then those of the application. Each `License` has a code, a name, an
address or a reference where its text is found, an SPDX identifier when it has one, and its requirements.

## The built-in licenses

| Code | License | Requirements |
|---|---|---|
| `orc` | ORC License | `attribution`, `license_notice`, `share_alike`, `reserved_material` |
| `cc-by-4.0` | Creative Commons Attribution 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `indicate_changes` |
| `cc-by-sa-4.0` | Creative Commons Attribution-ShareAlike 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `share_alike`, `indicate_changes` |
| `cc-by-nc-4.0` | Creative Commons Attribution-NonCommercial 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `indicate_changes`, `non_commercial` |
| `cc-by-nc-sa-4.0` | Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `share_alike`, `indicate_changes`, `non_commercial` |
| `cc-by-nd-4.0` | Creative Commons Attribution-NoDerivatives 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `indicate_changes`, `no_derivatives` |
| `cc-by-nc-nd-4.0` | Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `indicate_changes`, `non_commercial`, `no_derivatives` |
| `cc0-1.0` | CC0 1.0 Universal | none |
| `ogl-1.0a` | Open Game License 1.0a | `full_text`, `copyright_notice`, `share_alike`, `product_identity`, `designated_content` |
| `custom` | Custom license | `license_notice` |

The requirements summarize the official texts; the license, at its address, is what binds.

- **ORC License**, at `https://paizo.com/orclicense`, registered as Library of Congress TX 9-307-067: the ORC Notice,
  the attribution of the Licensed Material used, the good-faith identification of the Reserved Material, and adapted
  Licensed Material offered under the license. Its text need not be reproduced. Expressly Designated Licensed
  Material is optional: the declaration's designated content holds it.
- **Creative Commons Attribution 4.0**: the credit of the creators as they ask, the notices they supply, a reference
  to the license, and an indication of changes; a link to the license suffices. **ShareAlike** adds adaptations under
  the same license; **NonCommercial** keeps the material out of commercial use; **NoDerivatives** keeps adapted
  material from being shared.
- **CC0 1.0** dedicates the work to the public domain and asks nothing.
- **Open Game License 1.0a**: a copy of its text with every copy of Open Game Content, the copyright notices of the
  Open Game Content used with the work's own (its section 15), the designation of Product Identity, and the clear
  identification of the Open Game Content.
- **custom** is the work's own terms: the declaration holds them as its license text.

## Requirements and the checks

| Requirement | On the work's license | On a resource's license |
|---|---|---|
| `attribution` | — | the attribution names an author or carries a notice |
| `license_notice` | a license text when the license has no address nor reference | — |
| `full_text` | a license text | — |
| `copyright_notice` | the work's notice | the resource's notice |
| `share_alike` | — | the work is under the same license |
| `indicate_changes` | — | the attribution says what changed, or that nothing did |
| `reserved_material` | the Reserved Material | — |
| `product_identity` | the Product Identity | — |
| `designated_content` | the designated content | — |
| `non_commercial` | — | — (the publisher keeps the material out of commercial use) |
| `no_derivatives` | — | — (the publisher shares no adaptation of the material) |

The [license finder](license-finder.md) chooses among these licenses from what an author allows.

## The names

`LicenseNames` gives the name of a license in the reader's language: `license.<code>` of the translation domain
`phil_licensing` when it is translated, else the name of the catalog. The built-in licenses are translated in English
and French; an application translates its own.
