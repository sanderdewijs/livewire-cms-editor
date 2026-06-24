import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import { MediaImage } from './extensions/MediaImage.js'

/**
 * Alpine component that owns the TipTap instance, registered as
 * `cmsEditor`. The mount element carries `wire:ignore` so Livewire's morphing
 * never touches the editor DOM (ADR-006).
 *
 * Sync direction (ADR-006):
 *   editor -> Livewire : continuous, debounced, via $wire.set()
 *   Livewire -> editor : only on explicit external set (loadDocument event)
 */
export default function cmsEditor(config = {}) {
    return {
        editor: null,
        _debounce: null,

        init() {
            this.editor = new Editor({
                element: this.$refs.editor,
                extensions: [
                    // StarterKit v3 bundles Link and Underline (both on by
                    // default); configure Link here instead of registering it
                    // separately, which would duplicate the extension.
                    StarterKit.configure({
                        link: { openOnClick: false },
                    }),
                    MediaImage,
                ],
                content: this.initialContent(),
                onUpdate: ({ editor }) => this.pushToLivewire(editor),
            })

            // Open the server-side media picker.
            this.$refs.editor.addEventListener('cms-editor:open-picker', () => {
                this.$wire.openPicker()
            })

            // Livewire -> editor: insert an image chosen in the picker.
            this.$wire.on('cms-editor:insert-image', ({ media }) => {
                this.editor.chain().focus().insertMediaImage({
                    mediaId: media.mediaId,
                    src: media.src,
                    alt: media.alt ?? '',
                    width: media.width ?? null,
                    height: media.height ?? null,
                }).run()
            })

            // Livewire -> editor: load a document externally (e.g. record load).
            // Guarded so we never echo our own pushes back into the editor.
            this.$wire.on('cms-editor:load-document', ({ doc }) => {
                this.editor.commands.setContent(doc, false)
            })
        },

        destroy() {
            // Critical: avoid leaking instances across wire:navigate (ADR-006).
            this.editor?.destroy()
            this.editor = null
        },

        initialContent() {
            const raw = this.$wire.get(config.property ?? 'content')
            return raw && Object.keys(raw).length ? raw : ''
        },

        pushToLivewire(editor) {
            clearTimeout(this._debounce)
            this._debounce = setTimeout(() => {
                this.$wire.set(config.property ?? 'content', editor.getJSON())
            }, config.debounce ?? 400)
        },

        // --- Toolbar helpers (bound from the Blade view) ---
        cmd(name, attrs = {}) {
            const chain = this.editor.chain().focus()
            const map = {
                bold: () => chain.toggleBold(),
                italic: () => chain.toggleItalic(),
                underline: () => chain.toggleUnderline(),
                strike: () => chain.toggleStrike(),
                h2: () => chain.toggleHeading({ level: 2 }),
                h3: () => chain.toggleHeading({ level: 3 }),
                paragraph: () => chain.setParagraph(),
                bulletList: () => chain.toggleBulletList(),
                orderedList: () => chain.toggleOrderedList(),
                blockquote: () => chain.toggleBlockquote(),
                link: () => this.promptLink(chain),
                image: () => { this.$wire.openPicker(); return chain },
                undo: () => chain.undo(),
                redo: () => chain.redo(),
            }
            ;(map[name]?.() ?? chain).run()
        },

        isActive(name, attrs = {}) {
            return this.editor?.isActive(name, attrs) ?? false
        },

        promptLink(chain) {
            const url = window.prompt('Link URL')
            if (url === null) return chain
            if (url === '') return chain.unsetLink()
            return chain.setLink({ href: url })
        },

        /**
         * Update the currently-selected image's presentation attrs (ADR-004).
         * Wire this to your "image properties" panel.
         */
        updateSelectedImage(attrs) {
            this.editor.chain().focus().updateMediaImage(attrs).run()
        },
    }
}
