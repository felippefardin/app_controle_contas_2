<?php
return [
    'id' => '202611230001_notas_fiscais',
    'description' => 'Estrutura de NFC-e por empresa',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS `notas_fiscais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_venda` int NOT NULL,
  `ambiente` int NOT NULL,
  `status` varchar(50) NOT NULL,
  `chave_acesso` varchar(44) DEFAULT NULL,
  `protocolo` varchar(100) DEFAULT NULL,
  `xml_path` varchar(255) DEFAULT NULL,
  `mensagem_erro` text,
  `data_emissao` datetime DEFAULT NULL,
  `numero` int DEFAULT NULL,
  `serie` int DEFAULT NULL,
  `modelo` int NOT NULL DEFAULT '65',
  PRIMARY KEY (`id`),
  KEY `fk_nf_venda` (`id_venda`),
  CONSTRAINT `fk_nf_venda` FOREIGN KEY (`id_venda`) REFERENCES `vendas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL);
        $ctx->addColumn('notas_fiscais', 'numero', 'INT NULL');
        $ctx->addColumn('notas_fiscais', 'serie', 'INT NULL');
        $ctx->addColumn('notas_fiscais', 'modelo', 'INT NOT NULL DEFAULT 65');
    },
];
