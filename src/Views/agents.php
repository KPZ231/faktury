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


<div class="agent-form-container">
    <h1 class="agent-form-heading">Dodaj nowego agenta</h1>

    <?php if (!empty($error)): ?>
        <p class="agent-error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post" action="" class="agent-form">
        <label class="agent-form-label" for="imie">Imię:</label>
        <input class="agent-form-input" type="text" id="imie" name="imie" required>
        <br>
        <br>
        <label class="agent-form-label" for="nazwisko">Nazwisko:</label>
        <input class="agent-form-input" type="text" id="nazwisko" name="nazwisko" required>
        <br><br>
        <button type="submit" name="add_agent" id="addAgent" class="agent-submit-button">Dodaj Agenta</button>
    </form>

    <h2 class="agent-list-heading">Lista Agentów (<?php echo $count; ?>)</h2>
    <?php if ($count > 0): ?>
        <ul class="agent-list">
            <?php foreach ($agents as $agent): ?>
                <li class="agent-list-item">
                    <strong class="agent-name"><?php echo htmlspecialchars($agent['imie'] . ' ' . $agent['nazwisko']); ?></strong>
                    <div class="agent-cases-container">
                        <?php
                        $cases = json_decode($agent['sprawy'], true);
                        if (is_array($cases) && count($cases) > 0) {
                            foreach ($cases as $case) {
                                $url = sprintf(
                                    'agent_details.php?agent_id=%d&amp;case=%s',
                                    $agent['agent_id'],
                                    urlencode($case)
                                );
                                echo '<a class="agent-case-link" href="' . $url . '">' . htmlspecialchars($case) . '</a>';
                            }
                        } else {
                            echo '<em class="agent-no-cases">Brak przypisanych spraw</em>';
                        }
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