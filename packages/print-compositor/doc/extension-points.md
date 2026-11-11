# Extension points

The library is plain PHP: it declares no service and no port. What an application chooses goes through arguments.

| Point | How |
|---|---|
| The images | Any `Raster`: decoded from files or bytes the application stores where it wants, or copied from a GD image it drew itself with `Raster::fromGdImage()` |
| The fonts | Any TrueType or OpenType file, read with `TypeFace::fromFile()`; the application checks the licence `phil/fonts` reads |
| The inks | Any name on a `Layer`: one file comes out per name |
| The resolution | The `dpi` of a `PrintJob`, and of `TextFitter::fit()` when a text is checked ahead |
| The shading | The strength of a `Mockup`, from 0 to 1 |
| Encoding | `Raster::png()` and `Raster::webp()`, or `toGdImage()` for an encoder of the application's own |
| Where the files go | `PrintFiles::png()` and `Raster::webp()` return bytes: the application writes them to its storage, attaches them to an order or sends them to a printer |

`Item` is implemented by `ImageItem` and `TextItem` only: `PrintCompositor` refuses an item of another class with
`InvalidLayout`. Something else drawn in a print file is drawn by the application on a GD image, then placed as an
`ImageItem`.
