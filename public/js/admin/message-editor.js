(() => {
    const emojiCategories = [
        {
            label: 'Sourires',
            emojis: [
                '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '🙂', '🙃', '😉', '😊',
                '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '😋', '😛', '😜', '🤪',
                '😝', '🤑', '🤗', '🤭', '🫢', '🫣', '🤫', '🤔', '🫡', '🤐', '🤨', '😐',
                '😑', '😶', '🫥', '😏', '😒', '🙄', '😬', '😮‍💨', '🤥', '😌', '😔', '😪',
                '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '🥴', '😵',
                '🤯', '🤠', '🥳', '🥸', '😎', '🤓', '🧐', '😕', '🫤', '😟', '🙁', '☹️',
                '😮', '😯', '😲', '😳', '🥺', '🥹', '😦', '😧', '😨', '😰', '😥', '😢',
                '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠'
            ],
        },
        {
            label: 'Gestes',
            emojis: [
                '👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🫰', '🤟',
                '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '🫵', '👍', '👎', '✊',
                '👊', '🤛', '🤜', '👏', '🙌', '🫶', '👐', '🤲', '🤝', '🙏', '✍️', '💪'
            ],
        },
        {
            label: 'Ferme',
            emojis: [
                '🥕', '🥔', '🍅', '🥒', '🥬', '🥦', '🧄', '🧅', '🌽', '🌶️', '🫑', '🥑',
                '🍆', '🍓', '🍒', '🍎', '🍏', '🍐', '🍊', '🍋', '🍇', '🍉', '🍈', '🍑',
                '🥭', '🍍', '🥝', '🍌', '🌰', '🥜', '🍯', '🥛', '🧀', '🥚', '🍞', '🥖',
                '🥐', '🥗', '🍲', '🥣', '🧺', '🚜', '🌾', '🌱', '🌿', '☘️', '🍀', '🍃',
                '🍂', '🍁', '🌻', '🌼', '🌸', '🌺', '🌷', '🪴'
            ],
        },
        {
            label: 'Infos',
            emojis: [
                '✅', '❌', '⚠️', '🚨', '📣', '🔔', '📌', '📍', '🛒', '📦', '🎁', '🏷️',
                '💬', '📝', '📄', '📋', '📅', '🗓️', '⏰', '⌚', '🕘', '🔒', '🔓', '🔑',
                '💳', '💶', '📞', '📧', '📲', '💡', '⭐', '✨', '💥', '🔥', '❤️', '💚'
            ],
        },
        {
            label: 'Météo',
            emojis: [
                '☀️', '🌤️', '⛅', '🌥️', '☁️', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️', '❄️', '☃️',
                '⛄', '🌬️', '💨', '🌪️', '🌈', '☔', '💧', '💦', '🌊', '🌙', '⭐', '🌟'
            ],
        },
        {
            label: 'Transport',
            emojis: [
                '🚗', '🚙', '🚚', '🚛', '🚜', '🛻', '🚲', '🛵', '🏍️', '🚍', '🚆', '🚉',
                '🛤️', '⛽', '🚦', '🚧', '🛑', '🅿️'
            ],
        },
        {
            label: 'Symboles',
            emojis: [
                '➡️', '⬅️', '⬆️', '⬇️', '↗️', '↘️', '↙️', '↖️', '🔁', '🔄', '➕', '➖',
                '➗', '✖️', '💯', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '⚫', '⚪', '🟤',
                '🟥', '🟧', '🟨', '🟩', '🟦', '🟪', '⬛', '⬜'
            ],
        },
    ];

    const allEmojis = emojiCategories.flatMap((category) =>
        category.emojis.map((emoji) => ({ emoji, category: category.label.toLowerCase() }))
    );

    function normalizeStoredContent(content) {
        return content
            .replace(/&nbsp;/g, ' ')
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/p>\s*<p>/gi, '\n')
            .replace(/<\/div>\s*<div>/gi, '\n')
            .replace(/<\/?div>/gi, '')
            .replace(/<\/?p>/gi, '')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function insertAtCursor(textarea, value) {
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;

        textarea.value = `${textarea.value.slice(0, start)}${value}${textarea.value.slice(end)}`;
        textarea.selectionStart = start + value.length;
        textarea.selectionEnd = start + value.length;
        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function createButton(label, title, onClick) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'message-editor-button';
        button.textContent = label;
        button.title = title;
        button.setAttribute('aria-label', title);
        button.addEventListener('click', onClick);

        return button;
    }

    function initMessageEditor(textarea) {
        if (textarea.dataset.messageEditorReady === '1') {
            return;
        }
        textarea.dataset.messageEditorReady = '1';
        textarea.value = normalizeStoredContent(textarea.value || '');

        const wrapper = document.createElement('div');
        wrapper.className = 'message-editor';

        const toolbar = document.createElement('div');
        toolbar.className = 'message-editor-toolbar';

        const emojiPanel = createEmojiPanel(textarea);

        toolbar.append(
            createButton('↵', 'Nouvelle ligne', () => {
                insertAtCursor(textarea, '\n');
            }),
            createButton('⌨', 'Emojis', () => {
                emojiPanel.hidden = !emojiPanel.hidden;
            })
        );

        const form = textarea.closest('form');
        form?.addEventListener('submit', () => {
            textarea.value = normalizeStoredContent(textarea.value || '');
        });

        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.append(toolbar, textarea, emojiPanel);
    }

    function init() {
        document
            .querySelectorAll('textarea.message-editor-input')
            .forEach(initMessageEditor);
    }

    function createEmojiPanel(textarea) {
        const panel = document.createElement('div');
        panel.className = 'message-editor-emoji-panel';
        panel.hidden = true;

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'message-editor-emoji-search';
        search.placeholder = 'Rechercher: ferme, info, météo...';
        search.setAttribute('aria-label', 'Rechercher un emoji');

        const grid = document.createElement('div');
        grid.className = 'message-editor-emoji-grid';

        const insertEmoji = (emoji) => {
            insertAtCursor(textarea, emoji);
        };

        const render = (query = '') => {
            grid.innerHTML = '';
            const normalizedQuery = query.trim().toLowerCase();

            if (normalizedQuery) {
                allEmojis
                    .filter(({ category }) => category.includes(normalizedQuery))
                    .forEach(({ emoji }) => {
                        grid.appendChild(createButton(emoji, `Insérer ${emoji}`, () => insertEmoji(emoji)));
                    });
                return;
            }

            emojiCategories.forEach((category) => {
                const title = document.createElement('div');
                title.className = 'message-editor-emoji-category';
                title.textContent = category.label;
                grid.appendChild(title);

                category.emojis.forEach((emoji) => {
                    grid.appendChild(createButton(emoji, `Insérer ${emoji}`, () => insertEmoji(emoji)));
                });
            });
        };

        search.addEventListener('input', () => render(search.value));
        panel.append(search, grid);
        render();

        return panel;
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
})();
