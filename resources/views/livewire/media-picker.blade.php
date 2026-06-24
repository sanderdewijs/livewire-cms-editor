<div class="cms-picker">

    <header class="cms-picker__header">
        <h2 class="cms-picker__title">Media</h2>
        <input
            type="search"
            class="cms-picker__search"
            placeholder="Zoeken…"
            wire:model.live.debounce.300ms="search"
        />
        <button type="button" class="cms-picker__close" wire:click="$dispatch('cms-editor:close-picker')">
            &times;
        </button>
    </header>

    {{-- Upload (into the configured collection — ADR-005) --}}
    <label class="cms-picker__upload">
        <input type="file" accept="image/*" wire:model="upload" hidden />
        <span>Afbeelding uploaden</span>
        <div wire:loading wire:target="upload" class="cms-picker__uploading">Uploaden…</div>
    </label>
    @error('upload') <p class="cms-picker__error">{{ $message }}</p> @enderror

    {{-- Grid: only media on the configured model + collection --}}
    <div class="cms-picker__grid">
        @forelse ($this->media as $item)
            <button
                type="button"
                class="cms-picker__item"
                wire:key="media-{{ $item->id }}"
                wire:click="select({{ $item->id }})"
            >
                <img src="{{ $item->getUrl() }}" alt="{{ $item->getCustomProperty('alt', $item->name) }}" loading="lazy" />
            </button>
        @empty
            <p class="cms-picker__empty">Nog geen afbeeldingen in dit artikel.</p>
        @endforelse
    </div>

    <footer class="cms-picker__footer">
        {{ $this->media->links() }}
    </footer>
</div>
