# Accessibility

- A real `<table>` with its `<caption>`, `scope="col"` on the headings and `scope="row"` on the first cell of each row.
- `aria-sort` on the sorted column, and a real `<button>` in each sortable heading.
- The count of results, and what an action or an edit did, in a `role="status"` live region.
- The pagination is a labelled `<nav>`, and the current page carries `aria-current="page"`.
- Each row's checkbox and action is named after the row.
- Errors of an edit are in `role="alert"` and tied to the control with `aria-invalid` and `aria-describedby`.
- The question of an action is a native `<dialog>` with the role `alertdialog`, named by its question. It is drawn open,
  so a browser without JavaScript shows it in the page, before the rows, with its plain form and a Cancel link back to
  the table. The Stimulus controller makes it modal: the browser makes the page inert behind it and keeps the focus
  inside. The focus starts on Cancel, the answer that does no harm; Escape answers as Cancel does; once the dialog is
  gone, the focus goes back to the button that asked.
- When the row being edited is no longer on the page — another sort, filter or page —, the live region says so.
- Visually hidden text uses the bundle's own `phil-data-table__hidden`, so the table needs no utility of a stylesheet.

## The keyboard

Every control is a link, a button or a form field. An Edit link opens the control of the cell and moves the focus to it.
Enter saves and Escape goes back. The Stimulus controller gives the focus back to the control that had it after a
re-render, by its `data-phil-focus` key, and a control the server marks `autofocus` goes first. The browser suite checks
it in Chrome, after a filter, a column hidden, a sort, and the dialog of an action.

## The stacked list

The stylesheet stacks the rows when the table's container is narrower than `40rem`, and each cell is headed by
`data-label`. A browser drops the semantics of a table drawn in blocks, so the template repeats them as roles: `table`,
`rowgroup`, `row`, `columnheader`, `rowheader` and `cell`. The sort buttons stay available above the list.

The stylesheet uses no inline style, so a Content Security Policy without `unsafe-inline` shows the table as designed.
