-- database/028_fix_id_types.sql
-- Исправление несовместимости типов ID (Signed vs Unsigned)
-- После миграции 027 FK должны работать корректно благодаря INT UNSIGNED в 001

-- 1. Финальная проверка players.id - убеждаемся что это INT UNSIGNED
ALTER TABLE players MODIFY COLUMN id INT UNSIGNED AUTO_INCREMENT;

-- 2. Если есть проблемы с FK в search_logs, исправляем
-- (search_logs создается в 023 с правильным типом)
