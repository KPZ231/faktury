<!DOCTYPE html>

<html lang="pl">

<head>
  <meta charset="UTF-8">
  <title>Dodaj rekord</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    <fieldset>
      <legend>Podstawowe dane</legend>
      <label>Nazwa sprawy:<input type="text" name="case_name" required></label>
      <label>Zakończona:<input type="checkbox" name="is_completed"></label>
      <label>Wywalczona kwota:<input type="number" step="0.01" name="amount_won"></label>
      <label>Opłata wstępna:<input type="number" step="0.01" name="upfront_fee"></label>
      <label>Procent success fee:<input type="number" step="0.01" name="success_fee_percentage"></label>
      <label>Prowizja Kuby:<input type="number" step="0.01" name="kuba_percentage"></label>
    </fieldset>

    <fieldset class="controls">
      <legend>Konfiguracja</legend>
      <label>Liczba agentów (0-5):<input id="agentsCount" type="number" min="0" max="5" value="0"></label>
      <label>Liczba rat (0-4):<input id="installmentsCount" type="number" min="0" max="4" value="0"></label>
    </fieldset>

    <fieldset id="agentsSection">
      <legend>Agenci</legend>
      <!-- pola dynamicznie generowane -->
    </fieldset>

    <fieldset id="installmentsSection">
      <legend>Raty</legend>
      <!-- pola dynamicznie generowane -->
    </fieldset>

    <button type="submit" class="btn">Zapisz rekord</button>

  </form>

  <script>
    const agentsInput = document.getElementById('agentsCount');
    const instInput = document.getElementById('installmentsCount');
    const agentsSection = document.getElementById('agentsSection');
    const instSection = document.getElementById('installmentsSection');

    // Lista agentów pobrana z PHP
    const agents = <?php echo json_encode($agents); ?>;

    // Funkcja pomocnicza: przymusowe „przycinanie” wartości do zakresu [min..max]
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

    // Podpinamy handler na każdą zmianę (typing, paste, strzałki)
    agentsInput.addEventListener('input', () => clampInput(agentsInput));
    instInput.addEventListener('input', () => clampInput(instInput));

    // Dodatkowo blokujemy klawisze, które mogłyby ominąć input event
    [agentsInput, instInput].forEach(input => {
      input.addEventListener('keydown', e => {
        // jeśli to strzałka góra/dół lub PageUp/PageDown
        if (['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown'].includes(e.code)) {
          // odrobinę opóźniamy sprawdzenie, bo wartość zmienia się chwilę po keydown
          setTimeout(() => clampInput(input), 0);
        }
      });
    });

    function renderAgents() {
      agentsSection.innerHTML = '<legend>Agenci</legend>';
      const count = Number(agentsInput.value);
      for (let i = 1; i <= count; i++) {
        // Kontener dla pary dropdown + pole procentu
        const container = document.createElement('div');
        container.className = 'agent-container';

        // Dropdown do wyboru agenta
        const selectContainer = document.createElement('label');
        selectContainer.innerHTML = `Agent ${i}:`;
        const select = document.createElement('select');
        select.name = `agent${i}_id`;
        select.id = `agent${i}_id`;

        // Dodaj pustą opcję
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = '-- Wybierz agenta --';
        select.appendChild(emptyOption);

        // Dodaj opcje agentów
        agents.forEach(agent => {
          const option = document.createElement('option');
          option.value = agent.agent_id;
          option.textContent = `${agent.imie} ${agent.nazwisko}`;
          select.appendChild(option);
        });

        selectContainer.appendChild(select);
        container.appendChild(selectContainer);

        // Pole na procent
        const percentContainer = document.createElement('label');
        percentContainer.innerHTML = `Procent:`;
        const percentInput = document.createElement('input');
        percentInput.type = 'number';
        percentInput.step = '0.01';
        percentInput.name = `agent${i}_percentage`;
        percentContainer.appendChild(percentInput);
        container.appendChild(percentContainer);

        agentsSection.appendChild(container);
      }

      // Dodaj style dla lepszego wyświetlania
      const style = document.createElement('style');
      style.textContent = `
        .agent-container {
          display: flex;
          gap: 10px;
          margin-bottom: 10px;
        }
        .agent-container label {
          display: flex;
          align-items: center;
          gap: 5px;
        }
      `;
      document.head.appendChild(style);
    }

    function renderInstallments() {
      instSection.innerHTML = '<legend>Raty</legend>';
      const count = Number(instInput.value);
      for (let i = 1; i <= count; i++) {
        const lbl = document.createElement('label');
        lbl.innerHTML = `Rata ${i} kwota:<input type='number' step='0.01' name='installment${i}_amount'>`;
        instSection.appendChild(lbl);
      }
    }

    // Dodajemy event listenery dla obu pól
    agentsInput.addEventListener('input', renderAgents);
    instInput.addEventListener('input', renderInstallments);

    // Inicjalizujemy formularze przy załadowaniu strony
    renderAgents();
    renderInstallments();
  </script>

</body>

</html>