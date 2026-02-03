document.addEventListener('DOMContentLoaded', function() {
    const syncBtn = document.getElementById('sync-vsd-btn');
    
    if (!syncBtn) {
        console.error('Sync button not found');
        return;
    }
    
    syncBtn.addEventListener('click', function() {
        const btnText = document.getElementById('sync-btn-text');
        const btnSpinner = document.getElementById('sync-btn-spinner');
        const messageContainer = document.getElementById('sync-message-container');
        
        // Disable button and show spinner
        syncBtn.disabled = true;
        if (btnText) btnText.style.display = 'none';
        if (btnSpinner) btnSpinner.style.display = 'inline-block';
        
        // Clear previous messages
        if (messageContainer) messageContainer.innerHTML = '';
        
        // Make API call
        fetch('../api/sync_vsd_documents.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            // Log raw response for debugging
            console.log('Raw response:', text);
            
            // Try to parse JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response from server. Please check the browser console for details.');
            }
            
            if (data.success) {
                // Show success message
                const message = `<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Успешно!</strong> ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
                if (messageContainer) messageContainer.innerHTML = message;
                
                // Reload page after 2 seconds
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                // Show error message
                const errorMsg = data.error || 'Unknown error occurred';
                const message = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Ошибка!</strong> ${errorMsg}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
                if (messageContainer) messageContainer.innerHTML = message;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            const message = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Ошибка!</strong> ${error.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
            if (messageContainer) messageContainer.innerHTML = message;
        })
        .finally(() => {
            // Re-enable button and hide spinner
            syncBtn.disabled = false;
            if (btnText) btnText.style.display = 'inline';
            if (btnSpinner) btnSpinner.style.display = 'none';
        });
    });
});
