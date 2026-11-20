<?php

declare(strict_types=1);

function caixa_data_valida(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function caixa_buscar_sessao(mysqli $db, int $ownerId, string $date, bool $forUpdate = false): ?array
{
    if (!caixa_data_valida($date)) return null;
    $sql = 'SELECT * FROM aberturas_caixa WHERE id_usuario=? AND data_caixa=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $db->prepare($sql); $stmt->bind_param('is', $ownerId, $date); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return $row ?: null;
}

function caixa_esta_aberto(mysqli $db, int $ownerId, string $date): bool
{
    $session = caixa_buscar_sessao($db, $ownerId, $date);
    return $session !== null && $session['status'] === 'aberto';
}

function caixa_abrir(mysqli $db, int $ownerId, int $operatorId, float $openingAmount, ?string $note = null): array
{
    if ($openingAmount < 0) throw new InvalidArgumentException('O valor inicial não pode ser negativo.');
    $date = date('Y-m-d');
    $db->begin_transaction();
    try {
        $existing = caixa_buscar_sessao($db, $ownerId, $date, true);
        if ($existing) throw new RuntimeException($existing['status'] === 'aberto' ? 'O caixa de hoje já está aberto.' : 'O caixa de hoje já foi fechado.');
        $status = 'aberto';
        $stmt = $db->prepare('INSERT INTO aberturas_caixa (id_usuario,data_caixa,valor_abertura,status,aberto_por,observacao) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('isdsis', $ownerId, $date, $openingAmount, $status, $operatorId, $note);
        $stmt->execute(); $openingId = (int) $db->insert_id; $stmt->close();

        $type = 'abertura'; $description = 'Saldo inicial da abertura do caixa'; $payment = 'dinheiro';
        $movement = $db->prepare('INSERT INTO caixa_diario (data,valor,tipo,descricao,usuario_id,forma_pagamento,abertura_id,criado_por) VALUES (?,?,?,?,?,?,?,?)');
        $movement->bind_param('sdssisii', $date, $openingAmount, $type, $description, $ownerId, $payment, $openingId, $operatorId);
        $movement->execute(); $movement->close();
        $db->commit();
        return ['id' => $openingId, 'data_caixa' => $date, 'valor_abertura' => $openingAmount, 'status' => 'aberto'];
    } catch (Throwable $e) {
        $db->rollback(); throw $e;
    }
}

function caixa_registrar_venda(mysqli $db, int $ownerId, int $operatorId, int $saleId, float $amount, string $paymentMethod): void
{
    if ($paymentMethod === 'receber') return;
    $session = caixa_buscar_sessao($db, $ownerId, date('Y-m-d'), true);
    if (!$session || $session['status'] !== 'aberto') throw new RuntimeException('Abra o caixa de hoje antes de registrar vendas.');
    $date = date('Y-m-d'); $type = 'venda'; $description = 'Venda PDV #' . $saleId;
    $openingId = (int) $session['id'];
    $stmt = $db->prepare('INSERT INTO caixa_diario (data,valor,tipo,descricao,usuario_id,forma_pagamento,id_venda,abertura_id,criado_por) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param('sdssisiii', $date, $amount, $type, $description, $ownerId, $paymentMethod, $saleId, $openingId, $operatorId);
    $stmt->execute(); $stmt->close();
}

function caixa_resumo(mysqli $db, int $ownerId, string $date): array
{
    $session = caixa_buscar_sessao($db, $ownerId, $date);
    $stmt = $db->prepare("SELECT
        COALESCE(SUM(CASE WHEN tipo='venda' AND forma_pagamento='dinheiro' THEN valor ELSE 0 END),0) total_dinheiro,
        COALESCE(SUM(CASE WHEN tipo='venda' AND forma_pagamento<>'dinheiro' THEN valor ELSE 0 END),0) total_outros,
        COALESCE(SUM(CASE WHEN tipo='manual_entrada' THEN valor WHEN tipo='manual_saida' THEN -valor ELSE 0 END),0) total_manual
        FROM caixa_diario WHERE usuario_id=? AND data=?");
    $stmt->bind_param('is', $ownerId, $date); $stmt->execute(); $totals = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $opening = (float) ($session['valor_abertura'] ?? 0);
    $cashSales = (float) ($totals['total_dinheiro'] ?? 0);
    $manual = (float) ($totals['total_manual'] ?? 0);
    return [
        'sessao' => $session,
        'valor_abertura' => $opening,
        'total_dinheiro' => $cashSales,
        'total_outros' => (float) ($totals['total_outros'] ?? 0),
        'total_manual' => $manual,
        'saldo_dinheiro_esperado' => $opening + $cashSales + $manual,
    ];
}
