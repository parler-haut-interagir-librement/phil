# Changelog

All notable changes to `phil/print-compositor` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Colour`, `Box`, `PixelBox`, `Zone`, `Units`, `HorizontalAlign`, `VerticalAlign` and `Fit`: sRGB colours with their
  opacity read from hex, rectangles in millimetres from the top left corner of a zone and in pixels, print zones at
  their real size, millimetres and points turned into pixels at a resolution, alignments on both axes, images
  contained or covering their box.
- `Raster`: an image in true colour with its alpha channel, decoded from bytes or a file with a pixel limit checked
  before decoding, copied from a GD image, blank, read pixel by pixel, encoded in PNG with its resolution or in WebP
  at a quality.
- `TypeFace`: a TrueType or OpenType font read through `phil/fonts`, WOFF, WOFF2 and collections refused, and the
  characters of a text it does not cover.
- `ImageItem`, `TextItem`, `Item`, `Layer` and `PrintJob`: the items of a print file in their boxes, layers drawn in
  order in an ink, a job at 300 dpi by default whose every item lies inside its zone.
- `TextFitter`, `FittedText` and `FittedLine`: the largest size of a text, by half points from its maximum down to
  its minimum, at which its ink fits its box once wrapped at spaces; a text with a missing glyph or that does not fit
  at its minimum refused.
- `PrintCompositor` and `PrintFiles`: one transparent print file per ink at the zone's real size, images scaled and
  aligned, texts drawn in their colour and aligned by their ink, each file as a raster or a PNG at the job's
  resolution.
- `FacePhoto`, `Placement`, `Mockup` and `MockupCompositor`: artworks placed in the zones of a photo of a blank
  product, darkened and lightened by the fabric's shading relative to the zone, at a strength from 0 to 1.
- `PrintCompositorException`, implemented by `InvalidLayout`, `InvalidImage`, `UnsupportedFont`, `MissingGlyphs`,
  `TextDoesNotFit` and `MissingCapability`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
