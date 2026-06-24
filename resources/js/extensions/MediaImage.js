import { Node, mergeAttributes } from '@tiptap/core'

/**
 * Custom TipTap node for MediaLibrary-backed images (ADR-001 / ADR-004).
 *
 * `mediaId` is the source of truth (server re-resolves src from it on render).
 * width/height/class/style are per-insertion presentation overrides that live
 * in the document, NOT on the Media record.
 *
 * Keep this in sync with src/Extensions/MediaImage.php (Onderhoudsrisico #3).
 */
export const MediaImage = Node.create({
    name: 'mediaImage',

    group: 'block',
    inline: false,
    draggable: true,
    selectable: true,
    atom: true,

    addAttributes() {
        return {
            mediaId: { default: null, parseHTML: (el) => el.getAttribute('data-media-id') },
            src: { default: null },
            alt: { default: '' },
            width: { default: null },
            height: { default: null },
            class: { default: null },
            style: { default: null },
        }
    },

    parseHTML() {
        return [{ tag: 'img[data-media-id]' }]
    },

    renderHTML({ HTMLAttributes }) {
        const { mediaId, ...rest } = HTMLAttributes
        return ['img', mergeAttributes(rest, {
            'data-media-id': mediaId,
            loading: 'lazy',
        })]
    },

    addCommands() {
        return {
            insertMediaImage: (attrs) => ({ commands }) =>
                commands.insertContent({ type: this.name, attrs }),

            updateMediaImage: (attrs) => ({ commands }) =>
                commands.updateAttributes(this.name, attrs),
        }
    },
})
