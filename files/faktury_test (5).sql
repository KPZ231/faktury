-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Maj 16, 2025 at 12:40 PM
-- Wersja serwera: 10.4.32-MariaDB
-- Wersja PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `faktury_test`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `agenci`
--

CREATE TABLE `agenci` (
  `id_agenta` int(11) NOT NULL,
  `nazwa_agenta` varchar(100) NOT NULL COMMENT 'Imię i nazwisko lub identyfikator agenta',
  `sprawy` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sprawy`)),
  `nadagent` varchar(255) NOT NULL COMMENT 'Pole oznaczajace o nadagencie, przypisanym do agenta'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agenci`
--

INSERT INTO `agenci` (`id_agenta`, `nazwa_agenta`, `sprawy`, `nadagent`) VALUES
(1, 'Kuba', NULL, ''),
(2, 'Ivan lolopek', NULL, ''),
(3, 'Maciej Musiał', NULL, ''),
(4, 'Kamil Konieczny', NULL, ''),
(5, 'Piotr P', NULL, ''),
(6, 'Kardynał Popielec', NULL, ''),
(7, 'Piotr Nowakowski', NULL, ''),
(8, 'Anna Wiśniewska', NULL, ''),
(9, 'Marek Kowalski', NULL, ''),
(10, 'Katarzyna Wójcik', NULL, ''),
(11, 'Tomasz Kamiński', NULL, ''),
(12, 'Magdalena Lewandowska', NULL, ''),
(13, 'Marcin Zieliński', NULL, ''),
(14, 'Joanna Szymańska', NULL, ''),
(15, 'Krzysztof Dąbrowski', NULL, ''),
(16, 'Agnieszka Jankowska', NULL, ''),
(17, 'Grzegorz Wojciechowski', NULL, ''),
(18, 'Barbara Mazur', NULL, ''),
(19, 'Adam Krawczyk', NULL, ''),
(20, 'Monika Piotrowska', NULL, ''),
(21, 'Łukasz Grabowski', NULL, ''),
(22, 'Maria Pawlak', NULL, ''),
(23, 'Paweł Michalski', NULL, ''),
(24, 'Ewa Zając', NULL, ''),
(25, 'Michał Król', NULL, ''),
(26, 'Zofia Jabłońska', NULL, ''),
(27, 'Relraviediev Sheibenaschulangenistanov', NULL, ''),
(28, 'RObert', NULL, ''),
(29, 'Robert Smolczyk', NULL, 'Kamil Konieczny'),
(30, 'Daniel Urzed', NULL, 'Robert Smolczyk'),
(32, 'Daniel Urzedi', NULL, 'Robert Smolczyk'),
(33, 'kuba Ty', NULL, '');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `agenci_wyplaty`
--

CREATE TABLE `agenci_wyplaty` (
  `id_wyplaty` int(11) NOT NULL,
  `id_sprawy` int(11) NOT NULL,
  `id_agenta` int(11) NOT NULL,
  `opis_raty` varchar(50) NOT NULL,
  `kwota` decimal(10,2) NOT NULL,
  `czy_oplacone` tinyint(1) DEFAULT 0,
  `numer_faktury` varchar(100) DEFAULT NULL,
  `data_platnosci` date DEFAULT NULL,
  `data_utworzenia` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_modyfikacji` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Dumping data for table `agenci_wyplaty`
--

INSERT INTO `agenci_wyplaty` (`id_wyplaty`, `id_sprawy`, `id_agenta`, `opis_raty`, `kwota`, `czy_oplacone`, `numer_faktury`, `data_platnosci`, `data_utworzenia`, `data_modyfikacji`) VALUES
(86, 25, 19, 'Prowizja Rata 1', 300.00, 1, 'fv/agent1/25', '2025-05-16', '2025-05-16 09:52:59', '2025-05-16 10:38:43');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `faktury`
--

CREATE TABLE `faktury` (
  `LP` int(2) DEFAULT NULL,
  `numer` varchar(13) DEFAULT NULL,
  `Typ` varchar(7) DEFAULT NULL,
  `Sprzedający` varchar(16) DEFAULT NULL,
  `Nazwa skrócona działu` varchar(5) DEFAULT NULL,
  `NIP sprzedającego` int(8) DEFAULT NULL,
  `Status` varchar(8) DEFAULT NULL,
  `Data wystawienia` varchar(10) DEFAULT NULL,
  `Data sprzedaży` varchar(10) DEFAULT NULL,
  `Termin płatności` varchar(10) DEFAULT NULL,
  `Nabywca` varchar(20) DEFAULT NULL,
  `NIP` int(8) DEFAULT NULL,
  `Ulica i nr` varchar(9) DEFAULT NULL,
  `Kod pocztowy` varchar(6) DEFAULT NULL,
  `Miejscowość` varchar(6) DEFAULT NULL,
  `Kraj` varchar(2) DEFAULT NULL,
  `E-mail klienta` varchar(10) DEFAULT NULL,
  `Telefon klienta` varchar(10) DEFAULT NULL,
  `Telefon komórkowy` varchar(10) DEFAULT NULL,
  `Wartość netto` decimal(7,2) DEFAULT NULL,
  `VAT` decimal(6,2) DEFAULT NULL,
  `Wartość brutto` decimal(7,2) DEFAULT NULL,
  `Wartość netto PLN` decimal(7,2) DEFAULT NULL,
  `VAT PLN` decimal(6,2) DEFAULT NULL,
  `Wartość brutto PLN` decimal(7,2) DEFAULT NULL,
  `Płatność` varchar(7) DEFAULT NULL,
  `Data płatności` varchar(10) DEFAULT NULL,
  `Kwota opłacona` decimal(7,2) DEFAULT NULL,
  `Waluta` varchar(3) DEFAULT NULL,
  `Nr zamówienia` varchar(10) DEFAULT NULL,
  `Adresat` varchar(10) DEFAULT NULL,
  `Kategoria` varchar(10) DEFAULT NULL,
  `Uwagi` varchar(10) DEFAULT NULL,
  `Kody GTU` varchar(10) DEFAULT NULL,
  `Oznaczenia dotyczące procedur` varchar(10) DEFAULT NULL,
  `Oryginalny dokument` varchar(10) DEFAULT NULL,
  `Przyczyna korekty` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `faktury`
--

INSERT INTO `faktury` (`LP`, `numer`, `Typ`, `Sprzedający`, `Nazwa skrócona działu`, `NIP sprzedającego`, `Status`, `Data wystawienia`, `Data sprzedaży`, `Termin płatności`, `Nabywca`, `NIP`, `Ulica i nr`, `Kod pocztowy`, `Miejscowość`, `Kraj`, `E-mail klienta`, `Telefon klienta`, `Telefon komórkowy`, `Wartość netto`, `VAT`, `Wartość brutto`, `Wartość netto PLN`, `VAT PLN`, `Wartość brutto PLN`, `Płatność`, `Data płatności`, `Kwota opłacona`, `Waluta`, `Nr zamówienia`, `Adresat`, `Kategoria`, `Uwagi`, `Kody GTU`, `Oznaczenia dotyczące procedur`, `Oryginalny dokument`, `Przyczyna korekty`) VALUES
(1, 'FV/6/03/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-01-15', '2025-01-15', '2025-01-23', 'CHF-Moszczyńscy', 12312312, 'Testowa 5', '44-208', 'Rybnik', 'PL', '', '', '', 2000.00, 1863.00, 9963.00, 8100.00, 1863.00, 9963.00, 'Przelew', '2025-04-14', 2000.00, 'PLN', '', '', '', '', '', '', '', ''),
(2, 'FV/5/02/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-06-03', '2025-06-03', '2025-06-04', 'CHF-Moszczyńscy', 12312312, 'Testowa 4', '44-207', 'Rybnik', 'PL', '', '', '', 8100.00, 1863.00, 9963.00, 8100.00, 1863.00, 9963.00, 'Przelew', '2025-03-10', 2000.00, 'PLN', '', '', '', '', '', '', '', ''),
(3, 'FV/4/01/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-09-30', '2025-09-30', '2025-10-10', 'Krzysztof Zieliński', 12312312, 'Testowa 5', '44-208', 'Rybnik', 'PL', '', '', '', 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'Przelew', '2025-02-10', 10035.00, 'PLN', '', '', '', '', '', '', '', ''),
(4, 'FV/3/01/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-10-31', '2025-10-31', '2025-11-10', 'Katarzyna Wiśniewska', 12312312, 'Testowa 4', '44-207', 'Rybnik', 'PL', '', '', '', 7.17, 0.57, 7.74, 7.17, 0.57, 7.74, 'Przelew', '2025-01-23', 7.74, 'PLN', '', '', '', '', '', '', '', ''),
(5, 'FV/2/01/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-11-18', '2025-11-18', '2025-11-22', 'Joanna Wojciechowska', 12312312, 'Testowa 9', '44-212', 'Rybnik', 'PL', '', '', '', 15000.00, 1200.00, 16200.00, 15000.00, 1200.00, 16200.00, 'Przelew', '2025-01-27', 16200.00, 'PLN', '', '', '', '', '', '', '', ''),
(6, 'FV/1/01/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-11-30', '2025-11-30', '2025-12-10', 'Jan Woźniak', 12312312, 'Testowa 8', '44-211', 'Rybnik', 'PL', '', '', '', 7500.00, 600.00, 8100.00, 7500.00, 600.00, 8100.00, 'Przelew', '2025-01-27', 8100.00, 'PLN', '', '', '', '', '', '', '', ''),
(7, 'FV/15/12/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-12-11', '2025-12-11', '2025-12-11', 'Grzegorz Krawczyk', 12312312, 'Testowa 7', '44-210', 'Rybnik', 'PL', '', '', '', 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'Przelew', '2025-01-09', 10035.00, 'PLN', '', '', '', '', '', '', '', ''),
(8, 'FV/14/12/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-12-31', '2025-12-31', '2025-01-10', 'Ewa Lewandowska', 12312312, 'Testowa 6', '44-209', 'Rybnik', 'PL', '', '', '', 30.00, 2.40, 32.40, 30.00, 2.40, 32.40, 'Przelew', '2024-12-11', 32.40, 'PLN', '', '', '', '', '', '', '', ''),
(9, 'FV/13/11/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-01-16', '2025-01-16', '2025-01-27', 'Barbara Dąbrowska', 12312312, 'Testowa 4', '44-207', 'Rybnik', 'PL', '', '', '', 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'Przelew', '2024-12-10', 10035.00, 'PLN', '', '', '', '', '', '', '', ''),
(10, 'FV/12/11/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-06-26', '2025-06-26', '2025-07-03', 'Tomasz Wójcik', 12312312, 'Testowa 9', '44-213', 'Rybnik', 'PL', '', '0324392000', '', 341.46, 78.54, 420.00, 341.46, 78.54, 420.00, 'Przelew', '2024-11-21', 420.00, 'PLN', '', '', '', '', '', '', '', ''),
(11, 'FV/11/10/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-01-22', '2025-01-22', '2025-01-22', 'Anna Nowak', 12312312, 'Testowa 4', '44-210', 'Rybnik', 'PL', '', '', '', 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'Przelew', '2024-11-18', 10035.00, 'PLN', '', '', '', '', '', '', '', ''),
(12, 'FV/10/09/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-06-27', '2025-06-27', '2025-06-28', 'Tomasz Wójcik', 12312312, 'Testowa 8', '44-212', 'Rybnik', 'PL', '', '', '', 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'Przelew', '2024-10-16', 10035.00, 'PLN', '', '', '', '', '', '', '', ''),
(13, 'FV/9/09/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-01-31', '2025-01-31', '2025-02-10', 'Anna Nowak', 12312312, 'Testowa 3', '44-209', 'Rybnik', 'PL', '', '', '', 28.27, 2.26, 30.53, 28.27, 2.26, 30.53, 'Przelew', '2024-09-17', 30.53, 'PLN', '', '', '', '', '', '', '', ''),
(14, 'FV/8/09/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-06-28', '2025-06-28', '2025-07-12', 'Piotr Kowalski', 12312312, 'Testowa 7', '44-211', 'Rybnik', 'PL', '', '', '', 14800.00, 1184.00, 15984.00, 14800.00, 1184.00, 15984.00, 'Przelew', '2024-09-30', 15984.00, 'PLN', '', '', '', '', '', '', '', ''),
(15, 'FV/7/08/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-02-28', '2025-02-28', '2025-03-10', 'Andrzej Kozłowski', 12312312, 'Testowa 2', '44-208', 'Rybnik', 'PL', '', '', '', 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'Przelew', '2024-09-12', 10035.00, 'PLN', '', '', '', '', '', '', '', ''),
(16, 'FV/6/07/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-07-15', '2025-07-15', '2025-07-18', 'Paweł Mazur', 12312312, 'Testowa 6', '44-210', 'Rybnik', 'PL', '', '', '', 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'Przelew', '2024-08-14', 10035.00, 'PLN', '', '', '', '', '', '', '', ''),
(17, 'FV/5/07/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-03-31', '2025-03-31', '2025-04-10', 'Agnieszka Kowalczyk', 12312312, 'Testowa 1', '44-207', 'Rybnik', 'PL', '', '', '', 7500.00, 600.00, 8100.00, 7500.00, 600.00, 8100.00, 'Przelew', '2024-07-15', 8100.00, 'PLN', '', '', '', '', '', '', '', ''),
(18, 'FV/4/06/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-07-31', '2025-07-31', '2025-08-10', 'Monika Piotrowska', 12312312, 'Testowa 5', '44-209', 'Rybnik', 'PL', '', '', '', 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'Przelew', '2024-07-12', 10035.00, 'PLN', '', '', '', '', '', '', '', ''),
(19, 'FV/3/06/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-08-30', '2025-08-30', '2025-09-10', 'Maria Szymańska', 12312312, 'Testowa 4', '44-208', 'Rybnik', 'PL', '', '', '', 7756.20, 620.50, 8376.70, 7756.20, 620.50, 8376.70, 'Przelew', '2024-06-19', 8376.70, 'PLN', '', '', '', '', '', '', '', ''),
(20, 'FV/2/06/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-09-17', '2025-09-17', '2025-10-01', 'Marcin Kamiński', 12312312, 'Testowa 4', '44-207', 'Rybnik', 'PL', '', '', '', 195.00, 44.85, 239.85, 195.00, 44.85, 239.85, 'Przelew', '2024-06-28', 239.85, 'PLN', '', '', '', '', '', '', '', ''),
(21, 'FV/1/06/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-09-17', '2025-09-17', '2025-09-17', 'Łukasz Kwiatkowski', 12312312, 'Testowa 6', '44-209', 'Rybnik', 'PL', '', '', '', 7756.20, 620.50, 8376.70, 7756.20, 620.50, 8376.70, 'Przelew', '2024-05-29', 8376.70, 'PLN', '', '', '', '', '', '', '', ''),
(22, 'FV/15/05/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-01-15', '2025-01-15', '2025-01-23', 'Ewa Lewandowska', 12312312, 'Testowa 5', '44-208', 'Rybnik', 'PL', '', '', '', 8100.00, 1863.00, 9963.00, 8100.00, 1863.00, 9963.00, 'Przelew', '2025-04-14', 3000.00, 'PLN', '', '', '', '', '', '', '', ''),
(24, 'FV/16/05/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-01-15', '2025-01-15', '2025-01-23', 'Ewa Lewandowska', 12312312, 'Testowa 5', '44-208', 'Rybnik', 'PL', '', '', '', 8100.00, 1863.00, 9963.00, 8100.00, 1863.00, 9963.00, 'Przelew', '2025-04-14', 3000.00, 'PLN', '', '', '', '', '', '', '', '');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `oplaty_spraw`
--

CREATE TABLE `oplaty_spraw` (
  `id_oplaty_sprawy` int(11) NOT NULL,
  `id_sprawy` int(11) NOT NULL COMMENT 'Klucz obcy do tabeli Sprawy',
  `opis_raty` varchar(50) NOT NULL COMMENT 'Opis raty (np. "Rata 1", "Ostatnia")',
  `oczekiwana_kwota` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Planowana kwota danej raty ogólnej',
  `czy_oplacona` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Czy rata została opłacona',
  `data_oplaty` date DEFAULT NULL COMMENT 'Data faktycznego opłacenia raty',
  `faktura_id` varchar(20) DEFAULT NULL,
  `data_platnosci` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `oplaty_spraw`
--

INSERT INTO `oplaty_spraw` (`id_oplaty_sprawy`, `id_sprawy`, `opis_raty`, `oczekiwana_kwota`, `czy_oplacona`, `data_oplaty`, `faktura_id`, `data_platnosci`) VALUES
(341, 25, 'Rata 1', 2000.00, 1, '2025-05-16', 'FV/6/03/2025', NULL),
(342, 25, 'Rata 2', 2000.00, 1, NULL, 'FV/5/02/2025', NULL),
(343, 25, 'Rata 3', 1000.00, 0, NULL, NULL, NULL),
(344, 25, 'Rata końcowa', 6118.73, 0, NULL, NULL, NULL),
(349, 27, 'Rata 1', 3000.00, 1, NULL, 'FV/15/05/2025', NULL),
(350, 27, 'Rata 2', 3000.00, 1, NULL, 'FV/16/05/2025', NULL),
(351, 27, 'Rata 3', 3000.00, 0, NULL, NULL, NULL),
(352, 27, 'Rata końcowa', 24000.00, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `prowizje_agentow_spraw`
--

CREATE TABLE `prowizje_agentow_spraw` (
  `id_prowizji_agenta_sprawy` int(11) NOT NULL,
  `id_sprawy` int(11) NOT NULL COMMENT 'Klucz obcy do tabeli Sprawy',
  `id_agenta` int(11) NOT NULL COMMENT 'Klucz obcy do tabeli Agenci',
  `udzial_prowizji_proc` decimal(5,4) NOT NULL DEFAULT 0.0000 COMMENT 'Udział procentowy agenta w prowizji (np. 0.10 dla 10%)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prowizje_agentow_spraw`
--

INSERT INTO `prowizje_agentow_spraw` (`id_prowizji_agenta_sprawy`, `id_sprawy`, `id_agenta`, `udzial_prowizji_proc`) VALUES
(75, 25, 19, 0.1500),
(76, 25, 16, 0.0500),
(77, 25, 1, 0.2800),
(81, 27, 16, 0.1500),
(82, 27, 8, 0.0500),
(83, 27, 1, 0.2500);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `sprawy`
--

CREATE TABLE `sprawy` (
  `id_sprawy` int(11) NOT NULL,
  `identyfikator_sprawy` varchar(255) NOT NULL COMMENT 'Nazwa sprawy lub klienta',
  `czy_zakonczona` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Czy sprawa jest zakończona',
  `wywalczona_kwota` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Kwota wywalczona w sprawie',
  `oplata_wstepna` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Początkowa opłata za sprawę',
  `stawka_success_fee` decimal(5,4) NOT NULL DEFAULT 0.0000 COMMENT 'Stawka procentowa success fee (np. 0.08 dla 8%)',
  `uwagi` text DEFAULT NULL COMMENT 'Dodatkowe uwagi dotyczące sprawy'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sprawy`
--

INSERT INTO `sprawy` (`id_sprawy`, `identyfikator_sprawy`, `czy_zakonczona`, `wywalczona_kwota`, `oplata_wstepna`, `stawka_success_fee`, `uwagi`) VALUES
(25, 'CHF-Moszczyńscy', 0, 61187.31, 5000.00, 0.1000, NULL),
(27, 'Ewa Lewandowska', 0, 300000.00, 9000.00, 0.0800, NULL);

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `agenci`
--
ALTER TABLE `agenci`
  ADD PRIMARY KEY (`id_agenta`),
  ADD UNIQUE KEY `nazwa_agenta` (`nazwa_agenta`);

--
-- Indeksy dla tabeli `agenci_wyplaty`
--
ALTER TABLE `agenci_wyplaty`
  ADD PRIMARY KEY (`id_wyplaty`),
  ADD UNIQUE KEY `unique_wyplata` (`id_sprawy`,`id_agenta`,`opis_raty`),
  ADD KEY `id_agenta` (`id_agenta`);

--
-- Indeksy dla tabeli `oplaty_spraw`
--
ALTER TABLE `oplaty_spraw`
  ADD PRIMARY KEY (`id_oplaty_sprawy`),
  ADD UNIQUE KEY `unique_sprawa_opis_raty` (`id_sprawy`,`opis_raty`),
  ADD KEY `idx_oplaty_id_sprawy` (`id_sprawy`);

--
-- Indeksy dla tabeli `prowizje_agentow_spraw`
--
ALTER TABLE `prowizje_agentow_spraw`
  ADD PRIMARY KEY (`id_prowizji_agenta_sprawy`),
  ADD UNIQUE KEY `unikalna_prowizja_agent_sprawa` (`id_sprawy`,`id_agenta`),
  ADD KEY `idx_prowizje_id_sprawy` (`id_sprawy`),
  ADD KEY `idx_prowizje_id_agenta` (`id_agenta`);

--
-- Indeksy dla tabeli `sprawy`
--
ALTER TABLE `sprawy`
  ADD PRIMARY KEY (`id_sprawy`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agenci`
--
ALTER TABLE `agenci`
  MODIFY `id_agenta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `agenci_wyplaty`
--
ALTER TABLE `agenci_wyplaty`
  MODIFY `id_wyplaty` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `oplaty_spraw`
--
ALTER TABLE `oplaty_spraw`
  MODIFY `id_oplaty_sprawy` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=358;

--
-- AUTO_INCREMENT for table `prowizje_agentow_spraw`
--
ALTER TABLE `prowizje_agentow_spraw`
  MODIFY `id_prowizji_agenta_sprawy` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `sprawy`
--
ALTER TABLE `sprawy`
  MODIFY `id_sprawy` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agenci_wyplaty`
--
ALTER TABLE `agenci_wyplaty`
  ADD CONSTRAINT `agenci_wyplaty_ibfk_1` FOREIGN KEY (`id_sprawy`) REFERENCES `sprawy` (`id_sprawy`) ON DELETE CASCADE,
  ADD CONSTRAINT `agenci_wyplaty_ibfk_2` FOREIGN KEY (`id_agenta`) REFERENCES `agenci` (`id_agenta`) ON DELETE CASCADE;

--
-- Constraints for table `oplaty_spraw`
--
ALTER TABLE `oplaty_spraw`
  ADD CONSTRAINT `oplaty_spraw_ibfk_1` FOREIGN KEY (`id_sprawy`) REFERENCES `sprawy` (`id_sprawy`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `prowizje_agentow_spraw`
--
ALTER TABLE `prowizje_agentow_spraw`
  ADD CONSTRAINT `prowizje_agentow_spraw_ibfk_1` FOREIGN KEY (`id_sprawy`) REFERENCES `sprawy` (`id_sprawy`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `prowizje_agentow_spraw_ibfk_2` FOREIGN KEY (`id_agenta`) REFERENCES `agenci` (`id_agenta`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
