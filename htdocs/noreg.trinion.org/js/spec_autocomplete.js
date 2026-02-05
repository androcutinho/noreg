// Autocomplete for form fields (organization, vendor, employee)
const specAutocompleteFields = [
    { inputId: 'organization_id', table: 'organizacii', col: 'naimenovanie' },
    { inputId: 'kontragenti_id', table: 'kontragenti', col: 'naimenovanie' },
    { inputId: 'sotrudniki_id', table: 'sotrudniki', col: 'fio' }
];

// Helper function to position dropdown using fixed positioning relative to viewport
function positionDropdown(dropdown, input) {
    const rect = input.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    dropdown.style.left = rect.left + 'px';
    dropdown.style.top = (rect.bottom + 2) + 'px';
    dropdown.style.width = rect.width + 'px';
}

// Event listeners for spec form field autocomplete
document.addEventListener('DOMContentLoaded', () => {
    specAutocompleteFields.forEach(field => {
        const input = document.getElementById(field.inputId);
        
        if (!input) {
            return;
        }

        let hiddenInput = input.parentNode.querySelector(`input[type="hidden"][name="${field.inputId}"]`);

        if (!hiddenInput || hiddenInput.type !== 'hidden') {
            return;
        }

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

            // Clear the hidden ID field when user types - it will be set only if they select from dropdown
            hiddenInput.value = '';

            try {
                const timestamp = new Date().getTime(); // Cache busting
                const url = `/api/autocomplete.php?search=${encodeURIComponent(query)}&table=${field.table}&col=${field.col}&id=id&t=${timestamp}`;

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
