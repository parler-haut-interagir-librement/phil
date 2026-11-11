# Extension points

The library is plain PHP: it declares no service and no port. What an application chooses goes through arguments.

| Point | How |
|---|---|
| The axes | Any axes, up to 8: a size and a colour, a format and a paper, a version |
| The SKU | Any pattern of literals and placeholders, `{design}` among them |
| The prices | A base, a price per value of one axis, supplements on any axis |
| The channels | The channels each value is sold through, as the application's catalogue names them |
| The images | Paths relative to the application's store of images: photos, swatches, artworks |
| The limit of a plan | `new VariantPlanner($maxVariants)`, at most `VariantPlanner::MAX_VARIANTS` |
| Storage | `TemplateArray` and `DesignArray`, or the application's own mapping of the public members |

What a plan becomes — products of a catalogue, mockups, options of a line — is the application's, or the bundle's.
