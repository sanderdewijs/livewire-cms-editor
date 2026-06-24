@php($buttons = collect($toolbar))

<div class="cms-editor" x-data="cmsEditor({ property: 'content', debounce: 400 })">

    {{-- Toolbar (lives OUTSIDE wire:ignore so it can re-render normally) --}}
    <div class="cms-editor__toolbar" role="toolbar">
        @foreach ($buttons as $button)
            @if ($button === '|')
                <span class="cms-editor__sep" aria-hidden="true"></span>
            @else
                <button
                    type="button"
                    class="cms-editor__btn"
                    :class="{ 'is-active': isActive(@js($button)) }"
                    @click="cmd(@js($button))"
                    title="{{ ucfirst($button) }}"
                >
                    {{ ucfirst($button) }}
                </button>
            @endif
        @endforeach
    </div>

    {{--
        The editor surface. wire:ignore keeps Livewire's morph away from the
        contenteditable DOM that TipTap manages (ADR-006). x-ref is how the
        Alpine component grabs the mount element.
    --}}
    <div wire:ignore>
        <div x-ref="editor" class="cms-editor__surface"></div>
    </div>

    {{-- Media picker modal (server-driven, re-renderable) --}}
    @if ($showPicker)
        <div class="cms-editor__modal">
            <div class="cms-editor__modal-backdrop" wire:click="closePicker"></div>
            <div class="cms-editor__modal-body">
                <livewire:cms-editor.media-picker
                    :model="$model"
                    :model-class="$modelClass"
                    :key="'cms-picker-'.($model?->getKey() ?? 'new')"
                />
            </div>
        </div>
    @endif
</div>
