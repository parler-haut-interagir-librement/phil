<!-- phil-dev source-sha256=7e579e1be297205b54f24805c4cd1f17202c0d8ebcc73acccc70528e93c15c76 -->
# Points d'extension

## `Recipients`

```php
interface Recipients
{
    /** @return non-empty-list<string> */
    public function for(ContactMessage $message): array;
}
```

`ConfiguredRecipients` donne les adresses de la configuration. Une application dont les pages écrivent à des personnes
différentes — une organisation d'un répertoire, une boutique d'un réseau — remplace l'alias `Recipients` par son propre
service, qui lit par exemple la requête ou un champ qu'elle ajoute au formulaire.

## Le formulaire ailleurs

`ContactFormType` et `ContactMailer` sont des services : un contrôleur de l'application construit le formulaire, le
traite et envoie `$form->getData()` avec `ContactMailer::send()`, dans une page de sa propre conception.
