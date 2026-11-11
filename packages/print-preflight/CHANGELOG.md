# Changelog

All notable changes to `phil/print-preflight` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Length`, `Size`, `Rectangle` and `Edge`: lengths kept in points and built from points, millimetres, inches or CSS
  pixels, compared within half a point; trim sizes with A4, A5 and Letter; frames measured from the top left corner of
  the trim box, or turned from PDF coordinates with the page's bleed.
- `Document`, `Page`, `PlacedImage`, `Box`, `UsedFont` and `ColourSpace`: the model of a document to print — pages with
  their trim size and bleed, images with their pixel size, frame, scaled size and effective resolution, other boxes,
  elements that bleed or not, ink coverage, overprint, line width and transparency when the application knows them,
  fonts with their `phil/fonts` embedding permissions, colour spaces read from PDF names.
- `PrintProfile` and `ColourWorkflow`: a printer's trim sizes, uniform size, bleed, safety margin, minimum, target and
  maximum resolutions, page count minimum, maximum and multiple, colour workflow and spot colours, maximum ink
  coverage, minimum line width, overprint and transparency, with the severity of any rule set or ignored by its code;
  contradictory rules refused.
- `Severity`, `Rule`, `Finding` and `Report`: errors, warnings and notices, the codes and default severities of the
  built-in rules, findings with an English sentence, page, subject and parameters, a report ordered document first
  then page by page, with `passes()`, `isClean()`, filters and `toArray()`.
- `Check\PageCount`, `Check\PageGeometry`, `Check\Fonts`, `Check\ImageResolution`, `Check\Colours`,
  `Check\InkCoverage`, `Check\Overprint`, `Check\Transparency`, `Check\Hairlines` and `Check\Placement`: the built-in
  checks, and `Check\Check` for an application's own.
- `Preflight`: the checks run in order on a document, the profile's severities and ignored rules applied, a check added
  with `withCheck()`.
- `PrintPreflightException`, implemented by `InvalidDocument` and `InvalidProfile`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
