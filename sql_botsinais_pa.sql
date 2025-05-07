-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 08/05/2025 às 00:43
-- Versão do servidor: 10.7.3-MariaDB-log
-- Versão do PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `sql_botsinais_pa`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Despejando dados para a tabela `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `user_id`, `action`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'Criou o usuário Sinicleis (ID: 4)', '172.71.10.233', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-06 01:53:18'),
(2, 1, 'Suspendeu o usuário Sinicleis (ID: 4)', '172.71.10.233', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-06 01:53:56'),
(3, 1, 'Ativou o usuário Sinicleis (ID: 4)', '172.71.10.233', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-06 01:53:59'),
(4, 1, 'Suspendeu o usuário Sinicleis (ID: 4)', '172.71.10.233', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-06 01:54:01'),
(5, 1, 'Ativou o usuário Sinicleis (ID: 4)', '172.71.10.233', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-06 01:54:02'),
(6, 1, 'Criou o usuário mega12 (ID: 5)', '172.71.11.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-06 02:11:30'),
(7, 1, 'Criou o usuário kkhfdsf (ID: 6)', '172.71.11.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-06 02:12:41'),
(8, 1, 'Renovou assinatura do usuário mega23 (ID: 7) por 30 dias', NULL, NULL, '2025-05-06 10:42:15'),
(9, 1, 'Suspendeu o usuário mega23 (ID: 7)', NULL, NULL, '2025-05-06 10:42:25'),
(10, 1, 'Removeu o usuário mega23 (ID: 7)', NULL, NULL, '2025-05-06 10:42:31'),
(11, 1, 'Criou o usuário mega13 (ID: 8)', NULL, NULL, '2025-05-06 10:43:55'),
(12, 1, 'Removeu o usuário mega13 (ID: 8)', NULL, NULL, '2025-05-06 10:51:02'),
(13, 1, 'Ativou o usuário mega13 (ID: 8)', NULL, NULL, '2025-05-06 10:51:08'),
(14, 1, 'Removeu o usuário mega13 (ID: 8)', NULL, NULL, '2025-05-06 10:51:11'),
(15, 1, 'Removeu o usuário mega13 (ID: 8)', NULL, NULL, '2025-05-06 10:51:14'),
(16, 1, 'Ativou o usuário mega13 (ID: 8)', NULL, NULL, '2025-05-06 10:51:21'),
(17, 1, 'Criou o usuário Siniclei (ID: 9)', NULL, NULL, '2025-05-06 11:00:25'),
(18, 1, 'Removeu o usuário  (ID: 0) [soft delete]', NULL, NULL, '2025-05-06 11:00:33'),
(19, 1, 'Removeu o usuário  (ID: 0) [soft delete]', NULL, NULL, '2025-05-06 11:00:39'),
(20, 1, 'Removeu o usuário  (ID: 0) [soft delete]', NULL, NULL, '2025-05-06 11:00:44'),
(21, 1, 'Ativou o usuário  (ID: 0)', NULL, NULL, '2025-05-06 11:00:48'),
(22, 1, 'Ativou o usuário  (ID: 0)', NULL, NULL, '2025-05-06 11:00:52'),
(23, 1, 'Removeu o usuário Siniclei (ID: 9) [soft delete]', NULL, NULL, '2025-05-06 11:25:29'),
(24, 1, 'Removeu o usuário Siniclei (ID: 9) [soft delete]', NULL, NULL, '2025-05-06 11:25:51'),
(25, 1, 'Criou o usuário Mega13 (ID: 10)', NULL, NULL, '2025-05-06 11:26:13'),
(26, 1, 'Renovou assinatura do usuário Mega13 (ID: 10) por 30 dias', NULL, NULL, '2025-05-06 11:26:22'),
(27, 1, 'suspendeu o usuário Mega13 (ID: 10)', NULL, NULL, '2025-05-06 11:27:12'),
(28, 1, 'suspendeu o usuário Mega13 (ID: 10)', NULL, NULL, '2025-05-06 11:27:22'),
(29, 1, 'Removeu o usuário Mega13 (ID: 10) [soft delete]', NULL, NULL, '2025-05-06 11:27:39'),
(30, 1, 'Removeu permanentemente o usuário Mega13 (ID: 10)', NULL, NULL, '2025-05-06 12:14:36'),
(31, 1, 'ativou o usuário Siniclei (ID: 9)', NULL, NULL, '2025-05-06 12:34:08'),
(32, 1, 'Removeu o usuário Siniclei (ID: 9) permanentemente', NULL, NULL, '2025-05-06 12:34:12'),
(33, 1, 'Criou o usuário Mega12 (ID: 11)', NULL, NULL, '2025-05-06 12:35:03'),
(34, 1, 'Renovou assinatura do usuário Mega12 (ID: 11) por 30 dias', NULL, NULL, '2025-05-06 12:35:20'),
(35, 1, 'Criou o usuário super12 (ID: 12)', NULL, NULL, '2025-05-06 14:50:57'),
(36, 1, 'Criou o bot \'Sinais Pragmatic Play\' (ID: 1)', NULL, NULL, '2025-05-06 23:18:35'),
(37, 1, 'Criou o bot \'Sinais Pragmatic Vip\' (ID: 2)', NULL, NULL, '2025-05-07 00:00:20');

-- --------------------------------------------------------

--
-- Estrutura para tabela `bots`
--

CREATE TABLE `bots` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `telegram_token` varchar(255) NOT NULL,
  `provider` enum('PG','Pragmatic') NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date NOT NULL,
  `status` enum('active','suspended','expired') DEFAULT 'active',
  `signal_frequency` int(11) DEFAULT 30,
  `master_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estrutura para tabela `bot_channel_mappings`
