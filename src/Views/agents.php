<?php
// agents.php (View)
// Include this file in your AgentController@index method


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_agent'])) {
    // Sanitize input
    $nazwaAgenta = trim($_POST['nazwa_agenta']);
    $nadagent = isset($_POST['nadagent']) && !empty($_POST['nadagent']) ? trim($_POST['nadagent']) : null;

    if ($nazwaAgenta !== '') {
        // Wstaw nowego agenta z referencją do nadagenta
        if ($nadagent) {
            $sql = "INSERT INTO agenci (nazwa_agenta, nadagent) VALUES (:nazwa_agenta, :nadagent)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nazwa_agenta' => $nazwaAgenta,
                ':nadagent' => $nadagent
            ]);
        } else {
            $sql = "INSERT INTO agenci (nazwa_agenta) VALUES (:nazwa_agenta)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nazwa_agenta' => $nazwaAgenta
            ]);
        }
        
        // Redirect to avoid resubmission
        header('Location: ?');
        exit;
    } else {
        $error = 'Proszę wypełnić wszystkie pola.';
    }
}

// Fetch all agents (we still need this for the controller, but won't display the list)
$stmt = $pdo->query("SELECT id_agenta, nazwa_agenta, IFNULL(nadagent, '') as nadagent FROM agenci ORDER BY nazwa_agenta ASC");
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
$count = count($agents);

// Build agent hierarchy for the tree view
if (!function_exists('buildAgentTree')) {
    function buildAgentTree($agents, $parentName = '') {
        $tree = [];
        
        // Only collect agents who have a supervisor (nadagent)
        $agentsWithSupervisor = array_filter($agents, function($agent) {
            return !empty($agent['nadagent']);
        });
        
        foreach ($agents as $agent) {
            if ($agent['nadagent'] == $parentName) {
                // Użyj nazwy agenta jako klucza rodzica dla rekurencyjnego wywołania
                $children = buildAgentTree($agents, $agent['nazwa_agenta']);
                if ($children) {
                    $agent['children'] = $children;
                }
                $tree[] = $agent;
            }
        }
        
        return $tree;
    }
}

// Get only agents with supervisors for display in the tree
$agentsWithSupervisors = array_filter($agents, function($agent) {
    return !empty($agent['nadagent']);
});

// Build tree for display - using empty parent name to get top-level agents
$agentTree = buildAgentTree($agents);

// For the select dropdown, mark agents that already have a supervisor
foreach ($agents as &$agent) {
    $agent['has_supervisor'] = !empty($agent['nadagent']);
}
unset($agent); // Break the reference

