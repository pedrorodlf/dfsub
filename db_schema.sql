-- Schema de banco de dados para os associados Dfsub
-- Execute no phpMyAdmin ou importação SQL

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(191) NOT NULL,
  `data_nascimento` DATE NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `matricula` VARCHAR(64) NOT NULL,
  `senha_hash` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Ativo', 'Aguardando verificação', 'Vencido') NOT NULL DEFAULT 'Aguardando verificação',
  `data_emissao` DATE DEFAULT NULL,
  `data_vencimento` DATE DEFAULT NULL,
  `qr_token` VARCHAR(64) NOT NULL,
  `role` ENUM('admin', 'associado') NOT NULL DEFAULT 'associado',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_unique` (`email`),
  UNIQUE KEY `matricula_unique` (`matricula`),
  UNIQUE KEY `qr_token_unique` (`qr_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exemplo de inserção de administrador:
-- INSERT INTO users (nome, data_nascimento, email, matricula, senha_hash, status, qr_token, role)
-- VALUES ('Administrador', '1970-01-01', 'admin@seudominio.com', 'ADMIN001', '<hash_da_senha>', 'Ativo', 'abc123def456ghi789jkl012mno345pq', 'admin');
