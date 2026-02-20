// Autocomplete for form fields (organization, vendor, employee)
const specAutocompleteFields = [
    { inputId: 'organization_id', table: 'kontragenti', col: 'naimenovanie', nash_kontragent: 1 },
    { inputId: 'kontragenti_id', table: 'kontragenti', col: 'naimenovanie', nash_kontragent: 0 },
    { inputId: 'sotrudniki_id', table: 'sotrudniki', col: 'fio' }
];


function positionDropdown(dropdown, input) {
    const rect = input.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    dropdown.style.left = rect.left + 'px';
    dropdown.style.top = (rect.bottom + 2) + 'px';
    dropdown.style.width = rect.width + 'px';
}


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

            
            hiddenInput.value = '';

            try {
                const timestamp = new Date().getTime(); // Cache busting
                let url = `/api/autocomplete.php?search=${encodeURIComponent(query)}&table=${field.table}&col=${field.col}&id=id&t=${timestamp}`;
                
                // Add nash_kontragent filter if specified in field config
                if (field.nash_kontragent !== undefined) {
                    url += `&nash_kontragent=${field.nash_kontragent}`;
                }

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
