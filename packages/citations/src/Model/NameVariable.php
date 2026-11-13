<?php

declare(strict_types=1);

namespace Phil\Citations\Model;

/**
 * A role a person or an organisation holds for a document: the name variables of CSL 1.0.2.
 */
enum NameVariable: string
{
    case Author = 'author';
    case Chair = 'chair';
    case CollectionEditor = 'collection-editor';
    case Compiler = 'compiler';
    case Composer = 'composer';
    case ContainerAuthor = 'container-author';
    case Contributor = 'contributor';
    case Curator = 'curator';
    case Director = 'director';
    case Editor = 'editor';
    case EditorialDirector = 'editorial-director';
    case ExecutiveProducer = 'executive-producer';
    case Guest = 'guest';
    case Host = 'host';
    case Illustrator = 'illustrator';
    case Interviewer = 'interviewer';
    case Narrator = 'narrator';
    case Organizer = 'organizer';
    case OriginalAuthor = 'original-author';
    case Performer = 'performer';
    case Producer = 'producer';
    case Recipient = 'recipient';
    case ReviewedAuthor = 'reviewed-author';
    case ScriptWriter = 'script-writer';
    case SeriesCreator = 'series-creator';
    case Translator = 'translator';
}
