# Changelog

All notable changes to `phil/data-table-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.2-alpha.

### Added

- `LiveRequestsTest`: the component through the requests of LiveComponent 3.8.1, which serves it under its name only,
  renders nothing of it on a 422 raised before its action, and answers a redirection without `X-Live-Url`.

### Security

- Requires `symfony/ux-live-component` 3.8.1 or later, which fixes the advisories of 2026-11-24: a batched request
  without an action that rendered a component past its `#[IsGranted]`, and a component of its own route still reached
  through `/_components`; `symfony/ux-twig-component` 3.8 or later beside it.
- Requires `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on 2026-11-24.

## 0.1.1-alpha - 2026-11-24

### Changed

- The Node tests resolve `@hotwired/stimulus` to their stub with a synchronous hook given to `module.registerHooks()`,
  on the loading thread, instead of `module.register()`, which Node deprecates (DEP0205).
- The development requirements accept `phil/settings-bundle` 0.2, the minor version of its main branch and no earlier
  one, so that the package installs on its own beside that bundle.
- Requires the latest minor of each dependency (`symfony/ux-live-component` 3.8.1, `twig/twig` 3.31,
  `symfony/ux-twig-component` 3.8, `symfony/translation-contracts` 3.8), and of each development requirement
  (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `symfony/dom-crawler` 8.2, `symfony/stimulus-bundle` 3.8):
  `symfony/ux-live-component` 3.8.1 fixes CVE-2026-133774 and CVE-2026-133778. The window of a page given to the
  paginator starts at zero or later and holds one row or more.

## 0.1.0-alpha - 2026-11-14

### Added

- A table declared in PHP: the port `DataTable`, `TableBuilder` and `TableDefinition`; `Column` for text, numbers, dates,
  enums, booleans, links and Twig blocks, a block drawn by a `Twig\BlockChain` of the application's `cell_themes` then
  the column's own template (`CellBlocks`, `phil_data_table_cell()`); labels by translation key, a sort by default,
  page sizes up to 500 and the furthest offset a page starts from. A table is built each time it is opened, and a
  mistake in its declaration shows at the first render with `InvalidDefinition`.
- The port `DataSource`, which returns a `ResultPage` from a typed `TableQuery`, with `InMemoryDataSource` and
  `DoctrineDataSource`. The Doctrine adapter only knows the fields of its allow-list, binds every value, escapes the
  wildcards of a text filter, works on a copy of its QueryBuilder and reads a page through the ORM's `OffsetPaginator`
  and a `Window`. Sorts end on the identifier, so rows that tie keep their place between two pages. An offset is
  bounded by the table's `maxOffset`.
- `Filter` for text, enums, date ranges and booleans, and `StateResolver`, which checks every value of the address
  against the table's declaration before it reaches a query.
- `TableViews`: the table laid out for a template, translated and formatted for the locale, with `TableLinks` for the
  addresses of its states and the page numbers around the current one.
- The Live Component `PhilDataTable:Table`: its state in the address through `url` LiveProps, under a prefix when a page
  holds several tables; the other parameters of the page kept in its links; every control a plain link or a plain form
  that works the same without JavaScript.
- `Action` on a row, on a selection or both, asking its voter attribute on each row at every call and a confirmation
  unless told otherwise, in a native `<dialog>` drawn open — in the page without JavaScript, modal with it, dismissed
  by Escape, the focus back on the button that asked —; `TableOperations` reads the rows again from the table's own
  data source, so an identifier outside it does nothing. `ActionRefused` says why an action stopped.
- Inline editing by column with `Editing`: the voter asked again, the value typed by its column and checked by Symfony
  Validator, conflicts by the row's version or `EditConflict`, what stops the edit announced and tied to its control, and
  a notice in the live region when the row being edited is no longer on the page.
- `TableGuard`: the stateless CSRF token `phil_data_table` on every change and the seal of the context a plain form
  carries; `TableController` and `config/routes.php` for the changes of a browser without JavaScript, with a page back
  to a path of the site only; `CsrfProtectionPass`.
- The port `TableAccess`, answered by Symfony Security when it is there and refusing every attribute otherwise.
- The port `TablePreferences` for the columns and the page size a user keeps: nothing stored by default,
  `SettingsTablePreferences` when phil/settings-bundle is there, which logs a warning, with the table and the length,
  when choices longer than 2,000 characters are not kept.
- The export of a table that offers it with `exportable()`: its filtered and sorted rows, page by page, in its visible
  columns, as CSV or XLSX through phil/data-table-export when it is installed; `ExportRows`, `TableExports`, the route
  `phil_data_table_export` and `TableExportController`, which asks the seal, the voters and the most rows before it
  writes anything.
- Accessibility: a real `<table>` with its `<caption>`, `scope`, `aria-sort`, buttons in its headings, the result count
  in a live region, the pagination as a labelled `nav`, Enter and Escape in a cell, and the Stimulus controller `table`
  that gives the focus back after a re-render.
- The buttons drawn with the Button recipe of the PHIL UX kit, named by `phil_data_table_kit()` after the subdirectory
  of the Symfony UX Toolkit or `kit_subdirectory` (`KitComponents`, `KitSubdirectoryPass`, `DataTableExtension`); the
  fields of the filters given to Live Component under every key, so that typing in a filter sets its value.
- The stylesheet `data-table.css`, on the tokens of PHIL UX with values of its own, without inline styles: in a narrow
  container the rows are stacked under the headings of `data-label`, and the roles of the template keep them a table;
  the dialog of an action and the visually hidden text of its own.
- Translations in English and French.
- The browser suite under Panther: filters, columns, sort, focus and the dialog in Chrome, run apart, and skipped when
  its Stimulus is not installed.
- The Flex recipe in `recipe/0.1`: the bundle registered, `config/packages/phil_data_table.yaml` with no cell theme and
  `config/routes/phil_data_table.yaml` with the routes of plain forms and of the export.
