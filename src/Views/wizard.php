<!DOCTYPE html>
<html lang="pl">

<head>
  <meta charset="UTF-8">
  <title>Dodaj rekord</title>
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
  </style>
</head>

<body class="wizard">
  <nav class="cleannav">
    <ul class="cleannav__list">
      <li class="cleannav__item">
        <a href="/" class="cleannav__link">
          <i class="fa-solid fa-house cleannav__icon"></i>
          Home
        </a>
      </li>
      <li class="cleannav__item">
        <a href="/agents" class="cleannav__link">
          <i class="fa-solid fa-plus cleannav__icon"></i>
          Dodaj Agenta
        </a>
      </li>
      <li class="cleannav__item">
        <a href="/table" class="cleannav__link">
          <i class="fa-solid fa-briefcase cleannav__icon"></i>
          Tabela Z Danymi
        </a>
      </li>
      <li class="cleannav__item">
        <a href="/wizard" class="cleannav__link">
          <i class="fa-solid fa-database cleannav__icon"></i>
          Kreator Rekordu
        </a>
      </li>
      <li class="cleannav__item">
        <a href="/logout" class="cleannav__link">
          <i class="fa-solid fa-sign-out-alt cleannav__icon"></i>
          Wyloguj (<?= htmlspecialchars($_SESSION['user'] ?? 'Gość') ?>)
        </a>
      </li>
    </ul>
  </nav>

  <?php if (isset($_GET['success'])): ?>
    <?php
      include 'Notify.php';
      Notify::show("Rekord został dodany pomyślnie!", "success", 4000);
    ?>
  <?php endif; ?>

  <?php
    // Pobieranie agentów z bazy danych
    global $pdo;
    $agentsQuery = $pdo->query("SELECT agent_id, imie, nazwisko FROM agenci ORDER BY nazwisko, imie");
    $agents = $agentsQuery->fetchAll(PDO::FETCH_ASSOC);
  ?>

  <form id="wizardForm" method="post" action="/wizard">
    <h2>Dodaj rekord do bazy</h2>

    <!-- Globalne, ewentualne komunikaty (opcjonalne) -->
    <div id="globalErrorContainer" style="color:red; font-weight:bold;"></div>

    <fieldset>
      <legend>Podstawowe dane</legend>
      <div class="field-group">
        <label>
          <span class="field-label">Nazwa sprawy:</span>
          <input type="text" name="case_name" required>
          <span class="error-message" id="error_case_name"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Zakończona:</span>
          <input type="checkbox" name="is_completed">
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Wywalczona kwota:</span>
          <input type="number" step="0.01" name="amount_won" id="amount_won">
          <span class="error-message" id="error_amount_won"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Opłata wstępna:</span>
          <input type="number" step="0.01" name="upfront_fee" id="upfront_fee">
          <span class="error-message" id="error_upfront_fee"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Procent success fee:</span>
          <input type="number" step="0.01" name="success_fee_percentage" id="success_fee_percentage">
          <span class="error-message" id="error_success_fee_percentage"></span>
        </label>
      </div>
      
      <div class="field-group">
        <label>
          <span class="field-label">Prowizja Kuby:</span>
          <input type="number" step="0.01" name="kuba_percentage" id="kuba_percentage">
          <span class="error-message" id="error_kuba_percentage"></span>
        </label>
      </div>

      <!-- Sekcja wyświetlająca wyniki obliczeń -->
      <div class="calculation-section">
        <div class="calculation-title">Wyniki obliczeń:</div>
        <div class="split-item">
          <span>Całość prowizji:</span>
          <span id="total_commission">0.00 zł</span>
        </div>
        <div class="split-item">
          <span>Do wypłaty Kuba:</span>
          <span id="kuba_payout_percentage">0.00%</span>
        </div>
        <div class="split-item">
          <span>Kwota do wypłaty Kuba:</span>
          <span id="kuba_payout_amount">0.00 zł</span>
        </div>
      </div>
    </fieldset>

    <fieldset class="controls">
      <legend>Konfiguracja</legend>
      <label>
        Liczba agentów (0-5):
        <input id="agentsCount" type="number" min="0" max="5" value="0">
      </label>
      <br>
      <label>
        Liczba rat (0-4):
        <input id="installmentsCount" type="number" min="0" max="4" value="0">
      </label>
    </fieldset>

    <fieldset id="agentsSection">
      <legend>Agenci</legend>
      <!-- Dynamicznie generowane pola agentów -->
    </fieldset>

    <!-- Tu umieszczamy wspólny kontener na błędy agentów -->
    <div id="error_agents" class="error-message"></div>

    <fieldset id="installmentsSection">
      <legend>Raty</legend>
      <!-- Dynamicznie generowane pola rat -->
    </fieldset>
    
    <!-- Sekcja podsumowania rat -->
    <fieldset id="installmentSummarySection" style="display: none;">
      <legend>Podsumowanie rat</legend>
      <div id="installmentSummary"></div>
      <div class="split-item">
        <span>Ostatnia rata:</span>
        <span id="final_installment">0.00 zł</span>
      </div>
    </fieldset>

    <button type="submit" class="btn" id="submitButton">Zapisz rekord</button>
  </form>

  <script>
    const agentsInput = document.getElementById('agentsCount');
    const instInput = document.getElementById('installmentsCount');
    const agentsSection = document.getElementById('agentsSection');
    const instSection = document.getElementById('installmentsSection');
    const installmentSummarySection = document.getElementById('installmentSummarySection');

    // Lista agentów pobrana z PHP
    const agents = <?php echo json_encode($agents); ?>;

    // Funkcja pomocnicza: przycinanie wartości do zakresu [min..max]
    function clampInput(el) {
      const min = Number(el.min) || 0;
      const max = Number(el.max);
      let val = Number(el.value);
      if (isNaN(val)) {
        el.value = min;
      } else if (val > max) {
        el.value = max;
      } else if (val < min) {
        el.value = min;
      }
    }

    // Funkcja renderująca pola agentów
    function renderAgents() {
      // Czyścimy zawartość sekcji
      agentsSection.innerHTML = '<legend>Agenci</legend>';
      const count = Number(agentsInput.value);
      // Kontener dla listy agentów
      const agentsContainer = document.createElement('div');
      agentsContainer.id = 'agentsContainer';
      for (let i = 1; i <= count; i++) {
        const container = document.createElement('div');
        container.className = 'agent-container field-group';

        // Dropdown do wyboru agenta
        const selectContainer = document.createElement('label');
        selectContainer.innerHTML = `Agent ${i}: `;
        const select = document.createElement('select');
        select.name = `agent${i}_id`;
        select.id = `agent${i}_id`;
        select.className = 'agent-select';

        // Pusta opcja
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = '-- Wybierz agenta --';
        select.appendChild(emptyOption);

        // Opcje agentów
        agents.forEach(agent => {
          const option = document.createElement('option');
          option.value = agent.agent_id;
          option.textContent = `${agent.imie} ${agent.nazwisko}`;
          select.appendChild(option);
        });

        selectContainer.appendChild(select);
        container.appendChild(selectContainer);

        // Pole procentu dla agenta
        const percentContainer = document.createElement('label');
        percentContainer.innerHTML = ` Procent: `;
        const percentInput = document.createElement('input');
        percentInput.type = 'number';
        percentInput.step = '0.01';
        percentInput.name = `agent${i}_percentage`;
        percentInput.id = `agent${i}_percentage`;
        percentInput.className = 'agent-percentage';
        percentInput.addEventListener('input', calculateAll);
        percentContainer.appendChild(percentInput);

        // Dodanie elementu dla wyświetlania kwoty agenta
        const amountDisplay = document.createElement('div');
        amountDisplay.className = 'calculation-result';
        amountDisplay.id = `agent${i}_amount`;
        amountDisplay.textContent = '0.00 zł';
        percentContainer.appendChild(amountDisplay);

        container.appendChild(percentContainer);
        agentsContainer.appendChild(container);
      }
      agentsSection.appendChild(agentsContainer);
      
      // Dodajemy wspólny kontener (jeśli nie został jeszcze dodany)
      if (!document.getElementById('error_agents')) {
        const agentErrorContainer = document.createElement('div');
        agentErrorContainer.id = 'error_agents';
        agentErrorContainer.className = 'error-message';
        agentsSection.appendChild(agentErrorContainer);
      }
    }

    // Funkcja renderująca pola rat
    function renderInstallments() {
      instSection.innerHTML = '<legend>Raty</legend>';
      const count = Number(instInput.value);
      
      for (let i = 1; i <= count; i++) {
        const container = document.createElement('div');
        container.className = 'field-group';
        
        const lbl = document.createElement('label');
        lbl.innerHTML = `<span class="field-label">Rata ${i} kwota:</span>`;
        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.01';
        input.name = `installment${i}_amount`;
        input.id = `installment${i}_amount`;
        input.className = 'installment-amount';
        input.addEventListener('input', calculateAll);
        lbl.appendChild(input);

        // Dodajemy span dla komunikatu błędu dla raty
        const errorSpan = document.createElement('span');
        errorSpan.className = 'error-message';
        errorSpan.id = `error_installment${i}`;
        lbl.appendChild(errorSpan);
        
        container.appendChild(lbl);
        
        // Dodanie sekcji podziału raty
        const splitContainer = document.createElement('div');
        splitContainer.className = 'installment-split';
        splitContainer.id = `installment${i}_split`;
        splitContainer.innerHTML = `
          <div class="split-item">
            <span>Kuba (do wypłaty):</span>
            <span id="installment${i}_kuba">0.00 zł</span>
          </div>
          <div id="installment${i}_agents_split"></div>
        `;
        
        container.appendChild(splitContainer);
        instSection.appendChild(container);
      }
      
      // Pokaż sekcję podsumowania rat jeśli są jakieś raty
      if (count > 0) {
        installmentSummarySection.style.display = 'block';
      } else {
        installmentSummarySection.style.display = 'none';
      }
      
      // Wywołaj obliczenia, aby zaktualizować wartości
      calculateAll();
    }

    // Funkcja walidacyjna z dodatkowymi obliczeniami
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
      const installmentInputs = document.querySelectorAll('input[name^="installment"][name$="_amount"]');
      installmentInputs.forEach((input, index) => {
        const errorSpan = document.getElementById(`error_installment${index + 1}`);
        if (errorSpan) {
          errorSpan.innerText = "";
        }
      });

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
      const agentInputs = document.querySelectorAll('input[name^="agent"][name$="_percentage"]');
      agentInputs.forEach((input, index) => {
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
      if (!isNaN(kubaValue) && sumAgentPercents > kubaValue) {
        agentErrors.push(`Suma agentów (${sumAgentPercents}%) nie może przekraczać prowizji Kuby (${kubaValue}%).`);
      }
      if (agentErrors.length > 0) {
        document.getElementById('error_agents').innerText = agentErrors.join(" ");
        formValid = false;
      } else {
        document.getElementById('error_agents').innerText = "";
      }

      // 5. Walidacja rat – każda rata musi być liczbą nieujemną
      let sumInstallments = 0;
      installmentInputs.forEach((input, index) => {
        const errorSpan = document.getElementById(`error_installment${index + 1}`);
        errorSpan.innerText = "";
        if (input.value !== "") {
          const num = parseFloat(input.value);
          if (isNaN(num) || num < 0) {
            errorSpan.innerText = "Rata musi być liczbą nieujemną.";
            input.classList.add('input-error');
            formValid = false;
          } else {
            input.classList.remove('input-error');
            sumInstallments += num;
          }
        }
      });
      
      // 6. Nowa walidacja: suma rat nie może przekraczać opłaty wstępnej
      const upfrontFee = parseFloat(document.getElementById('upfront_fee').value) || 0;
      if (sumInstallments > upfrontFee) {
        const errorMessage = `Suma rat (${sumInstallments.toFixed(2)} zł) nie może przekraczać opłaty wstępnej (${upfrontFee.toFixed(2)} zł).`;
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

    // Funkcja wykonująca wszystkie obliczenia
    function calculateAll() {
      // 1. Obliczenie całkowitej prowizji (F = D + (C * E))
      const amountWon = parseFloat(document.getElementById('amount_won').value) || 0;
      const upfrontFee = parseFloat(document.getElementById('upfront_fee').value) || 0;
      const successFeePercentage = parseFloat(document.getElementById('success_fee_percentage').value) || 0;
      
      const totalCommission = upfrontFee + (amountWon * (successFeePercentage / 100));
      document.getElementById('total_commission').textContent = formatCurrency(totalCommission);
      
      // 2. Obliczenie prowizji Kuby i agentów
      const kubaPercentage = parseFloat(document.getElementById('kuba_percentage').value) || 0;
      let sumAgentPercents = 0;
      
      // Suma procentów agentów
      const agentInputs = document.querySelectorAll('.agent-percentage');
      agentInputs.forEach(input => {
        if (input.value) {
          sumAgentPercents += parseFloat(input.value) || 0;
        }
      });
      
      // Do wypłaty Kuba (H = G - SUM(I:K))
      const kubaPayoutPercentage = Math.max(0, kubaPercentage - sumAgentPercents);
      document.getElementById('kuba_payout_percentage').textContent = formatPercent(kubaPayoutPercentage);
      
      // Kwota do wypłaty dla Kuby
      const kubaPayoutAmount = totalCommission * (kubaPayoutPercentage / 100);
      document.getElementById('kuba_payout_amount').textContent = formatCurrency(kubaPayoutAmount);
      
      // 3. Obliczenie wartości dla każdego agenta
      agentInputs.forEach((input, index) => {
        const agentId = index + 1;
        const agentPercentage = parseFloat(input.value) || 0;
        const agentAmount = totalCommission * (agentPercentage / 100);
        
        const amountDisplay = document.getElementById(`agent${agentId}_amount`);
        if (amountDisplay) {
          amountDisplay.textContent = formatCurrency(agentAmount);
        }
      });
      
      // 4. Obliczenie rat i ich podziału
      let totalInstallments = 0;
      const installmentInputs = document.querySelectorAll('.installment-amount');
      
      // Obliczenie sumy rat
      installmentInputs.forEach(input => {
        if (input.value) {
          totalInstallments += parseFloat(input.value) || 0;
        }
      });
      
      // Obliczenie ostatniej raty (T = F - SUM(N:R))
      const finalInstallment = Math.max(0, totalCommission - totalInstallments);
      document.getElementById('final_installment').textContent = formatCurrency(finalInstallment);
      
      // 5. Aktualizacja podziału rat dla Kuby i agentów
      installmentInputs.forEach((input, index) => {
        const installmentId = index + 1;
        const installmentAmount = parseFloat(input.value) || 0;
        
        // Kwota dla Kuby z tej raty
        const kubaInstallment = installmentAmount * (kubaPayoutPercentage / 100);
        document.getElementById(`installment${installmentId}_kuba`).textContent = formatCurrency(kubaInstallment);
        
        // Kwoty dla agentów z tej raty
        const agentsSplitContainer = document.getElementById(`installment${installmentId}_agents_split`);
        agentsSplitContainer.innerHTML = '';
        
        agentInputs.forEach((agentInput, agentIndex) => {
          const agentId = agentIndex + 1;
          const agentPercentage = parseFloat(agentInput.value) || 0;
          if (agentPercentage > 0) {
            const agentInstallment = installmentAmount * (agentPercentage / 100);
            
            const agentSplitItem = document.createElement('div');
            agentSplitItem.className = 'split-item';
            agentSplitItem.innerHTML = `
              <span>Agent ${agentId}:</span>
              <span>${formatCurrency(agentInstallment)}</span>
            `;
            
            agentsSplitContainer.appendChild(agentSplitItem);
          }
        });
      });
      
      // 6. Aktualizacja podziału ostatniej raty
      if (finalInstallment > 0) {
        // Dodaj sekcję podziału ostatniej raty do sekcji podsumowania
        const finalInstallmentSplit = document.createElement('div');
        finalInstallmentSplit.className = 'installment-split';
        finalInstallmentSplit.innerHTML = `
          <div class="split-item">
            <span>Kuba (ostatnia rata):</span>
            <span>${formatCurrency(finalInstallment * (kubaPayoutPercentage / 100))}</span>
          </div>
        `;
        
        // Dodaj podział dla agentów
        const agentsFinalSplit = document.createElement('div');
        
        agentInputs.forEach((agentInput, agentIndex) => {
          const agentId = agentIndex + 1;
          const agentPercentage = parseFloat(agentInput.value) || 0;
          if (agentPercentage > 0) {
            const agentFinalInstallment = finalInstallment * (agentPercentage / 100);
            
            const agentSplitItem = document.createElement('div');
            agentSplitItem.className = 'split-item';
            agentSplitItem.innerHTML = `
              <span>Agent ${agentId} (ostatnia rata):</span>
              <span>${formatCurrency(agentFinalInstallment)}</span>
            `;
            
            agentsFinalSplit.appendChild(agentSplitItem);
          }
        });
        
        // Wyczyść i dodaj zaktualizowany podział
        const installmentSummary = document.getElementById('installmentSummary');
        installmentSummary.innerHTML = '';
        finalInstallmentSplit.appendChild(agentsFinalSplit);
        installmentSummary.appendChild(finalInstallmentSplit);
      }
    }

    // Funkcje pomocnicze do formatowania wartości
    function formatCurrency(value) {
      return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' zł';
    }
    
    function formatPercent(value) {
      return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + '%';
    }

    // Inicjalizacja dynamicznych pól oraz eventy
    agentsInput.addEventListener('input', () => {
      clampInput(agentsInput);
      renderAgents();
      calculateAll();
      validateForm();
    });
    
    instInput.addEventListener('input', () => {
      clampInput(instInput);
      renderInstallments();
      validateForm();
    });

    // Dodanie eventów do pól podstawowych dla obliczeń na żywo
    document.getElementById('amount_won').addEventListener('input', calculateAll);
    document.getElementById('upfront_fee').addEventListener('input', calculateAll);
    document.getElementById('success_fee_percentage').addEventListener('input', calculateAll);
    document.getElementById('kuba_percentage').addEventListener('input', calculateAll);

    // Walidacja formularza na każdej zmianie
    document.getElementById('wizardForm').addEventListener('input', validateForm);

    // Renderuj pola przy starcie
    renderAgents();
    renderInstallments();
    calculateAll();

    // Zapobieganie przesłaniu formularza, jeśli nie jest poprawny
    document.getElementById('wizardForm').addEventListener('submit', function(e) {
      if (!validateForm()) {
        e.preventDefault();
      }
    });
  </script>
</body>

</html>
