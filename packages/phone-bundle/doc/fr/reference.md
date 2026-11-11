<!-- phil-dev source-sha256=ab2fead3c661453cd8fa8fe4021383fb624bc7653b558da7b5152d92a01fdf70 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilPhoneBundle` | `final class`, `AbstractBundle` | Alias `phil_phone`, clé `default_region` ; les services et le type de colonne |
| `Infrastructure\Form\PhoneType` | type de formulaire | Un numéro dans un champ `tel` |
| `Infrastructure\Form\PhoneNumberTransformer` | transformateur de données | Entre un `PhoneNumber` et son texte |
| `Infrastructure\Validator\ValidPhoneNumber`, `ValidPhoneNumberValidator` | contrainte et validateur | Un numéro valide de types et de pays acceptés |
| `Infrastructure\Doctrine\PhoneNumberColumnType` | type DBAL | `phil_phone` |
| `Infrastructure\Twig\PhoneExtension` | extension Twig | `phone`, `phone_link` |
