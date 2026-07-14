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




    const variantsWrapper = document.querySelector('.variants-wrapper');

    const variantItems = () =>
        variantsWrapper ? [...variantsWrapper.querySelectorAll('.field-collection-item')] : [];

    const setVariantTitle = (item) => {
        const header = item.querySelector('.accordion-button');
        if (!header) return;

        const labelInput = item.querySelector('input[name$="[label]"]');
        const index = variantItems().indexOf(item);
        const text = labelInput?.value.trim() || ('Variant ' + (index + 1));

        let title = header.querySelector('.variant-title-js');
        if (!title) {
            [...header.childNodes].forEach((node) => {
                if (node.nodeType === Node.TEXT_NODE) node.remove();
            });
            title = document.createElement('span');
            title.classList.add('variant-title-js');
            header.appendChild(title);
        }
        title.textContent = text;
    };

    variantItems().forEach(setVariantTitle);

    document.addEventListener('click', (event) => {
        if (event.target.closest('.field-collection-add-button')) {
            setTimeout(() => {
                const items = variantItems();
                if (items.length) {
                    setVariantTitle(items[items.length - 1]);
                }
            }, 0);
            return;
        }

        const validate = event.target.closest('.variant-validate-button');
        if (validate) {
            const item = validate.closest('.field-collection-item');
            if (item) {
                setVariantTitle(item);
            }
        }
    });

});