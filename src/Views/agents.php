<?php
// agents.php (View)
// Include this file in your AgentController@index method


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_agent'])) {
    // Sanitize input
    $imie = trim($_POST['imie']);
    $nazwisko = trim($_POST['nazwisko']);

    if ($imie !== '' && $nazwisko !== '') {
        $sql = "INSERT INTO agenci (agent_id, imie, nazwisko, sprawy) VALUES (NULL, :imie, :nazwisko, JSON_ARRAY())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':imie' => $imie,
            ':nazwisko' => $nazwisko
        ]);
        // Redirect to avoid resubmission
        header('Location: ?');
        exit;
    } else {
        $error = 'Proszę wypełnić wszystkie pola.';
    }
}

// Fetch all agents
$stmt = $pdo->query("SELECT agent_id, imie, nazwisko, sprawy FROM agenci ORDER BY agent_id ASC");
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
$count = count($agents);
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
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
        <label class="agent-form-label" for="imie">Imię:</label>
        <input class="agent-form-input" type="text" id="imie" name="imie" required>
        <br>
        <br>
        <label class="agent-form-label" for="nazwisko">Nazwisko:</label>
        <input class="agent-form-input" type="text" id="nazwisko" name="nazwisko" required>
        <br><br>
        <button type="submit" class="agent-submit-button">Dodaj Agenta</button>
    </form>

    <h2 class="agent-list-heading">Lista Agentów (<?php echo count($agents); ?>)</h2>

    <?php if (!empty($agents)): ?>
        <ul class="agent-list">
            <?php foreach ($agents as $agent): ?>
                <li class="agent-list-item">
                    <a href="/table?agent_id=<?php echo $agent['agent_id']; ?>" class="agent-name-link">
                        <strong class="agent-name"><?php echo htmlspecialchars($agent['imie'] . ' ' . $agent['nazwisko']); ?></strong>
                    </a>
                    <div class="agent-cases-container">
                        <?php
                        if (!empty($agent['sprawy'])):
                            $sprawy = is_string($agent['sprawy']) ? json_decode($agent['sprawy'], true) : $agent['sprawy'];
                            if (is_array($sprawy) && !empty($sprawy)):
                                foreach ($sprawy as $sprawa):
                                    if (is_array($sprawa) && isset($sprawa['case_name']) && isset($sprawa['rola']) && isset($sprawa['percentage'])):
                                        ?>
                                        <span class="agent-case-link">
                                            <?php echo htmlspecialchars($sprawa['case_name'] . ' (' . $sprawa['rola'] . ')'); ?>
                                            <br><small><?php echo number_format((float)$sprawa['percentage'], 2, ',', ' '); ?>%</small>
                                        </span>
                                        <?php
                                    endif;
                                endforeach;
                            else:
                                echo '<em class="agent-no-cases">Brak przypisanych spraw</em>';
                            endif;
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