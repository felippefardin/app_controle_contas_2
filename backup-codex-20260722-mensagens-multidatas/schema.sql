-- =========================================
-- SCHEMA COMPLETO MULTI-TENANT SAAS (VERSÃO FINAL CORRIGIDA)
-- =========================================

-- Usuários
CREATE TABLE IF NOT EXISTS usuarios (
  id INT NOT NULL AUTO_INCREMENT,
  id_criador INT DEFAULT NULL,
  nome VARCHAR(100) NOT NULL,
  tipo_pessoa VARCHAR(10) NOT NULL,
  documento VARCHAR(20) DEFAULT NULL,
  telefone VARCHAR(20) DEFAULT NULL,
  email VARCHAR(100) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'usuario',
  senha VARCHAR(255) NOT NULL,
  perfil ENUM('padrao','admin') DEFAULT 'padrao',
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tipo ENUM('admin','padrao') DEFAULT 'padrao',
  owner_id INT DEFAULT NULL,
  foto VARCHAR(255) DEFAULT 'default-profile.png',
  banco_usuario VARCHAR(100) NOT NULL DEFAULT 'app_controle_contas',
  criado_por_usuario_id INT DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ativo',
  nivel_acesso VARCHAR(20) DEFAULT 'padrao',
  tenant_id VARCHAR(32) DEFAULT NULL,
  tema_preferencia VARCHAR(10) DEFAULT 'dark',
  -- Correção: Definição da coluna adicionada corretamente
  tipo_cancelamento ENUM('desativar', 'excluir') DEFAULT NULL,
  -- Correção: Sintaxe da coluna gerada consertada
  documento_clean VARCHAR(14) GENERATED ALWAYS AS (REGEXP_REPLACE(documento, '[^0-9]', '')) STORED,
  cpf VARCHAR(14) DEFAULT NULL,
  token_reset VARCHAR(255) DEFAULT NULL,
  token_expira_em DATETIME DEFAULT NULL,
  is_master TINYINT(1) DEFAULT 0,
  permissoes TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ux_usuarios_email_tenant (email, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categorias
CREATE TABLE IF NOT EXISTS categorias (
  id INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  id_pai INT DEFAULT NULL,
  nome VARCHAR(100) NOT NULL,
  tipo ENUM('receita','despesa') NOT NULL,
  PRIMARY KEY (id),
  KEY id_usuario (id_usuario),
  CONSTRAINT fk_categorias_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pessoas / Fornecedores
CREATE TABLE IF NOT EXISTS pessoas_fornecedores (
  id INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  nome VARCHAR(255) NOT NULL,
  cpf_cnpj VARCHAR(20) DEFAULT NULL,
  endereco VARCHAR(255) DEFAULT NULL,
  contato VARCHAR(20) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  tipo ENUM('pessoa','fornecedor') NOT NULL,
  PRIMARY KEY (id),
  KEY id_usuario (id_usuario),
  CONSTRAINT fk_pf_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contas a Receber
CREATE TABLE `contas_receber` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `id_pessoa_fornecedor` int DEFAULT NULL,
  `id_categoria` int DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  `numero` varchar(50) DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `status` enum('pendente','baixada') DEFAULT 'pendente',
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `data_baixa` date DEFAULT NULL,
  `baixado_por` int DEFAULT NULL,
  `juros` decimal(10,2) DEFAULT '0.00',
  `comprovante` varchar(255) DEFAULT NULL,
  `observacao` text,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr_usuario` (`usuario_id`),
  KEY `idx_cr_pessoa` (`id_pessoa_fornecedor`),
  KEY `idx_cr_categoria` (`id_categoria`),
  KEY `idx_cr_status` (`status`),
  CONSTRAINT `fk_cr_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cr_pessoa` FOREIGN KEY (`id_pessoa_fornecedor`) REFERENCES `pessoas_fornecedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cr_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Contas a Pagar 
CREATE TABLE IF NOT EXISTS `contas_pagar` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `usuario_id` INT DEFAULT NULL,
  `id_categoria` INT DEFAULT NULL,
  `id_pessoa_fornecedor` INT DEFAULT NULL,
  `fornecedor` VARCHAR(255) DEFAULT NULL, 
  `numero` VARCHAR(50) DEFAULT NULL,    
  `valor` DECIMAL(10,2) DEFAULT NULL,
  `juros` DECIMAL(10,2) DEFAULT 0.00,   
  `status` ENUM('pendente','baixada') DEFAULT 'pendente',
  `forma_pagamento` VARCHAR(50) DEFAULT NULL,
  `data_vencimento` DATE DEFAULT NULL,
  `data_baixa` DATE DEFAULT NULL,         
  `descricao` TEXT DEFAULT NULL,
  `comprovante` VARCHAR(255) DEFAULT NULL, 
  `enviar_email` CHAR(1) DEFAULT 'N',      
  `baixado_por` INT DEFAULT NULL,          
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_contas_pagar_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contas_pagar_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contas_pagar_fornecedor` FOREIGN KEY (`id_pessoa_fornecedor`) REFERENCES `pessoas_fornecedores`(`id`),
  CONSTRAINT `fk_cp_baixado_por` FOREIGN KEY (`baixado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contas Bancárias
CREATE TABLE IF NOT EXISTS `contas_bancarias` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_usuario` INT NOT NULL,
  `nome_banco` VARCHAR(100) NOT NULL,
  `agencia` VARCHAR(20) DEFAULT NULL,
  `conta` VARCHAR(20) NOT NULL,
  `tipo_conta` VARCHAR(50) DEFAULT NULL,
  `chave_pix` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `fk_contas_bancarias_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produtos (
  id INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  nome VARCHAR(255) NOT NULL,
  descricao TEXT,
  preco_compra DECIMAL(10,2) DEFAULT NULL,
  preco_venda DECIMAL(10,2) DEFAULT NULL,
  quantidade_estoque INT NOT NULL DEFAULT 0,
  unidade_medida VARCHAR(50) DEFAULT NULL,
  data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  quantidade_minima INT NOT NULL DEFAULT 0,
  ncm VARCHAR(8) DEFAULT NULL,
  cfop VARCHAR(4) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY id_usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vendas
CREATE TABLE IF NOT EXISTS vendas (
  id INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  id_cliente INT NOT NULL,
  data_venda TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  valor_total DECIMAL(10,2) NOT NULL,
  desconto DECIMAL(10,2) DEFAULT 0,
  observacao TEXT,
  forma_pagamento VARCHAR(50) NOT NULL,
  numero_parcelas INT DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT fk_vendas_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
  CONSTRAINT fk_vendas_cliente FOREIGN KEY (id_cliente) REFERENCES pessoas_fornecedores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Itens da Venda
CREATE TABLE IF NOT EXISTS venda_items (
  id INT NOT NULL AUTO_INCREMENT,
  id_venda INT NOT NULL,
  id_produto INT NOT NULL,
  quantidade INT NOT NULL,
  preco_unitario DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_vi_venda FOREIGN KEY (id_venda) REFERENCES vendas(id) ON DELETE CASCADE,
  CONSTRAINT fk_vi_produto FOREIGN KEY (id_produto) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Movimento Estoque
CREATE TABLE IF NOT EXISTS movimento_estoque (
  id INT NOT NULL AUTO_INCREMENT,
  id_produto INT NOT NULL,
  id_usuario INT NOT NULL,
  id_pessoa_fornecedor INT DEFAULT NULL,
  tipo ENUM('entrada','saida') NOT NULL,
  quantidade INT NOT NULL,
  data_movimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  observacao TEXT,
  PRIMARY KEY (id),
  CONSTRAINT fk_me_produto FOREIGN KEY (id_produto) REFERENCES produtos(id),
  CONSTRAINT fk_me_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
  CONSTRAINT fk_me_fornecedor FOREIGN KEY (id_pessoa_fornecedor) REFERENCES pessoas_fornecedores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compras
CREATE TABLE IF NOT EXISTS compras (
  id INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  id_fornecedor INT NOT NULL,
  data_compra TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  valor_total DECIMAL(10,2) NOT NULL,
  observacao TEXT,
  PRIMARY KEY (id),
  CONSTRAINT fk_compras_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
  CONSTRAINT fk_compras_fornecedor FOREIGN KEY (id_fornecedor) REFERENCES pessoas_fornecedores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Itens da Compra
CREATE TABLE IF NOT EXISTS compra_items (
  id INT NOT NULL AUTO_INCREMENT,
  id_compra INT NOT NULL,
  id_produto INT NOT NULL,
  quantidade INT NOT NULL,
  preco_unitario DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_ci_compra FOREIGN KEY (id_compra) REFERENCES compras(id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_produto FOREIGN KEY (id_produto) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Caixa Diário
CREATE TABLE IF NOT EXISTS caixa_diario (
  id INT NOT NULL AUTO_INCREMENT,
  data DATE NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  tipo ENUM('entrada','saida') NOT NULL DEFAULT 'entrada',
  descricao VARCHAR(255) DEFAULT NULL,
  id_venda INT DEFAULT NULL,
  usuario_id INT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ux_caixa (data, usuario_id),
  CONSTRAINT fk_cd_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notas Fiscais
CREATE TABLE IF NOT EXISTS notas_fiscais (
  id INT NOT NULL AUTO_INCREMENT,
  id_venda INT NOT NULL,
  ambiente INT NOT NULL,
  status VARCHAR(50) NOT NULL,
  chave_acesso VARCHAR(44) DEFAULT NULL,
  protocolo VARCHAR(100) DEFAULT NULL,
  xml_path VARCHAR(255) DEFAULT NULL,
  mensagem_erro TEXT,
  data_emissao DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_nf_venda FOREIGN KEY (id_venda) REFERENCES vendas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Logs Webhook
CREATE TABLE IF NOT EXISTS logs_webhook (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(100),
  acao VARCHAR(100),
  data_id VARCHAR(100),
  payload TEXT,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Solicitações de Exclusão
CREATE TABLE IF NOT EXISTS solicitacoes_exclusao (
  id INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  token VARCHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_se_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configurações do Tenant
CREATE TABLE IF NOT EXISTS configuracoes_tenant (
  id INT NOT NULL AUTO_INCREMENT,
  chave VARCHAR(100) NOT NULL,
  valor TEXT,
  PRIMARY KEY (id),
  UNIQUE KEY uk_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tenants (master)
-- Tabela Tenants (Versão Corrigida para Produção)
CREATE TABLE IF NOT EXISTS tenants (
  id INT NOT NULL AUTO_INCREMENT,
  tenant_id VARCHAR(32) DEFAULT NULL,
  usuario_id INT DEFAULT NULL,
  nome VARCHAR(255) DEFAULT NULL,
  nome_empresa VARCHAR(255) NOT NULL,
  admin_email VARCHAR(255) NOT NULL,
  subdominio VARCHAR(191) DEFAULT NULL,
  
  -- Credenciais do Banco do Tenant
  db_host VARCHAR(255) NOT NULL,
  db_database VARCHAR(255) NOT NULL,
  db_user VARCHAR(255) NOT NULL,
  db_password VARCHAR(255) NOT NULL,
  
  -- Controle de Assinatura e Limites
  plano_atual VARCHAR(50) DEFAULT 'basico',
  status_assinatura VARCHAR(50) DEFAULT 'trial',
  id_assinatura_mp VARCHAR(100) DEFAULT NULL, -- Importante para o checkout
  data_inicio_teste TIMESTAMP NULL DEFAULT NULL,
  data_renovacao DATE DEFAULT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'proprietario',
  usuarios_extras INT DEFAULT 0, -- Importante para o add user
  
  -- Marketing e Cancelamento
  cupom_registro VARCHAR(50) DEFAULT NULL,
  msg_cupom_visto TINYINT(1) DEFAULT 0,
  msg_indicacao_visto TINYINT(1) DEFAULT 0,
  tipo_cancelamento ENUM('desativar', 'excluir') DEFAULT NULL,
  data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY admin_email (admin_email),
  UNIQUE KEY subdominio (subdominio),
  KEY idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faturas_assinatura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(32) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE,
    status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
    forma_pagamento VARCHAR(50),
    transacao_id VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `empresa_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `razao_social` varchar(255) NOT NULL,
  `cnpj` varchar(14) NOT NULL,
  `fantasia` varchar(255) DEFAULT NULL,
  `ie` varchar(20) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `municipio` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `cep` varchar(8) DEFAULT NULL,
  `cod_municipio` varchar(7) DEFAULT NULL,
  `regime_tributario` int DEFAULT NULL,
  `csc` varchar(100) NOT NULL,
  `csc_id` varchar(10) NOT NULL,
  `certificado_a1_path` varchar(255) DEFAULT NULL,
  `certificado_senha` varchar(255) DEFAULT NULL,
  `ultimo_numero_nfce` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `cnpj` (`cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Suporte solicitado diretamente pela tela de login
CREATE TABLE IF NOT EXISTS suporte_login (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  protocolo VARCHAR(30) NOT NULL,
  nome VARCHAR(150) NOT NULL,
  whatsapp VARCHAR(30) DEFAULT NULL,
  email VARCHAR(190) DEFAULT NULL,
  descricao TEXT NOT NULL,
  anonimo TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'pendente',
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolvido_em DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ux_suporte_login_protocolo (protocolo),
  KEY idx_suporte_login_status_criado (status, criado_em),
  KEY idx_suporte_login_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suporte_historico (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  suporte_id INT UNSIGNED NOT NULL,
  mensagem TEXT NOT NULL,
  tipo VARCHAR(30) NOT NULL DEFAULT 'sistema',
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_suporte_historico_suporte (suporte_id, criado_em),
  CONSTRAINT fk_suporte_historico_login FOREIGN KEY (suporte_id)
    REFERENCES suporte_login (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
