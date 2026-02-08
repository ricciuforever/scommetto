-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Creato il: Feb 08, 2026 alle 19:11
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
  `requests_limit` int(11) DEFAULT 7500,
  `requests_used` int(11) DEFAULT 0,
  `requests_remaining` int(11) DEFAULT 7500,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `bets`
--

CREATE TABLE `bets` (
  `id` int(11) NOT NULL,
  `fixture_id` varchar(100) NOT NULL,
  `bookmaker_id` int(11) DEFAULT NULL,
  `bookmaker_name` varchar(100) DEFAULT NULL,
  `match_name` varchar(255) NOT NULL,
  `advice` text DEFAULT NULL,
  `market` varchar(100) DEFAULT NULL,
  `odds` decimal(8,2) DEFAULT NULL,
  `stake` decimal(8,2) DEFAULT NULL,
  `urgency` varchar(50) DEFAULT NULL,
  `confidence` int(11) DEFAULT 0,
  `status` enum('pending','won','lost','void') DEFAULT 'pending',
  `timestamp` datetime DEFAULT current_timestamp(),
  `result` varchar(50) DEFAULT NULL,
  `betfair_id` varchar(100) DEFAULT NULL,
  `adm_id` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `bet_types`
--

CREATE TABLE `bet_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `type` enum('pre-match','live') DEFAULT 'pre-match',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `bookmakers`
--

CREATE TABLE `bookmakers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `coaches`
--

CREATE TABLE `coaches` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `birth_country` varchar(100) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `career_json` longtext DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `countries`
--

CREATE TABLE `countries` (
  `name` varchar(100) NOT NULL,
  `code` varchar(10) DEFAULT NULL,
  `flag` varchar(255) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fixtures`
--

CREATE TABLE `fixtures` (
  `id` int(11) NOT NULL,
  `league_id` int(11) DEFAULT NULL,
  `round` varchar(100) DEFAULT NULL,
  `team_home_id` int(11) DEFAULT NULL,
  `team_away_id` int(11) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `status_short` varchar(10) DEFAULT NULL,
  `status_long` varchar(50) DEFAULT NULL,
  `elapsed` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `score_home` int(11) DEFAULT NULL,
  `score_away` int(11) DEFAULT NULL,
  `score_home_ht` int(11) DEFAULT NULL,
  `score_away_ht` int(11) DEFAULT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_detailed_update` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fixture_events`
--

CREATE TABLE `fixture_events` (
  `id` int(11) NOT NULL,
  `fixture_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
  `assist_id` int(11) DEFAULT NULL,
  `time_elapsed` int(11) DEFAULT NULL,
  `time_extra` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `detail` varchar(100) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fixture_injuries`
--

CREATE TABLE `fixture_injuries` (
  `id` int(11) NOT NULL,
  `fixture_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
  `player_name` varchar(100) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fixture_lineups`
--

CREATE TABLE `fixture_lineups` (
  `fixture_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `formation` varchar(20) DEFAULT NULL,
  `coach_id` int(11) DEFAULT NULL,
  `start_xi_json` longtext DEFAULT NULL,
  `substitutes_json` longtext DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fixture_odds`
--

CREATE TABLE `fixture_odds` (
  `fixture_id` int(11) NOT NULL,
  `bookmaker_id` int(11) NOT NULL,
  `bet_id` int(11) NOT NULL,
  `odds_json` longtext DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fixture_player_stats`
--

CREATE TABLE `fixture_player_stats` (
  `fixture_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `stats_json` longtext DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fixture_statistics`
--

CREATE TABLE `fixture_statistics` (
  `fixture_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `stats_json` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `h2h_records`
--

CREATE TABLE `h2h_records` (
  `team1_id` int(11) NOT NULL,
  `team2_id` int(11) NOT NULL,
  `h2h_json` longtext DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `leagues`
--

CREATE TABLE `leagues` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `coverage_json` text DEFAULT NULL,
  `season` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `league_seasons`
--

CREATE TABLE `league_seasons` (
  `league_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `live_odds`
--

CREATE TABLE `live_odds` (
  `fixture_id` int(11) NOT NULL,
  `odds_json` longtext DEFAULT NULL,
  `status_json` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `players`
--

CREATE TABLE `players` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `height` varchar(20) DEFAULT NULL,
  `weight` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `player_seasons`
--

CREATE TABLE `player_seasons` (
  `year` int(11) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `player_statistics`
--

CREATE TABLE `player_statistics` (
  `id` int(11) NOT NULL,
  `player_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `league_id` int(11) DEFAULT NULL,
  `season` int(11) DEFAULT NULL,
  `stats_json` longtext DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `predictions`
--

CREATE TABLE `predictions` (
  `fixture_id` int(11) NOT NULL,
  `advice` text DEFAULT NULL,
  `comparison_json` text DEFAULT NULL,
  `percent_json` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `rounds`
--

CREATE TABLE `rounds` (
  `league_id` int(11) NOT NULL,
  `season` int(11) NOT NULL,
  `round_name` varchar(100) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `seasons`
--

CREATE TABLE `seasons` (
  `year` int(11) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `sidelined`
--

CREATE TABLE `sidelined` (
  `id` int(11) NOT NULL,
  `player_id` int(11) DEFAULT NULL,
  `coach_id` int(11) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `squads`
--

CREATE TABLE `squads` (
  `team_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `position` varchar(50) DEFAULT NULL,
  `number` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `standings`
--

CREATE TABLE `standings` (
  `league_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `rank` int(11) DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `goals_diff` int(11) DEFAULT NULL,
  `form` varchar(20) DEFAULT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `played` int(11) DEFAULT NULL,
  `win` int(11) DEFAULT NULL,
  `draw` int(11) DEFAULT NULL,
  `lose` int(11) DEFAULT NULL,
  `goals_for` int(11) DEFAULT NULL,
  `goals_against` int(11) DEFAULT NULL,
  `home_stats_json` text DEFAULT NULL,
  `away_stats_json` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `code` varchar(10) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `founded` int(11) DEFAULT NULL,
  `national` tinyint(1) DEFAULT 0,
  `venue_name` varchar(255) DEFAULT NULL,
  `venue_capacity` int(11) DEFAULT NULL,
  `coach_id` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `team_leagues`
--

CREATE TABLE `team_leagues` (
  `team_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `season` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `team_stats`
--

CREATE TABLE `team_stats` (
  `team_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `season` int(11) NOT NULL,
  `played` int(11) DEFAULT NULL,
  `wins` int(11) DEFAULT NULL,
  `draws` int(11) DEFAULT NULL,
  `losses` int(11) DEFAULT NULL,
  `goals_for` int(11) DEFAULT NULL,
  `goals_against` int(11) DEFAULT NULL,
  `clean_sheets` int(11) DEFAULT NULL,
  `failed_to_score` int(11) DEFAULT NULL,
  `avg_goals_for` decimal(4,2) DEFAULT NULL,
  `avg_goals_against` decimal(4,2) DEFAULT NULL,
  `full_stats_json` longtext DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `top_stats`
--

CREATE TABLE `top_stats` (
  `league_id` int(11) NOT NULL,
  `season` int(11) NOT NULL,
  `type` enum('scorers','assists','yellow_cards','red_cards') NOT NULL,
  `stats_json` longtext DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `transfers`
--

CREATE TABLE `transfers` (
  `id` int(11) NOT NULL,
  `player_id` int(11) DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `team_out_id` int(11) DEFAULT NULL,
  `team_in_id` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `trophies`
--

CREATE TABLE `trophies` (
  `id` int(11) NOT NULL,
  `player_id` int(11) DEFAULT NULL,
  `coach_id` int(11) DEFAULT NULL,
  `league` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `season` varchar(20) DEFAULT NULL,
  `place` varchar(50) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `venues`
--

CREATE TABLE `venues` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `surface` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_match_summary`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_match_summary` (
`id` int(11)
,`date` datetime
,`status_short` varchar(10)
,`home_name` varchar(255)
,`home_logo` varchar(255)
,`score_home` int(11)
,`away_name` varchar(255)
,`away_logo` varchar(255)
,`score_away` int(11)
,`league_name` varchar(255)
,`country_name` varchar(100)
);

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
-- Indici per le tabelle `bet_types`
--
ALTER TABLE `bet_types`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `bookmakers`
--
ALTER TABLE `bookmakers`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `coaches`
--
ALTER TABLE `coaches`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`name`);

--
-- Indici per le tabelle `fixtures`
--
ALTER TABLE `fixtures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fixtures_date` (`date`),
  ADD KEY `idx_fixtures_status` (`status_short`),
  ADD KEY `idx_fixtures_teams` (`team_home_id`,`team_away_id`);

--
-- Indici per le tabelle `fixture_events`
--
ALTER TABLE `fixture_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fixture_id` (`fixture_id`);

--
-- Indici per le tabelle `fixture_injuries`
--
ALTER TABLE `fixture_injuries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fixture_id` (`fixture_id`);

--
-- Indici per le tabelle `fixture_lineups`
--
ALTER TABLE `fixture_lineups`
  ADD PRIMARY KEY (`fixture_id`,`team_id`);

--
-- Indici per le tabelle `fixture_odds`
--
ALTER TABLE `fixture_odds`
  ADD PRIMARY KEY (`fixture_id`,`bookmaker_id`,`bet_id`);

--
-- Indici per le tabelle `fixture_player_stats`
--
ALTER TABLE `fixture_player_stats`
  ADD PRIMARY KEY (`fixture_id`,`team_id`,`player_id`);

--
-- Indici per le tabelle `fixture_statistics`
--
ALTER TABLE `fixture_statistics`
  ADD PRIMARY KEY (`fixture_id`,`team_id`);

--
-- Indici per le tabelle `h2h_records`
--
ALTER TABLE `h2h_records`
  ADD PRIMARY KEY (`team1_id`,`team2_id`);

--
-- Indici per le tabelle `leagues`
--
ALTER TABLE `leagues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_leagues_country` (`country_name`);

--
-- Indici per le tabelle `league_seasons`
--
ALTER TABLE `league_seasons`
  ADD PRIMARY KEY (`league_id`,`year`);

--
-- Indici per le tabelle `live_odds`
--
ALTER TABLE `live_odds`
  ADD PRIMARY KEY (`fixture_id`);

--
-- Indici per le tabelle `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_players_name` (`name`);

--
-- Indici per le tabelle `player_seasons`
--
ALTER TABLE `player_seasons`
  ADD PRIMARY KEY (`year`);

--
-- Indici per le tabelle `player_statistics`
--
ALTER TABLE `player_statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `player_team_league_season` (`player_id`,`team_id`,`league_id`,`season`);

--
-- Indici per le tabelle `predictions`
--
ALTER TABLE `predictions`
  ADD PRIMARY KEY (`fixture_id`);

--
-- Indici per le tabelle `rounds`
--
ALTER TABLE `rounds`
  ADD PRIMARY KEY (`league_id`,`season`,`round_name`);

--
-- Indici per le tabelle `seasons`
--
ALTER TABLE `seasons`
  ADD PRIMARY KEY (`year`);

--
-- Indici per le tabelle `sidelined`
--
ALTER TABLE `sidelined`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `coach_id` (`coach_id`);

--
-- Indici per le tabelle `squads`
--
ALTER TABLE `squads`
  ADD PRIMARY KEY (`team_id`,`player_id`),
  ADD KEY `idx_squads_player` (`player_id`);

--
-- Indici per le tabelle `standings`
--
ALTER TABLE `standings`
  ADD PRIMARY KEY (`league_id`,`team_id`);

--
-- Indici per le tabelle `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `team_leagues`
--
ALTER TABLE `team_leagues`
  ADD PRIMARY KEY (`team_id`,`league_id`,`season`);

--
-- Indici per le tabelle `team_stats`
--
ALTER TABLE `team_stats`
  ADD PRIMARY KEY (`team_id`,`league_id`,`season`);

--
-- Indici per le tabelle `top_stats`
--
ALTER TABLE `top_stats`
  ADD PRIMARY KEY (`league_id`,`season`,`type`);

--
-- Indici per le tabelle `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `team_in_id` (`team_in_id`),
  ADD KEY `team_out_id` (`team_out_id`);

--
-- Indici per le tabelle `trophies`
--
ALTER TABLE `trophies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `coach_id` (`coach_id`);

--
-- Indici per le tabelle `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `bets`
--
ALTER TABLE `bets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `fixture_events`
--
ALTER TABLE `fixture_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `fixture_injuries`
--
ALTER TABLE `fixture_injuries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `player_statistics`
--
ALTER TABLE `player_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `sidelined`
--
ALTER TABLE `sidelined`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `trophies`
--
ALTER TABLE `trophies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Struttura per vista `v_match_summary`
--
DROP TABLE IF EXISTS `v_match_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`scommetto`@`%` SQL SECURITY DEFINER VIEW `v_match_summary`  AS SELECT `f`.`id` AS `id`, `f`.`date` AS `date`, `f`.`status_short` AS `status_short`, `t1`.`name` AS `home_name`, `t1`.`logo` AS `home_logo`, `f`.`score_home` AS `score_home`, `t2`.`name` AS `away_name`, `t2`.`logo` AS `away_logo`, `f`.`score_away` AS `score_away`, `l`.`name` AS `league_name`, `l`.`country_name` AS `country_name` FROM (((`fixtures` `f` join `teams` `t1` on(`f`.`team_home_id` = `t1`.`id`)) join `teams` `t2` on(`f`.`team_away_id` = `t2`.`id`)) join `leagues` `l` on(`f`.`league_id` = `l`.`id`)) ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