// Convert agent tree to JSON for jsTree
$agentTreeJson = json_encode($agentTree);
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie agentami</title>
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- jsTree CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.16/themes/default/style.min.css" />
    
    <style>
        /* Styling for the agent tree and components */
        .agent-tree {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .agent-tree-title {
            margin-bottom: 15px;
            font-size: 1.2rem;
            color: #333;
        }
        
        #agent-tree-container {
            min-height: 200px;
            max-height: 500px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
        }
        
        .agent-tree-search {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        #agent-tree-search-input {
            flex-grow: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
        }
        
        #agent-tree-search-button, #agent-tree-search-clear {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background-color: #f8f9fa;
            cursor: pointer;
        }
        
        #agent-tree-search-button {
            border-radius: 0;
            border-left: none;
        }
        
        #agent-tree-search-clear {
            border-radius: 0 4px 4px 0;
            border-left: none;
        }
        
        .agent-tree-legend {
            margin-top: 15px;
            padding: 10px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .legend-title {
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .legend-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Node styling */
        .jstree-animate-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        .jstree-loading:before {
            content: "Ładowanie hierarchii...";
            display: block;
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .kuba-node-highlight {
            background-color: #fff8e1 !important;
        }
        
        .kuba-node-anchor {
            font-weight: bold !important;
            color: #ff9800 !important;
        }
        
        .selected-agent-node > .jstree-anchor {
            background-color: #e3f2fd !important;
            color: #1976d2 !important;
        }
        
        .has-supervisor {
            color: #007bff;
            background-color: #e7f1ff;
            font-weight: bold;
        }
        
        .view-cases-button {
            margin-top: 15px;
            padding: 10px 15px;
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
        }
        
        .view-cases-button i {
            margin-right: 8px;
        }
        
        .view-cases-button:hover {
            background-color: #388e3c;
        }
        
        .agent-count {
            font-size: 0.9rem;
            color: #666;
            font-weight: normal;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
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
        <a href="/wizard" class="cleannav__link" data-tooltip="Kreator rekordu">
          <i class="fa-solid fa-wand-magic-sparkles cleannav__icon"></i>
        </a>
      </li>
      <li class="cleannav__item">
        <a href="/podsumowanie-spraw" class="cleannav__link" data-tooltip="Podsumowanie Faktur">
          <i class="fa-solid fa-file-invoice-dollar cleannav__icon"></i>
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
        
        <label class="agent-form-label" for="nadagent">Nadagent (opcjonalnie):</label>
        <select class="agent-form-input" id="nadagent" name="nadagent">
            <option value="">-- Brak nadagenta --</option>
            <?php foreach ($agents as $agent): ?>
                <option value="<?php echo htmlspecialchars($agent['nazwa_agenta']); ?>" 
                    class="<?php echo $agent['has_supervisor'] ? 'has-supervisor' : ''; ?>">
                    <?php echo htmlspecialchars($agent['nazwa_agenta']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br><br>
        
        <button type="submit" name="add_agent" class="agent-submit-button">Dodaj Agenta</button>
    </form>
    
    <!-- Hierarchiczne drzewko agentów -->
    <div class="agent-tree">
        <h3 class="agent-tree-title">Struktura hierarchii agentów:</h3>
        <div id="agent-tree-container">
            <!-- jsTree will be initialized here -->
        </div>
    </div>
</div>

<!-- jQuery - required for jsTree -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<!-- jsTree library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.16/jstree.min.js"></script>

<script>
// JavaScript do obsługi wyboru nadagenta i tworzenia hierarchii
document.addEventListener('DOMContentLoaded', function() {
    const nadagentSelect = document.getElementById('nadagent');
    
    // Highlight options in the select dropdown that have supervisors
    Array.from(nadagentSelect.options).forEach(option => {
        if (option.classList.contains('has-supervisor')) {
            option.style.color = '#007bff';
            option.style.backgroundColor = '#e7f1ff';
            option.style.fontWeight = 'bold';
        }
    });
    
    // Funkcja do sprawdzania czy wybrany nadagent ma jakiegoś nadagenta
    nadagentSelect.addEventListener('change', function() {
        const selectedAgent = this.value;
        
        if (selectedAgent) {
            // Sprawdź hierarchię nadagentów, aby zapobiec cyklom
            checkAgentHierarchy(selectedAgent);
        }
    });
    
    // Funkcja sprawdzająca hierarchię nadagentów
    function checkAgentHierarchy(agentName) {
        fetch(`/api/agent-hierarchy?agent_name=${encodeURIComponent(agentName)}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    nadagentSelect.value = '';
                } else {
                    // Wyświetl informację o hierarchii
                    let hierarchyInfo = '';
                    if (data.hierarchy_path && data.hierarchy_path.length > 0) {
                        hierarchyInfo = 'Ścieżka hierarchii: ';
                        data.hierarchy_path.forEach((agent, index) => {
                            hierarchyInfo += agent.nazwa_agenta;
                            if (index < data.hierarchy_path.length - 1) {
                                hierarchyInfo += ' → ';
                            }
                        });
                    }
                    
                    console.log(hierarchyInfo);
                    console.log(`Najwyższy nadagent w hierarchii: ${data.top_agent ? data.top_agent.nazwa_agenta : 'brak'}`);
                    
                    // Refresh the tree with the latest data
                    if (data.full_tree) {
                        initializeTree(data.full_tree);
                    }
                }
            })
            .catch(error => {
                console.error('Błąd pobierania hierarchii agentów:', error);
            });
    }

    // Initialize the tree directly from the API for the most up-to-date data
    initializeTreeFromApi();
    
    // Function to initialize the tree from API data
    function initializeTreeFromApi() {
        fetch('/api/agent-hierarchy?agent_name=dummy')
            .then(response => response.json())
            .then(data => {
                if (data.full_tree) {
                    initializeTree(data.full_tree);
                } else {
                    // Fallback to the PHP-generated tree data
                    const agentData = <?php echo $agentTreeJson; ?>;
                    initializeTree(agentData);
                }
            })
            .catch(error => {
                console.error('Error fetching agent tree:', error);
                // Fallback to the PHP-generated tree data
                const agentData = <?php echo $agentTreeJson; ?>;
                initializeTree(agentData);
            });
    }
    
    // Function to transform agent data into jsTree format
    function transformAgentDataForJsTree(agentData) {
        // If there are no agents, return a placeholder
        if (!agentData || agentData.length === 0) {
            return [{
                id: 'no-agents',
                text: 'Brak agentów w systemie',
                icon: 'fas fa-info-circle',
                state: {
                    opened: true,
                    disabled: true
                }
            }];
        }
        
        // Convert agent data to jsTree format
        return agentData.map(agent => {
            // Check if this is Kuba/Jakub for special styling
            const isKuba = agent.nazwa_agenta.toLowerCase() === 'kuba' || 
                         agent.nazwa_agenta.toLowerCase() === 'jakub';
            
            // Create the node
            const node = {
                id: 'agent_' + agent.id_agenta,
                text: agent.nazwa_agenta,
                // Determine the node type based on children and special names
                type: isKuba ? 'kuba' : 
                     (agent.children && agent.children.length > 0) ? 'supervisor' : 'subordinate',
                state: {
                    opened: true
                }
            };
            
            // Add children if they exist
            if (agent.children && agent.children.length > 0) {
                node.children = transformAgentDataForJsTree(agent.children);
            }
            
            return node;
        });
    }
    
    // Function to initialize jsTree with agent data
    function initializeTree(agentData) {
        // Destroy existing tree if it exists
        if ($.jstree.reference('#agent-tree-container')) {
            $('#agent-tree-container').jstree('destroy');
        }
        
        // Display loading indicator
        $('#agent-tree-container').addClass('jstree-loading');
        
        // Transform the data for jsTree format
        const jsTreeData = transformAgentDataForJsTree(agentData);
        
        // Initialize jsTree
        $('#agent-tree-container').jstree({
            'core': {
                'data': jsTreeData,
                'themes': {
                    'responsive': true,
                    'variant': 'large',
                    'dots': false,
                    'stripes': true
                },
                'animation': 200,
                'check_callback': true
            },
            'plugins': ['types', 'wholerow', 'state', 'search', 'sort'],
            'types': {
                'supervisor': {
                    'icon': 'fas fa-user-tie',
                    'li_attr': {
                        'class': 'supervisor-node'
                    }
                },
                'subordinate': {
                    'icon': 'fas fa-user',
                    'li_attr': {
                        'class': 'subordinate-node'
                    }
                },
                'kuba': {
                    'icon': 'fas fa-crown',
                    'li_attr': {
                        'class': 'kuba-node'
                    }
                }
            },
            'state': {
                'key': 'agent_tree_state',
                'opened': true // Start with all nodes open
            },
            'search': {
                'show_only_matches': true,
                'show_only_matches_children': true
            },
            'sort': function(a, b) {
                // Always put Kuba at the top
                const aText = this.get_node(a).text.toLowerCase();
                const bText = this.get_node(b).text.toLowerCase();
                
                if (aText === 'kuba' || aText === 'jakub') return -1;
                if (bText === 'kuba' || bText === 'jakub') return 1;
                
                // Sort supervisors before subordinates
                const aType = this.get_node(a).type;
                const bType = this.get_node(b).type;
                
                if (aType === 'supervisor' && bType !== 'supervisor') return -1;
                if (aType !== 'supervisor' && bType === 'supervisor') return 1;
                
                // Default alphabetical sort
                return aText > bText ? 1 : -1;
            }
        }).on('ready.jstree', function() {
            // Remove loading state
            $('#agent-tree-container').removeClass('jstree-loading');
            
            // Add animation class
            $('#agent-tree-container').addClass('jstree-animate-in');
            
            // Open all nodes by default
            $(this).jstree('open_all');
            
            // Highlight Kuba in the tree
            highlightKubaInTree();
            
            // Add a little delay to ensure the tree is fully rendered
            setTimeout(function() {
                // Add agent count to the tree title
                const agentCount = $('#agent-tree-container .jstree-node').length;
                $('.agent-tree-title').append(' <span class="agent-count">(' + agentCount + ' agentów)</span>');
                
                // Add search box if there are enough agents
                if (agentCount > 5) {
                    addSearchBox();
                }
                
                // Add legend
                addTreeLegend();
            }, 300);
        }).on('select_node.jstree', function(e, data) {
            // Add selected class to parent li
            const nodeId = data.node.id;
            $('#' + nodeId).addClass('selected-agent-node');
            
            // Get agent ID from the node ID (format: "agent_X")
            const agentId = nodeId.replace('agent_', '');
            
            // For special case of Kuba/Jakub, use "jakub" as the ID
            const isKuba = data.node.text.toLowerCase() === 'kuba' || 
                         data.node.text.toLowerCase() === 'jakub';
            
            // Store the selected agent info for later use
            window.selectedAgentId = agentId !== 'no-agents' ? agentId : null;
            window.selectedAgentName = data.node.text;
            window.selectedAgentIsKuba = isKuba;
            
            // Show the "View Cases" button if a valid agent is selected
            if (agentId !== 'no-agents') {
                // Create or update the view button
                let viewButton = $('#view-agent-cases-button');
                if (viewButton.length === 0) {
                    // Create the button if it doesn't exist
                    viewButton = $('<button id="view-agent-cases-button" class="view-cases-button">' +
                                   '<i class="fas fa-folder-open"></i> Zobacz sprawy agenta</button>');
                    
                    // Add click handler to navigate to the test page
                    viewButton.on('click', function() {
                        if (window.selectedAgentId) {
                            const redirectUrl = window.selectedAgentIsKuba ? 
                                '/podsumowanie-spraw?agent_id=1' : 
                                `/podsumowanie-spraw?agent_id=${window.selectedAgentId}`;
                            window.location.href = redirectUrl;
                        }
                    });
                    
                    // Add to the page after the tree
                    $('#agent-tree-container').after(viewButton);
                }
                
                // Update button text with agent name
                viewButton.html(`<i class="fas fa-folder-open"></i> Zobacz sprawy agenta: <strong>${data.node.text}</strong>`);
            }
        });
    }
    
    // Function to highlight Kuba in the tree
    function highlightKubaInTree() {
        // Find Kuba/Jakub node
        $('.jstree-node').each(function() {
            const nodeText = $(this).find('.jstree-anchor').text().toLowerCase();
            if (nodeText === 'kuba' || nodeText === 'jakub') {
                // Add special styling
                $(this).addClass('kuba-node-highlight');
                $(this).find('.jstree-anchor').addClass('kuba-node-anchor');
            }
        });
    }
    
    // Function to add search box for the tree
    function addSearchBox() {
        // Create search box
        const searchBox = `
            <div class="agent-tree-search">
                <input type="text" id="agent-tree-search-input" placeholder="Szukaj agenta...">
                <button id="agent-tree-search-button"><i class="fas fa-search"></i></button>
                <button id="agent-tree-search-clear"><i class="fas fa-times"></i></button>
            </div>
        `;
        
        // Add search box before the tree
        $('#agent-tree-container').before(searchBox);
        
        // Add search functionality
        $('#agent-tree-search-button').on('click', function() {
            const searchString = $('#agent-tree-search-input').val();
            $('#agent-tree-container').jstree('search', searchString);
        });
        
        // Add clear search functionality
        $('#agent-tree-search-clear').on('click', function() {
            $('#agent-tree-search-input').val('');
            $('#agent-tree-container').jstree('clear_search');
        });
        
        // Search on Enter key
        $('#agent-tree-search-input').on('keyup', function(e) {
            if (e.key === 'Enter') {
                const searchString = $(this).val();
                $('#agent-tree-container').jstree('search', searchString);
            }
        });
    }
    
    // Function to add legend for tree node types
    function addTreeLegend() {
        // Create legend HTML
        const legend = `
            <div class="agent-tree-legend">
                <div class="legend-title">Legenda:</div>
                <div class="legend-item">
                    <i class="fas fa-crown"></i> <span>Główny agent</span>
                </div>
                <div class="legend-item">
                    <i class="fas fa-user-tie"></i> <span>Agent z podwładnymi</span>
                </div>
                <div class="legend-item">
                    <i class="fas fa-user"></i> <span>Agent bez podwładnych</span>
                </div>
            </div>
        `;
        
        // Add legend after the tree
        $('.agent-tree').append(legend);
    }
});
</script>
</body>
</html>