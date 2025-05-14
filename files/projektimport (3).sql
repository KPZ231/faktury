-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Maj 14, 2025 at 09:04 AM
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
-- Database: `projektimport`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `agenci`
--

CREATE TABLE `agenci` (
  `id_agenta` int(11) NOT NULL,
  `nazwa_agenta` varchar(100) NOT NULL COMMENT 'Imię i nazwisko lub identyfikator agenta'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agenci`
--

INSERT INTO `agenci` (`id_agenta`, `nazwa_agenta`) VALUES
(19, 'Adam Krawczyk'),
(16, 'Agnieszka Jankowska'),
(8, 'Anna Wiśniewska'),
(18, 'Barbara Mazur'),
(24, 'Ewa Zając'),
(17, 'Grzegorz Wojciechowski'),
(2, 'Ivan lolopek'),
(14, 'Joanna Szymańska'),
(4, 'Kamil Konieczny'),
(6, 'Kardynał Popielec'),
(10, 'Katarzyna Wójcik'),
(15, 'Krzysztof Dąbrowski'),
(1, 'Kuba'),
(3, 'Maciej Musiał'),
(12, 'Magdalena Lewandowska'),
(13, 'Marcin Zieliński'),
(9, 'Marek Kowalski'),
(22, 'Maria Pawlak'),
(25, 'Michał Król'),
(20, 'Monika Piotrowska'),
(23, 'Paweł Michalski'),
(7, 'Piotr Nowakowski'),
(5, 'Piotr P'),
(27, 'Relraviediev Sheibenaschulangenistanov'),
(11, 'Tomasz Kamiński'),
(26, 'Zofia Jabłońska'),
(21, 'Łukasz Grabowski');

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
(3, 1, 2, 'Rata 2', 300.00, 1, 'FV/AGENT/R3/2025', '2025-05-12', '2025-05-12 06:58:49', '2025-05-12 06:59:33'),
(4, 1, 2, 'Rata 1', 300.00, 1, 'FV/AGENT/R1/2025', '2025-05-12', '2025-05-12 06:59:40', '2025-05-12 06:59:42'),
(5, 1, 1, 'Rata 1', 450.00, 1, 'FV/AGENT/R2/2025', '2025-05-12', '2025-05-12 07:00:09', '2025-05-12 07:00:09'),
(6, 1, 1, 'Rata 2', 450.00, 1, 'FV/AGENT/R4/2025', '2025-05-12', '2025-05-12 07:13:18', '2025-05-12 07:13:18'),
(7, 2, 3, 'Rata 3', 40.00, 1, 'FV/AGENT/R7/2025', '2025-05-12', '2025-05-12 07:26:44', '2025-05-12 07:26:44'),
(8, 2, 2, 'Rata 1', 75.00, 1, '2323', '2025-05-12', '2025-05-12 07:55:18', '2025-05-12 08:32:58'),
(9, 2, 3, 'Rata 1', 25.00, 1, 'uyfjhfkjglk', '2025-05-12', '2025-05-12 07:57:26', '2025-05-12 09:48:46'),
(10, 2, 1, 'Rata 1', 30.00, 1, '', '2025-05-12', '2025-05-12 10:52:36', '2025-05-12 10:52:36'),
(11, 2, 1, 'Rata 3', 48.00, 1, 'hh', '2025-05-12', '2025-05-12 10:53:01', '2025-05-12 10:53:03');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `faktury`
--

CREATE TABLE `faktury` (
  `id_faktury` int(11) NOT NULL,
  `numer_faktury` varchar(50) NOT NULL COMMENT 'Numer faktury z CSV (kolumna numer)',
  `typ_dokumentu` varchar(50) NOT NULL DEFAULT 'Faktura' COMMENT 'Typ z CSV (np. Faktura)',
  `sprzedajacy_nazwa` varchar(255) DEFAULT NULL COMMENT 'Kolumna Sprzedający z CSV',
  `sprzedajacy_dzial` varchar(100) DEFAULT NULL COMMENT 'Kolumna Nazwa skrócona działu z CSV',
  `sprzedajacy_nip` varchar(20) DEFAULT NULL COMMENT 'Kolumna NIP sprzedającego z CSV',
  `status` varchar(50) NOT NULL DEFAULT 'Nieopłacona' COMMENT 'Status z CSV (np. Opłacona)',
  `data_wystawienia` date NOT NULL COMMENT 'Data wystawienia z CSV',
  `data_sprzedazy` date NOT NULL COMMENT 'Data sprzedaży z CSV',
  `termin_platnosci` date NOT NULL COMMENT 'Termin płatności z CSV',
  `id_sprawy` int(11) DEFAULT NULL COMMENT 'Klucz obcy do tabeli Sprawy - powiązany na podstawie Nabywcy/identyfikatora_sprawy',
  `nabywca_nazwa_historyczna` varchar(255) DEFAULT NULL COMMENT 'Oryginalna nazwa Nabywcy z CSV (dla referencji)',
  `nabywca_nip` varchar(20) DEFAULT NULL COMMENT 'NIP nabywcy z CSV',
  `nabywca_ulica` varchar(200) DEFAULT NULL COMMENT 'Ulica nabywcy (wyodrębniona z Ulica i nr)',
  `nabywca_numer_budynku` varchar(50) DEFAULT NULL COMMENT 'Numer budynku/lokalu nabywcy (wyodrębniony z Ulica i nr)',
  `nabywca_kod_pocztowy` varchar(10) DEFAULT NULL COMMENT 'Kod pocztowy nabywcy z CSV',
  `nabywca_miejscowosc` varchar(100) DEFAULT NULL COMMENT 'Miejscowość nabywcy z CSV',
  `nabywca_kraj` varchar(5) DEFAULT 'PL' COMMENT 'Kraj nabywcy z CSV',
  `nabywca_email` varchar(255) DEFAULT NULL COMMENT 'E-mail klienta z CSV',
  `nabywca_telefon` varchar(50) DEFAULT NULL COMMENT 'Telefon klienta z CSV',
  `nabywca_telefon_kom` varchar(50) DEFAULT NULL COMMENT 'Telefon komórkowy z CSV',
  `wartosc_netto` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Wartość netto z CSV (w walucie faktury)',
  `wartosc_vat` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VAT z CSV (w walucie faktury)',
  `wartosc_brutto` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Wartość brutto z CSV (w walucie faktury)',
  `wartosc_netto_pln` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Wartość netto PLN z CSV',
  `wartosc_vat_pln` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VAT PLN z CSV',
  `wartosc_brutto_pln` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Wartość brutto PLN z CSV',
  `waluta` varchar(3) NOT NULL DEFAULT 'PLN' COMMENT 'Waluta z CSV',
  `metoda_platnosci` varchar(50) DEFAULT NULL COMMENT 'Płatność z CSV (np. Przelew)',
  `data_ostatniej_wplaty` date DEFAULT NULL COMMENT 'Data płatności z CSV (może być ostatnia wpłata)',
  `kwota_oplacona_lacznie` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Kwota opłacona z CSV',
  `nr_zamowienia` varchar(100) DEFAULT NULL COMMENT 'Nr zamówienia z CSV',
  `adresat` varchar(255) DEFAULT NULL COMMENT 'Adresat z CSV',
  `kategoria` varchar(100) DEFAULT NULL COMMENT 'Kategoria z CSV',
  `uwagi` text DEFAULT NULL COMMENT 'Uwagi z CSV',
  `kody_gtu` varchar(100) DEFAULT NULL COMMENT 'Kody GTU z CSV',
  `oznaczenia_procedur` varchar(100) DEFAULT NULL COMMENT 'Oznaczenia dotyczące procedur z CSV',
  `oryginalny_dokument` varchar(100) DEFAULT NULL COMMENT 'Oryginalny dokument z CSV (dla korekt)',
  `przyczyna_korekty` text DEFAULT NULL COMMENT 'Przyczyna korekty z CSV'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faktury`
--

INSERT INTO `faktury` (`id_faktury`, `numer_faktury`, `typ_dokumentu`, `sprzedajacy_nazwa`, `sprzedajacy_dzial`, `sprzedajacy_nip`, `status`, `data_wystawienia`, `data_sprzedazy`, `termin_platnosci`, `id_sprawy`, `nabywca_nazwa_historyczna`, `nabywca_nip`, `nabywca_ulica`, `nabywca_numer_budynku`, `nabywca_kod_pocztowy`, `nabywca_miejscowosc`, `nabywca_kraj`, `nabywca_email`, `nabywca_telefon`, `nabywca_telefon_kom`, `wartosc_netto`, `wartosc_vat`, `wartosc_brutto`, `wartosc_netto_pln`, `wartosc_vat_pln`, `wartosc_brutto_pln`, `waluta`, `metoda_platnosci`, `data_ostatniej_wplaty`, `kwota_oplacona_lacznie`, `nr_zamowienia`, `adresat`, `kategoria`, `uwagi`, `kody_gtu`, `oznaczenia_procedur`, `oryginalny_dokument`, `przyczyna_korekty`) VALUES
(22, 'FV/6/03/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-01-15', '2025-01-15', '2025-01-23', NULL, 'Ewa Lewandowska', '12312312', 'Testowa', '5', '44-208', 'Rybnik', 'PL', NULL, NULL, NULL, 8100.00, 1863.00, 9963.00, 8100.00, 1863.00, 9963.00, 'PLN', 'Przelew', '2025-04-14', 9963.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'FV/5/02/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-06-03', '2025-06-03', '2025-06-04', NULL, 'Monika Piotrowska', '12312312', 'Testowa', '4', '44-207', 'Rybnik', 'PL', NULL, NULL, NULL, 8100.00, 1863.00, 9963.00, 8100.00, 1863.00, 9963.00, 'PLN', 'Przelew', '2025-03-10', 9963.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 'FV/4/01/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-09-30', '2025-09-30', '2025-10-10', NULL, 'Krzysztof Zieliński', '12312312', 'Testowa', '5', '44-208', 'Rybnik', 'PL', NULL, NULL, NULL, 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'PLN', 'Przelew', '2025-02-10', 10035.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 'FV/3/01/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-10-31', '2025-10-31', '2025-11-10', NULL, 'Katarzyna Wiśniewska', '12312312', 'Testowa', '4', '44-207', 'Rybnik', 'PL', NULL, NULL, NULL, 7.17, 0.57, 7.74, 7.17, 0.57, 7.74, 'PLN', 'Przelew', '2025-01-23', 7.74, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, 'FV/2/01/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-11-18', '2025-11-18', '2025-11-22', NULL, 'Joanna Wojciechowska', '12312312', 'Testowa', '9', '44-212', 'Rybnik', 'PL', NULL, NULL, NULL, 15000.00, 1200.00, 16200.00, 15000.00, 1200.00, 16200.00, 'PLN', 'Przelew', '2025-01-27', 16200.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 'FV/1/01/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-11-30', '2025-11-30', '2025-12-10', NULL, 'Jan Woźniak', '12312312', 'Testowa', '8', '44-211', 'Rybnik', 'PL', NULL, NULL, NULL, 7500.00, 600.00, 8100.00, 7500.00, 600.00, 8100.00, 'PLN', 'Przelew', '2025-01-27', 8100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 'FV/15/12/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-12-11', '2025-12-11', '2025-12-11', NULL, 'Grzegorz Krawczyk', '12312312', 'Testowa', '7', '44-210', 'Rybnik', 'PL', NULL, NULL, NULL, 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'PLN', 'Przelew', '2025-01-09', 10035.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'FV/14/12/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-12-31', '2025-12-31', '2025-01-10', NULL, 'Ewa Lewandowska', '12312312', 'Testowa', '6', '44-209', 'Rybnik', 'PL', NULL, NULL, NULL, 30.00, 2.40, 32.40, 30.00, 2.40, 32.40, 'PLN', 'Przelew', '2024-12-11', 32.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'FV/13/11/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-01-16', '2025-01-16', '2025-01-27', NULL, 'Barbara Dąbrowska', '12312312', 'Testowa', '4', '44-207', 'Rybnik', 'PL', NULL, NULL, NULL, 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'PLN', 'Przelew', '2024-12-10', 10035.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'FV/12/11/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-06-26', '2025-06-26', '2025-07-03', NULL, 'Tomasz Wójcik', '12312312', 'Testowa', '9', '44-213', 'Rybnik', 'PL', NULL, '0324392000', NULL, 341.46, 78.54, 420.00, 341.46, 78.54, 420.00, 'PLN', 'Przelew', '2024-11-21', 420.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, 'FV/11/10/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-01-22', '2025-01-22', '2025-01-22', 3, 'Anna Nowak', '12312312', 'Testowa', '4', '44-210', 'Rybnik', 'PL', NULL, NULL, NULL, 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'PLN', 'Przelew', '2024-11-18', 10035.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(33, 'FV/10/09/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-06-27', '2025-06-27', '2025-06-28', NULL, 'Tomasz Wójcik', '12312312', 'Testowa', '8', '44-212', 'Rybnik', 'PL', NULL, NULL, NULL, 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'PLN', 'Przelew', '2024-10-16', 10035.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(34, 'FV/9/09/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-01-31', '2025-01-31', '2025-02-10', 3, 'Anna Nowak', '12312312', 'Testowa', '3', '44-209', 'Rybnik', 'PL', NULL, NULL, NULL, 28.27, 2.26, 30.53, 28.27, 2.26, 30.53, 'PLN', 'Przelew', '2024-09-17', 30.53, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(35, 'FV/8/09/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-06-28', '2025-06-28', '2025-07-12', NULL, 'Piotr Kowalski', '12312312', 'Testowa', '7', '44-211', 'Rybnik', 'PL', NULL, NULL, NULL, 14800.00, 1184.00, 15984.00, 14800.00, 1184.00, 15984.00, 'PLN', 'Przelew', '2024-09-30', 15984.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(36, 'FV/7/08/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-02-28', '2025-02-28', '2025-03-10', 2, 'Andrzej Kozłowski', '12312312', 'Testowa', '2', '44-208', 'Rybnik', 'PL', NULL, NULL, NULL, 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'PLN', 'Przelew', '2024-09-12', 2000.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'FV/6/07/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-07-15', '2025-07-15', '2025-07-18', NULL, 'Paweł Mazur', '12312312', 'Testowa', '6', '44-210', 'Rybnik', 'PL', NULL, NULL, NULL, 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'PLN', 'Przelew', '2024-08-14', 10035.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, 'FV/5/07/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-03-31', '2025-03-31', '2025-04-10', 1, 'Agnieszka Kowalczyk', '12312312', 'Testowa', '1', '44-207', 'Rybnik', 'PL', NULL, NULL, NULL, 7500.00, 600.00, 8100.00, 7500.00, 600.00, 8100.00, 'PLN', 'Przelew', '2024-07-15', 8100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, 'FV/4/06/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-07-31', '2025-07-31', '2025-08-10', NULL, 'Monika Piotrowska', '12312312', 'Testowa', '5', '44-209', 'Rybnik', 'PL', NULL, NULL, NULL, 8158.54, 1876.46, 10035.00, 8158.54, 1876.46, 10035.00, 'PLN', 'Przelew', '2024-07-12', 10035.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(40, 'FV/3/06/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-08-30', '2025-08-30', '2025-09-10', NULL, 'Maria Szymańska', '12312312', 'Testowa', '4', '44-208', 'Rybnik', 'PL', NULL, NULL, NULL, 7756.20, 620.50, 8376.70, 7756.20, 620.50, 8376.70, 'PLN', 'Przelew', '2024-06-19', 8376.70, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 'FV/2/06/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-09-17', '2025-09-17', '2025-10-01', NULL, 'Marcin Kamiński', '12312312', 'Testowa', '4', '44-207', 'Rybnik', 'PL', NULL, NULL, NULL, 195.00, 44.85, 239.85, 195.00, 44.85, 239.85, 'PLN', 'Przelew', '2024-06-28', 239.85, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, 'FV/1/06/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2025-09-17', '2025-09-17', '2025-09-17', NULL, 'Łukasz Kwiatkowski', '12312312', 'Testowa', '6', '44-209', 'Rybnik', 'PL', NULL, NULL, NULL, 7756.20, 620.50, 8376.70, 7756.20, 620.50, 8376.70, 'PLN', 'Przelew', '2024-05-29', 8376.70, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 'FV/AK/R1/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2024-07-10', '2024-07-10', '2024-07-17', 1, 'Agnieszka Kowalczyk', '12312312', 'Adres Agnieszki', '1', '00-001', 'Warszawa', 'PL', NULL, NULL, NULL, 2439.02, 560.98, 3000.00, 2439.02, 560.98, 3000.00, 'PLN', 'Przelew', '2024-07-15', 3000.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(44, 'FV/AK/R2/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2024-08-01', '2024-08-01', '2024-08-08', 1, 'Agnieszka Kowalczyk', '12312312', 'Adres Agnieszki', '1', '00-001', 'Warszawa', 'PL', NULL, NULL, NULL, 2439.02, 560.98, 3000.00, 2439.02, 560.98, 3000.00, 'PLN', 'Przelew', '2024-08-10', 3000.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(50, 'FV/AK/R3/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2024-08-04', '2024-08-01', '2024-08-08', 1, 'Agnieszka Kowalczyk', '12312312', 'Adres Agnieszki', '1', '00-001', 'Warszawa', 'PL', NULL, NULL, NULL, 2439.02, 560.98, 3000.00, 2439.02, 560.98, 3000.00, 'PLN', 'Przelew', '2024-08-10', 3000.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(51, 'FV/AK/R4/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2024-08-04', '2024-08-01', '2024-08-08', 2, 'Andrzej Kozłowski', '12312312', 'Adres Andrzeja', '1', '00-001', 'Warszawa', 'PL', NULL, NULL, NULL, 2439.02, 560.98, 3000.00, 2439.02, 560.98, 500.00, 'PLN', 'Przelew', '2024-08-10', 500.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(52, 'FV/AK/R5/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Opłacona', '2024-09-04', '2024-09-01', '2024-09-08', 2, 'Andrzej Kozłowski', '12312312', 'Adres Andrzeja', '1', '00-001', 'Warszawa', 'PL', NULL, NULL, NULL, 2439.02, 560.98, 3000.00, 2439.02, 560.98, 800.00, 'PLN', 'Przelew', '2024-09-10', 500.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(54, 'FV/AK/R6/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', '23523532', 'Nieopłacona', '2024-08-20', '2024-08-20', '2024-08-20', 2, 'Andrzej Kozłowski', '12312312', 'Adres Andrzeja', '1', '00-001', 'Warszawa', 'PL', NULL, NULL, NULL, 2439.02, 560.98, 3000.00, 2439.02, 560.98, 500.00, 'PLN', 'Przelew', '2024-08-20', 500.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

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
  `data_oplaty` date DEFAULT NULL COMMENT 'Data faktycznego opłacenia raty'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `oplaty_spraw`
--

INSERT INTO `oplaty_spraw` (`id_oplaty_sprawy`, `id_sprawy`, `opis_raty`, `oczekiwana_kwota`, `czy_oplacona`, `data_oplaty`) VALUES
(1, 1, 'Rata 1', 3000.00, 1, NULL),
(2, 1, 'Rata 2', 3000.00, 0, NULL),
(3, 1, 'Rata 3', 3000.00, 0, NULL),
(4, 1, 'Rata 4', 0.00, 0, NULL),
(5, 2, 'Rata 1', 500.00, 0, NULL),
(6, 2, 'Rata 4', 0.00, 0, NULL),
(7, 3, 'Rata 1', 10000.00, 0, NULL),
(263, 8, 'Rata 1', 2500.00, 0, NULL),
(267, 8, 'Rata 2', 4660.00, 0, NULL),
(268, 8, 'Rata 3', 5000.00, 0, NULL),
(269, 8, 'Rata 4', 7500.00, 0, NULL),
(270, 8, 'Rata 5', 340.00, 0, NULL),
(271, 2, 'Rata 2', 500.00, 0, NULL),
(272, 2, 'Rata 3', 800.00, 0, NULL),
(273, 2, 'Rata 5', 200.00, 0, NULL),
(274, 9, 'Rata 1', 20000.00, 0, NULL),
(275, 9, 'Rata 2', 10000.00, 0, NULL),
(276, 9, 'Rata 3', 50000.00, 0, NULL),
(277, 9, 'Rata 4', 0.00, 0, NULL),
(278, 9, 'Rata 5', 20000.00, 0, NULL),
(279, 10, 'Rata 1', 50.00, 0, NULL),
(280, 10, 'Rata 2', 50.00, 0, NULL),
(295, 14, 'Rata 1', 500.00, 0, NULL),
(296, 14, 'Rata 2', 500.00, 0, NULL),
(297, 15, 'Rata 1', 10000.00, 0, NULL);

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
(1, 1, 1, 0.2500),
(2, 1, 2, 0.1000),
(3, 2, 1, 0.2800),
(4, 2, 2, 0.1500),
(5, 2, 3, 0.0500),
(6, 3, 1, 0.2800),
(7, 3, 2, 0.1500),
(8, 8, 1, 0.2500),
(9, 8, 22, 0.0400),
(10, 8, 19, 0.1000),
(12, 8, 5, 0.0400),
(13, 8, 25, 0.0400),
(16, 2, 25, 0.0200),
(20, 8, 23, 0.0100),
(22, 3, 25, 0.0300),
(23, 9, 1, 0.5000),
(24, 9, 4, 0.2500),
(26, 10, 1, 0.1000),
(35, 14, 1, 0.2000),
(36, 14, 22, 0.1000),
(37, 15, 1, 1.0000),
(38, 15, 6, 0.1000),
(39, 16, 1, 1.0000);

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
(1, 'Agnieszka Kowalczyk', 0, 300000.00, 9000.00, 0.0800, NULL),
(2, 'Andrzej Kozłowski', 0, 10000.00, 2000.00, 0.2000, NULL),
(3, 'Anna Nowak', 0, 534607.62, 10000.00, 0.0800, NULL),
(8, 'Barbara Dąbrowska', 0, 60000.00, 20000.00, 0.1700, NULL),
(9, 'Ewa Lewandowska', 0, 500000.00, 100000.00, 0.2000, NULL),
(10, 'Tomasz Wójcik', 0, 2000.00, 100.00, 0.1000, NULL),
(14, 'Joanna Wojciechowska', 0, 100000.00, 1000.00, 0.0500, NULL),
(15, 'sdefrsfrw', 0, 100000.00, 10000.00, 0.2000, NULL),
(16, 'sdefrsfrwsrghtfc', 0, 1000.00, 1000.00, 0.2500, NULL);

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
-- Indeksy dla tabeli `faktury`
--
ALTER TABLE `faktury`
  ADD PRIMARY KEY (`id_faktury`),
  ADD UNIQUE KEY `numer_faktury` (`numer_faktury`),
  ADD KEY `idx_faktury_id_sprawy` (`id_sprawy`),
  ADD KEY `idx_faktury_data_wystawienia` (`data_wystawienia`),
  ADD KEY `idx_faktury_termin_platnosci` (`termin_platnosci`);

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
  MODIFY `id_agenta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `agenci_wyplaty`
--
ALTER TABLE `agenci_wyplaty`
  MODIFY `id_wyplaty` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `faktury`
--
ALTER TABLE `faktury`
  MODIFY `id_faktury` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `oplaty_spraw`
--
ALTER TABLE `oplaty_spraw`
  MODIFY `id_oplaty_sprawy` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=298;

--
-- AUTO_INCREMENT for table `prowizje_agentow_spraw`
--
ALTER TABLE `prowizje_agentow_spraw`
  MODIFY `id_prowizji_agenta_sprawy` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `sprawy`
--
ALTER TABLE `sprawy`
  MODIFY `id_sprawy` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
-- Constraints for table `faktury`
--
ALTER TABLE `faktury`
  ADD CONSTRAINT `faktury_ibfk_1` FOREIGN KEY (`id_sprawy`) REFERENCES `sprawy` (`id_sprawy`) ON DELETE SET NULL ON UPDATE CASCADE;

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
