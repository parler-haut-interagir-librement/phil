<!-- phil-dev source-sha256=c2792c2d9b6738ee19de5e0716dcf1fbb28c0b60a93d2cdb986809e64e0c6ec5 -->
# Le PDF

`Phil\CertificatesBundle\Application\CertificatePdf` produit le rendu d'un certificat :

```php
$output = $certificatePdf->render($certificate, 'fr');   // a Phil\PagedMediaBundle\Application\Output
file_put_contents($output->filename, $output->contents);  // certificate-7KQ2M9XA04TZ.pdf
```

Le registre le sert à `/<code>/certificate.pdf`, dans la locale de la requête.

## Ce qu'il montre

Une page à l'italienne, Letter ou A4 : un titre, le nom imprimé sur le certificat, le nom de la formation sur deux
lignes au plus, la date d'attribution et, quand elle existe, la fin de sa validité, le nom de l'émetteur et le code du
certificat. Un code QR dans le coin inférieur droit ouvre la page du certificat dans le registre, dont l'adresse est
écrite à côté : quiconque tient le papier peut ainsi vérifier qu'il est toujours valide. Le code est dessiné au niveau
de correction d'erreur M, chaque suite de modules sombres formant un rectangle, dans sa zone de silence de quatre
modules.

Les textes viennent du domaine de traduction `phil_certificates`, clés `pdf.*`, dans la locale donnée, sinon dans celle
du traducteur. Les dates sont mises en forme par `intl` dans cette locale. Le titre, l'auteur, le sujet et la date de
création du PDF nomment le certificat, l'émetteur, le destinataire et le moment de l'attribution : le même certificat
donne le même dessin.

## Polices

Sans polices dans la configuration, le texte est composé en Helvetica, qui n'imprime que le Latin-1. Un site dont les
noms des personnes apprenantes utilisent d'autres écritures fournit des polices TrueType qui les couvrent :

```yaml
phil_certificates:
    pdf:
        font: '%kernel.project_dir%/assets/fonts/NotoSans-Regular.ttf'
        bold_font: '%kernel.project_dir%/assets/fonts/NotoSans-Bold.ttf'
```

Les polices sont intégrées, réduites aux glyphes utilisés.

## L'attestation intégrée

Avec `pdf.bake: true`, la valeur par défaut, l'attestation signée est intégrée au PDF par l'étape `attach` du pipeline
PDF de Gotenberg, comme fichier associé PDF/A-3 de relation `Data` : `credential.jwt` (`application/vc+jwt`) pour un
jeton, `credential.json` (`application/vc`) pour un document avec sa preuve. Le fichier porte sa propre preuve : un
vérificateur extrait la pièce jointe et la vérifie, sans le registre. `phil_paged_media.gotenberg.url` nomme le
Gotenberg sur lequel s'exécute le pipeline.

## Le PDF d'archivage

Avec `pdf.archival: true`, le PDF est ensuite réparé au format PDF/A-3b, le profil d'archivage qui admet un fichier
associé de n'importe quel type, dans la langue de la locale du certificat. Le résultat passe la validation PDF/A-3b de
Gotenberg avec l'attestation jointe; le test d'intégration du bundle vérifie les deux contre un vrai Gotenberg 9. La
réparation coûte une seconde étape du pipeline : elle est donc désactivée par défaut.

## La signature

Avec `pdf.sign: true`, le PDF est signé avec le certificat de `phil_paged_media.signing`, après l'intégration de
l'attestation et la réparation du fichier, pour que tout changement au fichier se voie.

## Le sujet d'impression

`render()` nomme le sujet de l'impression (le type `certificate` et l'identifiant du certificat) pour les écouteurs de
`phil/paged-media-bundle`; [Points d'extension](extension-points.md#le-sujet-dimpression) dit comment un site qui
vérifie les licences de ses impressions laisse passer les certificats.
