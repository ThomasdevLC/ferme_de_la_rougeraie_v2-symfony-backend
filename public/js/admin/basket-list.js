// Baskets list: only one basket can be displayed at a time.
// The backend already hides the other baskets on save; here we mirror that
// visually so the other switches flip off instantly, without a page reload.
document.addEventListener('DOMContentLoaded', () => {
    const displaySwitches = document.querySelectorAll(
        'table tbody td .form-switch input[type="checkbox"]'
    );

    if (displaySwitches.length < 2) {
        return;
    }

    displaySwitches.forEach((input) => {
        input.addEventListener('change', () => {
            if (!input.checked) {
                return;
            }

            displaySwitches.forEach((other) => {
                if (other !== input) {
                    other.checked = false;
                }
            });
        });
    });
});
