<!DOCTYPE html>
<html lang="pl">

<head>
  <meta charset="UTF-8">
  <title>Edytuj rekord</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Style komunikatów błędów */
    .error-message {
      color: red;
      font-size: 0.9em;
      margin-top: 5px;
    }

    /* Wyróżnienie pola z błędem */
    .input-error {
      border: 1px solid red;
    }

    /* Style for calculation results */
    .calculation-result {
      display: inline-block;
      margin-left: 10px;
      padding: 5px 10px;
      background-color: #e3f2fd;
      border-radius: 4px;
      font-weight: 500;
      color: #1976D2;
    }

    .field-group {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
    }

    .field-group label {
      width: 100%;
    }

    .field-label {
      display: block;
      margin-bottom: 5px;
    }

    .calculation-section {
      background-color: #f5f5f5;
      border-radius: 8px;
      padding: 15px;
      margin-top: 20px;
      border-left: 4px solid #1976D2;
    }

    .calculation-title {
      font-weight: 600;
      margin-bottom: 10px;
      color: #1976D2;
    }

    .installment-split {
      margin-top: 10px;
      padding: 8px;
      background-color: #eef7ff;
      border-radius: 6px;
    }

    .split-item {
      display: flex;
      justify-content: space-between;
      padding: 5px 0;
      border-bottom: 1px dashed #ccc;
    }

    .split-item:last-child {
      border-bottom: none;
    }
    
    .back-link {
      display: inline-block;
      margin-bottom: 15px;
      color: #1976D2;
      text-decoration: none;
      transition: all 0.3s ease;
    }
    
    .back-link:hover {
      color: #0b5394;
      text-decoration: underline;
    }
  </style>
</head>

