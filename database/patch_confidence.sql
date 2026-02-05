-- 8. Aggiunta colonna confidence alla tabella bets
ALTER TABLE `bets` ADD COLUMN `confidence` INT DEFAULT 0 AFTER `urgency`;
