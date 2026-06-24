@props([
    'model' => null,
    'modelClass' => null,
])

{{-- Convenience wrapper: <x-cms-editor wire:model="body" :model="$article" /> --}}
<livewire:cms-editor.editor
    :model="$model"
    :model-class="$modelClass"
    {{ $attributes }}
/>
