

function attachInventoryAdjustmentListeners(row) {
    const productInput = row.querySelector('input[name*="[naimenovanie_tovara]"]');
    const productHidden = row.querySelector('input[name*="[id_tovara]"]');
    const seriaInput = row.querySelector('input[name*="[naimenovanie_serii]"]');
    const seriaHidden = row.querySelector('input[name*="[id_serii]"]');
    const ostatuokInput = row.querySelector('input[name*="[ostatok]"]');
    const ubavitInput = row.querySelector('input[name*="[ubavit]"]');
    const pribavitInput = row.querySelector('input[name*="[pribavit]"]');

    if (!ostatuokInput) return;

    /**
     * Fetch ostatok from database based on product and series
     */
    const updateOstatokField = async () => {
        if (!productHidden || !productHidden.value) {
            ostatuokInput.value = '';
            return;
        }

        try {
            let url = `/api/get_product_info.php?id_tovara=${encodeURIComponent(productHidden.value)}`;
            
            // If series is selected, add it to the query
            if (seriaHidden && seriaHidden.value) {
                url += `&id_serii=${encodeURIComponent(seriaHidden.value)}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data && data.ostatok !== undefined) {
                ostatuokInput.value = data.ostatok;
            } else {
                ostatuokInput.value = '';
            }
        } catch (error) {
            console.error('Error fetching ostatok:', error);
            ostatuokInput.value = '';
        }
    };

    /**
     * Validate ubavit/pribavit don't exceed ostatok
     */
    const validateAdjustments = () => {
        const ostatok = parseFloat(ostatuokInput.value) || 0;
        const ubavit = parseFloat(ubavitInput.value) || 0;
        const pribavit = parseFloat(pribavitInput.value) || 0;

        if (ubavit > ostatok) {
            ubavitInput.classList.add('is-invalid');
            ubavitInput.title = `Нельзя убавить больше, чем остаток (${ostatok})`;
        } else {
            ubavitInput.classList.remove('is-invalid');
            ubavitInput.title = '';
        }
    };

    // Update ostatok when product changes
    if (productInput && productHidden) {
        const originalChange = productInput.onchange;
        productInput.addEventListener('change', async () => {
            await updateOstatokField();
            validateAdjustments();
        });
    }

    // Update ostatok when series changes
    if (seriaInput && seriaHidden) {
        const originalChange = seriaInput.onchange;
        seriaInput.addEventListener('change', async () => {
            await updateOstatokField();
            validateAdjustments();
        });
    }

    // Validate when ubavit changes
    if (ubavitInput) {
        ubavitInput.addEventListener('change', validateAdjustments);
        ubavitInput.addEventListener('input', validateAdjustments);
    }

    // Validate when pribavit changes (just for UI feedback)
    if (pribavitInput) {
        pribavitInput.addEventListener('change', () => {
            const pribavit = parseFloat(pribavitInput.value) || 0;
            if (pribavit < 0) {
                pribavitInput.classList.add('is-invalid');
            } else {
                pribavitInput.classList.remove('is-invalid');
            }
        });
    }
}

/**
 * Initialize inventory adjustment listeners for all rows
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialize for existing rows
    document.querySelectorAll('.tovar-row').forEach(row => {
        attachInventoryAdjustmentListeners(row);
    });
});

/**
 * Handle adding new rows
 * Override the addRow function to include inventory adjustment listeners
 */
const originalAddRow = window.addRow;
window.addRow = function() {
    if (originalAddRow) {
        originalAddRow();
    }
    
    const tbody = document.getElementById('tovaryBody');
    const lastRow = tbody.lastElementChild;
    if (lastRow) {
        attachInventoryAdjustmentListeners(lastRow);
    }
};

/**
 * Form submission validation
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('documentForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const rows = document.querySelectorAll('.tovar-row');
            let hasError = false;

            rows.forEach((row, index) => {
                const productInput = row.querySelector('input[name*="[naimenovanie_tovara]"]');
                const ubavitInput = row.querySelector('input[name*="[ubavit]"]');
                const pribavitInput = row.querySelector('input[name*="[pribavit]"]');
                const ostatuokInput = row.querySelector('input[name*="[ostatok]"]');

                // Check if row has data
                const hasData = (productInput && productInput.value.trim()) ||
                               (ubavitInput && ubavitInput.value) ||
                               (pribavitInput && pribavitInput.value);

                if (hasData) {
                    // Validate ubavit doesn't exceed ostatok
                    const ostatok = parseFloat(ostatuokInput.value) || 0;
                    const ubavit = parseFloat(ubavitInput.value) || 0;

                    if (ubavit > ostatok) {
                        alert(`Строка ${index + 1}: Нельзя убавить больше, чем остаток (${ostatok})`);
                        hasError = true;
                        e.preventDefault();
                    }
                }
            });

            if (hasError) {
                e.preventDefault();
            }
        });
    }
});
