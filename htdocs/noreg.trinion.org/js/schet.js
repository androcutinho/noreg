// Autocomplete for account fields
function setupAccountAutocomplete() {
    const accountFields = [
        {
            inputId: 'schet_pokupatelya_id',
            hiddenClass: 'schet-pokupatelya-id'
        },
        {
            inputId: 'schet_postavschika_id',
            hiddenClass: 'schet-postavschika-id'
        }
    ];
    
    accountFields.forEach(field => {
        const inputElement = document.getElementById(field.inputId);
        const hiddenElement = document.querySelector('.' + field.hiddenClass);
        
        if (!inputElement) return;
        
        inputElement.addEventListener('input', function() {
            const searchValue = this.value.trim();
            
            if (searchValue.length === 0) {
                // Clear the hidden field if input is empty
                hiddenElement.value = '';
                return;
            }
            
            // Make AJAX call to autocomplete.php
            fetch(`../api/autocomplete.php?search=${encodeURIComponent(searchValue)}&table=raschetnye_scheta&col=naimenovanie&id=id`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response error');
                    return response.json();
                })
                .then(data => {
                    displayAccountAutocomplete(data, inputElement, field.inputId, field.hiddenClass);
                })
                .catch(error => console.error('Autocomplete error:', error));
        });
        
        // Close autocomplete when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== inputElement) {
                const dropdown = document.getElementById(field.inputId + '_dropdown');
                if (dropdown) {
                    dropdown.remove();
                }
            }
        });
    });
}

function displayAccountAutocomplete(data, inputElement, inputId, hiddenClass) {
    // Remove existing dropdown
    const existingDropdown = document.getElementById(inputId + '_dropdown');
    if (existingDropdown) {
        existingDropdown.remove();
    }
    
    if (!data || data.length === 0) {
        return;
    }
    
    // Create dropdown container
    const dropdown = document.createElement('div');
    dropdown.id = inputId + '_dropdown';
    dropdown.style.position = 'absolute';
    dropdown.style.backgroundColor = '#fff';
    dropdown.style.border = '1px solid #ddd';
    dropdown.style.borderRadius = '4px';
    dropdown.style.maxHeight = '250px';
    dropdown.style.overflowY = 'auto';
    dropdown.style.zIndex = '1000';
    dropdown.style.width = inputElement.offsetWidth + 'px';
    dropdown.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
    
    const parentContainer = inputElement.parentElement;
    parentContainer.style.position = 'relative';
    parentContainer.appendChild(dropdown);
    
    // Add items to dropdown
    data.forEach(item => {
        const option = document.createElement('div');
        option.style.padding = '8px 12px';
        option.style.cursor = 'pointer';
        option.style.borderBottom = '1px solid #f0f0f0';
        option.textContent = item.name;
        
        option.addEventListener('mouseover', function() {
            this.style.backgroundColor = '#f5f5f5';
        });
        
        option.addEventListener('mouseout', function() {
            this.style.backgroundColor = '#fff';
        });
        
        option.addEventListener('click', function() {
            inputElement.value = item.name;
            document.querySelector('.' + hiddenClass).value = item.id;
            dropdown.remove();
        });
        
        dropdown.appendChild(option);
    });
}

// Initialize autocomplete when page loads
document.addEventListener('DOMContentLoaded', setupAccountAutocomplete);
