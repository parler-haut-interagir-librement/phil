# Mockups

`Mockups::render('NAQ001', 'unisex')` composes the mockups of a group with `phil/print-compositor`, once the group is
generated and flushed.

## What is composed

For each active member and each face of the template:

- the **photo** is the one of the first value of the member, in the order of the axes, that has a photo of that
  face — the colour's, for a shirt;
- the **artworks** are those the plan places on that face whose zone has a `PhotoBox`;
- a face without a photo, or without such an artwork, has no mockup.

The artworks are placed in their boxes on the photo with the fabric's shading of `mockups.shading`, and encoded in
WebP at `mockups.quality`. The member keeps the path of each mockup by face code.

## Names and running again

A mockup is named by a digest of what it is made of — the bytes of the photo and of each artwork, the boxes, the
shading and the quality — under `mockups.prefix`: `mockups/3f9c….webp`. So:

- the sizes of a colour share one file, composed once;
- running it again composes only what is missing from the store: a new colour, a photo or an artwork replaced;
- a mockup is never overwritten with other content, so a page cached with an old path still shows a coherent image.

`render()` returns the number of mockups it composed. Each image is read once per run.

## The store of images

`ImageStore` reads photos and artworks and writes mockups by paths relative to the store. With `images_directory`
configured, `DirectoryImageStore` keeps them in that directory: a path that could leave it is refused, and a mockup is
written to a temporary file then renamed, so a reader never sees half of it. An application keeping its images
elsewhere implements `ImageStore` and aliases it ([Extension points](extension-points.md)).

## What is refused

`TemplateRefused::missingImage()` for a photo or artwork the store lacks, `TemplateRefused::mockup()` for an image the
compositor refuses — not an image, a box past the photo —, and the refusals of the generation for an unknown group,
template or design or a refused plan.

Composing runs in PHP over the pixels of each zone: run it in a worker, after the generation.
