<?php

namespace Degrinthorst\CmsEditor\Livewire;

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * WordPress-style media picker, restrained to the configured article model and
 * its editor collection (ADR-005). Also handles uploads into that collection.
 */
class MediaPicker extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?Model $model = null;
    public ?string $modelClass = null;

    #[Validate('image|max:12288')] // 12MB
    public $upload;

    public string $search = '';

    public function mount(?Model $model = null, ?string $modelClass = null): void
    {
        $this->model = $model;
        $this->modelClass = $modelClass
            ?? ($model ? $model::class : config('cms-editor.article_model'));
    }

    protected function collection(): string
    {
        return config('cms-editor.collection', 'article_body');
    }

    /**
     * The picker query (ADR-005): media on the configured model + collection.
     */
    public function getMediaProperty()
    {
        return Media::query()
            ->where('model_type', $this->modelClass)
            ->where('collection_name', $this->collection())
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(config('cms-editor.picker_per_page', 24));
    }

    public function updatedUpload(): void
    {
        $this->validate();

        $model = $this->resolveBindingModel();

        $media = $model
            ->addMedia($this->upload->getRealPath())
            ->usingFileName($this->upload->getClientOriginalName())
            ->toMediaCollection($this->collection());

        $this->reset('upload');
        $this->resetPage();

        // Auto-select the freshly uploaded image.
        $this->select($media->id);
    }

    public function select(int $mediaId): void
    {
        $media = Media::query()
            ->where('collection_name', $this->collection())
            ->findOrFail($mediaId);

        $this->dispatch('cms-editor:media-selected', media: [
            'mediaId' => $media->id,
            'src' => config('cms-editor.default_conversion') && $media->hasGeneratedConversion(config('cms-editor.default_conversion'))
                ? $media->getUrl(config('cms-editor.default_conversion'))
                : $media->getUrl(),
            'alt' => $media->getCustomProperty('alt', ''),
            'width' => $media->getCustomProperty('width'),
            'height' => $media->getCustomProperty('height'),
        ]);
    }

    /**
     * Resolve the model to attach uploads to, honouring upload_binding (ADR-005).
     */
    protected function resolveBindingModel(): Model
    {
        if ($this->model) {
            return $this->model;
        }

        return match (config('cms-editor.upload_binding', 'draft')) {
            // Host is expected to have supplied a (draft) model instance.
            'draft', 'model' => throw new \RuntimeException(
                'cms-editor: no model instance supplied to the picker. Pass :model to the editor, '
                . 'or set upload_binding=temporary.'
            ),
            // Attach to a fresh, unsaved-but-persisted record of the model.
            'temporary' => tap(new ($this->modelClass), fn ($m) => $m->save()),
        };
    }

    public function render()
    {
        return view('cms-editor::livewire.media-picker');
    }
}