--

CREATE TABLE `bot_channel_mappings` (
  `id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `channel_id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura para tabela `bot_group_mappings`
--

CREATE TABLE `bot_group_mappings` (
  `id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura para tabela `bot_signal_mappings`
--

CREATE TABLE `bot_signal_mappings` (
  `id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `source_id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura para tabela `channels`
--

CREATE TABLE `channels` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `telegram_id` varchar(100) NOT NULL,
  `type` enum('channel','group') NOT NULL,
  `bot_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date NOT NULL,
  `status` enum('active','suspended','expired') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estrutura para tabela `games`
--

CREATE TABLE `games` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `provider` enum('PG','Pragmatic') NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Despejando dados para a tabela `games`
--

INSERT INTO `games` (`id`, `name`, `provider`, `type`, `image_url`, `status`, `created_at`) VALUES
(1, 'Sweet Bonanza', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(2, 'Gates of Olympus', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(3, 'The Dog House', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(4, 'Wolf Gold', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(5, 'Fruit Party', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(6, 'Big Bass Bonanza', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(7, 'Buffalo King', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(8, 'Great Rhino', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(9, 'Starlight Princess', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(10, 'Sweet Bonanza Xmas', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(11, 'The Hand of Midas', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(12, 'Release the Kraken', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(13, 'Great Rhino Deluxe', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(14, 'Gems Bonanza', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(15, 'Sweet Bonanza CandyLand', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(16, 'Wild West Gold', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(17, 'Joker\'s Jewels', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(18, 'Hot to Burn', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(19, 'Chilli Heat', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(20, 'Fire Strike', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(21, '5 Lions', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(22, 'Master Joker', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(23, 'Da Vinci\'s Treasure', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(24, 'Mysterious Egypt', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(25, 'Aztec Gems', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(26, 'Pirate Gold', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(27, 'Diamond Strike', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(28, 'Extra Juicy', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(29, 'Bronco Spirit', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(30, 'Dragon Kingdom', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(31, 'Money Mouse', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(32, 'Triple Tigers', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(33, '888 Dragons', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(34, 'John Hunter and the Book of Tut', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(35, 'Mustang Gold', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(36, 'Buffalo King Megaways', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(37, 'Hot to Burn Hold & Spin', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(38, 'Aztec Bonanza', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(39, 'Dance Party', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(40, 'Great Rhino Megaways', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(41, 'Cowboys Gold', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(42, 'Emerald King', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(43, 'Fishin Reels', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(44, 'Lucky Lightning', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(45, 'Ultra Hold and Spin', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(46, 'Christmas Carol Megaways', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(47, 'Return of the Dead', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(48, 'Wild Wild Riches', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(49, 'Pyramid King', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(50, 'Curse of the Werewolf Megaways', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(51, 'Empty the Bank', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(52, 'Power of Thor Megaways', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(53, 'Wild West Gold Megaways', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(54, 'Book of Vikings', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(55, 'Juicy Fruits', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(56, 'Cash Elevator', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(57, 'Chicken Drop', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(58, 'Floating Dragon', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(59, 'Rise of Giza PowerNudge', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(60, 'Bigger Bass Bonanza', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(61, '5 Lions Megaways', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(62, 'Lucky Grace and Charm', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(63, 'Starlight Princess 1000', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(64, 'Sweet Bonanza Dice', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(65, 'Book of Golden Sands', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(66, 'Fire Hot 100', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(67, 'Zeus vs Hades - Gods of War', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(68, 'Big Bass Bonanza Megaways', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(69, 'Sugar Rush', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(70, 'Spaceman', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(71, 'Gates of Gatot Kaca', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(72, 'Sword of Ares', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(73, 'Sugar Rush Christmas', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(74, 'Sweet Powernudge', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(75, 'Candy Stars', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(76, 'Lion Dance', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(77, 'Panda\'s Fortune', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(78, 'The Dog House Multihold', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(79, 'Great Rhino Reel Kingdom', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(80, 'Sticky Bees', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(81, 'Fire Hot 5', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(82, 'Lucky New Year - Tiger Treasures', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(83, 'John Hunter and the Quest for Bermuda Riches', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(84, 'Grain of Gold', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(85, 'Drill That Gold', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(86, 'Mega Wheel', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(87, 'Mega Roulette', 'Pragmatic', NULL, NULL, 'active', '2025-05-06 18:45:36'),
(88, 'Fortune Tiger', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(89, 'Fortune Ox', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(90, 'Fortune Mouse', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(91, 'Fortune Rabbit', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(92, 'Mahjong Ways', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(93, 'Mahjong Ways 2', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(94, 'Mahjong Ways 3', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(95, 'Win Win Mahjong Ways', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(96, 'Ganesha Gold', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(97, 'Lucky Neko', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(98, 'Dragon Hatch', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(99, 'Dreams of Macau', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(100, 'Treasures of Aztec', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(101, 'Queen of Bounty', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(102, 'Genie\'s 3 Wishes', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(103, 'Candy Burst', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(104, 'Gem Saviour Sword', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(105, 'Medusa', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(106, 'Egypt\'s Book of Mystery', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(107, 'Jungle Delight', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(108, 'Double Fortune', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(109, 'Circus Delight', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(110, 'Leprechaun Riches', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(111, 'Dragon Legend', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(112, 'Oriental Prosperity', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(113, 'Guardians of Ice & Fire', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(114, 'Bali Vacation', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(115, 'Gem Saviour Conquest', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(116, 'Bangkok Dreams', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(117, 'Hip Hop Panda', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(118, 'Tree of Fortune', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(119, 'Ninja vs Samurai', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(120, 'Piggy Gold', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(121, 'Phoenix Rises', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(122, 'Journey to the Wealth', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(123, 'Crypto Gold', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(124, 'Emoji Riches', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(125, 'Fortune Dragon', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(126, 'Fortune Lion', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(127, 'Fortune Gods', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(128, 'Wizdom Wonders', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(129, 'Garuda Gems', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(130, 'Hotpot', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(131, 'Wild Bandito', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(132, 'Dragon Tiger Luck', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(133, 'Spirited Wonders', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(134, 'Captain\'s Bounty', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(135, 'Fortune Gods Pot', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(136, 'Win Win Fish', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(137, 'Mythical Treasure', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(138, 'Prosperity Lion', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(139, 'Honey Trap of Diao Chan', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(140, 'Opera Dynasty', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(141, 'Plushie Frenzy', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(142, 'Symbols of Egypt', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(143, 'Caishen Wins', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(144, 'Dragonball', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(145, 'Rogue Rocket', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(146, 'Fortune Mouse Crash', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57'),
(147, 'PG Crash', 'PG', NULL, NULL, 'active', '2025-05-06 18:46:57');

-- --------------------------------------------------------

--
-- Estrutura para tabela `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `telegram_id` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura para tabela `platforms`
--

CREATE TABLE `platforms` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Despejando dados para a tabela `platforms`
--

INSERT INTO `platforms` (`id`, `owner_id`, `name`, `url`, `logo_url`, `status`, `created_at`) VALUES
(2, NULL, 'Mega', 'https://webhook.painelcontrole.xyz/webhook/supernet', NULL, 'active', '2025-05-06 21:41:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Despejando dados para a tabela `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'site_name', 'BotDeSinais', '2025-05-06 02:04:37'),
(2, 'site_logo', 'logo.png', '2025-05-06 02:04:37'),
(3, 'signal_frequency_min', '25', '2025-05-06 02:04:37'),
(4, 'signal_frequency_max', '35', '2025-05-06 02:04:37'),
(5, 'admin_signal_frequency_min', '120', '2025-05-06 02:04:37'),
(6, 'admin_signal_frequency_max', '180', '2025-05-06 02:04:37');

-- --------------------------------------------------------

--
-- Estrutura para tabela `signals`
--

CREATE TABLE `signals` (
  `id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `platform_id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `rounds_normal` int(11) NOT NULL,
  `rounds_turbo` int(11) NOT NULL,
  `schedule_time` datetime NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estrutura para tabela `signal_generator_settings`
--

CREATE TABLE `signal_generator_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Despejando dados para a tabela `signal_generator_settings`
--

INSERT INTO `signal_generator_settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'premium_min_interval', '15', 'Intervalo mínimo em minutos entre sinais premium (15-30)', '2025-05-06 18:59:05', NULL),
(2, 'premium_max_interval', '30', 'Intervalo máximo em minutos entre sinais premium (15-30)', '2025-05-06 18:59:05', NULL),
(3, 'regular_min_interval', '120', 'Intervalo mínimo em minutos entre sinais regulares (120-180)', '2025-05-06 18:59:05', NULL),
(4, 'regular_max_interval', '180', 'Intervalo máximo em minutos entre sinais regulares (120-180)', '2025-05-06 18:59:05', NULL),
(5, 'win_rate_percentage', '80', 'Taxa de acerto simulada para os sinais gerados (%)', '2025-05-06 18:59:05', NULL),
(6, 'active', 'true', 'Se o gerador de sinais está ativo ou não', '2025-05-06 18:59:05', NULL),
(7, 'last_premium_signal', '1746636121', 'Timestamp do último sinal premium gerado', '2025-05-06 18:59:05', '2025-05-07 16:42:01'),
(8, 'last_regular_signal', '1746629281', 'Timestamp do último sinal regular gerado', '2025-05-06 18:59:05', '2025-05-07 14:48:01'),
(9, 'premium_delay', '45', 'Atraso em minutos para enviar sinais premium após geração', '2025-05-06 23:16:01', NULL),
(10, 'regular_delay', '5', 'Atraso em minutos para enviar sinais regulares após geração', '2025-05-06 23:16:01', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `signal_history`
--

CREATE TABLE `signal_history` (
  `id` int(11) NOT NULL,
  `queue_id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `channel_id` int(11) DEFAULT NULL,
  `signal_type` enum('premium','regular') NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura para tabela `signal_queue`
--

CREATE TABLE `signal_queue` (
  `id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `platform_id` int(11) NOT NULL,
  `signal_type` enum('premium','regular') NOT NULL,
  `strategy` varchar(100) NOT NULL,
  `entry_value` varchar(100) NOT NULL,
  `entry_type` varchar(50) NOT NULL,
  `multiplier` decimal(10,2) NOT NULL,
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Despejando dados para a tabela `signal_queue`
--

INSERT INTO `signal_queue` (`id`, `game_id`, `platform_id`, `signal_type`, `strategy`, `entry_value`, `entry_type`, `multiplier`, `scheduled_at`, `status`, `created_at`, `sent_at`, `error_message`) VALUES
(1, 48, 2, 'premium', 'Jogada única', 'R$10', 'Dobrar Após Perda', 2.00, '2025-05-06 22:43:16', 'failed', '2025-05-06 21:41:42', '2025-05-06 22:43:16', NULL),
(2, 90, 2, 'regular', 'Estratégia padrão', 'R$10', 'Dobrar Após Perda', 2.00, '2025-05-06 22:43:16', 'failed', '2025-05-06 21:41:42', '2025-05-06 22:43:16', NULL),
(3, 63, 2, 'premium', '5 Giros', 'R$5', 'Valor Mínimo', 1.50, '2025-05-06 22:55:22', 'failed', '2025-05-06 22:43:16', '2025-05-06 22:55:22', NULL),
(4, 52, 2, 'premium', 'Auto', 'R$100', 'Dobrar Após Perda', 1.50, '2025-05-06 23:05:01', 'failed', '2025-05-06 23:03:01', '2025-05-06 23:05:01', NULL),
(5, 89, 2, 'premium', 'Seguir o sinal', 'R$50', 'Valor Fixo', 5.00, '2025-05-07 00:11:02', 'failed', '2025-05-06 23:26:01', '2025-05-07 00:11:02', NULL),
(6, 33, 2, 'premium', '5 Giros', 'R$5', 'Valor Fixo', 2.50, '2025-05-07 00:28:01', 'failed', '2025-05-06 23:42:02', '2025-05-07 00:28:01', NULL),
(7, 5, 2, 'regular', 'Entrada Manual', 'R$50', 'Valor Fixo', 2.00, '2025-05-06 23:55:02', 'failed', '2025-05-06 23:50:02', '2025-05-06 23:55:02', NULL),
(8, 7, 2, 'premium', '15 Giros', 'R$20', 'Valor Fixo', 2.00, '2025-05-07 00:48:01', 'failed', '2025-05-07 00:03:01', '2025-05-07 00:48:01', NULL),
(9, 103, 2, 'premium', '10 Giros', 'R$20', 'Porcentagem', 2.50, '2025-05-07 01:05:01', 'failed', '2025-05-07 00:20:01', '2025-05-07 01:05:01', NULL),
(10, 146, 2, 'premium', 'Aposta única', 'R$20', 'Valor Fixo', 5.00, '2025-05-07 01:26:01', 'failed', '2025-05-07 00:41:01', '2025-05-07 01:26:01', NULL),
(11, 64, 2, 'premium', 'Auto', 'R$100', 'Valor Fixo', 10.00, '2025-05-07 01:43:01', 'failed', '2025-05-07 00:58:01', '2025-05-07 01:43:01', NULL),
(12, 29, 2, 'premium', 'Entrada Manual', 'R$10', 'Porcentagem', 2.50, '2025-05-07 02:03:02', 'failed', '2025-05-07 01:18:01', '2025-05-07 02:03:02', NULL),
(13, 69, 2, 'premium', '15 Giros', 'R$50', 'Dobrar Após Perda', 3.00, '2025-05-07 02:21:01', 'failed', '2025-05-07 01:36:01', '2025-05-07 02:21:01', NULL),
(14, 60, 2, 'premium', '15 Giros', 'R$10', 'Dobrar Após Perda', 3.00, '2025-05-07 02:40:01', 'failed', '2025-05-07 01:55:01', '2025-05-07 02:40:01', NULL),
(15, 20, 2, 'regular', 'Entrada Manual', 'R$10', 'Valor Fixo', 5.00, '2025-05-07 02:09:01', 'failed', '2025-05-07 02:03:02', '2025-05-07 02:09:01', NULL),
(16, 127, 2, 'premium', 'Auto', 'R$100', 'Porcentagem', 1.50, '2025-05-07 03:05:01', 'failed', '2025-05-07 02:20:01', '2025-05-07 03:05:01', NULL),
(17, 8, 2, 'premium', '5 Giros', 'R$50', 'Valor Mínimo', 10.00, '2025-05-07 03:26:01', 'failed', '2025-05-07 02:41:01', '2025-05-07 03:26:01', NULL),
(18, 134, 2, 'premium', 'Jogada única', 'R$20', 'Porcentagem', 10.00, '2025-05-07 03:43:01', 'failed', '2025-05-07 02:58:01', '2025-05-07 03:43:01', NULL),
(19, 146, 2, 'premium', 'Aposta única', 'R$100', 'Valor Mínimo', 2.50, '2025-05-07 04:03:01', 'failed', '2025-05-07 03:18:01', '2025-05-07 04:03:01', NULL),
(20, 41, 2, 'premium', 'Auto', 'R$100', 'Valor Mínimo', 2.00, '2025-05-07 04:24:01', 'failed', '2025-05-07 03:39:01', '2025-05-07 04:24:01', NULL),
(21, 88, 2, 'premium', 'Entrada manual', 'R$50', 'Dobrar Após Perda', 3.00, '2025-05-07 04:46:01', 'failed', '2025-05-07 04:01:01', '2025-05-07 04:46:01', NULL),
(22, 36, 2, 'regular', '15 Giros', 'R$50', 'Valor Mínimo', 5.00, '2025-05-07 04:15:01', 'failed', '2025-05-07 04:10:01', '2025-05-07 04:15:01', NULL),
(23, 21, 2, 'premium', '5 Giros', 'R$50', 'Porcentagem', 3.00, '2025-05-07 05:09:01', 'failed', '2025-05-07 04:24:01', '2025-05-07 05:09:01', NULL),
(24, 61, 2, 'premium', '5 Giros', 'R$50', 'Dobrar Após Perda', 1.50, '2025-05-07 05:30:01', 'failed', '2025-05-07 04:45:01', '2025-05-07 05:30:01', NULL),
(25, 126, 2, 'premium', 'Auto', 'R$50', 'Dobrar Após Perda', 3.00, '2025-05-07 05:52:01', 'failed', '2025-05-07 05:07:01', '2025-05-07 05:52:01', NULL),
(26, 80, 2, 'premium', 'Jogada única', 'R$20', 'Valor Fixo', 1.50, '2025-05-07 06:10:01', 'failed', '2025-05-07 05:25:01', '2025-05-07 06:10:01', NULL),
(27, 36, 2, 'premium', 'Jogada única', 'R$10', 'Valor Fixo', 5.00, '2025-05-07 06:29:01', 'failed', '2025-05-07 05:44:01', '2025-05-07 06:29:01', NULL),
(28, 13, 2, 'premium', 'Entrada Manual', 'R$10', 'Dobrar Após Perda', 1.50, '2025-05-07 06:48:01', 'failed', '2025-05-07 06:03:01', '2025-05-07 06:48:01', NULL),
(29, 2, 2, 'regular', '10 Giros', 'R$50', 'Porcentagem', 5.00, '2025-05-07 06:22:01', 'failed', '2025-05-07 06:17:01', '2025-05-07 06:22:01', NULL),
(30, 28, 2, 'premium', '5 Giros', 'R$20', 'Dobrar Após Perda', 10.00, '2025-05-07 07:10:01', 'failed', '2025-05-07 06:25:01', '2025-05-07 07:10:01', NULL),
(31, 53, 2, 'premium', 'Auto', 'R$10', 'Valor Fixo', 3.00, '2025-05-07 07:31:01', 'failed', '2025-05-07 06:46:01', '2025-05-07 07:31:01', NULL),
(32, 138, 2, 'premium', '15 Giros', 'R$20', 'Valor Fixo', 5.00, '2025-05-07 07:52:01', 'failed', '2025-05-07 07:07:01', '2025-05-07 07:52:01', NULL),
(33, 52, 2, 'premium', 'Jogada única', 'R$5', 'Valor Fixo', 2.50, '2025-05-07 08:16:01', 'failed', '2025-05-07 07:31:01', '2025-05-07 08:16:01', NULL),
(34, 88, 2, 'premium', 'Entrada após tigre', 'R$5', 'Valor Fixo', 3.00, '2025-05-07 08:38:01', 'failed', '2025-05-07 07:53:01', '2025-05-07 08:38:01', NULL),
(35, 17, 2, 'premium', 'Auto', 'R$20', 'Dobrar Após Perda', 10.00, '2025-05-07 08:55:01', 'failed', '2025-05-07 08:09:02', '2025-05-07 08:55:01', NULL),
(36, 64, 2, 'regular', '5 Giros', 'R$10', 'Porcentagem', 2.50, '2025-05-07 08:29:01', 'failed', '2025-05-07 08:24:01', '2025-05-07 08:29:01', NULL),
(37, 9, 2, 'premium', 'Jogada única', 'R$100', 'Valor Fixo', 5.00, '2025-05-07 09:16:01', 'failed', '2025-05-07 08:31:01', '2025-05-07 09:16:01', NULL),
(38, 119, 2, 'premium', 'Auto', 'R$20', 'Porcentagem', 5.00, '2025-05-07 09:34:01', 'failed', '2025-05-07 08:49:01', '2025-05-07 09:34:01', NULL),
(39, 101, 2, 'premium', '10 Giros', 'R$10', 'Porcentagem', 2.00, '2025-05-07 09:57:01', 'failed', '2025-05-07 09:12:01', '2025-05-07 09:57:01', NULL),
(40, 58, 2, 'premium', '15 Giros', 'R$100', 'Porcentagem', 5.00, '2025-05-07 10:15:01', 'failed', '2025-05-07 09:30:01', '2025-05-07 10:15:01', NULL),
(41, 54, 2, 'premium', 'Jogada única', 'R$10', 'Porcentagem', 3.00, '2025-05-07 10:36:01', 'failed', '2025-05-07 09:51:01', '2025-05-07 10:36:01', NULL),
(42, 111, 2, 'premium', '15 Giros', 'R$5', 'Valor Fixo', 1.50, '2025-05-07 10:54:01', 'failed', '2025-05-07 10:09:01', '2025-05-07 10:54:01', NULL),
(43, 136, 2, 'regular', '15 Giros', 'R$10', 'Valor Fixo', 2.00, '2025-05-07 10:34:01', 'failed', '2025-05-07 10:29:01', '2025-05-07 10:34:01', NULL),
(44, 140, 2, 'premium', '10 Giros', 'R$5', 'Valor Mínimo', 1.50, '2025-05-07 11:15:01', 'failed', '2025-05-07 10:30:01', '2025-05-07 11:15:01', NULL),
(45, 111, 2, 'premium', '15 Giros', 'R$50', 'Valor Mínimo', 2.00, '2025-05-07 11:33:01', 'failed', '2025-05-07 10:48:01', '2025-05-07 11:33:01', NULL),
(46, 85, 2, 'premium', '15 Giros', 'R$5', 'Valor Mínimo', 10.00, '2025-05-07 11:51:01', 'failed', '2025-05-07 11:06:01', '2025-05-07 11:51:01', NULL),
(47, 51, 2, 'premium', '10 Giros', 'R$100', 'Valor Mínimo', 2.50, '2025-05-07 12:14:01', 'failed', '2025-05-07 11:29:01', '2025-05-07 12:14:01', NULL),
(48, 4, 2, 'premium', '10 Giros', 'R$50', 'Porcentagem', 10.00, '2025-05-07 12:31:01', 'failed', '2025-05-07 11:46:01', '2025-05-07 12:31:01', NULL),
(49, 24, 2, 'premium', '10 Giros', 'R$50', 'Valor Fixo', 10.00, '2025-05-07 12:50:01', 'failed', '2025-05-07 12:05:01', '2025-05-07 12:50:01', NULL),
(50, 44, 2, 'premium', '10 Giros', 'R$20', 'Porcentagem', 10.00, '2025-05-07 13:12:01', 'failed', '2025-05-07 12:27:01', '2025-05-07 13:12:01', NULL),
(51, 105, 2, 'regular', '10 Giros', 'R$10', 'Valor Mínimo', 2.00, '2025-05-07 12:44:01', 'failed', '2025-05-07 12:38:02', '2025-05-07 12:44:01', NULL),
(52, 130, 2, 'premium', 'Jogada única', 'R$50', 'Valor Fixo', 10.00, '2025-05-07 13:29:01', 'failed', '2025-05-07 12:44:01', '2025-05-07 13:29:01', NULL),
(53, 6, 2, 'premium', 'Jogada única', 'R$50', 'Valor Fixo', 3.00, '2025-05-07 13:50:01', 'failed', '2025-05-07 13:05:01', '2025-05-07 13:50:01', NULL),
(54, 133, 2, 'premium', '5 Giros', 'R$10', 'Valor Mínimo', 10.00, '2025-05-07 14:08:01', 'failed', '2025-05-07 13:23:01', '2025-05-07 14:08:01', NULL),
(55, 80, 2, 'premium', 'Jogada única', 'R$20', 'Dobrar Após Perda', 2.50, '2025-05-07 14:30:01', 'failed', '2025-05-07 13:45:01', '2025-05-07 14:30:01', NULL),
(56, 126, 2, 'premium', '15 Giros', 'R$5', 'Dobrar Após Perda', 1.50, '2025-05-07 14:53:01', 'failed', '2025-05-07 14:08:01', '2025-05-07 14:53:01', NULL),
(57, 11, 2, 'premium', 'Entrada Manual', 'R$20', 'Porcentagem', 3.00, '2025-05-07 15:10:01', 'failed', '2025-05-07 14:25:01', '2025-05-07 15:10:01', NULL),
(58, 32, 2, 'premium', 'Auto', 'R$5', 'Valor Fixo', 2.00, '2025-05-07 15:29:01', 'failed', '2025-05-07 14:44:01', '2025-05-07 15:29:01', NULL),
(59, 88, 2, 'regular', 'Esperar 3 vitórias', 'R$10', 'Porcentagem', 2.00, '2025-05-07 14:53:01', 'failed', '2025-05-07 14:48:01', '2025-05-07 14:53:01', NULL),
(60, 68, 2, 'premium', 'Auto', 'R$20', 'Valor Fixo', 10.00, '2025-05-07 15:47:01', 'failed', '2025-05-07 15:02:01', '2025-05-07 15:47:01', NULL),
(61, 139, 2, 'premium', 'Entrada Manual', 'R$100', 'Valor Mínimo', 5.00, '2025-05-07 16:04:01', 'failed', '2025-05-07 15:19:01', '2025-05-07 16:04:01', NULL),
(62, 15, 2, 'premium', 'Jogada única', 'R$20', 'Dobrar Após Perda', 2.50, '2025-05-07 16:26:01', 'failed', '2025-05-07 15:41:01', '2025-05-07 16:26:01', NULL),
(63, 146, 2, 'premium', 'Jogar normal', 'R$5', 'Dobrar Após Perda', 2.50, '2025-05-08 03:44:01', 'pending', '2025-05-07 15:59:01', NULL, NULL),
(64, 79, 2, 'premium', '10 Giros', 'R$10', 'Dobrar Após Perda', 2.00, '2025-05-08 04:05:01', 'pending', '2025-05-07 16:20:01', NULL, NULL),
(65, 73, 2, 'premium', '5 Giros', 'R$20', 'Porcentagem', 1.50, '2025-05-08 04:27:01', 'pending', '2025-05-07 16:42:01', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `signal_sending_logs`
--

CREATE TABLE `signal_sending_logs` (
  `id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `destination_id` int(11) NOT NULL,
  `destination_type` enum('group','channel') NOT NULL,
  `signal_type` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura para tabela `signal_sources`
--

CREATE TABLE `signal_sources` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('pgsoft','pragmatic','outro') NOT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `config_json` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura para tabela `telegram_bots`
--

CREATE TABLE `telegram_bots` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `type` enum('premium','comum') NOT NULL DEFAULT 'comum',
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `webhook_url` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Despejando dados para a tabela `telegram_bots`
--

INSERT INTO `telegram_bots` (`id`, `name`, `token`, `username`, `type`, `description`, `status`, `webhook_url`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Sinais Pragmatic Play', '7300431120:AAHkmMB-KlExhXURvDZUPai27uGEHphOpcQ', 'sinaispragmaticplay_bot', 'comum', '', 'active', '', 1, '2025-05-06 23:18:35', NULL),
(2, 'Sinais Pragmatic Vip', '7887468081:AAFDC9Pr5nK-XaYl5iavrLbn-tuTqMEmiX4', 'sinaispragmaticvip_bot', 'premium', '', 'active', '', 1, '2025-05-07 00:00:20', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `telegram_channels`
--

CREATE TABLE `telegram_channels` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `channel_id` varchar(100) NOT NULL,
  `invite_link` varchar(255) DEFAULT NULL,
  `type` enum('premium','comum') NOT NULL DEFAULT 'comum',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura para tabela `telegram_groups`
--

CREATE TABLE `telegram_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `group_id` varchar(100) NOT NULL,
  `invite_link` varchar(255) DEFAULT NULL,
  `type` enum('premium','comum') NOT NULL DEFAULT 'comum',
  `level` enum('vip','comum') DEFAULT 'comum',
  `signal_frequency` int(11) DEFAULT 30,
  `min_minutes` int(11) DEFAULT 3,
  `max_minutes` int(11) DEFAULT 10,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura para tabela `telegram_users`
--

CREATE TABLE `telegram_users` (
  `id` int(11) NOT NULL,
  `telegram_id` varchar(100) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date NOT NULL,
  `status` enum('active','suspended','expired') DEFAULT 'active',
  `premium` tinyint(1) NOT NULL DEFAULT 0,
  `premium_expires_at` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `telegram` varchar(50) DEFAULT NULL,
  `role` enum('admin','master','user') NOT NULL DEFAULT 'user',
  `created_by` int(11) DEFAULT NULL,
  `status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Soft delete: 0=ativo, 1=excluído',
  `bot_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `telegram`, `role`, `created_by`, `status`, `created_at`, `expiry_date`, `last_login`, `deleted`, `bot_id`) VALUES
(1, 'admin', '$2y$12$4S5ax5qGtHxPmPTLAF7b6OGB7qtAy/XVhk8Lmnyi38db.7RwAHnba', NULL, NULL, NULL, NULL, 'admin', NULL, 'active', '2025-05-06 02:04:37', '2030-01-01', '2025-05-07 12:07:26', 0, NULL),
(11, 'Mega12', '$2y$12$Vh1C7WCQ7BfLhJc1obKoIurc4aPU.noWqkuSlw5gBzanZsv1jhZha', '', '', '', '', 'user', NULL, 'active', '2025-05-06 15:35:03', '2025-07-05', '2025-05-06 14:50:04', 0, NULL),
(12, 'super12', '$2y$12$KFQUdL1vcDY0cGhVNEOGUOi6mCHjjHjAoXSfBdbllm0l3tbJfz1X2', '', '', '', '', 'master', NULL, 'active', '2025-05-06 17:50:57', '2025-06-06', '2025-05-06 14:51:07', 0, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_bot_access`
--

CREATE TABLE `user_bot_access` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_group_access`
--

CREATE TABLE `user_group_access` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `group_type` varchar(50) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` enum('active','expired','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `bots`
--
ALTER TABLE `bots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_owner_id` (`owner_id`);

--
-- Índices de tabela `bot_channel_mappings`
--
ALTER TABLE `bot_channel_mappings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bot_id` (`bot_id`),
  ADD KEY `channel_id` (`channel_id`);

--
-- Índices de tabela `bot_group_mappings`
--
ALTER TABLE `bot_group_mappings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bot_id` (`bot_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Índices de tabela `bot_signal_mappings`
--
ALTER TABLE `bot_signal_mappings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bot_id` (`bot_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Índices de tabela `channels`
--
ALTER TABLE `channels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bot_id` (`bot_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Índices de tabela `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Índices de tabela `platforms`
--
ALTER TABLE `platforms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner_id` (`owner_id`);

--
-- Índices de tabela `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Índices de tabela `signals`
--
ALTER TABLE `signals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `platform_id` (`platform_id`),
  ADD KEY `bot_id` (`bot_id`);

--
-- Índices de tabela `signal_generator_settings`
--
ALTER TABLE `signal_generator_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Índices de tabela `signal_history`
--
ALTER TABLE `signal_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `queue_id` (`queue_id`),
  ADD KEY `bot_id` (`bot_id`);

--
-- Índices de tabela `signal_queue`
--
ALTER TABLE `signal_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `platform_id` (`platform_id`),
  ADD KEY `idx_status_scheduled` (`status`,`scheduled_at`);

--
-- Índices de tabela `signal_sending_logs`
--
ALTER TABLE `signal_sending_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bot_id` (`bot_id`),
  ADD KEY `destination_id` (`destination_id`);

--
-- Índices de tabela `signal_sources`
--
ALTER TABLE `signal_sources`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `telegram_bots`
--
ALTER TABLE `telegram_bots`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `telegram_channels`
--
ALTER TABLE `telegram_channels`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `telegram_groups`
--
ALTER TABLE `telegram_groups`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `telegram_users`
--
ALTER TABLE `telegram_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Índices de tabela `user_bot_access`
--
ALTER TABLE `user_bot_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_bot` (`user_id`,`bot_id`),
  ADD KEY `idx_bot_id` (`bot_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Índices de tabela `user_group_access`
--
ALTER TABLE `user_group_access`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `group_id` (`group_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de tabela `bots`
--
ALTER TABLE `bots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `bot_channel_mappings`
--
ALTER TABLE `bot_channel_mappings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `bot_group_mappings`
--
ALTER TABLE `bot_group_mappings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `bot_signal_mappings`
--
ALTER TABLE `bot_signal_mappings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `channels`
--
ALTER TABLE `channels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT de tabela `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `platforms`
--
ALTER TABLE `platforms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `signals`
--
ALTER TABLE `signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `signal_generator_settings`
--
ALTER TABLE `signal_generator_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `signal_history`
--
ALTER TABLE `signal_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `signal_queue`
--
ALTER TABLE `signal_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT de tabela `signal_sending_logs`
--
ALTER TABLE `signal_sending_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `signal_sources`
--
ALTER TABLE `signal_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `telegram_bots`
--
ALTER TABLE `telegram_bots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `telegram_channels`
--
ALTER TABLE `telegram_channels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `telegram_groups`
--
ALTER TABLE `telegram_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `telegram_users`
--
ALTER TABLE `telegram_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `user_bot_access`
--
ALTER TABLE `user_bot_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `user_group_access`
--
ALTER TABLE `user_group_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `bots`
--
ALTER TABLE `bots`
  ADD CONSTRAINT `bots_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `channels`
--
ALTER TABLE `channels`
  ADD CONSTRAINT `channels_ibfk_1` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`),
  ADD CONSTRAINT `channels_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `signals`
--
ALTER TABLE `signals`
  ADD CONSTRAINT `signals_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  ADD CONSTRAINT `signals_ibfk_2` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`),
  ADD CONSTRAINT `signals_ibfk_3` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`);

--
-- Restrições para tabelas `signal_history`
--
ALTER TABLE `signal_history`
  ADD CONSTRAINT `signal_history_ibfk_1` FOREIGN KEY (`queue_id`) REFERENCES `signal_queue` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `signal_history_ibfk_2` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `signal_queue`
--
ALTER TABLE `signal_queue`
  ADD CONSTRAINT `signal_queue_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `signal_queue_ibfk_2` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `telegram_users`
--
ALTER TABLE `telegram_users`
  ADD CONSTRAINT `telegram_users_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
