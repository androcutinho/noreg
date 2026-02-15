<?php
$page_title = 'Загрузить ВСД';
require_once '../header.php';
?>

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        Загрузить ВСД
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
                                    <input type="text" class="form-control" id="vsd-uuid-input" name="uuid" placeholder="Введите UUID ВСД" required>
                                </div>

                                <div class="form-footer">
                                    <button type="button" class="btn btn-primary" id="submitBtn" onclick="loadVSDSeries()">
                                        Загрузить ВСД
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
    // Function to load VSD series
    function loadVSDSeries() {
        const uuidInput = document.getElementById('vsd-uuid-input');
        const uuid = uuidInput.value.trim();
        
        if (uuid.length === 0) {
            alert('Пожалуйста, введите UUID');
            return;
        }
        
        window.location.href = 'https://noreg.trinion.org/postuplenie/vetis.php?uuid=' + encodeURIComponent(uuid);
    }

    // Allow Enter key to trigger the action
    document.addEventListener('DOMContentLoaded', function() {
        const uuidInput = document.getElementById('vsd-uuid-input');
        
        if (uuidInput) {
            uuidInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loadVSDSeries();
                }
            });
        }
    });
</script>

<?php include '../footer.php'; ?>