<?php

declare(strict_types=1);

/**
 * Workflow financeiro seguro e somente leitura.
 * Analisa vencimentos e produz um resumo acionável sem alterar lançamentos.
 */
function executarWorkflowFinanceiro(mysqli $conn, int $ownerId, int $dias = 3): array
{
    $resultado = [
        'pagar_vencidas_qtd' => 0,
        'pagar_vencidas_total' => 0.0,
        'pagar_proximas_qtd' => 0,
        'pagar_proximas_total' => 0.0,
        'receber_vencidas_qtd' => 0,
        'receber_vencidas_total' => 0.0,
        'receber_proximas_qtd' => 0,
        'receber_proximas_total' => 0.0,
        'saldo_previsto' => 0.0,
        'nivel' => 'ok',
        'titulo' => 'Fluxo financeiro sob controle',
        'mensagem' => 'Nenhuma pendência crítica foi encontrada para os próximos dias.',
        'gerado_em' => (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i'),
    ];

    $sql = "
        SELECT
            COALESCE(SUM(CASE WHEN tipo='pagar' AND data_vencimento < CURDATE() THEN 1 ELSE 0 END),0) pagar_vencidas_qtd,
            COALESCE(SUM(CASE WHEN tipo='pagar' AND data_vencimento < CURDATE() THEN valor ELSE 0 END),0) pagar_vencidas_total,
            COALESCE(SUM(CASE WHEN tipo='pagar' AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN 1 ELSE 0 END),0) pagar_proximas_qtd,
            COALESCE(SUM(CASE WHEN tipo='pagar' AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN valor ELSE 0 END),0) pagar_proximas_total,
            COALESCE(SUM(CASE WHEN tipo='receber' AND data_vencimento < CURDATE() THEN 1 ELSE 0 END),0) receber_vencidas_qtd,
            COALESCE(SUM(CASE WHEN tipo='receber' AND data_vencimento < CURDATE() THEN valor ELSE 0 END),0) receber_vencidas_total,
            COALESCE(SUM(CASE WHEN tipo='receber' AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN 1 ELSE 0 END),0) receber_proximas_qtd,
            COALESCE(SUM(CASE WHEN tipo='receber' AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN valor ELSE 0 END),0) receber_proximas_total
        FROM (
            SELECT 'pagar' tipo, valor, data_vencimento FROM contas_pagar
             WHERE usuario_id=? AND deleted_at IS NULL AND status='pendente'
            UNION ALL
            SELECT 'receber' tipo, valor, data_vencimento FROM contas_receber
             WHERE usuario_id=? AND deleted_at IS NULL AND status='pendente'
        ) movimentos
    ";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iiiiii', $dias, $dias, $dias, $dias, $ownerId, $ownerId);
        $stmt->execute();
        $dados = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        foreach ($dados as $campo => $valor) {
            $resultado[$campo] = str_ends_with($campo, '_qtd') ? (int)$valor : (float)$valor;
        }

        $resultado['saldo_previsto'] = $resultado['receber_proximas_total'] - $resultado['pagar_proximas_total'];

        if ($resultado['pagar_vencidas_qtd'] > 0) {
            $resultado['nivel'] = 'critico';
            $resultado['titulo'] = 'Atenção: existem contas vencidas';
            $resultado['mensagem'] = sprintf(
                '%d conta(s) a pagar estão vencidas, somando R$ %s. Priorize a conferência antes de assumir novos compromissos.',
                $resultado['pagar_vencidas_qtd'],
                number_format($resultado['pagar_vencidas_total'], 2, ',', '.')
            );
        } elseif ($resultado['saldo_previsto'] < 0) {
            $resultado['nivel'] = 'atencao';
            $resultado['titulo'] = 'Previsão de saída maior que a entrada';
            $resultado['mensagem'] = sprintf(
                'Nos próximos %d dias, as saídas previstas superam as entradas em R$ %s. Revise vencimentos e cobranças pendentes.',
                $dias,
                number_format(abs($resultado['saldo_previsto']), 2, ',', '.')
            );
        } elseif ($resultado['receber_vencidas_qtd'] > 0) {
            $resultado['nivel'] = 'atencao';
            $resultado['titulo'] = 'Há recebimentos atrasados para acompanhar';
            $resultado['mensagem'] = sprintf(
                '%d recebimento(s) estão vencidos, totalizando R$ %s. Considere iniciar uma rotina de cobrança.',
                $resultado['receber_vencidas_qtd'],
                number_format($resultado['receber_vencidas_total'], 2, ',', '.')
            );
        }
    } catch (Throwable $e) {
        error_log('Workflow financeiro: ' . $e->getMessage());
        $resultado['nivel'] = 'indisponivel';
        $resultado['titulo'] = 'Resumo automático temporariamente indisponível';
        $resultado['mensagem'] = 'Os demais recursos do painel continuam funcionando normalmente.';
    }

    return $resultado;
}