<body class="wizard">
  <?php include_once __DIR__ . '/components/user_info.php'; ?>
  <nav class="cleannav">
    <ul class="cleannav__list">
      <li class="cleannav__item">
        <a href="/" class="cleannav__link" data-tooltip="Strona główna">
          <i class="fa-solid fa-house cleannav__icon"></i>
        </a>
      </li>
      <li class="cleannav__item">
        <a href="/invoices" class="cleannav__link" data-tooltip="Faktury">
          <i class="fa-solid fa-file-invoice cleannav__icon"></i>
        </a>
      </li>
      <li class="cleannav__item">
        <a href="/agents" class="cleannav__link" data-tooltip="Dodaj agenta">
          <i class="fa-solid fa-user-plus cleannav__icon"></i>
        </a>
      </li>
      <li class="cleannav__item">
        <a href="/table" class="cleannav__link" data-tooltip="Tabela z danymi">
          <i class="fa-solid fa-table cleannav__icon"></i>
        </a>
      </li>
      <li class="cleannav__item">
        <a href="/wizard" class="cleannav__link" data-tooltip="Kreator rekordu">
          <i class="fa-solid fa-wand-magic-sparkles cleannav__icon"></i>
        </a>
      </li>
      <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
      <li class="cleannav__item">
        <a href="/database" class="cleannav__manage-btn" data-tooltip="Zarządzaj bazą">
          <i class="fa-solid fa-database cleannav__icon"></i>
        </a>
      </li>
      <?php endif; ?>
      <li class="cleannav__item">
        <a href="/logout" class="cleannav__link" data-tooltip="Wyloguj">
          <i class="fa-solid fa-sign-out-alt cleannav__icon"></i>
        </a>
      </li>
    </ul>
  </nav>

  <?php if (isset($_GET['success'])): ?>
    <?php
      include 'Notify.php';
      Notify::show("Rekord został zaktualizowany pomyślnie!", "success", 4000);
    ?>
  <?php endif; ?>

  <?php
    // Pobieranie agentów z bazy danych
    global $pdo;
    $agentsQuery = $pdo->query("SELECT agent_id, imie, nazwisko FROM agenci ORDER BY nazwisko, imie");
    $agents = $agentsQuery->fetchAll(PDO::FETCH_ASSOC);
  ?>

  <form id="wizardForm" method="post" action="/case/edit/<?php echo $case['id']; ?>">
    <h2>Edytuj rekord</h2>
    <a href="/table" class="back-link">⬅️ Powrót do tabeli</a>

    <!-- Globalne, ewentualne komunikaty (opcjonalne) -->
    <div id="globalErrorContainer" style="color:red; font-weight:bold;"></div>

    <fieldset>
      <legend>Podstawowe dane</legend>
      <div class="field-group">
        <label>
          <span class="field-label">Nazwa sprawy:</span>
          <input type="text" name="case_name" required value="<?php echo htmlspecialchars($case['case_name'] ?? '', ENT_QUOTES); ?>">
          <span class="error-message" id="error_case_name"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Zakończona:</span>
          <input type="checkbox" name="is_completed" <?php echo ($case['is_completed'] ?? false) ? 'checked' : ''; ?>>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Wywalczona kwota:</span>
          <input type="number" step="0.01" name="amount_won" id="amount_won" value="<?php echo htmlspecialchars($case['amount_won'] ?? '', ENT_QUOTES); ?>">
          <span class="error-message" id="error_amount_won"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Opłata wstępna:</span>
          <input type="number" step="0.01" name="upfront_fee" id="upfront_fee" value="<?php echo htmlspecialchars($case['upfront_fee'] ?? '', ENT_QUOTES); ?>">
          <span class="error-message" id="error_upfront_fee"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Procent success fee:</span>
          <input type="number" step="0.01" name="success_fee_percentage" id="success_fee_percentage" value="<?php echo htmlspecialchars($case['success_fee_percentage'] ?? '', ENT_QUOTES); ?>">
          <span class="error-message" id="error_success_fee_percentage"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Prowizja Kuby:</span>
          <input type="number" step="0.01" name="kuba_percentage" id="kuba_percentage" value="<?php echo htmlspecialchars($case['kuba_percentage'] ?? '', ENT_QUOTES); ?>">
          <span class="error-message" id="error_kuba_percentage"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Numer faktury:</span>
          <input type="text" name="kuba_invoice_number" value="<?php echo htmlspecialchars($case['kuba_invoice_number'] ?? '', ENT_QUOTES); ?>">
        </label>
      </div>

      <!-- Sekcja wyświetlająca wyniki obliczeń -->
      <div class="calculation-section">
        <div class="calculation-title">Wyniki obliczeń:</div>
        <div class="split-item">
          <span>Całość prowizji:</span>
          <span id="total_commission"><?php echo number_format((float)($case['total_commission'] ?? 0), 2, ',', ' '); ?> zł</span>
        </div>
        <div class="split-item">
          <span>Do wypłaty Kuba:</span>
          <span id="kuba_payout_percentage"><?php echo number_format((float)($case['kuba_payout'] ?? 0), 2, ',', ' '); ?>%</span>
        </div>
      </div>
    </fieldset>

    <fieldset id="agentsSection">
      <legend>Agenci</legend>
      
      <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="field-group">
          <label>
            <span class="field-label">Agent <?php echo $i; ?>:</span>
            <select name="agent<?php echo $i; ?>_id" id="agent<?php echo $i; ?>_id" class="agent-select">
              <option value="">-- Wybierz agenta --</option>
              <?php foreach ($agents as $agent): ?>
                <?php 
                $isSelected = isset($assignedAgents[$i]) && $assignedAgents[$i]['agent_id'] == $agent['agent_id'];
                ?>
                <option value="<?php echo $agent['agent_id']; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($agent['imie'] . ' ' . $agent['nazwisko'], ENT_QUOTES); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        
        <div class="field-group">
          <label>
            <span class="field-label">Procent prowizji Agenta <?php echo $i; ?>:</span>
            <input type="number" step="0.01" name="agent<?php echo $i; ?>_percentage" id="agent<?php echo $i; ?>_percentage" class="agent-percentage"
                  value="<?php echo isset($assignedAgents[$i]) ? htmlspecialchars($assignedAgents[$i]['percentage'], ENT_QUOTES) : ''; ?>">
            <div class="calculation-result" id="agent<?php echo $i; ?>_amount">0.00 zł</div>
          </label>
        </div>
      <?php endfor; ?>
      
      <!-- Wspólny kontener na błędy agentów -->
      <div id="error_agents" class="error-message"></div>
    </fieldset>

    <fieldset id="installmentsSection">
      <legend>Raty</legend>
      
      <!-- Rata 1 -->
      <div class="field-group">
        <label>
          <span class="field-label">Rata 1:</span>
          <input type="number" step="0.01" name="installment1_amount" id="installment1_amount" class="installment-amount"
                value="<?php echo htmlspecialchars($case['installment1_amount'] ?? '', ENT_QUOTES); ?>">
          <span class="error-message" id="error_installment1"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Opłacona Rata 1:</span>
          <input type="checkbox" name="installment1_paid" <?php echo ($case['installment1_paid'] ?? false) ? 'checked' : ''; ?>>
        </label>
      </div>
      
      <!-- Podział raty 1 -->
      <div class="installment-split" id="installment1_split">
        <div class="split-item">
          <span>Kuba (do wypłaty):</span>
          <span id="installment1_kuba">0.00 zł</span>
        </div>
        <div id="installment1_agents_split"></div>
      </div>
      
      <!-- Rata 2 -->
      <div class="field-group">
        <label>
          <span class="field-label">Rata 2:</span>
          <input type="number" step="0.01" name="installment2_amount" id="installment2_amount" class="installment-amount"
                value="<?php echo htmlspecialchars($case['installment2_amount'] ?? '', ENT_QUOTES); ?>">
          <span class="error-message" id="error_installment2"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Opłacona Rata 2:</span>
          <input type="checkbox" name="installment2_paid" <?php echo ($case['installment2_paid'] ?? false) ? 'checked' : ''; ?>>
        </label>
      </div>
      
      <!-- Podział raty 2 -->
      <div class="installment-split" id="installment2_split">
        <div class="split-item">
          <span>Kuba (do wypłaty):</span>
          <span id="installment2_kuba">0.00 zł</span>
        </div>
        <div id="installment2_agents_split"></div>
      </div>
      
      <!-- Rata 3 -->
      <div class="field-group">
        <label>
          <span class="field-label">Rata 3:</span>
          <input type="number" step="0.01" name="installment3_amount" id="installment3_amount" class="installment-amount"
                value="<?php echo htmlspecialchars($case['installment3_amount'] ?? '', ENT_QUOTES); ?>">
          <span class="error-message" id="error_installment3"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Opłacona Rata 3:</span>
          <input type="checkbox" name="installment3_paid" <?php echo ($case['installment3_paid'] ?? false) ? 'checked' : ''; ?>>
        </label>
      </div>
      
      <!-- Podział raty 3 -->
      <div class="installment-split" id="installment3_split">
        <div class="split-item">
          <span>Kuba (do wypłaty):</span>
          <span id="installment3_kuba">0.00 zł</span>
        </div>
        <div id="installment3_agents_split"></div>
      </div>
      
      <!-- Rata 4 (tylko status) -->
      <div class="field-group">
        <label>
          <span class="field-label">Opłacona Rata 4:</span>
          <input type="checkbox" name="final_installment_paid" <?php echo ($case['final_installment_paid'] ?? false) ? 'checked' : ''; ?>>
        </label>
      </div>
      
      <!-- Rata 4 (obliczana) -->
      <div class="calculation-section">
        <div class="calculation-title">Ostatnia rata (obliczana automatycznie):</div>
        <div class="split-item">
          <span>Rata 4:</span>
          <span id="final_installment"><?php echo number_format((float)($case['final_installment_amount'] ?? 0), 2, ',', ' '); ?> zł</span>
        </div>
        
        <!-- Podział ostatniej raty -->
        <div class="installment-split" id="final_installment_split">
          <div class="split-item">
            <span>Kuba (do wypłaty):</span>
            <span id="final_installment_kuba">0.00 zł</span>
          </div>
          <div id="final_installment_agents_split"></div>
        </div>
      </div>
    </fieldset>

    <button type="submit" class="btn" id="submitButton">Zapisz zmiany</button>
  </form>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Lista agentów pobrana z PHP
      const agents = <?php echo json_encode($agents); ?>;
      
      // Elementy formularza
      const amountWonInput = document.getElementById('amount_won');
      const upfrontFeeInput = document.getElementById('upfront_fee');
      const successFeeInput = document.getElementById('success_fee_percentage');
      const kubaPercentageInput = document.getElementById('kuba_percentage');
      
      // Funkcja do formatowania wartości walutowych
      function formatCurrency(value) {
        return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' zł';
      }
      
      // Funkcja do formatowania procentów
      function formatPercent(value) {
        return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + '%';
      }
      
      // Funkcja walidacyjna
      function validateForm() {
        let formValid = true;

        // Czyszczenie komunikatów dla statycznych pól
        document.getElementById('error_case_name').innerText = "";
        document.getElementById('error_amount_won').innerText = "";
        document.getElementById('error_upfront_fee').innerText = "";
        document.getElementById('error_success_fee_percentage').innerText = "";
        document.getElementById('error_kuba_percentage').innerText = "";
        // Czyszczenie zbiorczego kontenera błędów agentów
        document.getElementById('error_agents').innerText = "";
        
        // Czyszczenie komunikatów o błędach dla rat
        for (let i = 1; i <= 3; i++) {
          const errorSpan = document.getElementById(`error_installment${i}`);
          if (errorSpan) {
            errorSpan.innerText = "";
          }
        }

        // 1. Walidacja pola nazwy sprawy
        const caseNameInput = document.querySelector('input[name="case_name"]');
        const caseName = caseNameInput.value.trim();
        if (!caseName) {
          document.getElementById('error_case_name').innerText = "Nazwa sprawy jest wymagana.";
          caseNameInput.classList.add('input-error');
          formValid = false;
        } else {
          caseNameInput.classList.remove('input-error');
        }

        // 2. Walidacja pól numerycznych: amount_won, upfront_fee, success_fee_percentage
        const numericFields = ["amount_won", "upfront_fee", "success_fee_percentage"];
        numericFields.forEach(fieldName => {
          const input = document.querySelector(`input[name="${fieldName}"]`);
          const errorEl = document.getElementById(`error_${fieldName}`);
          if (input.value !== "") {
            const num = parseFloat(input.value);
            if (isNaN(num) || num < 0) {
              errorEl.innerText = `Pole "${fieldName}" musi być liczbą nieujemną.`;
              input.classList.add('input-error');
              formValid = false;
            } else {
              input.classList.remove('input-error');
            }
          } else {
            errorEl.innerText = "";
            input.classList.remove('input-error');
          }
        });

        // 3. Walidacja prowizji Kuby – musi być liczba w przedziale 0-100%
        const kubaInput = document.querySelector('input[name="kuba_percentage"]');
        const errorKuba = document.getElementById('error_kuba_percentage');
        let kubaValue = parseFloat(kubaInput.value);
        if (isNaN(kubaValue)) {
          errorKuba.innerText = "Prowizja Kuby musi być liczbą.";
          kubaInput.classList.add('input-error');
          formValid = false;
        } else if (kubaValue < 0 || kubaValue > 100) {
          errorKuba.innerText = "Prowizja Kuby musi być z przedziału 0 - 100%.";
          kubaInput.classList.add('input-error');
          formValid = false;
        } else {
          errorKuba.innerText = "";
          kubaInput.classList.remove('input-error');
        }

        // 4. Walidacja procentów agentów – wszystkie komunikaty zbieramy w jednym kontenerze
        let agentErrors = [];
        let sumAgentPercents = 0;
        
        // Sprawdzamy czy ten sam agent nie został wybrany więcej niż raz
        const selectedAgents = new Set();
        const duplicateAgents = [];
        
        const agentInputs = document.querySelectorAll('.agent-percentage');
        agentInputs.forEach((input, index) => {
          // Sprawdzanie zduplikowanych agentów
          const agentId = index + 1;
          const agentSelect = document.getElementById(`agent${agentId}_id`);
          
          if (agentSelect && agentSelect.value) {
            const agentValue = agentSelect.value;
            
            if (selectedAgents.has(agentValue)) {
              duplicateAgents.push(agentId);
              agentSelect.classList.add('input-error');
            } else {
              selectedAgents.add(agentValue);
              agentSelect.classList.remove('input-error');
            }
          }
          
          // Walidacja procentów
          if (input.value !== "") {
            const num = parseFloat(input.value);
            if (isNaN(num) || num < 0 || num > 100) {
              agentErrors.push(`Prowizja agenta ${index + 1} musi być z przedziału 0-100.`);
              input.classList.add('input-error');
            } else {
              input.classList.remove('input-error');
              sumAgentPercents += num;
              if (!isNaN(kubaValue) && num > kubaValue) {
                agentErrors.push(`Prowizja agenta ${index + 1} nie może przekraczać prowizji Kuby (${kubaValue}%).`);
                input.classList.add('input-error');
              }
            }
          }
        });
        
        // Dodaj błąd, jeśli znaleziono zduplikowanych agentów
        if (duplicateAgents.length > 0) {
          agentErrors.push(`Agenci ${duplicateAgents.join(', ')} są zduplikowani. Każdy agent może być wybrany tylko raz.`);
          formValid = false;
        }
        
        if (!isNaN(kubaValue) && sumAgentPercents > kubaValue) {
          agentErrors.push(`Suma agentów (${sumAgentPercents}%) nie może przekraczać prowizji Kuby (${kubaValue}%).`);
          formValid = false;
        }
        if (agentErrors.length > 0) {
          document.getElementById('error_agents').innerText = agentErrors.join(" ");
          formValid = false;
        } else {
          document.getElementById('error_agents').innerText = "";
        }

        // 5. Walidacja rat – każda rata musi być liczbą nieujemną
        let sumInstallments = 0;
        const installmentInputs = document.querySelectorAll('.installment-amount');
        installmentInputs.forEach((input, index) => {
          const errorSpan = document.getElementById(`error_installment${index + 1}`);
          if (errorSpan) errorSpan.innerText = "";
          if (input.value !== "") {
            const num = parseFloat(input.value);
            if (isNaN(num) || num < 0) {
              if (errorSpan) errorSpan.innerText = "Rata musi być liczbą nieujemną.";
              input.classList.add('input-error');
              formValid = false;
            } else {
              input.classList.remove('input-error');
              sumInstallments += num;
            }
          }
        });
        
        // 6. Nowa walidacja: suma rat musi być równa opłacie wstępnej
        const upfrontFee = parseFloat(document.getElementById('upfront_fee').value) || 0;
        if (sumInstallments != upfrontFee) {
          const errorMessage = `Suma rat (${sumInstallments.toFixed(2)} zł) musi być równa opłacie wstępnej (${upfrontFee.toFixed(2)} zł).`;
          installmentInputs.forEach((input, index) => {
            const errorSpan = document.getElementById(`error_installment${index + 1}`);
            if (errorSpan && errorSpan.innerText === "") {
              errorSpan.innerText = errorMessage;
              input.classList.add('input-error');
            }
          });
          formValid = false;
        }

        // Blokujemy przycisk wysyłania, jeśli formularz zawiera błędy
        document.getElementById('submitButton').disabled = !formValid;
        return formValid;
      }
      
      // Aktualizuj wartości przy zmianie pól
      const updateValues = function() {
        // Pobierz wartości
        const amountWon = parseFloat(amountWonInput.value) || 0;
        const upfrontFee = parseFloat(upfrontFeeInput.value) || 0;
        const successFee = parseFloat(successFeeInput.value) || 0;
        const kubaPercentage = parseFloat(kubaPercentageInput.value) || 0;
        
        // Obliczenia
        const totalCommission = upfrontFee + (amountWon * successFee / 100);
        document.getElementById('total_commission').textContent = totalCommission.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' zł';
        
        // Oblicz to wypłaty dla Kuby
        let agentPercentageSum = 0;
        for (let i = 1; i <= 3; i++) {
          const agentPercentInput = document.querySelector(`input[name="agent${i}_percentage"]`);
          if (agentPercentInput && agentPercentInput.value) {
            agentPercentageSum += parseFloat(agentPercentInput.value) || 0;
          }
        }
        
        const kubaPayout = Math.max(0, Math.min(100, kubaPercentage - agentPercentageSum));
        document.getElementById('kuba_payout_percentage').textContent = kubaPayout.toFixed(2).replace('.', ',') + '%';
        
        // Oblicz kwotę do wypłaty dla Kuby
        const kubaPayoutAmount = totalCommission * (kubaPayout / 100);
        document.getElementById('kuba_payout_amount').textContent = kubaPayoutAmount.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' zł';
        
        // Oblicz ostatnią ratę
        const installment1 = parseFloat(document.getElementById('installment1_amount').value) || 0;
        const installment2 = parseFloat(document.getElementById('installment2_amount').value) || 0;
        const installment3 = parseFloat(document.getElementById('installment3_amount').value) || 0;
        
        const finalInstallment = Math.max(0, totalCommission - (installment1 + installment2 + installment3));
        document.getElementById('final_installment').textContent = 
          finalInstallment.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' zł';
           
        // Pokaż kwoty dla agentów
        const agentPercents = [];
        for (let i = 1; i <= 3; i++) {
          const agentPercentInput = document.querySelector(`input[name="agent${i}_percentage"]`);
          if (agentPercentInput) {
            const agentPercent = parseFloat(agentPercentInput.value) || 0;
            agentPercents[i-1] = agentPercent;
            const agentAmount = totalCommission * (agentPercent / 100);
            const agentAmountEl = document.getElementById(`agent${i}_amount`);
            if (agentAmountEl) {
              agentAmountEl.textContent = agentAmount.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' zł';
            }
          }
        }
        
        // Aktualizacja podziału rat
        const installmentAmounts = [installment1, installment2, installment3, finalInstallment];
        const installmentNames = ["installment1", "installment2", "installment3", "final_installment"];
        
        for (let i = 0; i < installmentAmounts.length; i++) {
          const installmentName = installmentNames[i];
          const installmentAmount = installmentAmounts[i];
          
          // Kwota dla Kuby z tej raty
          const kubaInstallment = installmentAmount * (kubaPayout / 100);
          document.getElementById(`${installmentName}_kuba`).textContent = 
            kubaInstallment.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' zł';
          
          // Pokaż podział dla agentów dla tej raty
          if (i < 3) { // Dla normalnych rat (nie ostatniej)
            const agentsSplitContainer = document.getElementById(`${installmentName}_agents_split`);
            agentsSplitContainer.innerHTML = '';
            
            for (let j = 1; j <= 3; j++) {
              const agentPercent = agentPercents[j-1];
              if (agentPercent > 0) {
                const agentInstallmentAmount = installmentAmount * (agentPercent / 100);
                
                const agentSplitItem = document.createElement('div');
                agentSplitItem.className = 'split-item';
                agentSplitItem.innerHTML = `
                  <span>Agent ${j}:</span>
                  <span>${agentInstallmentAmount.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, " ")} zł</span>
                `;
                
                agentsSplitContainer.appendChild(agentSplitItem);
              }
            }
          } else { // Dla ostatniej raty (finalInstallment)
            const agentsFinalSplitContainer = document.getElementById(`${installmentName}_agents_split`);
            agentsFinalSplitContainer.innerHTML = '';
            
            for (let j = 1; j <= 3; j++) {
              const agentPercent = agentPercents[j-1];
              if (agentPercent > 0) {
                const agentFinalInstallment = installmentAmount * (agentPercent / 100);
                
                const agentSplitItem = document.createElement('div');
                agentSplitItem.className = 'split-item';
                agentSplitItem.innerHTML = `
                  <span>Agent ${j}:</span>
                  <span>${agentFinalInstallment.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, " ")} zł</span>
                `;
                
                agentsFinalSplitContainer.appendChild(agentSplitItem);
              }
            }
          }
        }
      };
      
      // Dodaj nasłuchiwacze zdarzeń
      const inputs = [
        amountWonInput, upfrontFeeInput, successFeeInput, kubaPercentageInput,
        document.getElementById('installment1_amount'),
        document.getElementById('installment2_amount'),
        document.getElementById('installment3_amount')
      ];
      
      inputs.forEach(input => {
        if (input) input.addEventListener('input', function() {
          updateValues();
          validateForm();
        });
      });
      
      // Dodaj nasłuchiwacze do pól procentowych agentów
      for (let i = 1; i <= 3; i++) {
        const agentPercentInput = document.querySelector(`input[name="agent${i}_percentage"]`);
        if (agentPercentInput) {
          agentPercentInput.addEventListener('input', function() {
            updateValues();
            validateForm();
          });
        }
        
        // Dodaj nasłuchiwacze do selectów agentów
        const agentSelect = document.getElementById(`agent${i}_id`);
        if (agentSelect) {
          agentSelect.addEventListener('change', function() {
            validateForm();
          });
        }
      }
      
      // Walidacja formularza na każdej zmianie
      document.getElementById('wizardForm').addEventListener('input', validateForm);
      
      // Zapobieganie przesłaniu formularza, jeśli nie jest poprawny
      document.getElementById('wizardForm').addEventListener('submit', function(e) {
        if (!validateForm()) {
          e.preventDefault();
        }
      });
      
      // Inicjalizacja wartości
      updateValues();
      validateForm();
    });
  </script>
</body>

</html> 