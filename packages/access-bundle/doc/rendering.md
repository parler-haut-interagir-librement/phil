# Rendering

The bundle's two components are drawn by the server with the recipes of the PHIL UX kit, installed in the application
by the Symfony UX Toolkit; they need no JavaScript.

| Component | Draws | Recipes |
|---|---|---|
| `<twig:PhilAccess:ViewAsBanner />` | While an admin views the site as a member: an alert with the member, a line saying the view only reads, and the form that stops it. Nothing otherwise. | `Alert`, `Alert:Title`, `Alert:Description`, `Button` |
| `<twig:PhilAccess:AccessSummary item="page:42" />` | Who has access to an item: its visibility as a badge, then its shares — member or group, level, end — in a table. Only for those holding the widest level on the item. | `Badge`, `Table` and its parts |

`item` is a `ResourceReference`, an `AccessibleItem` or its text. The recipes are found in the subdirectory of the
application's `ux_toolkit` configuration — `ui:Alert` with `ui` —, or of `phil_access.kit_subdirectory`.

The banner has the role `status`, so a screen reader announces it. The summary's table has a caption, and each end is
a `<time>` with its machine-readable instant, written in UTC.

The texts are in the translation domain `phil_access`, in English and French; an application overrides a template
under `templates/bundles/PhilAccessBundle/components/`.