/**
 * Enriquece o resumo com IA executada no próprio computador via Ollama.
 * Usa cache de sessão e preserva integralmente o fallback determinístico.
 */
function aplicarIaLocalAoWorkflow(array $resultado): array
{
    $resultado['origem_resumo'] = 'automacao_local';
    if (($_ENV['OLLAMA_ENABLED'] ?? 'true') !== 'true' || !function_exists('curl_init')) {
        return $resultado;
    }

    $modelo = $_ENV['OLLAMA_MODEL'] ?? 'gemma3:1b';
    $endpoint = rtrim($_ENV['OLLAMA_URL'] ?? 'http://127.0.0.1:11434', '/');
    $dadosIa = [
        'pagamentos_vencidos' => [$resultado['pagar_vencidas_qtd'], $resultado['pagar_vencidas_total']],
        'pagamentos_proximos_3_dias' => [$resultado['pagar_proximas_qtd'], $resultado['pagar_proximas_total']],
        'recebimentos_atrasados' => [$resultado['receber_vencidas_qtd'], $resultado['receber_vencidas_total']],
        'recebimentos_proximos_3_dias' => [$resultado['receber_proximas_qtd'], $resultado['receber_proximas_total']],
        'saldo_previsto_3_dias' => $resultado['saldo_previsto'],
    ];
    $cacheKey = 'workflow_ia_' . hash('sha256', json_encode($dadosIa));
    $cache = $_SESSION[$cacheKey] ?? null;
    if (is_array($cache) && ($cache['expira'] ?? 0) > time()) {
        $resultado['mensagem'] = $cache['mensagem'];
        $resultado['origem_resumo'] = 'ia_local_cache';
        return $resultado;
    }

    $prompt = "Você é um assistente financeiro prudente. Analise apenas os totais JSON abaixo. "
        . "Escreva em português brasileiro uma recomendação objetiva com no máximo 220 caracteres. "
        . "Não invente dados, não prometa resultados e não mande alterar ou excluir registros. JSON: "
        . json_encode($dadosIa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $payload = json_encode([
        'model' => $modelo,
        'prompt' => $prompt,
        'stream' => false,
        'options' => ['temperature' => 0.2, 'num_predict' => 90],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        $ch = curl_init($endpoint . '/api/generate');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $status !== 200) {
            throw new RuntimeException($erroCurl ?: 'Ollama HTTP ' . $status);
        }
        $json = json_decode($raw, true);
        $mensagem = trim((string)($json['response'] ?? ''));
        $mensagem = preg_replace('/\s+/', ' ', strip_tags($mensagem));
        if ($mensagem === '' || mb_strlen($mensagem) > 500) {
            throw new RuntimeException('Resposta local inválida');
        }
        $resultado['mensagem'] = $mensagem;
        $resultado['origem_resumo'] = 'ia_local';
        $_SESSION[$cacheKey] = ['mensagem' => $mensagem, 'expira' => time() + 900];
    } catch (Throwable $e) {
        error_log('IA local do workflow: ' . $e->getMessage());
    }

    return $resultado;
}
