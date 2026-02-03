function addRow() {
    const table = document.getElementById('productsBody');
    const rowCount = table.rows.length;
    const newRow = table.insertRow();
    
    newRow.className = 'product-row';
    newRow.innerHTML = `
        <td>${rowCount + 1}</td>
        <td>
            <div class="search-container" style="position: relative;">
                <input class="form-control" type="text" name="products[${rowCount}][product_name]" placeholder="Введите товар..." autocomplete="off">
                <input type="hidden" name="products[${rowCount}][product_id]" class="product-id">
            </div>
        </td>
        <td>
            <div class="search-container" style="position: relative;">
                <input class="form-control" type="text" name="products[${rowCount}][seria_name]" placeholder="Введите серию..." autocomplete="off">
                <input type="hidden" name="products[${rowCount}][seria_id]" class="seria-id">
            </div>
        </td>
        <td><input class="form-control" type="text" name="products[${rowCount}][price]" placeholder="0" autocomplete="off"></td>
        <td><input class="form-control" type="text" name="products[${rowCount}][quantity]" placeholder="0" autocomplete="off"></td>
        <td><input class="form-control" type="text" name="products[${rowCount}][summa]" placeholder="0" autocomplete="off"></td>
        <td>
            <div class="search-container" style="position: relative;">
                <input class="form-control" type="text" name="products[${rowCount}][unit_name]" placeholder="Введите ед." autocomplete="off">
                <input type="hidden" name="products[${rowCount}][unit_id]" class="unit-id">
            </div>
        </td>
        <td>
            <select class="form-control" name="products[${rowCount}][nds_id]">
                <option value="">--</option>
                ${ndsOptionsTemplate ? ndsOptionsTemplate.split('<option').slice(1).map(opt => '<option' + opt).join('') : ''}
            </select>
        </td>
        <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
    `;
}

function deleteRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    
    // Renumber rows
    const table = document.getElementById('productsBody');
    Array.from(table.rows).forEach((row, index) => {
        row.cells[0].textContent = index + 1;
    });
}

// Autocomplete for form fields
const autocompleteFields = [
    { inputId: 'warehouse_id', table: 'sklady', col: 'naimenovanie' },
    { inputId: 'vendor_id', table: 'postavshchiki', col: 'naimenovanie' },
    { inputId: 'organization_id', table: 'organizacii', col: 'naimenovanie' },
    { inputId: 'responsible_id', table: 'users', col: 'user_name', idCol: 'user_id' }
];

// Table manipulation functions
function createRowTemplate(rowIndex) {
    return `
        <td>${rowIndex + 1}</td>
        <td>
            <div class="search-container" style="position: relative;">
                <input class="form-control" type="text" name="products[${rowIndex}][product_name]" placeholder="Введите товар..." autocomplete="off">
                <input type="hidden" name="products[${rowIndex}][product_id]" class="product-id">
            </div>
        </td>
        <td>
            <div class="search-container" style="position: relative;">
                <input class="form-control" type="text" name="products[${rowIndex}][seria_name]" placeholder="Введите серию..." autocomplete="off">
                <input type="hidden" name="products[${rowIndex}][seria_id]" class="seria-id">   
            </div>
        </td>
         <td>
            <div class="search-container" style="position: relative;">
                <input class="form-control" type="text" name="products[${rowIndex}][unit_name]" placeholder="Введите ед." autocomplete="off">
                <input type="hidden" name="products[${rowIndex}][unit_id]" class="unit-id">     
            </div>
        </td>
        <td><input class="form-control" type="text" name="products[${rowIndex}][quantity]" placeholder="0" autocomplete="off"></td>
        <td><input class="form-control" type="text" name="products[${rowIndex}][price]" placeholder="0" autocomplete="off"></td>
        <td><select class="form-control" name="products[${rowIndex}][nds_id]">${ndsOptionsTemplate}</select></td>
        <td><input class="form-control" type="text" name="products[${rowIndex}][summa_stavka]" placeholder="0" autocomplete="off"></td>
        <td><input class="form-control" type="text" name="products[${rowIndex}][summa]" placeholder="0" autocomplete="off"></td>
        <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
    `;
}

function addRow() {
    const tbody = document.getElementById('productsBody');
    const rowCount = tbody.rows.length;
    const newRow = document.createElement('tr');
    newRow.className = 'product-row';
    newRow.innerHTML = createRowTemplate(rowCount);
    tbody.appendChild(newRow);
    initTableAutocomplete(newRow);
    attachCalculationListeners(newRow);
    updateRowNumbers();
}

