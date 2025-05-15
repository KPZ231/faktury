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
    <link rel="shortcut icon" href="../../assets/images/favicon.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- jsTree CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.16/themes/default/style.min.css" />
    <title>Dodaj Agenta</title>
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
                                '/test?agent_id=jakub' : 
                                `/test?agent_id=${window.selectedAgentId}`;
                            window.location.href = redirectUrl;
                        }
                    });
                    
                    // Add to the page after the tree
                    $('#agent-tree-container').after(viewButton);
                }
                
                // Update button text with agent name
                viewButton.html(`<i class="fas fa-folder-open"></i> Zobacz sprawy agenta: <strong>${data.node.text}</strong>`);
                viewButton.show();
            }
            
            // Optional: do something when a node is selected
            console.log('Selected agent:', data.node.text, 'with ID:', agentId);
            
            // Highlight hierarchy path
            highlightHierarchyPath(data.node);
        }).on('deselect_node.jstree', function(e, data) {
            // Remove selected class from parent li
            $('.selected-agent-node').removeClass('selected-agent-node');
            
            // Hide the view button when deselecting
            $('#view-agent-cases-button').hide();
            
            // Clear the selected agent info
            window.selectedAgentId = null;
            window.selectedAgentName = null;
            window.selectedAgentIsKuba = false;
            
            // Remove hierarchy path highlighting
            resetHierarchyHighlighting();
        });
        
        // Add cursor pointer style to tree nodes to indicate they are clickable
        $('<style>').text(`
            .jstree-anchor {
                cursor: pointer;
            }
            .jstree-anchor:hover {
                background-color: rgba(0, 123, 255, 0.1);
            }
            .view-cases-button {
                display: none;
                margin-top: 15px;
                padding: 10px 15px;
                background-color: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                transition: background-color 0.2s;
            }
            .view-cases-button:hover {
                background-color: #0056b3;
            }
            .view-cases-button i {
                margin-right: 8px;
            }
            .view-cases-button strong {
                font-weight: 600;
            }
        `).appendTo('head');
    }
    
    // Function to transform agent data for jsTree format
    function transformAgentDataForJsTree(agents) {
        if (!agents || agents.length === 0) {
            return [{
                'id': 'no-agents',
                'text': 'Brak agentów w systemie lub brak relacji nadrzędnych.',
                'type': 'default'
            }];
        }
        
        // Filter out agents with supervisors to show only supervisors at the top level
        let topLevelAgents = agents.filter(agent => !agent.nadagent);
        
        // If there are no top level agents, show all agents
        if (topLevelAgents.length === 0) {
            topLevelAgents = agents;
        }
        
        return topLevelAgents.map(agent => {
            // Check if this is Kuba/Jakub
            const isKuba = agent.nazwa_agenta.toLowerCase() === 'kuba' || 
                           agent.nazwa_agenta.toLowerCase() === 'jakub';
            
            // Create node object
            const node = {
                'id': 'agent_' + agent.id_agenta,
                'text': agent.nazwa_agenta,
                'type': agent.children && agent.children.length > 0 ? 'supervisor' : 'subordinate'
            };
            
            // If this is Kuba, set special type
            if (isKuba) {
                node.type = 'kuba';
            }
            
            // Add children if they exist
            if (agent.children && agent.children.length > 0) {
                node.children = transformAgentDataForJsTree(agent.children);
                // If the agent has children, mark as a supervisor
                node.type = 'supervisor';
            }
            
            return node;
        });
    }
    
    // Function to highlight Kuba in the tree
    function highlightKubaInTree() {
        fetch(`/api/agent-hierarchy?agent_name=Kuba`)
            .then(response => response.json())
            .then(data => {
                if (data.kuba_agent) {
                    const kubaId = 'agent_' + data.kuba_agent.id_agenta;
                    const kubaNode = $('#agent-tree-container').find(`li[id="${kubaId}"]`);
                    
                    if (kubaNode.length) {
                        kubaNode.addClass('kuba-agent');
                        // Ensure Kuba is visible by expanding all its parent nodes
                        $('#agent-tree-container').jstree('_open_to', kubaId);
                    }
                }
            })
            .catch(error => {
                console.error('Błąd pobierania informacji o Kubie:', error);
            });
    }

    // Function to add a search box
    function addSearchBox() {
        // Create search box
        const searchBox = $('<div class="tree-search-container">' +
            '<input type="text" class="tree-search-input" placeholder="Szukaj agenta...">' +
            '<button class="tree-search-clear"><i class="fas fa-times"></i></button>' +
            '</div>');
        
        // Add search box before the tree
        $('#agent-tree-container').before(searchBox);
        
        // Add search functionality
        const searchTimer = {};
        $('.tree-search-input').on('keyup', function() {
            const searchString = $(this).val();
            clearTimeout(searchTimer);
            
            searchTimer.search = setTimeout(function() {
                $('#agent-tree-container').jstree('search', searchString);
                
                if (searchString.length > 0) {
                    $('.tree-search-clear').show();
                } else {
                    $('.tree-search-clear').hide();
                }
            }, 250);
        });
        
        // Add clear button functionality
        $('.tree-search-clear').on('click', function() {
            $('.tree-search-input').val('').trigger('keyup');
            $(this).hide();
        }).hide();
        
        // Add styles
        $('<style>').text(`
            .tree-search-container {
                margin-bottom: 15px;
                position: relative;
            }
            .tree-search-input {
                width: 100%;
                padding: 10px 35px 10px 15px;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                font-size: 14px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .tree-search-input:focus {
                outline: none;
                border-color: #007bff;
                box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
            }
            .tree-search-clear {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: #6c757d;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 20px;
                height: 20px;
                border-radius: 50%;
            }
            .tree-search-clear:hover {
                background-color: #f0f0f0;
                color: #007bff;
            }
            .agent-count {
                font-size: 14px;
                color: #6c757d;
                font-weight: normal;
            }
        `).appendTo('head');
    }

    // Function to add legend
    function addTreeLegend() {
        const legend = $('<div class="tree-legend">' +
            '<div class="legend-title">Legenda:</div>' +
            '<div class="legend-item"><span class="legend-icon supervisor-icon"><i class="fas fa-user-tie"></i></span> Agent zarządzający</div>' +
            '<div class="legend-item"><span class="legend-icon subordinate-icon"><i class="fas fa-user"></i></span> Agent podwładny</div>' +
            '<div class="legend-item"><span class="legend-icon kuba-icon"><i class="fas fa-crown"></i></span> Agent główny (Kuba)</div>' +
            '</div>');
        
        // Add legend after the tree
        $('#agent-tree-container').after(legend);
        
        // Add styles
        $('<style>').text(`
            .tree-legend {
                margin-top: 20px;
                padding: 15px;
                background-color: #f8f9fa;
                border-radius: 6px;
                border: 1px solid #e0e0e0;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 15px;
            }
            .legend-title {
                font-weight: 600;
                color: #495057;
                margin-right: 10px;
            }
            .legend-item {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                color: #495057;
            }
            .legend-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 24px;
                height: 24px;
                border-radius: 50%;
            }
            .supervisor-icon {
                color: #007bff;
                background-color: #e7f5ff;
            }
            .subordinate-icon {
                color: #6c757d;
                background-color: #f8f9fa;
            }
            .kuba-icon {
                color: #ffc107;
                background-color: #fff3cd;
            }
        `).appendTo('head');
    }

    // Function to highlight the path in the hierarchy
    function highlightHierarchyPath(node) {
        // Remove previous highlighting
        resetHierarchyHighlighting();
        
        // Add highlighting to current node
        $('#' + node.id).addClass('highlight-current');
        
        // Get all parents
        const tree = $('#agent-tree-container').jstree(true);
        const parents = tree.get_path(node.id, false, true);
        
        // Highlight parents
        parents.forEach(function(parentId) {
            if (parentId !== node.id) {
                $('#' + parentId).addClass('highlight-parent');
            }
        });
        
        // Highlight children
        if (node.children) {
            node.children.forEach(function(childId) {
                $('#' + childId).addClass('highlight-child');
            });
        }
        
        // Add styles
        if (!$('#hierarchy-highlight-styles').length) {
            $('<style id="hierarchy-highlight-styles">').text(`
                .highlight-current > .jstree-anchor {
                    box-shadow: 0 0 0 2px #007bff !important;
                    z-index: 10;
                }
                .highlight-parent > .jstree-anchor {
                    box-shadow: 0 0 0 1px rgba(0, 123, 255, 0.5) !important;
                    background-color: rgba(0, 123, 255, 0.05) !important;
                }
                .highlight-child > .jstree-anchor {
                    box-shadow: 0 0 0 1px rgba(108, 117, 125, 0.5) !important;
                    background-color: rgba(108, 117, 125, 0.05) !important;
                }
            `).appendTo('head');
        }
    }

    // Function to reset hierarchy highlighting
    function resetHierarchyHighlighting() {
        $('.highlight-current, .highlight-parent, .highlight-child').removeClass('highlight-current highlight-parent highlight-child');
    }
});
</script>

</body>

</html>