# Extension points

The package is one adapter: `LoupeIndex`, the `Phil\SearchBundle\Application\SearchIndex` of `phil/search-bundle`
over Loupe. It declares no port of its own; what an application chooses goes through the bundle and the constructor.

| Point | How |
|---|---|
| Choosing it | `adapter: loupe` in the configuration of `phil/search-bundle` |
| Where the indexes live | `loupe.directory`, a directory per index; none keeps them in memory, for tests |
| How Loupe is built | The `LoupeFactoryInterface` given to `LoupeIndex`: the application's own factory, to set Loupe up otherwise |
| What is searched and filtered | The documents the bundle's `DocumentProvider`s give: the text fields are searched, in the order a provider gives them, and the filters are kept as lists of text — see [How it indexes](indexing.md) |

An application that wants another engine writes its own `SearchIndex` for `phil/search-bundle` rather than extending
this one: `LoupeIndex` is final.