function deleteRow(button) {
    const tbody = document.getElementById('productsBody');
    if (tbody.rows.length > 1) {
        button.closest('tr').remove();
        updateRowNumbers();
    } else {
        alert('Должна остаться хотя бы одна строка!');
    }
}

function updateRowNumbers() {
    const tbody = document.getElementById('productsBody');
    const rows = tbody.querySelectorAll('tr');
    rows.forEach((row, index) => {
        row.querySelector('td:first-child').textContent = index + 1;
        row.querySelectorAll('input, select').forEach(input => {
            if (input.name) {
                input.name = input.name.replace(/\[\d+\]/, `[${index}]`);
            }
        });
    });
}

// Helper function to position dropdown using fixed positioning relative to viewport
function positionDropdown(dropdown, input) {
    const rect = input.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    dropdown.style.left = rect.left + 'px';
    dropdown.style.top = (rect.bottom + 2) + 'px';
    dropdown.style.width = rect.width + 'px';
}

// Calculate summa based on price and quantity
function attachCalculationListeners(row) {
    const priceInput = row.querySelector('input[name*="[price]"]');
    const quantityInput = row.querySelector('input[name*="[quantity]"]');
    const summaInput = row.querySelector('input[name*="[summa]"]');
    const ndsSelect = row.querySelector('select[name*="[nds_id]"]');
    const ndsAmountInput = row.querySelector('input[name*="[summa_stavka]"]');

    const calculateSumma = () => {
        const price = parseFloat(priceInput.value) || 0;
        const quantity = parseFloat(quantityInput.value) || 0;
        const summa = price * quantity;
        summaInput.value = summa > 0 ? summa.toFixed(2) : '';
        
        // Recalculate НДС amount when summa changes
        calculateNdsAmount();
    };

    const calculateNdsAmount = () => {
        const summa = parseFloat(summaInput.value) || 0;
        
        if (!ndsSelect || ndsSelect.selectedIndex === 0) {
            ndsAmountInput.value = '';
            return;
        }
        
        // Get НДС rate from the selected option's text content
        const selectedOption = ndsSelect.options[ndsSelect.selectedIndex];
        const ndsRate = parseFloat(selectedOption.textContent) || 0;
        
        
        const ndsAmount = summa * (ndsRate / 100);
        ndsAmountInput.value = ndsAmount > 0 ? ndsAmount.toFixed(2) : '';
    };

    if (priceInput && quantityInput && summaInput) {
        priceInput.addEventListener('input', calculateSumma);
        quantityInput.addEventListener('input', calculateSumma);
    }
    
    if (ndsSelect) {
        ndsSelect.addEventListener('change', calculateNdsAmount);
    }
}

