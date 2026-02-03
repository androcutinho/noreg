// Helper function to position dropdown using fixed positioning (same as in add_product.js)
function positionDropdown(dropdown, input) {
    const rect = input.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    dropdown.style.left = rect.left + 'px';
    dropdown.style.top = (rect.bottom + 2) + 'px';
    dropdown.style.width = rect.width + 'px';
}

// Initialize series autocomplete on document ready
document.addEventListener('DOMContentLoaded', () => {
    const seriaInput = document.getElementById('nomer');
    const dropdown = document.getElementById('seria-dropdown');

    if (!seriaInput) return;

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
});
