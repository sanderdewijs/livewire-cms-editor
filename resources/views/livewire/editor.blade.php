@php
    $buttons = collect($toolbar);

    $labels = [
        'bold' => __('Vet'),
        'italic' => __('Cursief'),
        'underline' => __('Onderstrepen'),
        'strike' => __('Doorhalen'),
        'h2' => __('Kop 2'),
        'h3' => __('Kop 3'),
        'paragraph' => __('Paragraaf'),
        'bulletList' => __('Opsomming'),
        'orderedList' => __('Genummerde lijst'),
        'blockquote' => __('Citaat'),
        'link' => __('Link'),
        'image' => __('Afbeelding'),
        'undo' => __('Ongedaan maken'),
        'redo' => __('Opnieuw'),
    ];

    // Inline Lucide-style icons (currentColor) — no icon-font dependency, and
    // they inherit the toolbar text colour so dark mode just works.
    $svg = fn (string $inner) => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$inner.'</svg>';

    $icons = [
        'bold' => $svg('<path d="M14 12a4 4 0 0 0 0-8H6v8"/><path d="M15 20a4 4 0 0 0 0-8H6v8Z"/>'),
        'italic' => $svg('<line x1="19" x2="10" y1="4" y2="4"/><line x1="14" x2="5" y1="20" y2="20"/><line x1="15" x2="9" y1="4" y2="20"/>'),
        'underline' => $svg('<path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" x2="20" y1="20" y2="20"/>'),
        'strike' => $svg('<path d="M16 4H9a3 3 0 0 0-2.83 4"/><path d="M14 12a4 4 0 0 1 0 8H6"/><line x1="4" x2="20" y1="12" y2="12"/>'),
        'h2' => $svg('<path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M21 18h-4c0-4 4-3 4-6 0-1.5-2-2.5-4-1"/>'),
        'h3' => $svg('<path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17.5 10.5c1.7-1 3.5 0 3.5 1.5a2 2 0 0 1-2 2"/><path d="M17 17.5c2 1.5 4 .3 4-1.5a2 2 0 0 0-2-2"/>'),
        'paragraph' => $svg('<path d="M13 4v16"/><path d="M17 4v16"/><path d="M19 4H9.5a4.5 4.5 0 0 0 0 9H13"/>'),
        'bulletList' => $svg('<line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/>'),
        'orderedList' => $svg('<line x1="10" x2="21" y1="6" y2="6"/><line x1="10" x2="21" y1="12" y2="12"/><line x1="10" x2="21" y1="18" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>'),
        'blockquote' => $svg('<path d="M17 6H3"/><path d="M21 12H8"/><path d="M21 18H8"/><path d="M3 12v6"/>'),
        'link' => $svg('<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>'),
        'image' => $svg('<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>'),
        'undo' => $svg('<path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/>'),
        'redo' => $svg('<path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/>'),
    ];
@endphp

<div class="cms-editor" x-data="cmsEditor({ property: 'content', debounce: 400 })">

    {{--
        The whole editor chrome (toolbar, image panel, surface) is Alpine-driven
        and lives under one wire:ignore (ADR-006). Livewire never morphs it, so
        toggling the picker modal below can't tear down / duplicate the TipTap
        instance (which would throw "mismatched transaction" RangeErrors).
    --}}
    <div wire:ignore>

        {{-- Toolbar --}}
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
                        title="{{ $labels[$button] ?? ucfirst($button) }}"
                        aria-label="{{ $labels[$button] ?? ucfirst($button) }}"
                    >
                        {!! $icons[$button] ?? e($labels[$button] ?? ucfirst($button)) !!}
                    </button>
                @endif
            @endforeach
        </div>

        {{--
            Image-properties panel (ADR-004). Shows only while a mediaImage node is
            selected; edits the node's per-placement presentation attrs (width,
            height, alignment, custom style) — intrinsic data (alt/caption) is edited
            in the picker. Alpine drives its visibility.
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

        {{-- The editor surface. x-ref is how the Alpine component grabs the mount element. --}}
        <div x-ref="editor" class="cms-editor__surface"></div>
    </div>

    {{-- Media picker modal (server-driven, Livewire-rendered — kept OUTSIDE wire:ignore) --}}
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
