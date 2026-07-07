<?php

if (!function_exists('sanitizeFilename')) {
function sanitizeFilename(string $nome): string {
    if (function_exists('iconv')) {
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        if ($convertido !== false) {
            $nome = $convertido;
        }
    }

    $nome = preg_replace('/[^a-zA-Z0-9]+/', '_', $nome);
    return trim(strtolower($nome), '_') ?: 'boleto';
}
}

if (!function_exists('mapearParaPdf')) {
function mapearParaPdf(array $data): array {
    $venc = '';
    if (!empty($data['vencimento']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data['vencimento'], $m)) {
        $venc = "{$m[3]}/{$m[2]}/{$m[1]}";
    }

    $itens = [];
    foreach (($data['itens'] ?? []) as $it) {
        $itens[] = [
            'descricao' => $it['desc'] ?? $it['descricao'] ?? '',
            'valor' => (float)($it['valor'] ?? 0),
        ];
    }

    $endereco = trim(($data['logradouro'] ?? '') . (!empty($data['bairro']) ? ' - ' . $data['bairro'] : ''));
    $cepCidade = trim(($data['cep'] ?? '') . ' - ' . ($data['cidade'] ?? '') . ' - ' . ($data['uf'] ?? ''), ' -');

    $idEletronico = (string)($data['id'] ?? $data['identificador_eletronico'] ?? '');
    if (!empty($data['id_sufixo']) && strpos($idEletronico, '@') === false) {
        $idEletronico .= '@' . $data['id_sufixo'];
    }

    return [
        'numero_guia'        => ($data['matricula'] ?? '') ?: ($data['id'] ?? $data['identificador_eletronico'] ?? ''),
        'mes_ano'            => sprintf('%02d/%04d', (int)($data['referencia_mes'] ?? 0), (int)($data['referencia_ano'] ?? 0)),
        'cliente_nome'       => $data['nome'] ?? '',
        'cliente_endereco'   => $endereco,
        'cliente_cep_cidade' => $cepCidade,
        'id_eletronico'      => $idEletronico,
        'categoria'          => ['residencial' => 0, 'comercial' => 0, 'industrial' => 0, 'publica' => 0],
        'descricao_itens'    => $itens,
        'data_leitura'       => $data['data_leitura'] ?? '',
        'vencimento'         => $venc,
        'valor_a_pagar'      => (float)($data['valor'] ?? $data['valor_total'] ?? 0),
        'leitura_anterior'   => (int)($data['leitura_anterior'] ?? 0),
        'leitura_atual'      => (int)($data['leitura_atual'] ?? 0),
        'consumo_faturado'   => (int)($data['consumo_m3'] ?? 0),
        'consumo_medio'      => 0,
        'numero_hidrometro'  => $data['numero_hidrometro'] ?? '',
        'historico'          => [],
        'mensagem'           => !empty($data['mensagem']) ? $data['mensagem'] : 'SEM OCORRENCIA',
        'informativo_debito' => '',
        'linha_digitavel'    => $data['linha_digitavel'] ?? '',
        'codigo_barras'      => $data['codigo_barras'] ?? '',
    ];
}
}
