<?php

namespace Degrinthorst\CmsEditor\Livewire;

use Degrinthorst\CmsEditor\Support\ContentRenderer;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * The editor host component.
 *
 * Usage (binding to a parent property):
 *   <livewire:cms-editor.editor wire:model="body" :model="$article" />
 *
 * Or via the Blade wrapper:
 *   <x-cms-editor wire:model="body" :model="$article" />
 *
 * Sync model (ADR-006): the JS Alpine bridge writes the ProseMirror JSON to
 * $content (debounced) via $wire.set(). Livewire -> editor only happens on an
 * explicit external set (e.g. loadDocument()).
 */
class Editor extends Component
{
    /** ProseMirror JSON document (source of truth, ADR-003). */
    #[Modelable]
    public array $content = [];

    /** Optional persisted host model these images attach to. */
    public ?Model $model = null;

    /** Model class when no instance is supplied yet (new article). */
    public ?string $modelClass = null;

    public bool $showPicker = false;

    public function mount(?Model $model = null, ?string $modelClass = null): void
    {
        $this->model = $model;
        $this->modelClass = $modelClass
            ?? ($model ? $model::class : config('cms-editor.article_model'));
    }

    public function openPicker(): void
    {
        $this->showPicker = true;
    }

    #[\Livewire\Attributes\On('cms-editor:close-picker')]
    public function closePicker(): void
    {
        $this->showPicker = false;
    }

    /**
     * Picker selected an image. Forward intrinsic data to the browser, which
     * inserts a `mediaImage` node at the cursor.
     */
    #[\Livewire\Attributes\On('cms-editor:media-selected')]
    public function onMediaSelected(array $media): void
    {
        $this->showPicker = false;

        // The JS bridge listens for this Livewire event and runs
        // editor.commands.insertMediaImage(...).
        $this->dispatch('cms-editor:insert-image', media: $media)->self();
    }

    /**
     * Server-rendered HTML preview (ADR-003), handy for a live preview pane.
     */
    public function renderedHtml(ContentRenderer $renderer): string
    {
        return $renderer->toHtml($this->content);
    }

    public function render()
    {
        return view('cms-editor::livewire.editor', [
            'toolbar' => config('cms-editor.toolbar'),
        ]);
    }
}
