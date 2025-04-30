-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 30, 2025 at 02:40 PM
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
-- Database: `test1`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `agenci`
--

CREATE TABLE `agenci` (
  `agent_id` int(11) NOT NULL,
  `imie` varchar(50) NOT NULL,
  `nazwisko` varchar(50) NOT NULL,
  `sprawy` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT json_array() CHECK (json_valid(`sprawy`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `sprawy`
--

CREATE TABLE `sprawy` (
  `id` int(11) NOT NULL,
  `nazwa_sprawy` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `test`
--

CREATE TABLE `test` (
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
-- Dumping data for table `test`
--

INSERT INTO `test` (`LP`, `numer`, `Typ`, `Sprzedający`, `Nazwa skrócona działu`, `NIP sprzedającego`, `Status`, `Data wystawienia`, `Data sprzedaży`, `Termin płatności`, `Nabywca`, `NIP`, `Ulica i nr`, `Kod pocztowy`, `Miejscowość`, `Kraj`, `E-mail klienta`, `Telefon klienta`, `Telefon komórkowy`, `Wartość netto`, `VAT`, `Wartość brutto`, `Wartość netto PLN`, `VAT PLN`, `Wartość brutto PLN`, `Płatność`, `Data płatności`, `Kwota opłacona`, `Waluta`, `Nr zamówienia`, `Adresat`, `Kategoria`, `Uwagi`, `Kody GTU`, `Oznaczenia dotyczące procedur`, `Oryginalny dokument`, `Przyczyna korekty`) VALUES
(1, 'FV/6/03/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-01-15', '2025-01-15', '2025-01-23', 'Ewa Lewandowska', 12312312, 'Testowa 5', '44-208', 'Rybnik', 'PL', '', '', '', 8100.00, 1863.00, 9963.00, 8100.00, 1863.00, 9963.00, 'Przelew', '2025-04-14', 9963.00, 'PLN', '', '', '', '', '', '', '', ''),
(2, 'FV/5/02/2025', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-06-03', '2025-06-03', '2025-06-04', 'Monika Piotrowska', 12312312, 'Testowa 4', '44-207', 'Rybnik', 'PL', '', '', '', 8100.00, 1863.00, 9963.00, 8100.00, 1863.00, 9963.00, 'Przelew', '2025-03-10', 9963.00, 'PLN', '', '', '', '', '', '', '', ''),
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
(21, 'FV/1/06/2024', 'Faktura', 'Firma Sp. z o.o.', 'Firma', 23523532, 'Opłacona', '2025-09-17', '2025-09-17', '2025-09-17', 'Łukasz Kwiatkowski', 12312312, 'Testowa 6', '44-209', 'Rybnik', 'PL', '', '', '', 7756.20, 620.50, 8376.70, 7756.20, 620.50, 8376.70, 'Przelew', '2024-05-29', 8376.70, 'PLN', '', '', '', '', '', '', '', '');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `test2`
--

CREATE TABLE `test2` (
  `id` int(11) NOT NULL,
  `case_name` varchar(255) DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `amount_won` decimal(15,2) DEFAULT NULL,
  `upfront_fee` decimal(15,2) DEFAULT NULL,
  `success_fee_percentage` decimal(5,2) DEFAULT NULL,
  `total_commission` decimal(15,2) DEFAULT NULL,
  `kuba_percentage` decimal(5,2) DEFAULT NULL,
  `kuba_payout` decimal(15,2) DEFAULT NULL,
  `agent1_percentage` decimal(5,2) DEFAULT NULL,
  `agent2_percentage` decimal(5,2) DEFAULT NULL,
  `agent3_percentage` decimal(5,2) DEFAULT NULL,
  `agent4_percentage` decimal(5,2) DEFAULT NULL,
  `agent5_percentage` decimal(5,2) DEFAULT NULL,
  `installment1_amount` decimal(15,2) DEFAULT NULL,
  `installment1_paid` tinyint(1) DEFAULT 0,
  `installment2_amount` decimal(15,2) DEFAULT NULL,
  `installment2_paid` tinyint(1) DEFAULT 0,
  `installment3_amount` decimal(15,2) DEFAULT NULL,
  `installment3_paid` tinyint(1) DEFAULT 0,
  `final_installment_amount` decimal(15,2) DEFAULT NULL,
  `final_installment_paid` tinyint(1) DEFAULT 0,
  `kuba_installment1_amount` decimal(15,2) DEFAULT NULL,
  `kuba_invoice_number` varchar(50) DEFAULT NULL,
  `kuba_installment2_amount` decimal(15,2) DEFAULT NULL,
  `kuba_installment3_amount` decimal(15,2) DEFAULT NULL,
  `kuba_final_installment_amount` decimal(15,2) DEFAULT NULL,
  `agent1_installment1_amount` decimal(15,2) DEFAULT NULL,
  `agent1_installment2_amount` decimal(15,2) DEFAULT NULL,
  `agent1_installment3_amount` decimal(15,2) DEFAULT NULL,
  `agent1_final_installment_amount` decimal(15,2) DEFAULT NULL,
  `agent2_installment1_amount` decimal(15,2) DEFAULT NULL,
  `agent2_installment2_amount` decimal(15,2) DEFAULT NULL,
  `agent2_installment3_amount` decimal(15,2) DEFAULT NULL,
  `agent2_final_installment_amount` decimal(15,2) DEFAULT NULL,
  `agent3_installment1_amount` decimal(15,2) DEFAULT NULL,
  `agent3_installment2_amount` decimal(15,2) DEFAULT NULL,
  `agent3_installment3_amount` decimal(15,2) DEFAULT NULL,
  `agent3_final_installment_amount` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `test2`
--

INSERT INTO `test2` (`id`, `case_name`, `is_completed`, `amount_won`, `upfront_fee`, `success_fee_percentage`, `total_commission`, `kuba_percentage`, `kuba_payout`, `agent1_percentage`, `agent2_percentage`, `agent3_percentage`, `agent4_percentage`, `agent5_percentage`, `installment1_amount`, `installment1_paid`, `installment2_amount`, `installment2_paid`, `installment3_amount`, `installment3_paid`, `final_installment_amount`, `final_installment_paid`, `kuba_installment1_amount`, `kuba_invoice_number`, `kuba_installment2_amount`, `kuba_installment3_amount`, `kuba_final_installment_amount`, `agent1_installment1_amount`, `agent1_installment2_amount`, `agent1_installment3_amount`, `agent1_final_installment_amount`, `agent2_installment1_amount`, `agent2_installment2_amount`, `agent2_installment3_amount`, `agent2_final_installment_amount`, `agent3_installment1_amount`, `agent3_installment2_amount`, `agent3_installment3_amount`, `agent3_final_installment_amount`, `created_at`, `updated_at`) VALUES
(15, 'Dawid Kucharczyk', NULL, 300000.00, 9000.00, 8.00, 33000.00, 25.00, 15.00, 10.00, NULL, NULL, NULL, NULL, 3000.00, NULL, 3000.00, NULL, 3000.00, NULL, 24000.00, 0, NULL, NULL, NULL, NULL, 15.00, NULL, NULL, NULL, 3300.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-30 12:30:01', '2025-04-30 12:36:20');

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `agenci`
--
ALTER TABLE `agenci`
  ADD PRIMARY KEY (`agent_id`);

--
-- Indeksy dla tabeli `sprawy`
--
ALTER TABLE `sprawy`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `test2`
--
ALTER TABLE `test2`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agenci`
--
ALTER TABLE `agenci`
  MODIFY `agent_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sprawy`
--
ALTER TABLE `sprawy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test2`
--
ALTER TABLE `test2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
