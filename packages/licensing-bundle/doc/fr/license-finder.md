<!-- phil-dev source-sha256=4bfd0ee2993596606e128d4588ba1c80131be0998ceda49b4ccebd6543eecfa7 -->
# L'assistant de licence

L'assistant aide un auteur à choisir la licence d'une œuvre selon ce qu'il permet, et rédige des conditions propres
quand aucune licence du catalogue ne convient.

## L'arbre de décision

`LicenseFinder` est du PHP pur : ni requête, ni formulaire, ni traduction. Il lit `LicenseChoices`, les réponses
données jusqu'ici, et dit quelle `Question` vient ensuite, ou recommande une licence une fois qu'il ne reste rien à
demander.

| Question | Réponses |
|---|---|
| `adapted_material` | La licence de partage à l'identique du matériel que l'œuvre adapte, ou aucune. |
| `attribution` | Si qui réutilise l'œuvre doit en créditer les créateurs. |
| `commercial_use` | Si l'œuvre peut servir à des fins commerciales. |
| `modifications` | `allowed`, `share_alike` ou `forbidden` : ce que d'autres peuvent faire des adaptations. |

Le matériel sous une licence de partage à l'identique, soit l'ORC License, CC BY-SA 4.0, CC BY-NC-SA 4.0, l'Open Game
License 1.0a et chaque licence de partage à l'identique de l'application, impose sa licence : la recommandation est
cette licence, marquée `imposed`, et les autres questions sont sautées. Sinon :

| Crédit | Usage commercial | Adaptations | Recommandation |
|---|---|---|---|
| exigé | permis | permises | `cc-by-4.0` |
| exigé | permis | à l'identique | `cc-by-sa-4.0` |
| exigé | permis | interdites | `cc-by-nd-4.0` |
| exigé | interdit | permises | `cc-by-nc-4.0` |
| exigé | interdit | à l'identique | `cc-by-nc-sa-4.0` |
| exigé | interdit | interdites | `cc-by-nc-nd-4.0` |
| non exigé | permis | permises | `cc0-1.0` |
| non exigé | toute autre réponse | | `custom`, avec `CustomTerms` |

```php
$finder = new LicenseFinder($catalog);
$choices = LicenseChoices::none()->withAdaptedLicense(null)->withAttribution(false)->withCommercialUse(false)->withModifications(Modifications::Allowed);

$finder->nextQuestion($choices);              // null: every question is answered
$recommendation = $finder->recommend($choices);
$recommendation->license;                     // 'custom'
$recommendation->terms?->clauses;             // Share, NoCommercialUse, Adaptations, NoAttribution, NoWarranty
```

`recommend()` lance `InvalidLicensing` tant qu'une question reste sans réponse, ou pour une licence adaptée qui n'est
pas une licence de partage à l'identique du catalogue.

## La licence propre

`CustomTerms` énumère ses clauses dans l'ordre de lecture : partage, usage commercial, adaptations, crédit, garantie.
`CustomTermsWriter` les rédige dans la langue du lecteur, soit un titre qui nomme le titulaire et un paragraphe par
clause, `terms.<clause>` du domaine `phil_licensing`, et la déclaration conserve le texte :

```php
$declaration = LicenseDeclaration::declare($subject, $catalog->get($recommendation->license), $now);

if ($recommendation->terms instanceof CustomTerms) {
    $declaration->includeLicenseText($writer->write($recommendation->terms, 'Example Press', 'en'), $now);
}
```

Les conditions sont un point de départ en langage clair que l'auteur relit ; elles ne sont pas un avis juridique.

## Le formulaire en étapes

Avec `symfony/form` et `symfony/validator`, `LicenseFinderType` pose une question par page sur le flux de formulaire de
Symfony. Chaque étape est soumise, les réponses sont conservées d'une étape à l'autre dans la session, et les boutons
« Précédent », « Suivant » et « Terminer » permettent de s'y déplacer : aucun JavaScript n'est nécessaire. Le matériel
sous partage à l'identique fait sauter les autres étapes.

```php
public function __invoke(Request $request, LicenseFinder $finder): Response
{
    $flow = $this->createForm(LicenseFinderType::class, new LicenseFinderData())->handleRequest($request);

    if ($flow->isSubmitted() && $flow->isValid() && $flow->isFinished()) {
        return $this->render('licensing/recommendation.html.twig', ['recommendation' => $finder->recommend($flow->getData()->choices())]);
    }

    return $this->render('licensing/finder.html.twig', ['form' => $flow->getStepForm()->createView()]);
}
```

L'option `data_storage` remplace la session par tout stockage du flux de formulaire. `LicenseFinderData` contient
l'étape courante et une propriété par question ; `choices()` donne ce que lit l'assistant.

## Les gabarits

`@PhilLicensing/finder/form.html.twig` affiche l'étape courante, avec `form` : les étapes en liste ordonnée, la courante
marquée `aria-current="step"`, puis la question. `phil_licensing_recommendation(recommendation, holder, locale)` rend
`@PhilLicensing/finder/recommendation.html.twig` : la licence, le lien vers son texte et les conditions propres.
`phil_licensing_terms(terms, holder, locale)` donne le texte de conditions propres. Le balisage est du HTML sémantique,
avec les classes `phil-licensing-finder`, `phil-licensing-recommendation` et `phil-licensing-terms`.
