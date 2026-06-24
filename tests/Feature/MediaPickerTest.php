<?php

use Degrinthorst\CmsEditor\Livewire\MediaPicker;
use Degrinthorst\CmsEditor\Tests\Fixtures\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('lists only media in the configured collection for the model', function () {
    Storage::fake('public');

    $article = Article::create(['title' => 'A']);
    $in = $article->addMedia(UploadedFile::fake()->image('in.jpg'))->toMediaCollection('article_body');
    $out = $article->addMedia(UploadedFile::fake()->image('out.jpg'))->toMediaCollection('other_collection');

    Livewire::test(MediaPicker::class, ['model' => $article])
        ->assertSee('media-' . $in->id)
        ->assertDontSee('media-' . $out->id);
});

it('uploads an image into the collection and dispatches a selection', function () {
    Storage::fake('public');

    $article = Article::create(['title' => 'A']);

    Livewire::test(MediaPicker::class, ['model' => $article])
        ->set('upload', UploadedFile::fake()->image('fresh.jpg'))
        ->assertDispatched('cms-editor:media-selected');

    expect($article->fresh()->getMedia('article_body'))->toHaveCount(1);
});

it('rejects a model-less picker upload under the default draft binding', function () {
    Storage::fake('public');

    Livewire::test(MediaPicker::class, ['model' => null])
        ->set('upload', UploadedFile::fake()->image('orphan.jpg'));
})->throws(RuntimeException::class);
