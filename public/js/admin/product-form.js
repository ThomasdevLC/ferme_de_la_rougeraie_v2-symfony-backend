document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM entièrement chargé pour Tomselected');


    const interWrapper = document.querySelector('.inter-wrapper');

    // Show the "inter" (Intervalle) field only when the selected unit is "Kilo".
    // Unité is  rendered as pills (radio buttons)
    const unitRadios = document.querySelectorAll('input[name$="[unit]"]');

    function selectedUnitLabel() {
        const checked = document.querySelector('input[name$="[unit]"]:checked');
        return checked?.closest('.form-check')?.querySelector('label')?.textContent.trim() ?? '';
    }

    function toggleInterField() {
        if (!interWrapper) return;
        interWrapper.style.display = (selectedUnitLabel() === 'Kilo') ? 'block' : 'none';
    }

    if (interWrapper && unitRadios.length) {
        unitRadios.forEach((radio) => radio.addEventListener('change', toggleInterField));
        toggleInterField();
    }


    // Hide the "stock text" field if the "has_stock" switch is not checked

    const hasStockRow = document.querySelector('.has-stock-wrapper');
    const stockRow    = document.querySelector('.stock-wrapper');

    const wrapper = document.createElement('div');
    wrapper.classList.add('stock-container');

    hasStockRow.parentNode.insertBefore(wrapper, hasStockRow);

    wrapper.appendChild(hasStockRow);
    wrapper.appendChild(stockRow);

    const checkbox = hasStockRow.querySelector('input[type="checkbox"]');

    const toggleStock = () => {
        stockRow.style.display = checkbox.checked ? '' : 'none';
    };

    toggleStock();
    checkbox.addEventListener('change', toggleStock);


    const discountRow     = document.querySelector('.discount-wrapper');
    const discountTextRow = document.querySelector('.discountText-wrapper');

    if (discountRow && discountTextRow) {
        const wrapper = document.createElement('div');
        wrapper.classList.add('discount-container');
        discountRow.parentNode.insertBefore(wrapper, discountRow);
        wrapper.appendChild(discountRow);
        wrapper.appendChild(discountTextRow);

        const checkbox = discountRow.querySelector('input[type="checkbox"]');
        const toggleDiscountText = () => {
            if (checkbox.checked) {
                discountTextRow.style.removeProperty('display');
            } else {
                discountTextRow.style.display = 'none';
            }
        };

        toggleDiscountText();
        checkbox.addEventListener('change', toggleDiscountText);
    }


    // When the "variants" switch is ON, show the variants block and hide the
    // product-level price / stock / promo (which live on each variant instead)

    const hasVariantsRow   = document.querySelector('.has-variants-wrapper');
    const variantsRow      = document.querySelector('.variants-wrapper');
    const priceRow         = document.querySelector('.price-wrapper');
    const stockContainer   = document.querySelector('.stock-container');
    const discountContainer = document.querySelector('.discount-container');

    if (hasVariantsRow && variantsRow) {
        const variantsCheckbox = hasVariantsRow.querySelector('input[type="checkbox"]');

        const toggleVariants = () => {
            const on = variantsCheckbox.checked;
            variantsRow.style.display = on ? '' : 'none';
            if (priceRow)          priceRow.style.display          = on ? 'none' : '';
            if (stockContainer)    stockContainer.style.display    = on ? 'none' : '';
            if (discountContainer) discountContainer.style.display = on ? 'none' : '';
        };

        toggleVariants();
        variantsCheckbox.addEventListener('change', toggleVariants);
    }


    // Live preview: show the freshly selected image right next to the file field

    const imageInput = document.querySelector('.image-field input[type="file"]');
    const fileUpload = imageInput?.closest('.ea-fileupload');

    if (imageInput && fileUpload) {
        const preview = document.createElement('div');
        preview.classList.add('image-live-preview');
        const previewImg = document.createElement('img');
        preview.appendChild(previewImg);

        const previewHost = document.querySelector('.identity-card .form-fieldset-body') || fileUpload.parentNode;
        previewHost.appendChild(preview);

        // Edit mode: show the already-saved image (its filename is the file label)
        const currentName = fileUpload.querySelector('.custom-file-label')?.textContent.trim() ?? '';
        if (/\.(jpe?g|png|webp|gif|avif|bmp|svg)$/i.test(currentName)) {
            previewImg.src = '/uploads/images/' + encodeURIComponent(currentName);
            preview.classList.add('is-visible');
        }

        imageInput.addEventListener('change', () => {
            const file = imageInput.files?.[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                    preview.classList.add('is-visible');
                };
                reader.readAsDataURL(file);
            } else {
                previewImg.removeAttribute('src');
                preview.classList.remove('is-visible');
            }
        });
    }




    // Collapsible collection items whose accordion header shows a live title.
    // Same treatment for the product variants and the basket composition; each
    // collection just computes its title from its own fields.
    // Each titleFor returns an ordered list of {text, className?} segments so
    // parts of the title (e.g. the unit) can be styled independently.
    const titleConfigs = [
        {
            wrapper: document.querySelector('.variants-wrapper'),
            titleFor: (item, index) => {
                const labelInput = item.querySelector('input[name$="[label]"]');
                return [{ text: labelInput?.value.trim() || ('Variant ' + (index + 1)) }];
            },
        },
        {
            wrapper: document.querySelector('.basket-items-wrapper'),
            titleFor: (item, index) => {
                const select = item.querySelector('select[name$="[product]"]');
                const chosen = select?.selectedOptions?.[0];
                const name = (chosen && chosen.value) ? chosen.text.trim() : '';
                if (!name) return [{ text: 'Produit ' + (index + 1) }];

                const segments = [{ text: name }];

                const unit = chosen.dataset.unit?.trim();
                if (unit) segments.push({ text: unit, className: 'composition-unit-js' });

                const qty = item.querySelector('input[name$="[quantity]"]')?.value.trim();
                if (qty) segments.push({ text: '× ' + qty });

                return segments;
            },
        },
    ].filter((config) => config.wrapper);

    const itemsOf = (wrapper) => [...wrapper.querySelectorAll('.field-collection-item')];

    const applyTitle = (config, item) => {
        const header = item.querySelector('.accordion-button');
        if (!header) return;

        const index = itemsOf(config.wrapper).indexOf(item);
        const segments = config.titleFor(item, index);

        let title = header.querySelector('.collection-title-js');
        if (!title) {
            [...header.childNodes].forEach((node) => {
                if (node.nodeType === Node.TEXT_NODE) node.remove();
            });
            title = document.createElement('span');
            title.classList.add('collection-title-js');
            header.appendChild(title);
        }

        title.textContent = '';
        segments.forEach((segment, i) => {
            const span = document.createElement('span');
            span.textContent = (i > 0 ? ' ' : '') + segment.text;
            if (segment.className) span.classList.add(segment.className);
            title.appendChild(span);
        });
    };

    titleConfigs.forEach((config) => {
        itemsOf(config.wrapper).forEach((item) => applyTitle(config, item));

        // Live update as the user fills the row (variant label, product, qty).
        config.wrapper.addEventListener('change', (event) => {
            const item = event.target.closest('.field-collection-item');
            if (item) applyTitle(config, item);
        });
    });

    const configForItem = (item) => titleConfigs.find((config) => config.wrapper.contains(item));

    document.addEventListener('click', (event) => {
        if (event.target.closest('.field-collection-add-button')) {
            setTimeout(() => {
                titleConfigs.forEach((config) => {
                    const items = itemsOf(config.wrapper);
                    if (items.length) applyTitle(config, items[items.length - 1]);
                });
            }, 0);
            return;
        }

        const validate = event.target.closest('.variant-validate-button');
        if (validate) {
            const item = validate.closest('.field-collection-item');
            const config = item && configForItem(item);
            if (config) applyTitle(config, item);
        }
    });


    // Basket composition: we take the row "Valider" button away from Bootstrap
    // and drive the collapse ourselves, so a row only closes once a product and
    // a positive quantity are set and the product is not already used in another
    // row. (The header title toggle stays Bootstrap's. Server + unique index
    // still enforce all of this on submit.)
    const basketWrapper = document.querySelector('.basket-items-wrapper');

    if (basketWrapper) {
        const rowState = (item) => {
            const productEl = item.querySelector('select[name$="[product]"]');
            const product = productEl?.value ?? '';
            const qtyRaw = item.querySelector('input[name$="[quantity]"]')?.value.trim() ?? '';
            const qty = qtyRaw !== '' ? Number(qtyRaw) : NaN;

            let duplicate = false;
            if (product) {
                basketWrapper.querySelectorAll('select[name$="[product]"]').forEach((sel) => {
                    if (sel !== productEl && sel.value === product) {
                        duplicate = true;
                    }
                });
            }

            return {
                product: !!product,
                quantity: qtyRaw !== '' && !Number.isNaN(qty) && qty > 0,
                duplicate,
            };
        };

        const setRowError = (item, message) => {
            let box = item.querySelector('.basket-row-error');
            if (!message) {
                box?.remove();
                return;
            }
            if (!box) {
                box = document.createElement('div');
                box.className = 'basket-row-error';
                const actions = item.querySelector('.variant-actions-row');
                actions?.parentNode.insertBefore(box, actions);
            }
            box.textContent = message;
        };

        // Strip Bootstrap's collapse toggle from the "Valider" buttons (also on
        // rows added later via "+ Ajouter"), keeping the target for our own use.
        const releaseValidateButtons = () => {
            basketWrapper
                .querySelectorAll('.variant-validate-button[data-bs-toggle="collapse"]')
                .forEach((btn) => {
                    btn.dataset.basketTarget = btn.getAttribute('data-bs-target') || '';
                    btn.removeAttribute('data-bs-toggle');
                    btn.removeAttribute('data-bs-target');
                });
        };
        releaseValidateButtons();

        const collapseRow = (item, validateBtn) => {
            const sel = validateBtn.dataset.basketTarget;
            const content = sel ? document.querySelector(sel) : item.querySelector('.accordion-collapse');
            const header = item.querySelector('.accordion-button');
            content?.classList.remove('show');
            if (header) {
                header.classList.add('collapsed');
                header.setAttribute('aria-expanded', 'false');
            }
        };

        basketWrapper.addEventListener('click', (event) => {
            const validate = event.target.closest('.variant-validate-button');
            if (!validate) return;

            const item = validate.closest('.field-collection-item');
            if (!item) return;

            const state = rowState(item);
            if (state.product && state.quantity && !state.duplicate) {
                setRowError(item, '');
                collapseRow(item, validate);
                return;
            }

            if (state.duplicate) {
                setRowError(item, 'Ce produit est déjà dans la composition.');
                return;
            }

            const missing = [];
            if (!state.product) missing.push('un produit');
            if (!state.quantity) missing.push('une quantité valide');
            setRowError(item, 'Renseignez ' + missing.join(' et ') + ' avant de valider.');
        });

        // New rows added via "+ Ajouter" ship with Bootstrap's toggle: release them too.
        document.addEventListener('click', (event) => {
            if (event.target.closest('.field-collection-add-button')) {
                setTimeout(releaseValidateButtons, 0);
            }
        });

        // Clear the message as soon as the row is edited.
        const clearOnEdit = (event) => {
            const item = event.target.closest('.field-collection-item');
            if (item) setRowError(item, '');
        };
        basketWrapper.addEventListener('change', clearOnEdit);
        basketWrapper.addEventListener('input', clearOnEdit);
    }

});