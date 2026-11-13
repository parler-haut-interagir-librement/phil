<?php

declare(strict_types=1);

namespace Phil\Citations\Model;

/**
 * The kind of a cited document: the item types of CSL 1.0.2, with their CSL-JSON names.
 */
enum ItemType: string
{
    case Article = 'article';
    case ArticleJournal = 'article-journal';
    case ArticleMagazine = 'article-magazine';
    case ArticleNewspaper = 'article-newspaper';
    case Bill = 'bill';
    case Book = 'book';
    case Broadcast = 'broadcast';
    case Chapter = 'chapter';
    case Classic = 'classic';
    case Collection = 'collection';
    case Dataset = 'dataset';
    case Document = 'document';
    case Entry = 'entry';
    case EntryDictionary = 'entry-dictionary';
    case EntryEncyclopedia = 'entry-encyclopedia';
    case Event = 'event';
    case Figure = 'figure';
    case Graphic = 'graphic';
    case Hearing = 'hearing';
    case Interview = 'interview';
    case LegalCase = 'legal_case';
    case Legislation = 'legislation';
    case Manuscript = 'manuscript';
    case Map = 'map';
    case MotionPicture = 'motion_picture';
    case MusicalScore = 'musical_score';
    case Pamphlet = 'pamphlet';
    case PaperConference = 'paper-conference';
    case Patent = 'patent';
    case Performance = 'performance';
    case Periodical = 'periodical';
    case PersonalCommunication = 'personal_communication';
    case Post = 'post';
    case PostWeblog = 'post-weblog';
    case Regulation = 'regulation';
    case Report = 'report';
    case Review = 'review';
    case ReviewBook = 'review-book';
    case Software = 'software';
    case Song = 'song';
    case Speech = 'speech';
    case Standard = 'standard';
    case Thesis = 'thesis';
    case Treaty = 'treaty';
    case Webpage = 'webpage';

    /**
     * A document published on its own, whose title a style sets in italics; an article, a chapter or an entry, whose
     * title is quoted, is not.
     */
    public function isStandalone(): bool
    {
        return \in_array($this, [self::Book, self::Classic, self::Collection, self::Dataset, self::Document, self::Graphic, self::Manuscript, self::Map, self::MotionPicture, self::MusicalScore, self::Pamphlet, self::Patent, self::Periodical, self::Report, self::Software, self::Standard, self::Thesis, self::Webpage, self::Legislation, self::Bill, self::Regulation, self::Treaty, self::Performance, self::Broadcast, self::Song, self::Figure], true);
    }
}
