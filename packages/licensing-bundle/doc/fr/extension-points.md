<!-- phil-dev source-sha256=1ad0a9254ae81b7e10bcd3fd2a3a2dbacefe359600013a049b46746810ce506f -->
# Points d'extension

Ce qu'une application implémente, remplace ou écoute.

## Les licences

`phil_licensing.licenses` ajoute des licences au catalogue, avec leurs exigences ([Configuration](configuration.md)).
`license.<code>` du domaine `phil_licensing` traduit leurs noms.

## Le stockage

`Domain\Contract\LicenseDeclarationRepository` est un alias de `DoctrineLicenseDeclarations`. Une application qui
conserve les déclarations ailleurs nomme son service dans `phil_licensing.repository` : `declarationOf()`, `add()` et
`remove()`.

## Les sujets

`Domain\Contract\Licensable` permet à un objet de l'application de tenir lieu de son sujet partout où le bundle en prend
un : la garde d'exportation, le voter, le moteur de rendu et les fonctions Twig.

## L'exportation

`Application\Event\ExportChecking` est réparti à chaque vérification de la garde d'exportation ; ses écouteurs ajoutent
des violations ([Conformité](compliance.md)). `ExportGuard` est lui-même un service public qu'une application appelle
depuis un contrôleur, un *handler* de message ou une commande. `Infrastructure\PagedMedia\GuardPrintedWork` l'appelle
avant une impression de `phil/paged-media-bundle` qui nomme son œuvre. `phil_licensing.printing.unlicensed_subjects`
nomme les types de sujets imprimés sans licence, et `phil_licensing.printing.strict` refuse une impression qui ne nomme
aucune œuvre ([Impression](printing.md)).

## La page

`phil_licensing.template` nomme un autre gabarit, dont le rendu reçoit `page`, un `AttributionPage`, et `locale`. Le
gabarit du bundle se remplace à `templates/bundles/PhilLicensingBundle/attribution.html.twig`. Le domaine de traduction
`phil_licensing` accepte d'autres langues et d'autres phrases. `AttributionPage` est le modèle d'une page construite
autrement.

## Les formulaires

`AttributionType` et `LicenseFinderType` sont des types de formulaire de la fabrique de formulaires de l'application,
étendus par des extensions de type et rendus par ses thèmes de formulaire : les préfixes de bloc sont
`phil_licensing_attribution` et `phil_licensing_finder`. `LicenseFinderType` accepte tout stockage de données du flux de
formulaire dans son option `data_storage`.

## L'assistant de licence

`Domain\Finder\LicenseFinder` est un service public qu'une application appelle avec son propre formulaire, une commande
ou une API ; `LicenseFinderData::choices()` transforme les réponses du formulaire du bundle en ses `LicenseChoices`. Les
licences à partage identique de l'application sont proposées comme matériel adapté. Les gabarits
`finder/form.html.twig` et `finder/recommendation.html.twig` se remplacent sous
`templates/bundles/PhilLicensingBundle/finder/`, et les clauses des conditions personnalisées sont des phrases du domaine
`phil_licensing`, `terms.<clause>`.
