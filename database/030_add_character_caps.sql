-- database/030_add_character_caps.sql
-- Добавляем поле caps для валюты персонажей

ALTER TABLE characters 
ADD COLUMN caps INT UNSIGNED DEFAULT 0 AFTER luck;
