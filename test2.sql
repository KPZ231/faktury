-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 29, 2025 at 12:56 PM
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
-- Struktura tabeli dla tabeli `test2`
--

CREATE TABLE `test2` (
  `Sprawa` varchar(255) DEFAULT NULL,
  `Zakończona?` varchar(255) DEFAULT NULL,
  `Wywalczona kwota` decimal(10,2) DEFAULT NULL,
  `Opłata wstępna` decimal(10,2) DEFAULT NULL,
  `Success fee` decimal(10,2) DEFAULT NULL,
  `Całość prowizji` decimal(10,2) DEFAULT NULL,
  `Prowizja Kuba` decimal(10,2) DEFAULT NULL,
  `Do wypłaty Kuba` decimal(10,2) DEFAULT NULL,
  `Prowizja Agent 1` decimal(10,2) DEFAULT NULL,
  `Prowizja Agent 2` decimal(10,2) DEFAULT NULL,
  `Prowizja Agent 3` decimal(10,2) DEFAULT NULL,
  `Prowizja Agent 4` decimal(10,2) DEFAULT NULL,
  `Prowizja Agent 5` decimal(10,2) DEFAULT NULL,
  `Rata 1` decimal(10,2) DEFAULT NULL,
  `Rata 2` decimal(10,2) DEFAULT NULL,
  `Rata 3` decimal(10,2) DEFAULT NULL,
  `Ostatnia` decimal(10,2) DEFAULT NULL,
  `Rata 1_1` decimal(10,2) DEFAULT NULL,
  `Nr faktury` varchar(255) DEFAULT NULL,
  `Rata 2_1` decimal(10,2) DEFAULT NULL,
  `Rata 3_1` decimal(10,2) DEFAULT NULL,
  `Ostatnia_1` decimal(10,2) DEFAULT NULL,
  `Rata 1_2` decimal(10,2) DEFAULT NULL,
  `Rata 2_2` decimal(10,2) DEFAULT NULL,
  `Rata 3_2` decimal(10,2) DEFAULT NULL,
  `Ostatnia_2` decimal(10,2) DEFAULT NULL,
  `Rata 1_3` decimal(10,2) DEFAULT NULL,
  `Rata 2_3` decimal(10,2) DEFAULT NULL,
  `Rata 3_3` decimal(10,2) DEFAULT NULL,
  `Ostatnia_3` decimal(10,2) DEFAULT NULL,
  `Rata 1_4` decimal(10,2) DEFAULT NULL,
  `Rata 2_4` decimal(10,2) DEFAULT NULL,
  `Rata 3_4` decimal(10,2) DEFAULT NULL,
  `Ostatnia_4` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
