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
      <label>
        Nazwa sprawy:
        <input type="text" name="case_name" required>
        <span class="error-message" id="error_case_name"></span>
      </label>
      <br>
      <label>
        Zakończona:
        <input type="checkbox" name="is_completed">
      </label>
      <br>
      <label>
        Wywalczona kwota:
        <input type="number" step="0.01" name="amount_won">
        <span class="error-message" id="error_amount_won"></span>
      </label>
      <br>
      <label>
        Opłata wstępna:
        <input type="number" step="0.01" name="upfront_fee">
        <span class="error-message" id="error_upfront_fee"></span>
      </label>
      <br>
      <label>
        Procent success fee:
        <input type="number" step="0.01" name="success_fee_percentage">
        <span class="error-message" id="error_success_fee_percentage"></span>
      </label>
      <br>
      <label>
        Prowizja Kuby:
        <input type="number" step="0.01" name="kuba_percentage">
        <span class="error-message" id="error_kuba_percentage"></span>
      </label>
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

    <button type="submit" class="btn" id="submitButton">Zapisz rekord</button>
  </form>

  <script>
    const agentsInput = document.getElementById('agentsCount');
    const instInput = document.getElementById('installmentsCount');
    const agentsSection = document.getElementById('agentsSection');
    const instSection = document.getElementById('installmentsSection');

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
        container.className = 'agent-container';

        // Dropdown do wyboru agenta
        const selectContainer = document.createElement('label');
        selectContainer.innerHTML = `Agent ${i}: `;
        const select = document.createElement('select');
        select.name = `agent${i}_id`;
        select.id = `agent${i}_id`;

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

        // Pole procentu dla agenta - nie tworzymy już indywidualnych spanów dla błędów
        const percentContainer = document.createElement('label');
        percentContainer.innerHTML = ` Procent: `;
        const percentInput = document.createElement('input');
        percentInput.type = 'number';
        percentInput.step = '0.01';
        percentInput.name = `agent${i}_percentage`;
        percentInput.addEventListener('input', validateForm);
        percentContainer.appendChild(percentInput);

        container.appendChild(percentContainer);
        agentsContainer.appendChild(container);
      }
      agentsSection.appendChild(agentsContainer);
      // Dodajemy wspólny kontener (jeśli nie został jeszcze dodany) – ale go zawsze resetujemy w validateForm
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
        const lbl = document.createElement('label');
        lbl.innerHTML = `Rata ${i} kwota: `;
        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.01';
        input.name = `installment${i}_amount`;
        input.addEventListener('input', validateForm);
        lbl.appendChild(input);

        // Dodajemy span dla komunikatu błędu dla raty
        const errorSpan = document.createElement('span');
        errorSpan.className = 'error-message';
        errorSpan.id = `error_installment${i}`;
        lbl.appendChild(errorSpan);

        instSection.appendChild(lbl);
      }
    }

    // Funkcja walidacyjna – wyświetla komunikaty przy polach oraz zbiorczo dla agentów
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

      // 3. Walidacja prowizji Kuby – musi być liczba w przedziale 0-25%
      const kubaInput = document.querySelector('input[name="kuba_percentage"]');
      const errorKuba = document.getElementById('error_kuba_percentage');
      let kubaValue = parseFloat(kubaInput.value);
      if (isNaN(kubaValue)) {
        errorKuba.innerText = "Prowizja Kuby musi być liczbą.";
        kubaInput.classList.add('input-error');
        formValid = false;
      } else if (kubaValue < 0 || kubaValue > 25) {
        errorKuba.innerText = "Prowizja Kuby musi być z przedziału 0 - 25%.";
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
      const installmentInputs = document.querySelectorAll('input[name^="installment"][name$="_amount"]');
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
          }
        }
      });

      // Blokujemy przycisk wysyłania, jeśli formularz zawiera błędy
      document.getElementById('submitButton').disabled = !formValid;
      return formValid;
    }

    // Inicjalizacja dynamicznych pól oraz eventy
    agentsInput.addEventListener('input', () => {
      clampInput(agentsInput);
      renderAgents();
      validateForm();
    });
    instInput.addEventListener('input', () => {
      clampInput(instInput);
      renderInstallments();
      validateForm();
    });

    // Walidacja formularza na każdej zmianie
    document.getElementById('wizardForm').addEventListener('input', validateForm);

    // Renderuj pola przy starcie
    renderAgents();
    renderInstallments();

    // Zapobieganie przesłaniu formularza, jeśli nie jest poprawny
    document.getElementById('wizardForm').addEventListener('submit', function(e) {
      if (!validateForm()) {
        e.preventDefault();
      }
    });
  </script>
</body>

</html>
