<!DOCTYPE html>
<html lang="pl">

<head>
  <meta charset="UTF-8">
  <title>Dodaj rekord</title>
  <link rel="shortcut icon" href="../../assets/images/favicon.png" type="image/x-icon">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
      <li class="cleannav__item">
        <a href="/test" class="cleannav__link" data-tooltip="Test">
          <i class="fa-solid fa-vial cleannav__icon"></i>
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
      Notify::show("Rekord został dodany pomyślnie!", "success", 4000);
    ?>
  <?php endif; ?>

  <?php
    // Pobieranie agentów z bazy danych (bez Kuby)
    global $pdo;
    $agentsQuery = $pdo->query("SELECT id_agenta, nazwa_agenta FROM agenci 
                               WHERE LOWER(nazwa_agenta) NOT LIKE 'kuba%' 
                               AND LOWER(nazwa_agenta) NOT LIKE 'jakub%' 
                               ORDER BY nazwa_agenta");
    $agents = $agentsQuery->fetchAll(PDO::FETCH_ASSOC);
  ?>

  <form id="wizardForm" method="post" action="/wizard">
    <?php if (isset($_SESSION['wizard_form_data']['case_id']) && !empty($_SESSION['wizard_form_data']['case_id'])): ?>
    <h2>Edytuj sprawę ID: <?php echo htmlspecialchars($_SESSION['wizard_form_data']['case_id']); ?></h2>
    <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($_SESSION['wizard_form_data']['case_id']); ?>">
    <?php else: ?>
    <h2>Dodaj rekord do bazy</h2>
    <?php endif; ?>

    <!-- Globalne, ewentualne komunikaty z ulepszoną stylistyką -->
    <div id="globalErrorContainer">
      <?php if (isset($_SESSION['wizard_errors']) && !empty($_SESSION['wizard_errors'])): ?>
        <div class="error-container">
          <h3>Błędy walidacji</h3>
          <ul>
            <?php foreach ($_SESSION['wizard_errors'] as $error): ?>
              <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php 
          // Ustaw obecny krok na podstawie błędów
          $currentStep = 1; // Domyślnie krok 1
          
          // Jeśli są błędy związane z prowizją Kuby lub agentami/ratami, ustaw krok 2
          foreach ($_SESSION['wizard_errors'] as $error) {
            if (strpos($error, 'Kuba') !== false || 
                strpos($error, 'agent') !== false || 
                strpos($error, 'rat') !== false) {
              $currentStep = 2;
              break;
            }
          }
          
          // Skrypt JS do ustawienia odpowiedniego kroku po załadowaniu strony
          echo '<script>window.onload = function() { goToStep(' . $currentStep . '); setTimeout(highlightErrorFields, 300); };</script>';
          
          // Usuń błędy z sesji po wyświetleniu
          unset($_SESSION['wizard_errors']);
        ?>
      <?php endif; ?>
    </div>

    <!-- Wizard Steps Indicators -->
    <div class="wizard-steps">
      <div class="wizard-step active" data-step="1">
        <div class="step-number">1</div>
        <div class="step-title">Dane podstawowe</div>
      </div>
      <div class="wizard-step" data-step="2">
        <div class="step-number">2</div>
        <div class="step-title">Prowizje i płatności</div>
      </div>
    </div>

    <!-- Step 1 Content: Basic Case Information -->
    <div class="wizard-step-content active" id="step1">
      <fieldset>
        <legend>Podstawowe dane</legend>
        <div class="field-group">
          <label>
            <span class="field-label">Nazwa sprawy (wybierz z listy):</span>
            <select name="case_name" id="case_name">
              <option value="">-- Wybierz nabywcę --</option>
              <?php if (isset($buyers) && !empty($buyers)): ?>
                <?php foreach ($buyers as $buyer): ?>
                  <option value="<?php echo htmlspecialchars($buyer); ?>" <?php echo (isset($_SESSION['wizard_form_data']['case_name']) && $_SESSION['wizard_form_data']['case_name'] === $buyer) ? 'selected' : ''; ?>><?php echo htmlspecialchars($buyer); ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <option value="" disabled>Brak dostępnych nabywców</option>
              <?php endif; ?>
            </select>
            <span class="error-message" id="error_case_name"></span>
          </label>
        </div>
        
        <!-- Nowe pole do ręcznego wprowadzania nazwy sprawy -->
        <div class="field-group">
          <label>
            <span class="field-label">Nazwa sprawy (wpisz ręcznie):</span>
            <input type="text" name="manual_case_name" id="manual_case_name" placeholder="Wprowadź własną nazwę sprawy" 
                   value="<?php echo isset($_SESSION['wizard_form_data']['manual_case_name']) ? htmlspecialchars($_SESSION['wizard_form_data']['manual_case_name']) : ''; ?>">
            <span class="error-message" id="error_manual_case_name"></span>
          </label>
        </div>
        
        <div class="field-group">
          <label>
            <span class="field-label">Zakończona:</span>
            <input type="checkbox" name="is_completed" <?php echo (isset($_SESSION['wizard_form_data']['is_completed']) && $_SESSION['wizard_form_data']['is_completed']) ? 'checked' : ''; ?>>
          </label>
        </div>
        
        <div class="field-group">
          <label>
            <span class="field-label">Wywalczona kwota:</span>
            <div class="currency-input-wrapper">
              <input type="number" step="0.01" name="amount_won" id="amount_won" class="currency-input" 
                     placeholder="Wprowadź wywalczoną kwotę" 
                     value="<?php echo isset($_SESSION['wizard_form_data']['amount_won']) ? htmlspecialchars($_SESSION['wizard_form_data']['amount_won']) : ''; ?>">
              <span class="currency-display" id="amount_won_display"></span>
            </div>
            <span class="error-message" id="error_amount_won"></span>
          </label>
        </div>
        
        <div class="field-group">
          <label>
            <span class="field-label">Opłata wstępna:</span>
            <div class="currency-input-wrapper">
              <input type="number" step="0.01" name="upfront_fee" id="upfront_fee" class="currency-input"
                     placeholder="Wprowadź opłatę wstępną"
                     value="<?php echo isset($_SESSION['wizard_form_data']['upfront_fee']) ? htmlspecialchars($_SESSION['wizard_form_data']['upfront_fee']) : ''; ?>">
              <span class="currency-display" id="upfront_fee_display"></span>
            </div>
            <span class="error-message" id="error_upfront_fee"></span>
          </label>
        </div>
        
        <div class="field-group">
          <label>
            <span class="field-label">Procent success fee:</span>
            <div class="currency-input-wrapper">
              <input type="number" step="0.01" name="success_fee_percentage" id="success_fee_percentage" class="currency-input"
                     placeholder="Wprowadź procent success fee"
                     value="<?php echo isset($_SESSION['wizard_form_data']['success_fee_percentage']) ? htmlspecialchars($_SESSION['wizard_form_data']['success_fee_percentage']) : ''; ?>">
              <span class="currency-display" id="success_fee_percentage_display"></span>
            </div>
            <span class="error-message" id="error_success_fee_percentage"></span>
          </label>
        </div>

        <!-- Sekcja wyświetlająca wyniki obliczeń -->
        <div class="calculation-section">
          <div class="calculation-title">Wyniki obliczeń:</div>
          <div class="split-item">
            <span>Całość prowizji:</span>
            <span id="total_commission">0.00 zł</span>
          </div>
        </div>
      </fieldset>
      
      <div class="wizard-actions">
        <div></div> <!-- Empty div for flex spacing -->
        <button type="button" class="btn-next">Przejdź dalej <i class="fas fa-arrow-right"></i></button>
      </div>
    </div>

    <!-- Step 2 Content: Agents and Installments -->
    <div class="wizard-step-content" id="step2">
      <fieldset>
        <legend>Prowizja Kuby</legend>
        <div class="field-group">
          <label>
            <span class="field-label">Prowizja Kuby:</span>
            <div class="percentage-input-wrapper">
              <input type="number" step="0.01" name="kuba_percentage" id="kuba_percentage" class="currency-input" 
                   placeholder="Wprowadź procent prowizji Kuby"
                   value="<?php echo isset($_SESSION['wizard_form_data']['kuba_percentage']) ? htmlspecialchars($_SESSION['wizard_form_data']['kuba_percentage']) : ''; ?>">
              <span class="currency-display" id="kuba_percentage_display"></span>
            </div>
            <span class="error-message" id="error_kuba_percentage"></span>
          </label>
          <div style="margin-top: 10px; font-size: 0.9em; color: #666; background-color: #f8f9fa; padding: 8px; border-radius: 4px; border-left: 3px solid #1976D2;">
            <i class="fas fa-info-circle"></i> Kuba jest automatycznie przypisywany do sprawy z podaną prowizją. Agenci wybrani poniżej dzielą się częścią prowizji Kuby.
          </div>
        </div>

        <!-- Sekcja wyników obliczeń dla Kuby -->
        <div class="calculation-section">
          <div class="calculation-title">Wyniki obliczeń dla Kuby:</div>
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
          <input id="agentsCount" type="number" min="0" max="5" value="<?php 
            // Oblicz liczbę agentów na podstawie zapisanych danych
            if (isset($_SESSION['wizard_form_data'])) {
              $agentCount = 0;
              for ($i = 1; $i <= 5; $i++) {
                if (isset($_SESSION['wizard_form_data']["agent{$i}_id_agenta"]) && !empty($_SESSION['wizard_form_data']["agent{$i}_id_agenta"])) {
                  $agentCount++;
                }
              }
              echo $agentCount;
            } else {
              echo "0";
            }
          ?>">
        </label>
        <br>
        <label>
          Liczba rat (0-6):
          <input id="installmentsCount" type="number" min="0" max="6" value="<?php 
            // Oblicz liczbę rat na podstawie zapisanych danych
            if (isset($_SESSION['wizard_form_data'])) {
              $installmentCount = 0;
              for ($i = 1; $i <= 6; $i++) {
                if (isset($_SESSION['wizard_form_data']["installment{$i}_amount"]) && $_SESSION['wizard_form_data']["installment{$i}_amount"] !== '') {
                  $installmentCount++;
                }
              }
              echo $installmentCount;
            } else {
              echo "0";
            }
          ?>">
        </label>
      </fieldset>

      <!-- Ukryte pola przechowujące poprzednie wartości agentów i rat -->
      <?php if (isset($_SESSION['wizard_form_data'])): ?>
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <?php if (isset($_SESSION['wizard_form_data']["agent{$i}_id_agenta"]) && isset($_SESSION['wizard_form_data']["agent{$i}_percentage"])): ?>
            <input type="hidden" id="saved_agent<?php echo $i; ?>_id" value="<?php echo htmlspecialchars($_SESSION['wizard_form_data']["agent{$i}_id_agenta"]); ?>">
            <input type="hidden" id="saved_agent<?php echo $i; ?>_percentage" value="<?php echo htmlspecialchars($_SESSION['wizard_form_data']["agent{$i}_percentage"]); ?>">
          <?php endif; ?>
        <?php endfor; ?>
        
        <?php for ($i = 1; $i <= 6; $i++): ?>
          <?php if (isset($_SESSION['wizard_form_data']["installment{$i}_amount"])): ?>
            <input type="hidden" id="saved_installment<?php echo $i; ?>_amount" value="<?php echo htmlspecialchars($_SESSION['wizard_form_data']["installment{$i}_amount"]); ?>">
          <?php endif; ?>
        <?php endfor; ?>
      <?php endif; ?>

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

      <div class="wizard-actions">
        <button type="button" class="btn-prev"><i class="fas fa-arrow-left"></i> Wróć</button>
        <button type="submit" class="btn btn-submit" id="submitButton">
          <?php echo isset($_SESSION['wizard_form_data']['case_id']) ? 'Zapisz zmiany' : 'Zapisz rekord'; ?>
        </button>
      </div>
    </div>

  </form>

  <script>
    const agentsInput = document.getElementById('agentsCount');
    const instInput = document.getElementById('installmentsCount');
    const agentsSection = document.getElementById('agentsSection');
    const instSection = document.getElementById('installmentsSection');
    const installmentSummarySection = document.getElementById('installmentSummarySection');
    const caseNameDropdown = document.getElementById('case_name');
    const manualCaseNameInput = document.getElementById('manual_case_name');
    
    // Lista agentów pobrana z PHP
    const agents = <?php echo json_encode($agents); ?>;
    
    // Mapa zapisanych agentów (agent_id -> position)
    let savedAgentMap = new Map();
    
    // Inicjalizuj mapę zapisanych agentów jeśli edytujemy rekord
    function initSavedAgentMap() {
      savedAgentMap.clear();
      for (let i = 1; i <= 5; i++) {
        const savedAgentEl = document.getElementById(`saved_agent${i}_id`);
        if (savedAgentEl && savedAgentEl.value) {
          // Zapisz indeks pozycji dla tego ID agenta
          savedAgentMap.set(savedAgentEl.value, i);
        }
      }
      console.log("Saved agent map initialized:", Array.from(savedAgentMap.entries()));
    }
    
    // Wizard steps variables
    const wizardSteps = document.querySelectorAll('.wizard-step');
    const stepContents = document.querySelectorAll('.wizard-step-content');
    const nextButton = document.querySelector('.btn-next');
    const prevButton = document.querySelector('.btn-prev');
    let currentStep = 1;
    
    // Add event listeners for step navigation
    if (nextButton) {
      nextButton.addEventListener('click', function() {
        if (validateStep(currentStep)) {
          goToStep(currentStep + 1);
        }
      });
    }
    
    if (prevButton) {
      prevButton.addEventListener('click', function() {
        goToStep(currentStep - 1);
      });
    }
    
    // Function to navigate between steps
    function goToStep(stepNumber) {
      // Hide all steps
      stepContents.forEach(content => {
        content.classList.remove('active');
      });
      
      // Remove active class from step indicators
      wizardSteps.forEach(step => {
        step.classList.remove('active');
      });
      
      // Show the current step
      document.getElementById(`step${stepNumber}`).classList.add('active');
      
      // Add active class to the current step indicator
      document.querySelector(`.wizard-step[data-step="${stepNumber}"]`).classList.add('active');
      
      // Update current step
      currentStep = stepNumber;
    }

    // Initial rendering of agents and installment fields
    document.addEventListener('DOMContentLoaded', function() {
      // Inicjalizuj mapę zapisanych agentów
      initSavedAgentMap();
      
      // Jeśli edytujemy istniejący rekord i mamy zapisanych agentów, ustaw 
      // odpowiednią liczbę agentów na podstawie ilości zapisanych
      if (savedAgentMap.size > 0 && agentsInput) {
        agentsInput.value = savedAgentMap.size;
      }
      
      renderAgents();
      renderInstallments();
      calculateAll();
      validateForm();
      
      // Add change event listeners
      if (agentsInput) {
        agentsInput.addEventListener('change', function() {
          clampInput(this);
          renderAgents();
          validateForm();
        });
      }
      
      if (instInput) {
        instInput.addEventListener('change', function() {
          clampInput(this);
          renderInstallments();
          validateForm();
        });
      }
      
      // Set up CaseName and Manual CaseName interaction
      if (caseNameDropdown && manualCaseNameInput) {
        // When dropdown changes, clear manual input if a value is selected
        caseNameDropdown.addEventListener('change', function() {
          if (this.value !== '') {
            manualCaseNameInput.value = '';
          }
        });
        
        // When manual input changes, clear dropdown if text is entered
        manualCaseNameInput.addEventListener('input', function() {
          if (this.value !== '') {
            caseNameDropdown.value = '';
          }
        });
      }
      
      // Attach listeners to inputs for live display updates
      document.querySelectorAll('.currency-input').forEach(input => {
        input.addEventListener('input', function() {
          updateInputDisplay(this);
          calculateAll();
          validateForm();
        });
        
        // Initial display
        if (input.value) {
          updateInputDisplay(input);
        }
      });
    });

    // Update the validateStep function to include validation for the manual case name
    function validateStep(stepNumber) {
      let isValid = true;
      
      if (stepNumber === 1) {
        // Clear error messages
        showError(document.getElementById('error_case_name'), "");
        showError(document.getElementById('error_manual_case_name'), "");
        showError(document.getElementById('error_amount_won'), "");
        showError(document.getElementById('error_upfront_fee'), "");
        showError(document.getElementById('error_success_fee_percentage'), "");
        
        // Case name validation - either dropdown or manual input must have a value
        const caseNameInput = document.querySelector('select[name="case_name"]');
        const manualCaseNameInput = document.querySelector('input[name="manual_case_name"]');
        const caseName = caseNameInput.value.trim();
        const manualCaseName = manualCaseNameInput.value.trim();
        
        if (!caseName && !manualCaseName) {
          showError(document.getElementById('error_case_name'), "Wybierz nazwę sprawy z listy lub wprowadź ręcznie.");
          showError(document.getElementById('error_manual_case_name'), "Wprowadź nazwę sprawy lub wybierz z listy.");
          caseNameInput.classList.add('input-error');
          manualCaseNameInput.classList.add('input-error');
          isValid = false;
        } else {
          caseNameInput.classList.remove('input-error');
          manualCaseNameInput.classList.remove('input-error');
        }
        
        // Numeric fields validation
        const numericFields = ["amount_won", "upfront_fee", "success_fee_percentage"];
        numericFields.forEach(fieldName => {
          const input = document.querySelector(`input[name="${fieldName}"]`);
          const errorEl = document.getElementById(`error_${fieldName}`);
          if (input.value !== "") {
            const num = parseFloat(input.value);
            if (isNaN(num) || num < 0) {
              showError(errorEl, `Pole "${fieldName}" musi być liczbą nieujemną.`);
              input.classList.add('input-error');
              isValid = false;
            } else {
              input.classList.remove('input-error');
            }
          } else {
            input.classList.remove('input-error');
          }
        });
      }
      
      return isValid;
    }
    
    // Funkcja do aktualizacji wyświetlania wartości w polach walutowych
    function updateInputDisplay(input) {
      const value = parseFloat(input.value);
      const displayId = input.id + '_display';
      const displayElement = document.getElementById(displayId);
      
      if (displayElement) {
        if (!isNaN(value)) {
          // Formatuj wartość z separatorem tysięcy
          const formattedValue = formatNumberWithSpaces(value);
          
          // Jeśli to pole procentowe, dodaj znak %, w przeciwnym razie zł
          const unit = input.id.includes('percentage') ? '%' : ' zł';
          displayElement.textContent = formattedValue + unit;
          displayElement.style.display = 'block';
        } else {
          displayElement.style.display = 'none';
        }
      }
    }
    
    // Formatowanie liczb z separatorem tysięcy
    function formatNumberWithSpaces(num) {
      return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    }

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

    // Funkcja renderująca pola agentów z uwzględnieniem zapisanych wartości
    function renderAgents() {
      // Czyścimy zawartość sekcji
      agentsSection.innerHTML = '<legend>Agenci</legend>';
      const count = Number(agentsInput.value);
      // Kontener dla listy agentów
      const agentsContainer = document.createElement('div');
      agentsContainer.id = 'agentsContainer';
      
      // Uwaga: 'Kuba' nie jest wyświetlany na liście agentów, jego prowizja jest ustawiana osobno
      // w sekcji "Prowizja Kuby" i automatycznie dodawana do sprawy
      
      for (let i = 1; i <= count; i++) {
        const container = document.createElement('div');
        container.className = 'agent-container field-group';

        // Dropdown do wyboru agenta (bez Kuby - wyłączony w zapytaniu SQL)
        const selectContainer = document.createElement('label');
        selectContainer.innerHTML = `Agent ${i}: `;
        const select = document.createElement('select');
        select.name = `agent${i}_id_agenta`;
        select.id = `agent${i}_id_agenta`;
        select.className = 'agent-select';
        
        // Dodajemy event do obsługi zmiany agenta
        select.addEventListener('change', function() {
          updateAgentDropdowns();
          calculateAll();
          validateForm();
        });

        // Pusta opcja
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = '-- Wybierz agenta --';
        select.appendChild(emptyOption);

        // Opcje agentów
        let savedAgentIdForThisPosition = '';
        
        // Szukaj zapisanej wartości dla tej pozycji (i) 
        const savedAgentIdEl = document.getElementById(`saved_agent${i}_id`);
        if (savedAgentIdEl && savedAgentIdEl.value) {
          savedAgentIdForThisPosition = savedAgentIdEl.value.toString();
        }
        
        agents.forEach(agent => {
          const option = document.createElement('option');
          option.value = agent.id_agenta;
          option.textContent = agent.nazwa_agenta;
          
          // Sprawdź czy to opcja zapisana wcześniej - KONWERSJA NA STRING
          if (savedAgentIdForThisPosition && savedAgentIdForThisPosition === agent.id_agenta.toString()) {
            option.selected = true;
            console.log(`Setting agent ${agent.nazwa_agenta} (ID: ${agent.id_agenta}) as selected for position ${i}`);
          }
          
          select.appendChild(option);
        });

        selectContainer.appendChild(select);
        
        // Dodajemy indywidualny kontener błędów dla dropdowna agenta
        const selectErrorSpan = document.createElement('span');
        selectErrorSpan.className = 'error-message';
        selectErrorSpan.id = `error_agent${i}_select`;
        selectContainer.appendChild(selectErrorSpan);
        
        container.appendChild(selectContainer);

        // Pole procentu dla agenta
        const percentContainer = document.createElement('label');
        percentContainer.innerHTML = ` Procent: `;
        
        // Wrapper dla inputu z PROCENTAMI
        const inputWrapper = document.createElement('div');
        inputWrapper.className = 'percentage-input-wrapper';
        
        const percentInput = document.createElement('input');
        percentInput.type = 'number';
        percentInput.step = '0.01';
        percentInput.name = `agent${i}_percentage`;
        percentInput.id = `agent${i}_percentage`;
        percentInput.className = 'agent-percentage currency-input';
        percentInput.placeholder = `Wprowadź procent dla agenta ${i}`;
        
        // Sprawdź czy jest zapisana wartość procentowa
        const savedAgentPercentage = document.getElementById(`saved_agent${i}_percentage`);
        if (savedAgentPercentage) {
          percentInput.value = savedAgentPercentage.value;
        }
        
        percentInput.addEventListener('input', function() {
          calculateAll();
          updateInputDisplay(this);
          validateForm();
        });
        
        // Dodaj element wyświetlający wartość
        const displaySpan = document.createElement('span');
        displaySpan.className = 'currency-display';
        displaySpan.id = `agent${i}_percentage_display`;
        
        inputWrapper.appendChild(percentInput);
        inputWrapper.appendChild(displaySpan);
        percentContainer.appendChild(inputWrapper);

        // Dodanie elementu dla wyświetlania kwoty agenta
        const amountDisplay = document.createElement('div');
        amountDisplay.className = 'calculation-result';
        amountDisplay.id = `agent${i}_amount`;
        amountDisplay.textContent = '0.00 zł';
        percentContainer.appendChild(amountDisplay);
        
        // Dodajemy indywidualny kontener błędów dla pola procent
        const percentErrorSpan = document.createElement('span');
        percentErrorSpan.className = 'error-message';
        percentErrorSpan.id = `error_agent${i}_percentage`;
        percentContainer.appendChild(percentErrorSpan);

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
      
      // Aktualizujemy dropdowny, aby wyłączyć już wybrane opcje
      updateAgentDropdowns();
      
      // Aktualizuj wyświetlane wartości
      document.querySelectorAll('.agent-percentage').forEach(input => {
        if (input.value) {
          updateInputDisplay(input);
        }
      });
    }
    
    // Funkcja aktualizująca dostępność opcji w dropdownach agentów
    function updateAgentDropdowns() {
      const count = Number(agentsInput.value);
      if (count <= 1) return; // Nie ma potrzeby walidacji, gdy jest tylko jeden agent
      
      // Pobierz wszystkie wybrane wartości
      const selectedAgents = [];
      for (let i = 1; i <= count; i++) {
        const select = document.getElementById(`agent${i}_id_agenta`);
        if (select && select.value) {
          selectedAgents.push(select.value);
          console.log(`Agent at position ${i} has value: ${select.value} (${select.options[select.selectedIndex].text})`);
        }
      }
      
      // Dla każdego selecta, zaktualizuj dostępność opcji
      for (let i = 1; i <= count; i++) {
        const select = document.getElementById(`agent${i}_id_agenta`);
        if (!select) continue;
        
        const currentValue = select.value;
        
        // Aktualizuj opcje
        Array.from(select.options).forEach(option => {
          if (option.value === '') return; // Pomiń pustą opcję
          
          // Jeśli opcja jest już wybrana w innym dropdownie, wyłącz ją
          if (option.value !== currentValue && selectedAgents.includes(option.value)) {
            option.disabled = true;
          } else {
            option.disabled = false;
          }
        });
      }
    }

    // Funkcja renderująca pola rat z uwzględnieniem zapisanych wartości
    function renderInstallments() {
      instSection.innerHTML = '<legend>Raty</legend>';
      const count = Number(instInput.value);
      
      for (let i = 1; i <= count; i++) {
        const container = document.createElement('div');
        container.className = 'field-group';
        
        const lbl = document.createElement('label');
        lbl.innerHTML = `<span class="field-label">Rata ${i} kwota:</span>`;
        
        // Wrapper dla inputu walutowego
        const inputWrapper = document.createElement('div');
        inputWrapper.className = 'currency-input-wrapper';
        
        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.01';
        input.name = `installment${i}_amount`;
        input.id = `installment${i}_amount`;
        input.className = 'installment-amount currency-input';
        input.placeholder = `Wprowadź kwotę raty ${i}`;
        
        // Sprawdź czy jest zapisana wartość raty
        const savedInstallmentAmount = document.getElementById(`saved_installment${i}_amount`);
        if (savedInstallmentAmount) {
          input.value = savedInstallmentAmount.value;
        }
        
        input.addEventListener('input', function() {
          calculateAll();
          updateInputDisplay(this);
          validateForm();
        });
        
        // Dodaj element wyświetlający wartość
        const displaySpan = document.createElement('span');
        displaySpan.className = 'currency-display';
        displaySpan.id = `installment${i}_amount_display`;
        
        inputWrapper.appendChild(input);
        inputWrapper.appendChild(displaySpan);
        lbl.appendChild(inputWrapper);

        // Dodajemy span dla komunikatu błędu dla raty z lepszym stylem
        const errorSpan = document.createElement('span');
        errorSpan.className = 'error-message';
        errorSpan.id = `error_installment${i}`;
        lbl.appendChild(errorSpan);
        
        // Dodaj informację o pozostałej kwocie
        const remainingInfoSpan = document.createElement('span');
        remainingInfoSpan.className = 'remaining-amount';
        remainingInfoSpan.id = `installment${i}_remaining`;
        lbl.appendChild(remainingInfoSpan);
        
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
      
      // Dodajemy zbiorczy komunikat błędu dla rat, jeśli jeszcze nie istnieje
      if (!document.getElementById('error_installments')) {
        const installmentsErrorContainer = document.createElement('div');
        installmentsErrorContainer.id = 'error_installments';
        installmentsErrorContainer.className = 'error-message';
        instSection.appendChild(installmentsErrorContainer);
      }
      
      // Pokaż sekcję podsumowania rat jeśli są jakieś raty
      if (count > 0) {
        installmentSummarySection.style.display = 'block';
      } else {
        installmentSummarySection.style.display = 'none';
      }
      
      // Aktualizuj wyświetlane wartości
      document.querySelectorAll('.installment-amount').forEach(input => {
        if (input.value) {
          updateInputDisplay(input);
        }
      });
      
      // Wywołaj obliczenia, aby zaktualizować wartości
      calculateAll();
    }

    // Function to safely compare floating point numbers
    function areFloatsEqual(a, b, epsilon = 0.01) {
      return Math.abs(a - b) < epsilon;
    }

    // Funkcja walidacyjna z dodatkowymi obliczeniami
    function validateForm() {
      let formValid = true;

      // Czyszczenie komunikatów dla statycznych pól
      showError(document.getElementById('error_case_name'), "");
      showError(document.getElementById('error_amount_won'), "");
      showError(document.getElementById('error_upfront_fee'), "");
      showError(document.getElementById('error_success_fee_percentage'), "");
      showError(document.getElementById('error_kuba_percentage'), "");
      showError(document.getElementById('error_agents'), "");
      
      // Czyszczenie komunikatów o błędach dla rat
      const installmentInputs = document.querySelectorAll('input[name^="installment"][name$="_amount"]');
      installmentInputs.forEach((input, index) => {
        const errorSpan = document.getElementById(`error_installment${index + 1}`);
        if (errorSpan) {
          showError(errorSpan, "");
          input.classList.remove('input-error');
        }
      });

      // 1. Walidacja pola nazwy sprawy - sprawdź zarówno dropdown jak i pole ręczne
      const caseNameInput = document.querySelector('select[name="case_name"]');
      const manualCaseNameInput = document.querySelector('input[name="manual_case_name"]');
      const caseName = caseNameInput.value.trim();
      const manualCaseName = manualCaseNameInput.value.trim();
      
      if (!caseName && !manualCaseName) {
        showError(document.getElementById('error_case_name'), "Wybierz nazwę sprawy z listy lub wprowadź ręcznie.");
        showError(document.getElementById('error_manual_case_name'), "Wprowadź nazwę sprawy lub wybierz z listy.");
        caseNameInput.classList.add('input-error');
        manualCaseNameInput.classList.add('input-error');
        formValid = false;
      } else {
        caseNameInput.classList.remove('input-error');
        manualCaseNameInput.classList.remove('input-error');
      }

      // 2. Walidacja pól numerycznych: amount_won, upfront_fee, success_fee_percentage
      const numericFields = ["amount_won", "upfront_fee", "success_fee_percentage"];
      numericFields.forEach(fieldName => {
        const input = document.querySelector(`input[name="${fieldName}"]`);
        const errorEl = document.getElementById(`error_${fieldName}`);
        if (input.value !== "") {
          const num = parseFloat(input.value);
          if (isNaN(num) || num < 0) {
            showError(errorEl, `Pole "${fieldName}" musi być liczbą nieujemną.`);
            input.classList.add('input-error');
            formValid = false;
          } else {
            input.classList.remove('input-error');
          }
        } else {
          input.classList.remove('input-error');
        }
      });

      // 3. Walidacja prowizji Kuby – musi być liczba w przedziale 0-100%
      const kubaInput = document.querySelector('input[name="kuba_percentage"]');
      const errorKuba = document.getElementById('error_kuba_percentage');
      let kubaValue = parseFloat(kubaInput.value);
      if (isNaN(kubaValue)) {
        showError(errorKuba, "Prowizja Kuby musi być liczbą.");
        kubaInput.classList.add('input-error');
        formValid = false;
      } else if (kubaValue < 0 || kubaValue > 100) {
        showError(errorKuba, "Prowizja Kuby musi być z przedziału 0 - 100%.");
        kubaInput.classList.add('input-error');
        formValid = false;
      } else {
        kubaInput.classList.remove('input-error');
      }

      // 4. Walidacja procentów agentów – wszystkie komunikaty zbieramy w jednym kontenerze
      let agentErrors = [];
      let sumAgentPercents = 0;
      
      // Sprawdzamy czy ten sam agent nie został wybrany więcej niż raz
      const selectedAgents = new Set();
      const duplicateAgents = [];
      
      const agentInputs = document.querySelectorAll('input[name^="agent"][name$="_percentage"]');
      agentInputs.forEach((input, index) => {
        // Sprawdzanie zduplikowanych agentów
        const agentId = index + 1;
        const agentSelect = document.getElementById(`agent${agentId}_id_agenta`);
        
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
        
        // Walidacja procentów (istniejący kod)
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
      }
      
      if (agentErrors.length > 0) {
        showError(document.getElementById('error_agents'), agentErrors.join(" "));
        formValid = false;
      }

      // 5. Walidacja rat – każda rata musi być liczbą nieujemną
      let sumInstallments = 0;
      installmentInputs.forEach((input, index) => {
        const errorSpan = document.getElementById(`error_installment${index + 1}`);
        if (!errorSpan) return;
        
        if (input.value !== "") {
          const num = parseFloat(input.value);
          if (isNaN(num) || num < 0) {
            showError(errorSpan, "Rata musi być liczbą nieujemną.");
            input.classList.add('input-error');
            formValid = false;
          } else {
            input.classList.remove('input-error');
            sumInstallments += num;
          }
        }
      });
      
      // 6. Nowa walidacja: suma rat musi być równa opłacie wstępnej
      // Użyj małej wartości epsilon dla porównania liczb zmiennoprzecinkowych
      const upfrontFee = parseFloat(document.getElementById('upfront_fee').value) || 0;
      const epsilon = 0.01; // Tolerancja dla błędów zaokrąglenia (1 grosz)
      
      if (Math.abs(sumInstallments - upfrontFee) > epsilon) {
        const errorMessage = `Suma rat (${sumInstallments.toFixed(2)} zł) musi być równa opłacie wstępnej (${upfrontFee.toFixed(2)} zł).`;
        let errorDisplayed = false;
        
        installmentInputs.forEach((input, index) => {
          const errorSpan = document.getElementById(`error_installment${index + 1}`);
          if (errorSpan && errorSpan.textContent === "" && !errorDisplayed) {
            showError(errorSpan, errorMessage);
            input.classList.add('input-error');
            errorDisplayed = true; // Pokaż błąd tylko raz
          }
        });
        
        // Jeśli nie ma żadnych pól rat, pokaż błąd w pierwszym polu
        if (!errorDisplayed && installmentInputs.length > 0) {
          const firstErrorSpan = document.getElementById('error_installment1');
          if (firstErrorSpan) {
            showError(firstErrorSpan, errorMessage);
            installmentInputs[0].classList.add('input-error');
          }
        }
        
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
      let cumulativeSum = 0;
      installmentInputs.forEach((input, index) => {
        const installmentId = index + 1;
        const installmentAmount = parseFloat(input.value) || 0;
        
        // Aktualizacja informacji o pozostałej kwocie
        const remainingInfoElement = document.getElementById(`installment${installmentId}_remaining`);
        const previousTotal = cumulativeSum;
        cumulativeSum += installmentAmount;
        const remainingAfter = upfrontFee - cumulativeSum;
        
        if (remainingInfoElement) {
          // Jeśli różnica jest mniejsza od 1 grosza, uznaj że wartości są równe (tolerancja na błędy zaokrąglenia)
          const epsilon = 0.01;
          let statusClass = '';
          
          // Określ, czy to jest ostatnia rata
          const isLastInstallment = index === installmentInputs.length - 1;
          
          // Jeśli przekroczono opłatę wstępną, pokaż ostrzeżenie
          if (cumulativeSum > upfrontFee + epsilon) {
            statusClass = 'warning';
          }
          // Jeśli pozostało wciąż do opłacenia i to jest ostatnia rata, pokaż ostrzeżenie
          else if (remainingAfter > epsilon && isLastInstallment) {
            statusClass = 'warning';
          }
          // Jeśli opłata jest dokładnie równa oczekiwanej (z tolerancją epsilon), pokaż sukces
          else if (Math.abs(remainingAfter) < epsilon) {
            statusClass = 'success';
          }
          
          // Aktualizacja klasy CSS
          remainingInfoElement.className = `remaining-amount ${statusClass}`;
          
          // Formatowanie tekstu z lepszą klarownością
          let remainingText = `Opłacono: ${formatCurrency(cumulativeSum)} z ${formatCurrency(upfrontFee)}`;
          
          if (Math.abs(remainingAfter) < epsilon) {
            // Jeśli wszystko opłacone dokładnie
            remainingText = `<i class="fas fa-check-circle"></i> Opłacono: ${formatCurrency(cumulativeSum)} z ${formatCurrency(upfrontFee)} (opłata wstępna w pełni pokryta)`;
          } else if (remainingAfter > 0) {
            // Jeśli pozostaje do opłacenia
            remainingText = `<i class="fas fa-info-circle"></i> Opłacono: ${formatCurrency(cumulativeSum)} z ${formatCurrency(upfrontFee)} (pozostało jeszcze ${formatCurrency(remainingAfter)})`;
          } else {
            // Jeśli przekroczono opłatę wstępną
            remainingText = `<i class="fas fa-exclamation-triangle"></i> Opłacono: ${formatCurrency(cumulativeSum)} z ${formatCurrency(upfrontFee)} (przekroczono o ${formatCurrency(Math.abs(remainingAfter))})`;
          }
          
          remainingInfoElement.innerHTML = remainingText;
        }
        
        // Kwota dla Kuby z tej raty
        const kubaInstallment = installmentAmount * (kubaPayoutPercentage / 100);
        const kubaElement = document.getElementById(`installment${installmentId}_kuba`);
        if (kubaElement) {
          kubaElement.textContent = formatCurrency(kubaInstallment);
        }
        
        // Kwoty dla agentów z tej raty
        const agentsSplitContainer = document.getElementById(`installment${installmentId}_agents_split`);
        if (agentsSplitContainer) {
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
        }
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
        if (installmentSummary) {
          installmentSummary.innerHTML = '';
          finalInstallmentSplit.appendChild(agentsFinalSplit);
          installmentSummary.appendChild(finalInstallmentSplit);
        }
      }
      
      // Po obliczeniach, wykonaj walidację formularza
      validateForm();
    }

    // Funkcje pomocnicze do formatowania wartości
    function formatCurrency(value) {
      return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' zł';
    }
    
    function formatPercent(value) {
      return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + '%';
    }

    // Add events for installment fields - register for real-time validation
    function addInstallmentEvents() {
      document.querySelectorAll('.installment-amount').forEach(input => {
        // Remove existing event listeners first to avoid duplicates
        const newInput = input.cloneNode(true);
        input.parentNode.replaceChild(newInput, input);
        
        // Add new event listener
        newInput.addEventListener('input', function() {
          updateInputDisplay(this);
          calculateAll();
        });
      });
    }

    // Inicjalizacja dynamicznych pól oraz eventy
    agentsInput.addEventListener('input', () => {
      clampInput(agentsInput);
      renderAgents();
      calculateAll();
    });
    
    instInput.addEventListener('input', () => {
      clampInput(instInput);
      renderInstallments();
      setTimeout(addInstallmentEvents, 50); // Add events after the DOM is updated
      calculateAll();
    });

    // Dodanie eventów do pól podstawowych dla obliczeń na żywo
    document.getElementById('amount_won').addEventListener('input', function() {
      calculateAll();
      updateInputDisplay(this);
    });
    document.getElementById('upfront_fee').addEventListener('input', function() {
      calculateAll();
      updateInputDisplay(this);
    });
    document.getElementById('success_fee_percentage').addEventListener('input', function() {
      calculateAll();
      updateInputDisplay(this);
    });
    document.getElementById('kuba_percentage').addEventListener('input', function() {
      calculateAll();
      updateInputDisplay(this);
    });

    // Renderuj pola przy starcie
    renderAgents();
    renderInstallments();
    addInstallmentEvents(); // Dodaj eventy po renderowaniu rat
    calculateAll();
    
    // Inicjalizacja wyświetlania wartości dla istniejących pól
    document.querySelectorAll('.currency-input').forEach(input => {
      if (input.value) {
        updateInputDisplay(input);
      }
    });

    // Walidacja formularza przed wysłaniem
    document.getElementById('wizardForm').addEventListener('submit', function(e) {
      // First, re-enable disabled fields so their values are submitted
      const caseNameDropdown = document.getElementById('case_name');
      const manualCaseNameInput = document.getElementById('manual_case_name');
      
      // If manual name is entered, ensure the dropdown remains disabled visually
      // but is enabled for form submission
      if (manualCaseNameInput.value.trim() !== '') {
        caseNameDropdown.disabled = false;
        caseNameDropdown.value = '';
      }
      
      // Similarly, if dropdown is selected, ensure manual input remains disabled visually
      // but is enabled for form submission
      if (caseNameDropdown.value !== '') {
        manualCaseNameInput.disabled = false;
        manualCaseNameInput.value = '';
      }
      
      // Then perform validation
      if (!validateForm()) {
        e.preventDefault();
      }
    });

    // Po inicjalizacji, dodaj specjalny komunikat, jeśli edytujemy istniejący rekord
    <?php if (isset($_SESSION['wizard_form_data']['case_id'])): ?>
    console.log("Editing existing case with ID: <?php echo $_SESSION['wizard_form_data']['case_id']; ?>");
    
    // Opóźnione sprawdzenie, czy agenci zostali poprawnie załadowani
    setTimeout(() => {
      // Sprawdź, czy wszystkie zapisane wartości agentów zostały poprawnie odwzorowane
      let allAgentsLoadedCorrectly = true;
      
      for (let i = 1; i <= 5; i++) {
        const savedAgentIdEl = document.getElementById(`saved_agent${i}_id`);
        if (savedAgentIdEl && savedAgentIdEl.value) {
          const savedAgentId = savedAgentIdEl.value;
          const select = document.getElementById(`agent${i}_id_agenta`);
          
          if (select && select.value !== savedAgentId) {
            console.warn(`Agent mismatch at position ${i}: saved=${savedAgentId}, selected=${select.value}`);
            allAgentsLoadedCorrectly = false;
            
            // Próba naprawy poprzez ręczne ustawienie wyboru
            for (let j = 0; j < select.options.length; j++) {
              if (select.options[j].value.toString() === savedAgentId.toString()) {
                select.selectedIndex = j;
                console.log(`Fixed agent selection at position ${i}`);
                break;
              }
            }
          }
        }
      }
      
      if (allAgentsLoadedCorrectly) {
        console.log("All saved agents were loaded correctly");
      }
      
      // Odświeżenie dropdownów i obliczeń po ewentualnych naprawach
      updateAgentDropdowns();
      calculateAll();
    }, 500);
    <?php endif; ?>
    
    // Po inicjalizacji, usuń dane formularza z sesji
    <?php if (isset($_SESSION['wizard_form_data'])): ?>
      <?php unset($_SESSION['wizard_form_data']); ?>
    <?php endif; ?>
    
    // Przy pierwszym ładowaniu, zmień wrapery procentowe
    document.addEventListener("DOMContentLoaded", function() {
      // Prowizja Kuby
      const kubaLabel = document.querySelector('label[for="kuba_percentage"]') || document.querySelector('input[name="kuba_percentage"]').parentNode;
      if (kubaLabel) {
        const kubaWrapper = kubaLabel.querySelector('.currency-input-wrapper');
        if (kubaWrapper) {
          kubaWrapper.className = 'percentage-input-wrapper';
        }
      }
      
      // Success fee percentage
      const successFeeLabel = document.querySelector('label[for="success_fee_percentage"]') || document.querySelector('input[name="success_fee_percentage"]').parentNode;
      if (successFeeLabel) {
        const successFeeWrapper = successFeeLabel.querySelector('.currency-input-wrapper');
        if (successFeeWrapper) {
          successFeeWrapper.className = 'percentage-input-wrapper';
        }
      }
    });

    // ===== NOWE FUNKCJE DLA OBSŁUGI BŁĘDÓW =====
    
    // Funkcja podświetlająca pola z błędami po załadowaniu strony z błędami z serwera
    function highlightErrorFields() {
      // Znajdź wszystkie pola z klasą input-error
      const errorFields = document.querySelectorAll('.input-error');
      
      // Dodaj klasę animacji do każdego pola
      errorFields.forEach(field => {
        field.classList.add('highlight-error');
        
        // Przewiń do pierwszego pola z błędem
        if (field === errorFields[0]) {
          field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
      
      // Usuń klasę animacji po zakończeniu
      setTimeout(() => {
        errorFields.forEach(field => {
          field.classList.remove('highlight-error');
        });
      }, 2000);
    }
    
    // Funkcja do sprawdzania i pokazywania błędów w czasie rzeczywistym
    function addRealTimeValidation() {
      // Wszystkie pola, które powinny być walidowane w czasie rzeczywistym
      const fieldsToValidate = [
        { id: 'case_name', errorId: 'error_case_name' },
        { id: 'manual_case_name', errorId: 'error_manual_case_name' },
        { id: 'amount_won', errorId: 'error_amount_won' },
        { id: 'upfront_fee', errorId: 'error_upfront_fee' },
        { id: 'success_fee_percentage', errorId: 'error_success_fee_percentage' },
        { id: 'kuba_percentage', errorId: 'error_kuba_percentage' }
      ];
      
      // Dodaj nasłuchiwanie zdarzeń dla każdego pola
      fieldsToValidate.forEach(field => {
        const element = document.getElementById(field.id);
        if (element) {
          // Dla pól typu select nasłuchuj zmiany
          if (element.tagName.toLowerCase() === 'select') {
            element.addEventListener('change', function() {
              if (currentStep === 1) {
                validateStep(1);
              } else {
                validateForm();
              }
            });
          } 
          // Dla pól typu input nasłuchuj wprowadzania
          else {
            element.addEventListener('input', function() {
              if (currentStep === 1) {
                validateStep(1);
              } else {
                validateForm();
              }
            });
            
            // Dodatkowa walidacja przy opuszczeniu pola (blur)
            element.addEventListener('blur', function() {
              if (currentStep === 1) {
                validateStep(1);
              } else {
                validateForm();
              }
            });
          }
        }
      });
    }
    
    // Funkcja poprawiająca wyświetlanie błędów w walidacji
    function showError(errorElement, message) {
      if (!errorElement) return;
      
      // Jeśli jest błąd, pokaż go z animacją
      if (message) {
        errorElement.textContent = message;
        errorElement.style.opacity = '0';
        setTimeout(() => {
          errorElement.style.opacity = '1';
        }, 10);
      } else {
        // Jeśli nie ma błędu, ukryj element
        errorElement.textContent = '';
      }
    }
    
    // Zastąpienie prostych przypisań błędów funkcją showError w validateForm
    const originalValidateForm = validateForm;
    validateForm = function() {
      // Wywołaj oryginalną funkcję
      const result = originalValidateForm.apply(this, arguments);
      
      // Po walidacji, podświetl pola z błędami
      setTimeout(highlightErrorFields, 100);
      
      return result;
    };
    
    // Zastąpienie prostych przypisań błędów funkcją showError w validateStep
    const originalValidateStep = validateStep;
    validateStep = function(stepNumber) {
      // Wywołaj oryginalną funkcję
      const result = originalValidateStep.apply(this, [stepNumber]);
      
      // Po walidacji, podświetl pola z błędami
      setTimeout(highlightErrorFields, 100);
      
      return result;
    };
    
    // Inicjalizacja walidacji w czasie rzeczywistym przy załadowaniu strony
    document.addEventListener('DOMContentLoaded', function() {
      addRealTimeValidation();
    });
  </script>
</body>

</html>
