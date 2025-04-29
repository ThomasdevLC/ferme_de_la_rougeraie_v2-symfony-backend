document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM entièrement chargé pour Tomselected');


    const tsControl = document.querySelector('.ts-control');
    const interWrapper = document.querySelector('.inter-wrapper');
    const selectElement = document.querySelector('select[name$="[unit]"]');

    // Hide the "inter" field if the "unit"  is not kilo

    function toggleInterField() {
        const text = selectElement?.options[selectElement.selectedIndex]?.text ?? '';
        interWrapper.style.display = (text === 'Kilo') ? 'block' : 'none';
    }
    if (tsControl && selectElement) {
        tsControl.addEventListener('click', () => setTimeout(toggleInterField, 100));
        selectElement.addEventListener('change', toggleInterField);
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


    // Hide the "discount text" field if the "discount" switch is not checked

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

});