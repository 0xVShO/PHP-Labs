-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: db
-- Время создания: Окт 20 2026 г., 08:25
-- Версия сервера: 8.0.46
-- Версия PHP: 8.3.35

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `php_workshop_db`
--

-- --------------------------------------------------------

--
-- Структура таблицы `tickets`
--

CREATE TABLE `tickets` (
  `id` int NOT NULL,
  `description` varchar(500) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_urgent` tinyint(1) DEFAULT NULL,
  `sla_hours` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `tickets`
--

INSERT INTO `tickets` (`id`, `description`, `subject`, `priority`, `status`, `created_at`, `is_urgent`, `sla_hours`) VALUES
(9, 'Просто тест №1...', 'Тест №1', 'medium', 'open', '2026-10-20 08:12:27', 0, NULL),
(10, 'Просто тест №2...', 'Тест №2', 'medium', 'open', '2026-10-20 08:12:39', 0, NULL),
(11, 'Просто тест №3...', 'Тест №3', 'medium', 'open', '2026-10-20 08:12:49', 0, NULL),
(12, 'Просто тест №4...', 'Тест №4', 'high', 'open', '2026-10-20 08:13:00', 1, 2),
(13, 'Просто тест №5....', 'Тест №5', 'low', 'open', '2026-10-20 08:13:16', 0, NULL);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
