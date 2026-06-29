<?php

namespace Degrinthorst\CmsEditor\Livewire;

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * WordPress-style media picker over the editor collection (ADR-009): shows the
 * shared host-owned library plus the current user's pending bucket uploads, and
 * handles uploads into that collection.
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
     * Base query for media the current viewer may see AND select (ADR-009): the
     * shared host-owned pool (every article's adopted images — the WordPress-
     * style library, visible to all users) plus the current scope's own pending,
     * not-yet-adopted bucket uploads (private until adopted). Shared by the grid
     * and by select() so a client can never select media it could not see.
     */
    protected function visibleMedia()
    {
        $hostType = $this->modelClass ? (new $this->modelClass)->getMorphClass() : null;

        $bucketModel = config('cms-editor.upload_bucket.model');
        $bucketType = $bucketModel ? (new $bucketModel)->getMorphClass() : null;
        $bucketId = $bucketModel ? $bucketModel::currentKey() : null;

        return Media::query()
            ->where('collection_name', $this->collection())
            ->where(function ($query) use ($hostType, $bucketType, $bucketId) {
                if ($hostType) {
                    $query->where('model_type', $hostType);
                }

                if ($bucketType && $bucketId) {
                    $query->orWhere(fn ($q) => $q
                        ->where('model_type', $bucketType)
                        ->where('model_id', $bucketId));
                }
            });
    }

    /**
     * The picker grid (ADR-009): the shared library plus your pending uploads.
     */
    public function getMediaProperty()
    {
        return $this->visibleMedia()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(config('cms-editor.picker_per_page', 24));
    }

    public function updatedUpload(): void
    {
        $this->validate();

        $owner = $this->resolveUploadOwner();

        $media = $owner
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
        // Re-apply the grid's visibility filter: a client can only select media
        // it could actually see — the shared library or its own pending uploads,
        // never another user's not-yet-adopted bucket image.
        $media = $this->visibleMedia()->findOrFail($mediaId);

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
     * Resolve the model uploads attach to (ADR-009). With `upload_binding=model`
     * and a supplied instance, attach directly to it; otherwise attach to the
     * ever-present per-user bucket and let AdoptsEditorMedia re-parent on save.
     * Never throws — the create flow always has somewhere to put an upload.
     */
    protected function resolveUploadOwner(): Model
    {
        if ($this->model && config('cms-editor.upload_binding', 'bucket') === 'model') {
            return $this->model;
        }

        $bucketModel = config('cms-editor.upload_bucket.model');

        return $bucketModel::current();
    }

    public function render()
    {
        return view('cms-editor::livewire.media-picker');
    }
}
