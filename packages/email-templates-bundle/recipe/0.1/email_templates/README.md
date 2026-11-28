# Default email templates

One YAML file per template and locale, `<key>.<locale>.yaml`, as `ticket.fr.yaml`. A file here overrides the file of
the same name of phil/email-templates-bundle; a new key adds a template the sites may then edit. Check them with
`bin/console phil:email-templates:check`.
