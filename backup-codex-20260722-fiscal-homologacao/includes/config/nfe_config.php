<?php
// includes/config/nfe_config.php

// ✅ CORRIGIDO (Problema 2): A função agora recebe o array de config
function getConfigJson($config) {
    
    // ✅ CORRIGIDO (Problema 2): Removemos a busca duplicada ao banco ($pdo)
    // require_once __DIR__ . '/../../database.php';
    // $stmt = $pdo->query("SELECT * FROM empresa_config WHERE id = 1");
    // $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        throw new Exception("Configurações fiscais da empresa não fornecidas para getConfigJson.");
    }

    $configJson = [
        'atualizacao' => date('Y-m-d H:i:s'),
        // ✅ CORRIGIDO (Problema 4): Usa o ambiente salvo no banco
        'tpAmb'       => (int) $config['ambiente'], // 2=Homologação, 1=Produção
        'razaosocial' => $config['razao_social'],
        'cnpj'        => $config['cnpj'],
        'fantasia'    => $config['fantasia'],
        'ie'          => $config['ie'],
        'logradouro'  => $config['logradouro'],
        'numero'      => $config['numero'],
        'bairro'      => $config['bairro'],
        'municipio'   => $config['municipio'],
        'uf'          => $config['uf'],
        'cep'         => $config['cep'],
        'codMun'      => $config['cod_municipio'], // Este nome está correto
        'csc'         => $config['csc'],
        'cscId'       => $config['csc_id'],
        'schemes'     => 'PL_009_V4',
        'versao'      => '4.00',
    ];

    return json_encode($configJson);
}