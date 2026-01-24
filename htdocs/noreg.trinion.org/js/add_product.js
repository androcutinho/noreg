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
        <td><input class="form-control" type="text" name="products[${rowIndex}][price]" placeholder="0" autocomplete="off"></td>
        <td><input class="form-control" type="text" name="products[${rowIndex}][quantity]" placeholder="0" autocomplete="off"></td>
        <td>шт</td>
        <td><select class="form-control" name="products[${rowIndex}][nds_id]">${ndsOptionsTemplate}</select></td>
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

// Autocomplete for table product/series fields
function initTableAutocomplete(row) {
    const productInput = row.querySelector('input[name*="[product_name]"]');
    const productHidden = row.querySelector('input[name*="[product_id]"]');
    const seriaInput = row.querySelector('input[name*="[seria_name]"]');
    const seriaHidden = row.querySelector('input[name*="[seria_id]"]');

    if (productInput && productHidden) {
        const dropdown = document.createElement('div');
        dropdown.className = 'autocomplete-dropdown';
        dropdown.style.display = 'none';
        document.body.appendChild(dropdown); // Portal: append to body, not parent

        productInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim();
            if (query.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            try {
                const timestamp = new Date().getTime(); // Cache busting
                const url = `api/autocomplete.php?search=${encodeURIComponent(query)}&table=tovary_i_uslugi&col=naimenovanie&id=id&t=${timestamp}`;
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
                            option.style.backgroundColor = 'transparent';
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
        document.body.appendChild(dropdown); // Portal: append to body, not parent

        seriaInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim();
            
            if (query.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            try {
                const timestamp = new Date().getTime(); // Cache busting
                const url = `api/autocomplete.php?search=${encodeURIComponent(query)}&table=serii&col=nomer&id=id&t=${timestamp}`;
                
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
                            option.style.backgroundColor = 'transparent';
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
}

// Event listeners for form field autocomplete initialization
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.product-row').forEach(row => {
        initTableAutocomplete(row);
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
        document.body.appendChild(dropdown); // Portal: append to body, not parent

        input.addEventListener('input', async (e) => {
            const query = e.target.value.trim();
            
            if (query.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            try {
                const idCol = field.idCol || 'id';
                const timestamp = new Date().getTime(); // Cache busting
                const url = `api/autocomplete.php?search=${encodeURIComponent(query)}&table=${field.table}&col=${field.col}&id=${idCol}&t=${timestamp}`;
                
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
                            option.style.backgroundColor = 'transparent';
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
