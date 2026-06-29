<?php

use Degrinthorst\CmsEditor\Livewire\MediaPicker;
use Degrinthorst\CmsEditor\Models\EditorUpload;
use Degrinthorst\CmsEditor\Tests\Fixtures\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

it('lists only media in the configured collection for the model', function () {
    Storage::fake('public');

    $article = Article::create(['title' => 'A']);
    $in = $article->addMedia(UploadedFile::fake()->image('in.jpg'))->toMediaCollection('article_body');
    $out = $article->addMedia(UploadedFile::fake()->image('out.jpg'))->toMediaCollection('other_collection');

    Livewire::test(MediaPicker::class, ['model' => $article])
        ->assertSee('media-' . $in->id)
        ->assertDontSee('media-' . $out->id);
});

it('attaches directly to the model under upload_binding=model', function () {
    Storage::fake('public');
    config()->set('cms-editor.upload_binding', 'model');

    $article = Article::create(['title' => 'A']);

    Livewire::test(MediaPicker::class, ['model' => $article])
        ->set('upload', UploadedFile::fake()->image('fresh.jpg'))
        ->assertDispatched('cms-editor:media-selected');

    expect($article->fresh()->getMedia('article_body'))->toHaveCount(1);
});

it('uploads without a host model by attaching to the editor bucket', function () {
    Storage::fake('public');

    Livewire::test(MediaPicker::class, ['model' => null])
        ->set('upload', UploadedFile::fake()->image('fresh.jpg'))
        ->assertDispatched('cms-editor:media-selected');

    $media = Media::query()->where('collection_name', 'article_body')->sole();
    expect($media->model_type)->toBe(EditorUpload::class);
    expect(EditorUpload::query()->count())->toBe(1);
});

it('shows pending bucket uploads in the picker grid when creating', function () {
    Storage::fake('public');

    $component = Livewire::test(MediaPicker::class, ['model' => null])
        ->set('upload', UploadedFile::fake()->image('pending.jpg'));

    $media = Media::query()->where('collection_name', 'article_body')->sole();

    $component->assertSee('media-' . $media->id);
});
