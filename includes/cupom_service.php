<?php

function buscarTenantCupom(mysqli $conn, string $tenantUuid): ?array {
    $stmt = $conn->prepare('SELECT id, usuarios_extras FROM tenants WHERE tenant_id = ? LIMIT 1');
    $stmt->bind_param('s', $tenantUuid);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function validarCupomPassivo(mysqli $conn, string $codigo, int $tenantId): array {
    $hoje = date('Y-m-d');
    $stmt = $conn->prepare("SELECT c.*,
        (SELECT COUNT(*) FROM cupom_utilizacoes u WHERE u.cupom_id=c.id AND u.status IN ('pendente','aplicado')) AS usos
        FROM cupons_desconto c
        WHERE c.codigo=? AND c.modo_uso='passivo' AND c.ativo=1
          AND (c.data_expiracao IS NULL OR c.data_expiracao>=?) LIMIT 1");
    $stmt->bind_param('ss', $codigo, $hoje);
    $stmt->execute();
    $cupom = $stmt->get_result()->fetch_assoc();
    if (!$cupom) return ['ok' => false, 'msg' => 'Cupom inválido, expirado ou de uso interno.'];
    if ($cupom['limite_usos'] !== null && (int)$cupom['usos'] >= (int)$cupom['limite_usos']) {
        return ['ok' => false, 'msg' => 'O limite de usos deste cupom foi atingido.'];
    }
    if ((int)$cupom['uso_unico_tenant'] === 1) {
        $stmt = $conn->prepare("SELECT id FROM cupom_utilizacoes WHERE cupom_id=? AND tenant_id=? AND status IN ('pendente','aplicado') LIMIT 1");
        $stmt->bind_param('ii', $cupom['id'], $tenantId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows) return ['ok' => false, 'msg' => 'Este cupom já foi utilizado por sua conta.'];
    }
    return ['ok' => true, 'cupom' => $cupom];
}

function buscarPromocaoInterna(mysqli $conn, int $tenantId): ?array {
    $stmt = $conn->prepare("SELECT c.*, tp.id AS promocao_id, tp.data_fim
        FROM tenant_promocoes tp JOIN cupons_desconto c ON c.id=tp.cupom_id
        WHERE tp.tenant_id=? AND c.modo_uso='interno' AND c.ativo=1 AND tp.ativo=1
          AND tp.data_inicio<=CURDATE() AND tp.data_fim>=CURDATE()
        ORDER BY tp.id DESC LIMIT 1");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function calcularDescontoCupom(float $plano, float $extras, array $cupom): array {
    $baseDesconto = (int)$cupom['aplicar_extras'] === 1 ? $plano + $extras : $plano;
    $desconto = $cupom['tipo_desconto'] === 'porcentagem'
        ? $baseDesconto * min(100, max(0, (float)$cupom['valor'])) / 100
        : max(0, (float)$cupom['valor']);
    $final = (int)$cupom['aplicar_extras'] === 1
        ? ($plano + $extras - $desconto)
        : ($plano - $desconto + $extras);
    return ['original' => round($plano + $extras, 2), 'final' => round(max(0, $final), 2), 'desconto' => round($desconto, 2)];
}
