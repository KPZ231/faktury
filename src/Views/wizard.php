<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kreator nowej sprawy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .error-message {
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .dynamic-fields {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Kreator nowej sprawy</h1>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                Sprawa została pomyślnie dodana!
            </div>
        <?php endif; ?>

        <form action="/wizard/save" method="POST" id="wizardForm" onsubmit="return validateForm(this)">
            <!-- Basic Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Podstawowe informacje</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="Sprawa" class="form-label">Nazwa sprawy</label>
                        <input type="text" class="form-control" id="Sprawa" name="Sprawa" required 
                               value="<?php echo htmlspecialchars($formData['Sprawa'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="Zakończona" class="form-label">Status sprawy</label>
                        <select class="form-select" id="Zakończona" name="Zakończona?" required>
                            <option value="">Wybierz status</option>
                            <option value="Tak" <?php echo ($formData['Zakończona?'] ?? '') === 'Tak' ? 'selected' : ''; ?>>Zakończona</option>
                            <option value="Nie" <?php echo ($formData['Zakończona?'] ?? '') === 'Nie' ? 'selected' : ''; ?>>W toku</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="Wywalczona_kwota" class="form-label">Wywalczona kwota</label>
                        <input type="number" step="0.01" class="form-control" id="Wywalczona_kwota" name="Wywalczona_kwota" required
                               value="<?php echo htmlspecialchars($formData['Wywalczona_kwota'] ?? ''); ?>"
                               onchange="console.log('Wywalczona kwota changed:', this.value)"
                               oninput="console.log('Wywalczona kwota input:', this.value)"
                               min="0.01">
                    </div>
                </div>
            </div>

            <!-- Agents -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Agenci</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="Liczba_agentow" class="form-label">Ilu agentów brało udział w sprawie?</label>
                        <input type="number" min="1" max="5" class="form-control" id="Liczba_agentow" name="Liczba_agentow" required
                               value="<?php echo htmlspecialchars($formData['Liczba_agentow'] ?? ''); ?>">
                    </div>
                    <div id="agentFields" class="dynamic-fields">
                        <!-- Dynamic agent fields will be added here -->
                    </div>
                </div>
            </div>

            <!-- Fees -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Opłaty i prowizje</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="Opłata_wstępna" class="form-label">Opłata wstępna</label>
                        <input type="number" step="0.01" class="form-control" id="Opłata_wstępna" name="Opłata wstępna" required
                               value="<?php echo htmlspecialchars($formData['Opłata wstępna'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="Success_fee" class="form-label">Success fee</label>
                        <input type="number" step="0.01" class="form-control" id="Success_fee" name="Success fee" required
                               value="<?php echo htmlspecialchars($formData['Success fee'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="Prowizja_Kuba" class="form-label">Prowizja Kuba</label>
                        <input type="number" step="0.01" class="form-control" id="Prowizja_Kuba" name="Prowizja Kuba"
                               value="<?php echo htmlspecialchars($formData['Prowizja Kuba'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Installments -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Raty</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="Liczba_rat" class="form-label">Na ile rat jest rozłożona płatność?</label>
                        <input type="number" min="1" max="4" class="form-control" id="Liczba_rat" name="Liczba_rat" required
                               value="<?php echo htmlspecialchars($formData['Liczba_rat'] ?? ''); ?>">
                    </div>
                    <div id="installmentFields" class="dynamic-fields">
                        <!-- Dynamic installment fields will be added here -->
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-success">Zapisz</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle dynamic fields for agents
        document.getElementById('Liczba_agentow')?.addEventListener('change', function() {
            const agentFields = document.getElementById('agentFields');
            agentFields.innerHTML = '';
            
            for (let i = 1; i <= this.value; i++) {
                const agentValue = <?php echo json_encode($formData); ?>[`Prowizja Agent ${i}`] || '';
                agentFields.innerHTML += `
                    <div class="mb-3">
                        <label for="Prowizja_Agent_${i}" class="form-label">Prowizja Agenta ${i}</label>
                        <input type="number" step="0.01" class="form-control" id="Prowizja_Agent_${i}" name="Prowizja Agent ${i}" required
                               value="${agentValue}">
                    </div>
                `;
            }
        });

        // Handle dynamic fields for installments
        document.getElementById('Liczba_rat')?.addEventListener('change', function() {
            const installmentFields = document.getElementById('installmentFields');
            installmentFields.innerHTML = '';
            
            for (let i = 1; i <= this.value; i++) {
                const installmentValue = <?php echo json_encode($formData); ?>[`Rata ${i}`] || '';
                installmentFields.innerHTML += `
                    <div class="mb-3">
                        <label for="Rata_${i}" class="form-label">Rata ${i}</label>
                        <input type="number" step="0.01" class="form-control" id="Rata_${i}" name="Rata ${i}" required
                               value="${installmentValue}">
                    </div>
                `;
            }
        });

        // Form validation
        function validateForm(form) {
            const data = new FormData(form);
            console.log('Form data before submission:');
            for (let [key, value] of data.entries()) {
                console.log(`${key}: ${value}`);
            }

            // Check if Wywalczona kwota is empty
            const wywalczonaKwota = form.querySelector('[name="Wywalczona_kwota"]');
            if (!wywalczonaKwota.value || parseFloat(wywalczonaKwota.value) <= 0) {
                console.log('Wywalczona kwota is empty or zero!');
                wywalczonaKwota.classList.add('is-invalid');
                return false;
            }

            return true;
        }

        // Add event listener to form
        document.getElementById('wizardForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate all required fields
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value || (field.type === 'number' && parseFloat(field.value) <= 0)) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (isValid && validateForm(this)) {
                this.submit();
            }
        });

        // Initialize dynamic fields if values exist
        document.addEventListener('DOMContentLoaded', function() {
            const liczbaAgentow = document.getElementById('Liczba_agentow');
            const liczbaRat = document.getElementById('Liczba_rat');
            
            if (liczbaAgentow && liczbaAgentow.value) {
                liczbaAgentow.dispatchEvent(new Event('change'));
            }
            
            if (liczbaRat && liczbaRat.value) {
                liczbaRat.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>
