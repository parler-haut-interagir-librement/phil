<!-- phil-dev source-sha256=38f46c5deb3824e407c6709312b1c80869f4eddba63ec4c57f829d3215b1e17e -->
# Collecteurs et cibles

## Un collecteur

Un `ReferenceCollector` trouve vers quoi pointe un genre de source. Le champ est l'endroit où se trouve le lien : une
propriété, un chemin dans un document JSON — `blocks.2.image` —, fait de lettres, de chiffres, de `_`, `.`, `:`, `-` et
de crochets.

```php
final readonly class PageReferences implements ReferenceCollector
{
    public function supports(object $source): bool { return $source instanceof Page; }

    public function sourceOf(object $source): ResourceReference { return ResourceReference::of('page', $source->id); }

    public function collect(object $source): iterable
    {
        foreach ($source->blocks as $index => $block) {
            if (isset($block['image'])) {
                yield new Link('blocks.'.$index.'.image', ResourceReference::of('image', $block['image']));
            }
        }
    }
}
```

## Une cible

Un `ReferenceTarget` connaît un genre de ressource qu'utilisent les autres : si une ressource existe encore, et quelle
référence correspond à un objet. Sans cible, un genre ne voit jamais une suppression refusée ni ses liens rompus
vérifiés.

```php
final readonly class ImageReferences implements ReferenceTarget
{
    public function kind(): string { return 'image'; }

    public function exists(string $id): bool { return null !== $this->images->find($id); }

    public function referenceOf(object $resource): ?ResourceReference
    {
        return $resource instanceof Image ? ResourceReference::of('image', $resource->id) : null;
    }
}
```

Avec l'autoconfiguration, le bundle trouve les deux.

## Ce que fait un *flush*

Avec `track_entities`, `ReferenceTrackingListener` surveille les *flush* de sa connexion :

- avant le *flush*, **une cible encore utilisée par une source qui reste refuse sa suppression** : `StillReferenced`,
  et le *flush* est annulé. Une source supprimée dans le même *flush* ne compte pas ;
- après le *flush*, une source insérée ou mise à jour voit ses liens collectés de nouveau, une source supprimée les
  voit oubliés, dans l'ordre des *flush*. Seuls les liens qui ont changé sont écrits : une source enregistrée sans
  changement de ses liens coûte une lecture.

La référence d'une source supprimée est lue avant le *flush*, pendant que son identifiant est connu : Doctrine vide un
identifiant généré une fois la ligne supprimée. Un *flush* qui échoue ferme son gestionnaire d'entités, et ce qu'il
aurait enregistré disparaît avec lui. Vider le gestionnaire d'entités, ou le `kernel.reset` du conteneur entre les
messages d'un *worker*, le fait aussi disparaître.

Appelez `References::assertUnused()` avant de supprimer, pour montrer un message à la personne plutôt qu'un *flush*
annulé ; l'écouteur est le filet de sécurité. Pour des sources qui ne sont pas des entités Doctrine, appelez
vous-même `refresh()` et `forget()`.
