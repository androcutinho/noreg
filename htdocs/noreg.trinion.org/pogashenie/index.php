<?php
$page_title = 'Poгашение ВСД';
require_once '../header.php';
?>

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        Погасить ВСД
                    </h2>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <form id="closeVSDForm" onsubmit="handleFormSubmit(event)">
                                <div class="mb-3">
                                    <label class="form-label" for="uuid">UUID документа</label>
                                    <input type="text" class="form-control" id="uuid" name="uuid" placeholder="Введите UUID ВСД" required>
                                </div>

                                <div class="form-footer">
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        Гасить ВСД
                                    </button>
                                </div>
                            </form>

                            <!-- Error message display -->
                            <div id="errorMessage" style="display: none; margin-top: 20px;">
                                <div class="alert alert-danger" role="alert">
                                    <div class="d-flex">
                                        <div>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                            </svg>
                                        </div>
                                        <div class="ms-3">
                                            <h4 class="alert-title">Ошибка</h4>
                                            <div class="text-secondary" id="errorText"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function handleFormSubmit(event) {
        event.preventDefault();
        
        const form = document.getElementById('closeVSDForm');
        const uuid = document.getElementById('uuid').value.trim();
        const submitBtn = document.getElementById('submitBtn');
        const errorDiv = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        
        if (uuid.length === 0) {
            errorText.innerHTML = 'Пожалуйста, введите UUID документа';
            errorDiv.style.display = 'block';
            return;
        }
        
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Отправка...';
        
        
        errorDiv.style.display = 'none';
        
        
        fetch('/api/process_incoming_consignment_service.php?uuid=' + encodeURIComponent(uuid), {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            if (data.success) {
                
                form.reset();
                errorDiv.style.display = 'none';
            } else {
                errorText.innerHTML = data.error || 'Неизвестная ошибка при обработке запроса';
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            errorText.innerHTML = 'Ошибка соединения: ' + error.message;
            errorDiv.style.display = 'block';
        });
    }
    

    document.addEventListener('DOMContentLoaded', function() {
        const uuidInput = document.getElementById('uuid');
        if (uuidInput) {
            uuidInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('closeVSDForm').dispatchEvent(new Event('submit'));
                }
            });
        }
    });
</script>
