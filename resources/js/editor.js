import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import { MediaImage } from './extensions/MediaImage.js'

/**
 * Alpine component that owns the TipTap instance, registered as `cmsEditor`.
 *
 * IMPORTANT: the TipTap Editor is kept in a CLOSURE variable, never on `this`.
 * Alpine deep-proxies its reactive data, and a Proxy-wrapped editor breaks
 * ProseMirror's object-identity checks — every command then throws
 * "Applying a mismatched transaction". Flux keeps its editor off the reactive
 * object for the same reason. Only plain UI state (image panel, a redraw tick)
 * lives on `this`.
 *
 * Sync direction (ADR-006):
 *   editor -> Livewire : continuous, debounced, via $wire.set()
 *   Livewire -> editor : only on explicit external set (loadDocument event)
 */
export default function cmsEditor(config = {}) {
    // Non-reactive instance + timer (closure-private, never proxied by Alpine).
    let editor = null
    let debounce = null

    return {
        // Image-properties panel state (ADR-004). Reactive — drives the panel.
        image: { active: false, width: '', height: '', align: 'none', style: '' },

        // Bumped on every editor change so reactive toolbar bindings
        // (`is-active`) re-evaluate against the latest editor state.
        selectionTick: 0,

        init() {
            const el = this.$refs.editor

            // Defensive: adopt an editor a previous init already mounted on this
            // (wire:ignore'd) element instead of creating a duplicate.
            if (el.__cmsEditor) {
                editor = el.__cmsEditor
                return
            }

            editor = new Editor({
                element: el,
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
                onUpdate: ({ editor: ed }) => {
                    this.pushToLivewire(ed)
                    this.selectionTick++
                },
                onSelectionUpdate: () => {
                    this.syncImagePanel()
                    this.selectionTick++
                },
            })
            el.__cmsEditor = editor

            // Open the server-side media picker.
            el.addEventListener('cms-editor:open-picker', () => {
                this.$wire.openPicker()
            })

            // Livewire -> editor: insert an image chosen in the picker.
            this.$wire.on('cms-editor:insert-image', ({ media }) => {
                if (! editor) return
                editor.chain().focus().insertMediaImage({
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
                editor?.commands.setContent(doc, false)
            })
        },

        destroy() {
            // Critical: avoid leaking instances across wire:navigate (ADR-006).
            const el = this.$refs?.editor
            if (el && el.__cmsEditor === editor) {
                delete el.__cmsEditor
            }
            editor?.destroy()
            editor = null
        },

        initialContent() {
            const raw = this.$wire.get(config.property ?? 'content')
            return raw && Object.keys(raw).length ? raw : ''
        },

        pushToLivewire(ed) {
            clearTimeout(debounce)
            debounce = setTimeout(() => {
                this.$wire.set(config.property ?? 'content', ed.getJSON())
            }, config.debounce ?? 400)
        },

        // --- Toolbar helpers (bound from the Blade view) ---
        cmd(name) {
            if (! editor) return
            const chain = editor.chain().focus()
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
            // Touch the tick so Alpine re-runs this binding after editor changes.
            void this.selectionTick
            return editor?.isActive(name, attrs) ?? false
        },

        promptLink(chain) {
            const url = window.prompt('Link URL')
            if (url === null) return chain
            if (url === '') return chain.unsetLink()
            return chain.setLink({ href: url })
        },

        // --- Image-properties panel (ADR-004) ---

        // WordPress-familiar alignment classes, mapped to the node `class` attr.
        _alignClasses: { left: 'alignleft', center: 'aligncenter', right: 'alignright' },

        /**
         * Refresh the panel from the selected node. Runs on selection change
         * only (not on attr updates), so typing in a field is never clobbered.
         */
        syncImagePanel() {
            if (! editor) return
            const active = editor.isActive('mediaImage')
            this.image.active = active
            if (! active) return

            const attrs = editor.getAttributes('mediaImage')
            this.image.width = attrs.width ?? ''
            this.image.height = attrs.height ?? ''
            this.image.style = attrs.style ?? ''
            this.image.align = this.alignFromClass(attrs.class)
        },

        alignFromClass(cls) {
            for (const [align, name] of Object.entries(this._alignClasses)) {
                if ((cls ?? '').split(/\s+/).includes(name)) return align
            }
            return 'none'
        },

        setImageSize(key, value) {
            const n = value === '' || value === null ? null : Number(value)
            this.updateSelectedImage({ [key]: Number.isFinite(n) ? n : null })
        },

        setImageAlign(align) {
            this.image.align = align
            this.updateSelectedImage({ class: this._alignClasses[align] ?? null })
        },

        setImageStyle(value) {
            this.updateSelectedImage({ style: value?.trim() ? value.trim() : null })
        },

        /**
         * Update the currently-selected image's presentation attrs (ADR-004).
         */
        updateSelectedImage(attrs) {
            editor?.chain().focus().updateMediaImage(attrs).run()
        },
    }
}
