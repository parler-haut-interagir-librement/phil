# Extension points

## `Recipients`

```php
interface Recipients
{
    /** @return non-empty-list<string> */
    public function for(ContactMessage $message): array;
}
```

`ConfiguredRecipients` gives the configuration's addresses. An application whose pages write to different people
— an organisation of a directory, a shop of a network — replaces the alias `Recipients` with its own service, which
reads, for instance, the request or a field it adds to the form.

## The form elsewhere

`ContactFormType` and `ContactMailer` are services: a controller of the application builds the form, handles it and
sends `$form->getData()` with `ContactMailer::send()`, in a page of its own design.
