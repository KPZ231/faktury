-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 25, 2025 at 11:28 AM
-- Wersja serwera: 10.6.18-MariaDB-cll-lve
-- Wersja PHP: 8.1.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Baza danych: `srv46052_nffi1`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `testowy_bud__et___arkusz1`
--

CREATE TABLE `testowy_bud__et___arkusz1` (
  `nabywca` varchar(20) DEFAULT NULL,
  `data-wystawienia` varchar(10) DEFAULT NULL,
  `wartosc-netto-PLN` varchar(10) DEFAULT NULL,
  `produkt-usługa` varchar(27) DEFAULT NULL,
  `etykiety` varchar(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Zrzut danych tabeli `testowy_bud__et___arkusz1`
--

INSERT INTO `testowy_bud__et___arkusz1` (`nabywca`, `data-wystawienia`, `wartosc-netto-PLN`, `produkt-usługa`, `etykiety`) VALUES
('Agnieszka Kowalczyk', '2025-01-28', '2 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Andrzej Kozłowski', '2025-02-10', '1 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Anna Nowak', '2025-02-05', '500,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Anna Nowak', '2025-02-03', '1 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Barbara Dąbrowska', '2025-01-02', '1 250,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Ewa Lewandowska', '2025-02-03', '750,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Ewa Lewandowska', '2025-02-21', '1 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Grzegorz Krawczyk', '2025-01-02', '600,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Jan Woźniak', '2025-01-29', '2 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Joanna Wojciechowska', '2025-01-23', '2 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Katarzyna Wiśniewska', '2025-02-18', '2 500,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Krzysztof Zieliński', '2025-01-16', '2 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Łukasz Kwiatkowski', '2025-01-04', '2 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Marcin Kamiński', '2025-01-03', '2 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Maria Szymańska', '2025-01-14', '2 500,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Monika Piotrowska', '2025-01-02', '2 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Paweł Mazur', '2025-01-30', '2 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Piotr Kowalski', '2025-01-02', '2 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Piotr Kowalski', '2025-01-02', '600,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Tomasz Wójcik', '2025-01-28', '2 000,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Tomasz Wójcik', '2025-01-09', '1 250,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt'),
('Zofia Jankowska', '2025-02-03', '750,00zł', 'Doradztwo w sprawie kredytu', 'Kredyt');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
