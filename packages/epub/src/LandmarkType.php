<?php

declare(strict_types=1);

namespace Phil\Epub;

/**
 * The structural role of a chapter, from the EPUB Structural Semantics Vocabulary: written as the `epub:type` of its
 * body and listed in the landmarks of the navigation document.
 */
enum LandmarkType: string
{
    case Cover = 'cover';
    case TitlePage = 'titlepage';
    case FrontMatter = 'frontmatter';
    case BodyMatter = 'bodymatter';
    case BackMatter = 'backmatter';
    case Toc = 'toc';
    case Preface = 'preface';
    case Foreword = 'foreword';
    case Introduction = 'introduction';
    case Prologue = 'prologue';
    case Epilogue = 'epilogue';
    case Afterword = 'afterword';
    case Conclusion = 'conclusion';
    case Appendix = 'appendix';
    case Glossary = 'glossary';
    case Bibliography = 'bibliography';
    case Index = 'index';
    case Colophon = 'colophon';
    case Acknowledgments = 'acknowledgments';
    case Dedication = 'dedication';
    case Epigraph = 'epigraph';
    case CopyrightPage = 'copyright-page';
    case Endnotes = 'endnotes';
    case ListOfIllustrations = 'loi';
    case ListOfTables = 'lot';
}
