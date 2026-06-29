<?php

use Degrinthorst\CmsEditor\Models\EditorUpload;
use Degrinthorst\CmsEditor\Support\ContentRenderer;
use Degrinthorst\CmsEditor\Tests\Fixtures\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @return array{0: \Spatie\MediaLibrary\MediaCollections\Models\Media, 1: array<string, mixed>}
 */
function bucketMediaWithDocument(): array
{
    $bucket = EditorUpload::current();
    $media = $bucket->addMedia(UploadedFile::fake()->image('pending.jpg'))
        ->toMediaCollection('article_body');

    $document = [
        'type' => 'doc',
        'content' => [[
            'type' => 'mediaImage',
            'attrs' => ['mediaId' => $media->id, 'src' => $media->getUrl()],
        ]],
    ];

    return [$media, $document];
}

it('adopts referenced bucket media onto the host on save, keeping the id', function () {
    Storage::fake('public');

    [$media, $document] = bucketMediaWithDocument();
    $originalId = $media->id;

    $article = Article::create(['title' => 'A', 'body' => $document]);

    $media->refresh();
    expect($media->id)->toBe($originalId);            // never re-id'd (no Media::move)
    expect($media->model_type)->toBe(Article::class);
    expect($media->model_id)->toBe($article->id);
});

it('does not steal media already owned by another article', function () {
    Storage::fake('public');

    [$media, $document] = bucketMediaWithDocument();

    $owner = Article::create(['title' => 'Owner', 'body' => $document]); // adopts it
    $media->refresh();
    expect($media->model_id)->toBe($owner->id);

    // A second article referencing the same id must not take it over.
    $other = Article::create(['title' => 'Other', 'body' => $document]);
    $media->refresh();
    expect($media->model_id)->toBe($owner->id);
    expect($media->model_type)->toBe(Article::class);
});

it('leaves un-referenced bucket media on the bucket', function () {
    Storage::fake('public');

    $bucket = EditorUpload::current();
    $unused = $bucket->addMedia(UploadedFile::fake()->image('unused.jpg'))
        ->toMediaCollection('article_body');

    Article::create(['title' => 'Empty', 'body' => ['type' => 'doc', 'content' => []]]);

    $unused->refresh();
    expect($unused->model_type)->toBe(EditorUpload::class);
});

it('still renders the adopted media by its unchanged id', function () {
    Storage::fake('public');

    [$media, $document] = bucketMediaWithDocument();
    $article = Article::create(['title' => 'A', 'body' => $document]);

    $html = app(ContentRenderer::class)->toHtml($article->fresh()->body);

    expect($html)->toContain($media->fresh()->getUrl());
});
