-- Миграция 019: Добавление полей для босса и награды в таблицу dungeons
-- Цель: Явное хранение связи с боссом и типизированной награды

ALTER TABLE dungeons 
ADD COLUMN boss_id INT UNSIGNED DEFAULT NULL COMMENT 'ID монстра-босса из таблицы monsters',
ADD COLUMN reward_item_id INT UNSIGNED DEFAULT NULL COMMENT 'ID предмета-награды из loot/weapons/armors/consumables',
ADD COLUMN reward_xp INT UNSIGNED DEFAULT 0 COMMENT 'Опыт за прохождение',
ADD COLUMN reward_caps INT UNSIGNED DEFAULT 0 COMMENT 'Крышки за прохождение';

-- Добавляем внешние ключи (monsters.id имеет INT AUTO_INCREMENT, поэтому совместим)
ALTER TABLE dungeons
ADD CONSTRAINT fk_dungeon_boss FOREIGN KEY (boss_id) REFERENCES monsters(id) ON DELETE SET NULL;

-- Индекс для быстрого поиска данжей по боссу
CREATE INDEX idx_dungeons_boss ON dungeons(boss_id);

-- Комментарий к таблице (если поддерживается версией MySQL)
-- ALTER TABLE dungeons COMMENT = 'Подземелья с ручным управлением, боссами и наградами';
