<?php

declare(strict_types=1);

function account_deletion_consent_version(): string
{
    return '1.0';
}

function account_deletion_consent_text(): string
{
    return 'Declaro que li e compreendi que a exclusão da conta é permanente e apagará todos os usuários, contas a pagar e receber, vendas, compras, estoque, clientes, fornecedores, relatórios, históricos, configurações, arquivos e demais dados vinculados. Estou ciente de que esses dados não poderão ser recuperados após a conclusão da exclusão.';
}

function account_deletion_consent_hash(): string
{
    return hash('sha256', account_deletion_consent_text());
}
