<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Tabela</title>
</head>

<body>
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

    <header>
        <h1>Podejrzyj Tabele</h1>
    </header>

    <section id="dataTable">
        <?php if (isset($selectedAgent)): ?>
            <!-- Wyświetl dane dla wybranego agenta -->
            <h2>Sprawy agenta: <?= htmlspecialchars($selectedAgent['imie'] . ' ' . $selectedAgent['nazwisko'], ENT_QUOTES) ?></h2>
            <a href="/table" class="back-link">⬅️ Powrót do wyboru agenta</a>
            <?php $this->renderTable($selectedAgentId); ?>
        <?php else: ?>
            <!-- Wyświetl listę agentów do wyboru -->
            <h2>Wybierz agenta do wyświetlenia spraw</h2>
            <?php $this->renderAgentSelection(); ?>
        <?php endif; ?>
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sortables = document.querySelectorAll('.sortable');
            sortables.forEach(th => {
                th.addEventListener('click', function() {
                    const column = this.dataset.column;
                    // Implementacja sortowania - można rozwinąć w przyszłości
                    console.log('Sortowanie według kolumny:', column);
                });
            });
        });
    </script>

</body>

</html>