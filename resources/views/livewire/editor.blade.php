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
        Image-properties panel (ADR-004). Shows only while a mediaImage node is
        selected; edits the node's per-placement presentation attrs (width,
        height, alignment, custom style) — intrinsic data (alt/caption) is edited
        in the picker. Lives outside wire:ignore; Alpine drives its visibility.
    --}}
    <div class="cms-editor__imagebar" role="toolbar" x-show="image.active" x-cloak>
        <span class="cms-editor__imagebar-label">Afbeelding</span>

        <label class="cms-editor__field" title="Breedte (px)">
            B
            <input type="number" min="0" inputmode="numeric"
                   x-model.number="image.width"
                   @change="setImageSize('width', image.width)" />
        </label>
        <label class="cms-editor__field" title="Hoogte (px)">
            H
            <input type="number" min="0" inputmode="numeric"
                   x-model.number="image.height"
                   @change="setImageSize('height', image.height)" />
        </label>

        <span class="cms-editor__sep" aria-hidden="true"></span>

        @foreach (['none' => 'Geen', 'left' => 'Links', 'center' => 'Midden', 'right' => 'Rechts'] as $align => $label)
            <button type="button" class="cms-editor__btn"
                    :class="{ 'is-active': image.align === @js($align) }"
                    @click="setImageAlign(@js($align))"
                    title="Uitlijnen: {{ $label }}">{{ $label }}</button>
        @endforeach

        <span class="cms-editor__sep" aria-hidden="true"></span>

        <label class="cms-editor__field cms-editor__field--grow" title="Eigen CSS (gesaniteerd bij weergave)">
            style
            <input type="text" placeholder="bijv. border-radius: 8px"
                   x-model="image.style"
                   @change="setImageStyle(image.style)" />
        </label>
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
