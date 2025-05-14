<?php
// agents.php (View)
// Include this file in your AgentController@index method


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_agent'])) {
    // Sanitize input
    $nazwaAgenta = trim($_POST['nazwa_agenta']);

    if ($nazwaAgenta !== '') {
        $sql = "INSERT INTO agenci (nazwa_agenta) VALUES (:nazwa_agenta)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nazwa_agenta' => $nazwaAgenta
        ]);
        // Redirect to avoid resubmission
        header('Location: ?');
        exit;
    } else {
        $error = 'Proszę wypełnić wszystkie pola.';
    }
}

// Fetch all agents
$stmt = $pdo->query("SELECT id_agenta, nazwa_agenta FROM agenci ORDER BY nazwa_agenta ASC");
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
$count = count($agents);
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="../../assets/images/favicon.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Lista Agentów</title>
</head>

<body>
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


<div class="agent-form-container">
    <h1 class="agent-form-heading">Dodaj nowego agenta</h1>

    <?php if (isset($error) && !empty($error)): ?>
        <p class="agent-error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post" action="/agents" class="agent-form">
        <label class="agent-form-label" for="nazwa_agenta">Nazwa agenta:</label>
        <input class="agent-form-input" type="text" id="nazwa_agenta" name="nazwa_agenta" required>
        <br><br>
        <button type="submit" name="add_agent" class="agent-submit-button">Dodaj Agenta</button>
    </form>

    <h2 class="agent-list-heading">Lista Agentów (<?php echo count($agents); ?>)</h2>

    <?php if (!empty($agents)): ?>
        <ul class="agent-list">
            <?php foreach ($agents as $agent): ?>
                <li class="agent-list-item">
                    <a href="/table?agent_id=<?php echo $agent['id_agenta']; ?>" class="agent-name-link">
                        <strong class="agent-name"><?php echo htmlspecialchars($agent['nazwa_agenta']); ?></strong>
                    </a>
                    <div class="agent-cases-container">
                        <?php
                        if (isset($agent['sprawy']) && is_array($agent['sprawy']) && !empty($agent['sprawy'])):
                            foreach ($agent['sprawy'] as $sprawa):
                                $prowizja = (float)$sprawa['percentage'] * 100; // Convert from decimal to percentage
                                ?>
                                <span class="agent-case-link">
                                    <?php echo htmlspecialchars($sprawa['identyfikator_sprawy']); ?>
                                    <br><small><?php echo number_format($prowizja, 2, ',', ' '); ?>%</small>
                                </span>
                                <?php
                            endforeach;
                        else:
                            echo '<em class="agent-no-cases">Brak przypisanych spraw</em>';
                        endif;
                        ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="agent-no-cases">Brak agentów w bazie.</p>
    <?php endif; ?>
</div>

</body>

</html>