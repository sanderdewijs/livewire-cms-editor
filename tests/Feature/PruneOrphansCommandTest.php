<?php

use Degrinthorst\CmsEditor\Models\EditorUpload;
use Degrinthorst\CmsEditor\Tests\Fixtures\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

function uploadToBucket(string $name = 'img.jpg'): Media
{
    return EditorUpload::current()
        ->addMedia(UploadedFile::fake()->image($name))
        ->toMediaCollection('article_body');
}

it('deletes un-referenced bucket media past the ttl window and removes the file', function () {
    Storage::fake('public');

    $orphan = uploadToBucket();
    $orphan->forceFill(['created_at' => now()->subDays(10)])->save();
    $path = $orphan->getPath();
    expect(file_exists($path))->toBeTrue();

    $this->artisan('cms-editor:prune-orphans --ttl=7 --force')->assertSuccessful();

    expect(Media::query()->whereKey($orphan->id)->exists())->toBeFalse();
    expect(file_exists($path))->toBeFalse();
});

it('keeps media referenced by a stored document', function () {
    Storage::fake('public');
    config()->set('cms-editor.prune.bucket_ttl_days', 0);

    $media = uploadToBucket();
    Article::create(['title' => 'A', 'body' => [
        'type' => 'doc',
        'content' => [['type' => 'mediaImage', 'attrs' => ['mediaId' => $media->id]]],
    ]]); // adopts it onto the article

    $this->artisan('cms-editor:prune-orphans --force')->assertSuccessful();

    expect(Media::query()->whereKey($media->id)->exists())->toBeTrue();
});

it('deletes nothing on a dry run', function () {
    Storage::fake('public');

    $orphan = uploadToBucket();
    $orphan->forceFill(['created_at' => now()->subDays(10)])->save();

    $this->artisan('cms-editor:prune-orphans --ttl=7 --dry-run')->assertSuccessful();

    expect(Media::query()->whereKey($orphan->id)->exists())->toBeTrue();
});

it('spares fresh bucket uploads until they pass the ttl window', function () {
    Storage::fake('public');

    // No ttl configured and the upload is brand-new => left alone.
    $fresh = uploadToBucket();

    $this->artisan('cms-editor:prune-orphans --force')->assertSuccessful();

    expect(Media::query()->whereKey($fresh->id)->exists())->toBeTrue();
});
