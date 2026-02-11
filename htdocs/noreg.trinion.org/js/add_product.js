
const autocompleteFields = [
    { inputId: 'warehouse_id', table: 'sklady', col: 'naimenovanie' },
    { inputId: 'vendor_id', table: 'kontragenti', col: 'naimenovanie' },
    { inputId: 'organization_id', table: 'organizacii', col: 'naimenovanie' },
    { inputId: 'responsible_id', table: 'users', col: 'user_name', idCol: 'user_id' }
];


const defaultColumns = [
    { key: 'product', label: 'Товар', type: 'autocomplete' },
    { key: 'unit', label: 'Ед', type: 'autocomplete' },
    { key: 'quantity', label: 'Кол-во', type: 'text' },
    { key: 'price', label: 'Цена', type: 'text' },
    { key: 'nds_id', label: 'НДС', type: 'select' }
];


function createColumnHTML(rowIndex, column) {
    const fieldName = `products[${rowIndex}][${column.key}]`;
    
    switch (column.type) {
        case 'autocomplete':
            if (column.table === 'serii') {
                return `
                    <td>
                        <div class="search-container" style="position: relative;">
                            <input class="form-control" type="text" name="${fieldName}_name" placeholder="Введите ${column.label.toLowerCase()}..." autocomplete="off">
                            <input type="hidden" name="${fieldName}" class="${column.key}-id">
                        </div>
                    </td>
                `;
            } else if (column.table === 'tovary_i_uslugi') {
                return `
                    <td>
                        <div class="search-container" style="position: relative;">
                            <input class="form-control" type="text" name="${fieldName}_name" placeholder="Введите ${column.label.toLowerCase()}..." autocomplete="off">
                            <input type="hidden" name="${fieldName}" class="product-id">
                        </div>
                    </td>
                `;
            } else if (column.table === 'edinicy_izmenereniya') {
                return `
                    <td>
                        <div class="search-container" style="position: relative;">
                            <input class="form-control" type="text" name="${fieldName}_name" placeholder="Введите ${column.label.toLowerCase()}..." autocomplete="off">
                            <input type="hidden" name="${fieldName}" class="unit-id">
                        </div>
                    </td>
                `;
            }
            break;
            
        case 'select':
            return `
                <td>
                    <select class="form-control" name="${fieldName}">
                        ${ndsOptionsTemplate}
                    </select>
                </td>
            `;
            
        case 'date':
            return `
                <td>
                    <input class="form-control" type="date" name="${fieldName}" autocomplete="off">
                </td>
            `;
            
        case 'text':
        default:
            return `
                <td>
                    <input class="form-control" type="text" name="${fieldName}" placeholder="0" autocomplete="off">
                </td>
            `;
    }
}

function createRowTemplate(rowIndex, config = null) {
    
    let cfg = config || (typeof formConfig !== 'undefined' ? formConfig : { columns: defaultColumns });
    
    let ndsOptions = (typeof ndsOptionsTemplate !== 'undefined') ? ndsOptionsTemplate : '<option value="">--</option>';
    let html = `<td>${rowIndex + 1}</td>`;
    
   
    let deliveryDateColumn = null;
    let warehouseColumn = null;
    
    for (const column of cfg.columns) {
       
        if (column.key === 'delivery_date' || column.key === 'planiruemaya_data_postavki') {
            deliveryDateColumn = column;
            continue;
        }
        
        if (column.key === 'warehouse') {
            warehouseColumn = column;
            continue;
        }
        
        if (column.key === 'nds_id' || column.type === 'select') {
            const fieldName = `products[${rowIndex}][${column.key}]`;
            html += `
                <td>
                    <select class="form-control" name="${fieldName}">
                        ${ndsOptions}
                    </select>
                </td>
            `;
        } else if (column.key === 'quantity' || column.key === 'price') {
            const fieldName = `products[${rowIndex}][${column.key}]`;
            html += `<td><input class="form-control" type="text" name="${fieldName}" placeholder="0" autocomplete="off"></td>`;
        } else if (column.key === 'product') {
            const fieldName = `products[${rowIndex}][product_name]`;
            const hiddenName = `products[${rowIndex}][product_id]`;
            html += `
                <td>
                    <div class="search-container" style="position: relative;">
                        <input class="form-control" type="text" name="${fieldName}" placeholder="Введите товар..." autocomplete="off">
                        <input type="hidden" name="${hiddenName}" class="product-id">
                    </div>
                </td>
            `;
        } else if (column.key === 'seria') {
            const fieldName = `products[${rowIndex}][seria_name]`;
            const hiddenName = `products[${rowIndex}][seria_id]`;
            html += `
                <td>
                    <div class="search-container" style="position: relative;">
                        <input class="form-control" type="text" name="${fieldName}" placeholder="Введите серию..." autocomplete="off">
                        <input type="hidden" name="${hiddenName}" class="seria-id">
                    </div>
                </td>
            `;
        } else if (column.key === 'unit') {
            const fieldName = `products[${rowIndex}][unit_name]`;
            const hiddenName = `products[${rowIndex}][unit_id]`;
            html += `
                <td>
                    <div class="search-container" style="position: relative;">
                        <input class="form-control" type="text" name="${fieldName}" placeholder="Введите ед." autocomplete="off">
                        <input type="hidden" name="${hiddenName}" class="unit-id">
                    </div>
                </td>
            `;
        }
    }
    
    
    const summaBefore = `products[${rowIndex}][summa_stavka]`;
    const summaAfter = `products[${rowIndex}][summa]`;
    html += `
        <td><input class="form-control" type="text" name="${summaBefore}" placeholder="0" autocomplete="off" readonly></td>
        <td><input class="form-control" type="text" name="${summaAfter}" placeholder="0" autocomplete="off" readonly></td>
    `;
    
    
    if (warehouseColumn) {
        const fieldName = `products[${rowIndex}][warehouse_name]`;
        const hiddenName = `products[${rowIndex}][warehouse_id]`;
        html += `
            <td>
                <div class="search-container" style="position: relative;">
                    <input class="form-control" type="text" name="${fieldName}" placeholder="Введите склад" autocomplete="off">
                    <input type="hidden" name="${hiddenName}" class="warehouse-id">
                </div>
            </td>
        `;
    }
    
    if (deliveryDateColumn) {
        const fieldName = `products[${rowIndex}][${deliveryDateColumn.key}]`;
        html += `
            <td>
                <input class="form-control" type="date" name="${fieldName}" autocomplete="off" required>
            </td>
        `;
    }
    
    
    html += `
        <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
    `;
    
    return html;
}

