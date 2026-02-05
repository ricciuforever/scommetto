-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Creato il: Feb 05, 2026 alle 19:03
-- Versione del server: 10.3.39-MariaDB-0ubuntu0.20.04.2
-- Versione PHP: 8.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `scommetto_`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `analyses`
--

CREATE TABLE `analyses` (
  `fixture_id` int(11) NOT NULL,
  `last_checked` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `prediction_raw` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `api_usage`
--

CREATE TABLE `api_usage` (
  `id` int(11) NOT NULL DEFAULT 1,
  `requests_used` int(11) DEFAULT 0,
  `requests_remaining` int(11) DEFAULT 7500,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `api_usage`
--

INSERT INTO `api_usage` (`id`, `requests_used`, `requests_remaining`, `last_updated`) VALUES
(1, 3068, 4432, '2026-02-05 18:03:01');

-- --------------------------------------------------------

--
-- Struttura della tabella `bets`
--

CREATE TABLE `bets` (
  `id` int(11) NOT NULL,
  `fixture_id` int(11) NOT NULL,
  `match_name` varchar(255) NOT NULL,
  `advice` text DEFAULT NULL,
  `market` varchar(100) DEFAULT NULL,
  `odds` decimal(8,2) DEFAULT NULL,
  `stake` decimal(8,2) DEFAULT NULL,
  `urgency` varchar(50) DEFAULT NULL,
  `status` enum('pending','won','lost','void') DEFAULT 'pending',
  `timestamp` datetime DEFAULT current_timestamp(),
  `result` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `analyses`
--
ALTER TABLE `analyses`
  ADD PRIMARY KEY (`fixture_id`);

--
-- Indici per le tabelle `api_usage`
--
ALTER TABLE `api_usage`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `bets`
--
ALTER TABLE `bets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fixture_id` (`fixture_id`),
  ADD KEY `status` (`status`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `bets`
--
ALTER TABLE `bets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