// Autocomplete for table product/series fields
function initTableAutocomplete(row) {
    const productInput = row.querySelector('input[name*="[product_name]"]');
    const productHidden = row.querySelector('input[name*="[product_id]"]');
    const seriaInput = row.querySelector('input[name*="[seria_name]"]');
    const seriaHidden = row.querySelector('input[name*="[seria_id]"]');
    const unitInput = row.querySelector('input[name*="[unit_name]"]');
    const unitHidden = row.querySelector('input[name*="[unit_id]"]');

    if (productInput && productHidden) {
        const dropdown = document.createElement('div');
        dropdown.className = 'autocomplete-dropdown';
        dropdown.style.display = 'none';
        dropdown.style.backgroundColor = 'white';
        dropdown.style.zIndex = '10000';
        dropdown.style.border = '1px solid #ddd';
        dropdown.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
        dropdown.style.maxHeight = '300px';
        dropdown.style.overflowY = 'auto';
        document.body.appendChild(dropdown); // Portal: append to body, not parent

        productInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim();
            if (query.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            try {
                const timestamp = new Date().getTime(); // Cache busting
                const url = `/api/autocomplete.php?search=${encodeURIComponent(query)}&table=tovary_i_uslugi&col=naimenovanie&id=id&t=${timestamp}`;
                const response = await fetch(url);
                const results = await response.json();

                dropdown.innerHTML = '';
                if (results && results.length > 0) {
                    results.forEach(item => {
                        const option = document.createElement('div');
                        option.className = 'autocomplete-option';
                        option.textContent = item.name;
                        option.style.padding = '8px 12px';
                        option.style.cursor = 'pointer';
                        option.style.borderBottom = '1px solid #eee';

                        option.addEventListener('click', () => {
                            productInput.value = item.name;
                            productHidden.value = item.id;
                            dropdown.style.display = 'none';
                        });

                        option.addEventListener('mouseover', () => {
                            option.style.backgroundColor = '#f0f0f0';
                        });
                        option.addEventListener('mouseout', () => {
                            option.style.backgroundColor = 'white';
                        });

                        dropdown.appendChild(option);
                    });
                    dropdown.style.display = 'block';
                    positionDropdown(dropdown, productInput);
                } else {
                    dropdown.style.display = 'none';
                }
            } catch (error) {
                console.error('Product autocomplete error:', error);
            }
        });

        productInput.addEventListener('focus', () => {
            if (dropdown.children.length > 0 && productInput.value.trim()) {
                dropdown.style.display = 'block';
                positionDropdown(dropdown, productInput);
            }
        });

        productInput.addEventListener('blur', () => {
            setTimeout(() => dropdown.style.display = 'none', 200);
        });

        window.addEventListener('scroll', () => {
            if (dropdown.style.display === 'block') {
                positionDropdown(dropdown, productInput);
            }
        });
    }

    if (seriaInput && seriaHidden) {
        const dropdown = document.createElement('div');
        dropdown.className = 'autocomplete-dropdown';
        dropdown.style.display = 'none';
        dropdown.style.backgroundColor = 'white';
        dropdown.style.zIndex = '10000';
        dropdown.style.border = '1px solid #ddd';
        dropdown.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
        dropdown.style.maxHeight = '300px';
        dropdown.style.overflowY = 'auto';
        document.body.appendChild(dropdown); // Portal: append to body, not parent

        seriaInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim();

            if (query.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            try {
                const timestamp = new Date().getTime(); // Cache busting
                const url = `/api/autocomplete.php?search=${encodeURIComponent(query)}&table=serii&col=nomer&id=id&t=${timestamp}`;

                const response = await fetch(url);
                const results = await response.json();

                dropdown.innerHTML = '';
                if (results && results.length > 0) {
                    results.forEach(item => {
                        const option = document.createElement('div');
                        option.className = 'autocomplete-option';
                        option.textContent = item.name;
                        option.style.padding = '8px 12px';
                        option.style.cursor = 'pointer';
                        option.style.borderBottom = '1px solid #eee';

                        option.addEventListener('click', () => {
                            seriaInput.value = item.name;
                            seriaHidden.value = item.id;
                            dropdown.style.display = 'none';
                        });

                        option.addEventListener('mouseover', () => {
                            option.style.backgroundColor = '#f0f0f0';
                        });
                        option.addEventListener('mouseout', () => {
                            option.style.backgroundColor = 'white';
                        });

                        dropdown.appendChild(option);
                    });
                    dropdown.style.display = 'block';
                    positionDropdown(dropdown, seriaInput);
                } else {
                    dropdown.style.display = 'none';
                }
            } catch (error) {
                console.error('Series autocomplete error:', error);
            }
        });

        seriaInput.addEventListener('focus', () => {
            if (dropdown.children.length > 0 && seriaInput.value.trim()) {
                dropdown.style.display = 'block';
                positionDropdown(dropdown, seriaInput);
            }
        });

        seriaInput.addEventListener('blur', () => {
            setTimeout(() => dropdown.style.display = 'none', 200);
        });

        window.addEventListener('scroll', () => {
            if (dropdown.style.display === 'block') {
                positionDropdown(dropdown, seriaInput);
            }
        });
    }

    if (unitInput && unitHidden) {
        const dropdown = document.createElement('div');
        dropdown.className = 'autocomplete-dropdown';
        dropdown.style.display = 'none';
        dropdown.style.backgroundColor = 'white';
        dropdown.style.zIndex = '10000';
        dropdown.style.border = '1px solid #ddd';
        dropdown.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
        dropdown.style.maxHeight = '300px';
        dropdown.style.overflowY = 'auto';
        document.body.appendChild(dropdown); // Portal: append to body, not parent

        unitInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim();

            if (query.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            try {
                // Use local unitsData array instead of API call for better performance
                const results = unitsData.filter(unit =>
                    unit.naimenovanie.toLowerCase().includes(query.toLowerCase())
                );

                dropdown.innerHTML = '';
                if (results && results.length > 0) {
                    results.forEach(item => {
                        const option = document.createElement('div');
                        option.className = 'autocomplete-option';
                        option.textContent = item.naimenovanie;
                        option.style.padding = '8px 12px';
                        option.style.cursor = 'pointer';
                        option.style.borderBottom = '1px solid #eee';

                        option.addEventListener('click', () => {
                            unitInput.value = item.naimenovanie;
                            unitHidden.value = item.id;
                            dropdown.style.display = 'none';
                        });

                        option.addEventListener('mouseover', () => {
                            option.style.backgroundColor = '#f0f0f0';
                        });
                        option.addEventListener('mouseout', () => {
                            option.style.backgroundColor = 'white';
                        });

                        dropdown.appendChild(option);
                    });
                    dropdown.style.display = 'block';
                    positionDropdown(dropdown, unitInput);
                } else {
                    dropdown.style.display = 'none';
                }
            } catch (error) {
                console.error('Unit autocomplete error:', error);
            }
        });

        unitInput.addEventListener('focus', () => {
            if (dropdown.children.length > 0 && unitInput.value.trim()) {
                dropdown.style.display = 'block';
                positionDropdown(dropdown, unitInput);
            }
        });

        unitInput.addEventListener('blur', () => {
            setTimeout(() => dropdown.style.display = 'none', 200);
        });

        window.addEventListener('scroll', () => {
            if (dropdown.style.display === 'block') {
                positionDropdown(dropdown, unitInput);
            }
        });
    }
}