function addRow() {
    const tbody = document.getElementById('productsBody');
    const rowCount = tbody.rows.length;
    const newRow = document.createElement('tr');
    newRow.className = 'product-row';
    const cfg = typeof formConfig !== 'undefined' ? formConfig : null;
    newRow.innerHTML = createRowTemplate(rowCount, cfg);
    tbody.appendChild(newRow);
    initTableAutocomplete(newRow);
    attachCalculationListeners(newRow);
}

function deleteRow(button) {
    const tbody = document.getElementById('productsBody');
    if (tbody.rows.length > 1) {
        button.closest('tr').remove();
        updateRowNumbers();
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
        
        // Recalculate NDS amount when summa changes
        calculateNdsAmount();
    };

    const calculateNdsAmount = () => {
        const summa = parseFloat(summaInput.value) || 0;
        
        if (!ndsSelect || ndsSelect.selectedIndex === 0) {
            ndsAmountInput.value = '';
            return;
        }
        
        // Get NDS rate from the selected option's text content
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

// Check if product requires series (poserijnyj_uchet)
async function checkProductSeriesRequirement(productId, seriaInput, seriaHidden, productInput) {
    if (!productId) {
        
        seriaInput.disabled = false;
        seriaInput.classList.remove('disabled-field');
        return;
    }
    
    try {
        const response = await fetch(`/api/get_product_info.php?product_id=${encodeURIComponent(productId)}`);
        const data = await response.json();
        

        if (data && (data.poserijnyj_uchet === 0 || data.poserijnyj_uchet === '0')) {
            
            seriaInput.disabled = true;
            seriaInput.classList.add('disabled-field');
            seriaInput.value = '';
            seriaHidden.value = '';
        } else {
            
            seriaInput.disabled = false;
            seriaInput.classList.remove('disabled-field');
        }
    } catch (error) {
        console.error('Error checking product series requirement:', error);
        seriaInput.disabled = false;
        seriaInput.classList.remove('disabled-field');
    }
}

// Autocomplete for table product/series/unit fields
function initTableAutocomplete(row) {
    const productInput = row.querySelector('input[name*="[product_name]"]');
    const productHidden = row.querySelector('input[name*="[product_id]"]');
    const seriaInput = row.querySelector('input[name*="[seria_name]"]');
    const seriaHidden = row.querySelector('input[name*="[seria_id]"]');
    const unitInput = row.querySelector('input[name*="[unit_name]"]');
    const unitHidden = row.querySelector('input[name*="[unit_id]"]');

    // Product autocomplete
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
        document.body.appendChild(dropdown);

        productInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim();
            
            // If product field is cleared, enable series field
            if (query.length === 0) {
                dropdown.style.display = 'none';
                productHidden.value = '';
                seriaInput.disabled = false;
                seriaInput.classList.remove('disabled-field');
                return;
            }

            try {
                const timestamp = new Date().getTime();
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
                            // Check if product requires series
                            checkProductSeriesRequirement(item.id, seriaInput, seriaHidden, productInput);
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

    // Series autocomplete
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
        document.body.appendChild(dropdown);

        seriaInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim();

            if (query.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            try {
                const timestamp = new Date().getTime();
                const url = `/api/autocomplete.php?search=${encodeURIComponent(query)}&table=serii&col=naimenovanie&id=id&t=${timestamp}`;

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

    // Unit autocomplete - uses pre-loaded unitsData array for performance
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
        document.body.appendChild(dropdown);

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

    // Warehouse autocomplete
    const warehouseInput = row.querySelector('input[name*="[warehouse_name]"]');
    const warehouseHidden = row.querySelector('input[name*="[warehouse_id]"]');

    if (warehouseInput && warehouseHidden) {
        const dropdown = document.createElement('div');
        dropdown.className = 'autocomplete-dropdown';
        dropdown.style.display = 'none';
        dropdown.style.backgroundColor = 'white';
        dropdown.style.zIndex = '10000';
        dropdown.style.border = '1px solid #ddd';
        dropdown.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
        dropdown.style.maxHeight = '300px';
        dropdown.style.overflowY = 'auto';
        document.body.appendChild(dropdown);

        warehouseInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim();

            if (query.length === 0) {
                dropdown.style.display = 'none';
                warehouseHidden.value = '';
                return;
            }

            try {
                const timestamp = new Date().getTime();
                const url = `/api/autocomplete.php?search=${encodeURIComponent(query)}&table=sklady&col=naimenovanie&id=id&t=${timestamp}`;

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
                            warehouseInput.value = item.name;
                            warehouseHidden.value = item.id;
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
                    positionDropdown(dropdown, warehouseInput);
                } else {
                    dropdown.style.display = 'none';
                }
            } catch (error) {
                console.error('Warehouse autocomplete error:', error);
            }
        });

        warehouseInput.addEventListener('focus', () => {
            if (dropdown.children.length > 0 && warehouseInput.value.trim()) {
                dropdown.style.display = 'block';
                positionDropdown(dropdown, warehouseInput);
            }
        });

        warehouseInput.addEventListener('blur', () => {
            setTimeout(() => dropdown.style.display = 'none', 200);
        });

        window.addEventListener('scroll', () => {
            if (dropdown.style.display === 'block') {
                positionDropdown(dropdown, warehouseInput);
            }
        });
    }
}

// Event listeners for form field autocomplete initialization
document.addEventListener('DOMContentLoaded', () => {
    // Initialize autocomplete for existing rows
    document.querySelectorAll('.product-row').forEach(row => {
        initTableAutocomplete(row);
        attachCalculationListeners(row);
        
        // Check series requirement for existing products
        const productHidden = row.querySelector('input[name*="[product_id]"]');
        const seriaInput = row.querySelector('input[name*="[seria_name]"]');
        const seriaHidden = row.querySelector('input[name*="[seria_id]"]');
        if (productHidden && productHidden.value) {
            checkProductSeriesRequirement(productHidden.value, seriaInput, seriaHidden);
        }
    });

    // Initialize autocomplete for header fields
    autocompleteFields.forEach(field => {
        const input = document.getElementById(field.inputId);
        if (!input) return;

        // For the responsible field, find the hidden input with class responsible-id
        let hiddenInput;
        if (field.inputId === 'responsible_id') {
            hiddenInput = input.parentNode.querySelector('input[name="responsible_id"]');       
        } else {
            hiddenInput = input.parentNode.querySelector('input[name="' + field.inputId + '"]');
        }

        if (!hiddenInput || hiddenInput.type !== 'hidden') return;

        const dropdown = document.createElement('div');
        dropdown.className = 'autocomplete-dropdown';
        dropdown.style.display = 'none';
        dropdown.style.backgroundColor = 'white';
        dropdown.style.zIndex = '10000';
        dropdown.style.border = '1px solid #ddd';
        dropdown.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
        dropdown.style.maxHeight = '300px';
        dropdown.style.overflowY = 'auto';
        document.body.appendChild(dropdown);

        input.addEventListener('input', async (e) => {
            const query = e.target.value.trim();

            if (query.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            // Clear the hidden ID field when user types
            hiddenInput.value = '';

            try {
                const idCol = field.idCol || 'id';
                const timestamp = new Date().getTime();
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

// Close autocomplete when clicking elsewhere
document.addEventListener('click', function(e) {
    // This is handled by the blur events on inputs
});

// Handle form submission - ensure unit_id is populated for rows that weren't modified
document.getElementById('documentForm').addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('.product-row');
    let hasError = false;
    
    rows.forEach((row, index) => {
        const unitNameInput = row.querySelector('input[name*="[unit_name]"]');
        const unitIdHidden = row.querySelector('input[name*="[unit_id]"]');
        
        if (unitNameInput && unitIdHidden && unitNameInput.value.trim()) {
            // If unit name is filled but unit_id is empty, try to find it
            if (!unitIdHidden.value) {
                const matchedUnit = unitsData.find(unit => 
                    unit.naimenovanie.toLowerCase() === unitNameInput.value.toLowerCase()
                );
                
                if (matchedUnit) {
                    unitIdHidden.value = matchedUnit.id;
                }
            }
        }
    });
});
