-- Tabela pośrednia sprawa_agent
CREATE TABLE `sprawa_agent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sprawa_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `rola` varchar(10) NOT NULL, -- 'agent_1', 'agent_2', itd.
  `percentage` decimal(5,2) DEFAULT NULL, -- Procent prowizji agenta
  PRIMARY KEY (`id`),
  UNIQUE KEY `sprawa_agent_unique` (`sprawa_id`, `agent_id`),
  KEY `agent_id` (`agent_id`),
  CONSTRAINT `sprawa_agent_ibfk_1` FOREIGN KEY (`sprawa_id`) REFERENCES `test2` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sprawa_agent_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `agenci` (`agent_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 