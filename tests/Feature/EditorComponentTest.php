<?php

use Degrinthorst\CmsEditor\Livewire\Editor;
use Degrinthorst\CmsEditor\Tests\Fixtures\Article;
use Livewire\Livewire;

it('renders the editor component with its toolbar', function () {
    Livewire::test(Editor::class, ['model' => null])
        ->assertStatus(200)
        ->assertSee('cms-editor', false);
});

it('renders the image-properties panel markup', function () {
    Livewire::test(Editor::class, ['model' => null])
        ->assertSeeHtml('cms-editor__imagebar')
        ->assertSeeHtml('image.active');
});

it('opens and closes the media picker', function () {
    Livewire::test(Editor::class, ['model' => null])
        ->assertSet('showPicker', false)
        ->call('openPicker')
        ->assertSet('showPicker', true)
        ->call('closePicker')
        ->assertSet('showPicker', false);
});

it('defaults modelClass from the configured article model', function () {
    Livewire::test(Editor::class, ['model' => null])
        ->assertSet('modelClass', Article::class);
});

it('forwards a selected image to the browser as an insert-image event', function () {
    Livewire::test(Editor::class, ['model' => null])
        ->call('onMediaSelected', ['mediaId' => 1, 'src' => 'x.jpg'])
        ->assertSet('showPicker', false)
        ->assertDispatched('cms-editor:insert-image');
});
