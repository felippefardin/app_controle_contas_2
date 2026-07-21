-- Reparo local: tabelas InnoDB listadas pelo MySQL, mas ausentes no mecanismo.
-- Uma cópia da pasta de dados original deve ser preservada antes de executar.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `tenants`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `usuarios` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_criador` INT DEFAULT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `tipo_pessoa` VARCHAR(10) NOT NULL,
  `documento` VARCHAR(20) DEFAULT NULL,
  `telefone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'usuario',
  `senha` VARCHAR(255) NOT NULL,
  `perfil` ENUM('padrao','admin') DEFAULT 'padrao',
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo` ENUM('admin','padrao') DEFAULT 'padrao',
  `owner_id` INT DEFAULT NULL,
  `foto` VARCHAR(255) DEFAULT 'default-profile.png',
  `banco_usuario` VARCHAR(100) NOT NULL DEFAULT 'app_controle_contas',
  `criado_por_usuario_id` INT DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'ativo',
  `nivel_acesso` VARCHAR(20) DEFAULT 'padrao',
  `tenant_id` VARCHAR(32) DEFAULT NULL,
  `tema_preferencia` VARCHAR(10) DEFAULT 'dark',
  `tipo_cancelamento` ENUM('desativar', 'excluir') DEFAULT NULL,
  `documento_clean` VARCHAR(14) DEFAULT NULL,
  `cpf` VARCHAR(14) DEFAULT NULL,
  `token_reset` VARCHAR(255) DEFAULT NULL,
  `token_expira_em` DATETIME DEFAULT NULL,
  `is_master` TINYINT(1) DEFAULT 0,
  `permissoes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_usuarios_email_tenant` (`email`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `tenants` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` VARCHAR(32) DEFAULT NULL,
  `usuario_id` INT DEFAULT NULL,
  `nome` VARCHAR(255) DEFAULT NULL,
  `nome_empresa` VARCHAR(255) NOT NULL,
  `admin_email` VARCHAR(255) NOT NULL,
  `senha` VARCHAR(255) DEFAULT NULL,
  `subdominio` VARCHAR(191) DEFAULT NULL,
  `db_host` VARCHAR(255) NOT NULL,
  `db_database` VARCHAR(255) NOT NULL,
  `db_user` VARCHAR(255) NOT NULL,
  `db_password` VARCHAR(255) NOT NULL,
  `plano_atual` VARCHAR(50) DEFAULT 'basico',
  `status_assinatura` VARCHAR(50) DEFAULT 'trial',
  `id_assinatura_mp` VARCHAR(100) DEFAULT NULL,
  `data_inicio_teste` TIMESTAMP NULL DEFAULT NULL,
  `data_renovacao` DATE DEFAULT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'proprietario',
  `usuarios_extras` INT DEFAULT 0,
  `cupom_registro` VARCHAR(50) DEFAULT NULL,
  `msg_cupom_visto` TINYINT(1) DEFAULT 0,
  `msg_indicacao_visto` TINYINT(1) DEFAULT 0,
  `tipo_cancelamento` ENUM('desativar', 'excluir') DEFAULT NULL,
  `data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_email` (`admin_email`),
  UNIQUE KEY `subdominio` (`subdominio`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
