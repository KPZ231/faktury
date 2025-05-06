-- Migracja danych z istniejącego modelu do nowego modelu
-- Przenoszenie danych agentów 1-5 do tabeli sprawa_agent

-- Agent 1
INSERT INTO sprawa_agent (sprawa_id, agent_id, rola, percentage)
SELECT t.id, a.agent_id, 'agent_1', t.agent1_percentage
FROM test2 t
JOIN agenci a ON JSON_CONTAINS(a.sprawy, CAST(t.id AS JSON), '$')
WHERE t.agent1_percentage IS NOT NULL;

-- Agent 2
INSERT INTO sprawa_agent (sprawa_id, agent_id, rola, percentage)
SELECT t.id, a.agent_id, 'agent_2', t.agent2_percentage
FROM test2 t
JOIN agenci a ON JSON_CONTAINS(a.sprawy, CAST(t.id AS JSON), '$')
WHERE t.agent2_percentage IS NOT NULL;

-- Agent 3
INSERT INTO sprawa_agent (sprawa_id, agent_id, rola, percentage)
SELECT t.id, a.agent_id, 'agent_3', t.agent3_percentage
FROM test2 t
JOIN agenci a ON JSON_CONTAINS(a.sprawy, CAST(t.id AS JSON), '$')
WHERE t.agent3_percentage IS NOT NULL;

-- Agent 4
INSERT INTO sprawa_agent (sprawa_id, agent_id, rola, percentage)
SELECT t.id, a.agent_id, 'agent_4', t.agent4_percentage
FROM test2 t
JOIN agenci a ON JSON_CONTAINS(a.sprawy, CAST(t.id AS JSON), '$')
WHERE t.agent4_percentage IS NOT NULL;

-- Agent 5
INSERT INTO sprawa_agent (sprawa_id, agent_id, rola, percentage)
SELECT t.id, a.agent_id, 'agent_5', t.agent5_percentage
FROM test2 t
JOIN agenci a ON JSON_CONTAINS(a.sprawy, CAST(t.id AS JSON), '$')
WHERE t.agent5_percentage IS NOT NULL; 