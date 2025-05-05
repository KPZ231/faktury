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
          Kreator Rekorkdu
        </a>
      </li>
    </ul>
  </nav>


  <?php if (isset($_GET['success'])): ?>
    <div class="success">Rekord został dodany pomyślnie!</div>
  <?php endif; ?>
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

    function renderAgents() {
      agentsSection.innerHTML = '<legend>Agenci</legend>';
      const count = Number(agentsInput.value);
      for (let i = 1; i <= count; i++) {
        const lbl = document.createElement('label');
        lbl.innerHTML = `Agent ${i} procent:<input type='number' step='0.01' name='agent${i}_percentage'>`;
        agentsSection.appendChild(lbl);
      }
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