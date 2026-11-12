<!-- phil-dev source-sha256=017313cbf9d58227f41fa1285afc28c8c1a2e0f280a4b4766aa15dd6489fb140 -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `PhilContactBundle` | `final class`, `AbstractBundle` | Alias `phil_contact` ; le type de colonne, le thème de formulaire, l'extension Twig, le lecteur JSContact et sa configuration |
| `Infrastructure\Form\ContactCardType`, `ContactCardTransformer` | type de formulaire, transformateur de données | Le formulaire de fiche |
| `Infrastructure\Form\EmailAddressType`, `EmailTransformer` | type de formulaire, transformateur de données | Un courriel |
| `Infrastructure\Form\PhoneLineType`, `PhoneLineTransformer` | type de formulaire, transformateur de données | Une ligne téléphonique |
| `Infrastructure\Form\PersonNameType`, `PersonNameTransformer` | type de formulaire, transformateur de données | Un nom de personne en ses parties |
| `Infrastructure\Doctrine\ContactCardColumnType` | type DBAL | `phil_contact` |
| `Infrastructure\Http\VCardResponse` | réponse HTTP | Une fiche sous forme de fichier `.vcf` |
| `Infrastructure\Http\JSContactResponse` | réponse HTTP | Une fiche en JSContact |
| `Infrastructure\Twig\ContactExtension` | extension Twig | `contact_vcard`, `contact_json_ld`, `person_name` |
| `Phil\Contact\JSContactReader` | service | Le lecteur JSContact de `phil/contact`, ses limites tirées de `phil_contact.jscontact` |
| `assets/src/collection_controller.js` | contrôleur Stimulus | L'ajout et le retrait d'entrées |
| `templates/form/theme.html.twig` | thème de formulaire | Les boutons des collections |
