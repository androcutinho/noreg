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
        
        
        syncBtn.disabled = true;
        if (btnText) btnText.style.display = 'none';
        if (btnSpinner) btnSpinner.style.display = 'inline-block';
        
        
        if (messageContainer) messageContainer.innerHTML = '';
        
        
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
            
            console.log('Raw response:', text);
            
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response from server. Please check the browser console for details.');
            }
            
            if (data.success) {
                
                const message = `<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Успешно!</strong> ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
                if (messageContainer) messageContainer.innerHTML = message;
                
                
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                
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
            
            syncBtn.disabled = false;
            if (btnText) btnText.style.display = 'inline';
            if (btnSpinner) btnSpinner.style.display = 'none';
        });
    });
});
