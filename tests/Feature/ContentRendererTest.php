<?php

use Degrinthorst\CmsEditor\Support\ContentRenderer;
use Degrinthorst\CmsEditor\Tests\Fixtures\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function doc(array $content): array
{
    return ['type' => 'doc', 'content' => $content];
}

it('renders prosemirror json paragraphs to html', function () {
    $html = app(ContentRenderer::class)->toHtml(doc([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello world']]],
    ]));

    expect($html)->toContain('<p>Hello world</p>');
});

it('renders underline marks (not part of starter kit)', function () {
    $html = app(ContentRenderer::class)->toHtml(doc([
        ['type' => 'paragraph', 'content' => [[
            'type' => 'text',
            'marks' => [['type' => 'underline']],
            'text' => 'Underlined',
        ]]],
    ]));

    expect($html)->toContain('<u>Underlined</u>');
});

it('returns an empty string for an empty document', function () {
    expect(app(ContentRenderer::class)->toHtml([]))->toBe('');
});

it('accepts a json string as well as an array', function () {
    $json = json_encode(doc([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'From string']]],
    ]));

    expect(app(ContentRenderer::class)->toHtml($json))->toContain('From string');
});

it('re-resolves a media image src from its mediaId at render time', function () {
    Storage::fake('public');

    $article = Article::create(['title' => 'Test']);
    $media = $article
        ->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection('article_body');

    // Note the stored src is intentionally stale; the renderer should refresh it.
    $html = app(ContentRenderer::class)->toHtml(doc([
        ['type' => 'mediaImage', 'attrs' => [
            'mediaId' => $media->id,
            'src' => 'https://stale.example/old.jpg',
            'alt' => '',
            'width' => 300,
        ]],
    ]));

    expect($html)
        ->toContain('data-media-id="' . $media->id . '"')
        ->toContain($media->getUrl())
        ->not->toContain('stale.example');
});

it('renders per-placement presentation attrs and sanitizes the style', function () {
    Storage::fake('public');

    $article = Article::create(['title' => 'Test']);
    $media = $article
        ->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection('article_body');

    // The image-properties panel writes class/style/width as node attrs.
    $html = app(ContentRenderer::class)->toHtml(doc([
        ['type' => 'mediaImage', 'attrs' => [
            'mediaId' => $media->id,
            'class' => 'aligncenter',
            'style' => 'border-radius: 8px; position: absolute',
            'width' => 240,
        ]],
    ]));

    expect($html)
        ->toContain('class="aligncenter"')
        ->toContain('width="240"')
        ->toContain('border-radius: 8px')   // allowlisted style kept
        ->not->toContain('position');        // disallowed style stripped
});

it('blanks the src when the backing media record is gone', function () {
    $html = app(ContentRenderer::class)->toHtml(doc([
        ['type' => 'mediaImage', 'attrs' => [
            'mediaId' => 99999,
            'src' => 'https://example/should-be-dropped.jpg',
            'alt' => '',
        ]],
    ]));

    expect($html)->not->toContain('should-be-dropped');
});