// Event listeners for form field autocomplete initialization
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.product-row').forEach(row => {
        initTableAutocomplete(row);
        attachCalculationListeners(row);
    });

    autocompleteFields.forEach(field => {
        const input = document.getElementById(field.inputId);
        if (!input) return;

        // For the responsible field, find the hidden input with class responsible-id
        let hiddenInput;
        if (field.inputId === 'responsible_id') {
            hiddenInput = input.parentNode.querySelector('input[name="responsible_id"]');       
        } else {
            hiddenInput = input.nextElementSibling;
        }

        if (!hiddenInput || hiddenInput.type !== 'hidden') return;

        const dropdown = document.createElement('div');
        dropdown.className = 'autocomplete-dropdown';
        dropdown.style.display = 'none';
        dropdown.style.backgroundColor = 'white';
        document.body.appendChild(dropdown); // Portal: append to body, not parent

        input.addEventListener('input', async (e) => {
            const query = e.target.value.trim();

            if (query.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            // Clear the hidden ID field when user types - it will be set only if they select from dropdown
            hiddenInput.value = '';

            try {
                const idCol = field.idCol || 'id';
                const timestamp = new Date().getTime(); // Cache busting
                const url = `/api/autocomplete.php?search=${encodeURIComponent(query)}&table=${field.table}&col=${field.col}&id=${idCol}&t=${timestamp}`;

                const response = await fetch(url);
                const results = await response.json();

                dropdown.innerHTML = '';
                if (results.length > 0) {
                    results.forEach(item => {
                        const option = document.createElement('div');
                        option.className = 'autocomplete-option';
                        option.textContent = item.name;
                        option.style.padding = '8px 12px';
                        option.style.cursor = 'pointer';
                        option.style.borderBottom = '1px solid #eee';

                        option.addEventListener('click', () => {
                            input.value = item.name;
                            hiddenInput.value = item.id;
                            dropdown.style.display = 'none';
                        });

                        option.addEventListener('mouseover', () => {
                            option.style.backgroundColor = '#f0f0f0';
                        });
                        option.addEventListener('mouseout', () => {
                            option.style.backgroundColor = 'white';
                        });

                        dropdown.appendChild(option);
                    });
                    dropdown.style.display = 'block';
                    positionDropdown(dropdown, input);
                } else {
                    dropdown.style.display = 'none';
                }
            } catch (error) {
                console.error('Autocomplete error:', error);
            }
        });

        input.addEventListener('focus', () => {
            if (dropdown.children.length > 0 && input.value.trim()) {
                dropdown.style.display = 'block';
                positionDropdown(dropdown, input);
            }
        });

        input.addEventListener('blur', () => {
            setTimeout(() => dropdown.style.display = 'none', 200);
        });

        window.addEventListener('scroll', () => {
            if (dropdown.style.display === 'block') {
                positionDropdown(dropdown, input);
            }
        });
    });
});