# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the templates elsewhere than in the contents | Implement `Application\Contract\TemplateStore` — drafts, publication, versions, catalog, each in exactly the locale given — and name its service in `store`; `StoreTestCase` of the bundle's tests holds what a store promises |
| Guard or follow a publication | Listen to the events of the `phil_content` workflow of `phil/content-bundle`, whose subjects include the rows of `EmailTemplateDimension` |
| Ship default templates | Add `<key>.<locale>.yaml` files to a directory of `defaults_paths`, or replace `Application\Contract\DefaultTemplates` |
| Name the current site | Implement `Application\Contract\SiteScope` and alias the interface to it |
| Change the house layout | Override `@PhilEmailTemplates/email/layout.html.twig`, or name another template in `layout`; or implement `Application\Contract\EmailLayout` |
| Inline the styles otherwise | Implement `Application\Contract\StyleInliner` and alias the interface |
| Read the images elsewhere | Implement `Application\Contract\EmbedLoader` and alias the interface |
| Render Markdown otherwise | Implement `Application\Contract\MarkdownRenderer` and alias the interface |
| Give the sandbox a filter or a function | Tag a Twig extension `phil_email_templates.sandbox_extension` and list its names in `sandbox.filters` or `sandbox.functions` |
| Preview with other data, or with a dedicated preview package | Implement `Application\Contract\PreviewRenderer` and name its service in `preview` |
| Send from a message | Implement `Application\Contract\DeliveryResolver` and name its service in `messenger.delivery_resolver` |
| React to a change | Listen to `Domain\Event\TemplateSaved`, `TemplatePublished`, `TemplateDraftDiscarded` and `TemplateReset` |
| Replace the engine | Implement `Application\Contract\TemplateEngine` and alias the interface — the sandbox is then yours to keep |
