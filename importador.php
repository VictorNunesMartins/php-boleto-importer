<?php
session_start();
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/lib/InvoicePdfGenerator.php';
require_once __DIR__ . '/pdf_helpers.php';
$pdo = getDb();
$relatorio = null;
$pdfUrl = null;

if (!function_exists('sanitizeFilename')) {
function sanitizeFilename(string $nome): string {
    if (function_exists('iconv')) {
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        if ($convertido !== false) $nome = $convertido;
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
        $itens[] = ['descricao' => $it['desc'] ?? '', 'valor' => (float)($it['valor'] ?? 0)];
    }

    $endereco = trim(($data['logradouro'] ?? '') . (!empty($data['bairro']) ? ' - ' . $data['bairro'] : ''));
    $cepCidade = trim(($data['cep'] ?? '') . ' - ' . ($data['cidade'] ?? '') . ' - ' . ($data['uf'] ?? ''), ' -');

    $idEletronico = ($data['id'] ?? '') . '@' . ($data['id_sufixo'] ?? 'A');

    return [
        'numero_guia'        => ($data['matricula'] ?? '') ?: ($data['id'] ?? ''),
        'mes_ano'            => sprintf('%02d/%04d', (int)($data['referencia_mes'] ?? 0), (int)($data['referencia_ano'] ?? 0)),
        'cliente_nome'       => $data['nome'] ?? '',
        'cliente_endereco'   => $endereco,
        'cliente_cep_cidade' => $cepCidade,
        'id_eletronico'      => $idEletronico,
        'categoria'          => ['residencial' => 0, 'comercial' => 0, 'industrial' => 0, 'publica' => 0],
        'descricao_itens'    => $itens,
        'data_leitura'       => $data['data_leitura'] ?? '',
        'vencimento'         => $venc,
        'valor_a_pagar'      => (float)($data['valor'] ?? 0),
        'leitura_anterior'   => (int)($data['leitura_anterior'] ?? 0),
        'leitura_atual'      => (int)($data['leitura_atual'] ?? 0),
        'consumo_faturado'   => (int)($data['consumo_m3'] ?? 0),
        'consumo_medio'      => 0,
        'numero_hidrometro'  => $data['numero_hidrometro'] ?? '',
        'historico'          => [],
        'mensagem'           => $data['mensagem'] ?: 'SEM OCORRÊNCIA',
        'informativo_debito' => '',
        'linha_digitavel'    => $data['linha_digitavel'] ?? '',
        'codigo_barras'      => $data['codigo_barras'] ?? '',
    ];
}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivos'])) {
    $stats = ['total' => 0, 'sucesso' => 0, 'erros' => 0, 'boletos' => 0, 'criados' => 0, 'detalhes' => []];
    $contasPdf = [];

    foreach ($_FILES['arquivos']['tmp_name'] as $i => $tmp) {
        if (empty($tmp)) continue;
        $stats['total']++;
        $name = $_FILES['arquivos']['name'][$i];
        $boletosLidos = BillingFileParser::parseFile(file_get_contents($tmp), $name);

        if (empty($boletosLidos)) {
            $stats['detalhes'][] = "Arquivo \"{$name}\": nenhum boleto válido encontrado (formato não reconhecido).";
            $stats['erros']++;
            continue;
        }

        foreach ($boletosLidos as $data) {
            try {
                $pdo->beginTransaction();

                // 1) Localiza ou cria cliente
                // Compara após remover zeros à esquerda dos dois lados — evita falsos positivos com LIKE
                $stmt = $pdo->prepare("SELECT id FROM clientes WHERE TRIM(LEADING '0' FROM identificador_eletronico) = ?");
                $stmt->execute([$data['id']]);
                $cli = $stmt->fetch();

                if ($cli) {
                    $cliId = $cli['id'];
                    $pdo->prepare("UPDATE clientes SET nome=?, logradouro=?, bairro=?, cidade=?, uf=?, cep=?, matricula=?, cpf_cnpj=?, id_sufixo=? WHERE id=?")
                        ->execute([$data['nome'], $data['logradouro'], $data['bairro'], $data['cidade'], $data['uf'], $data['cep'], $data['matricula'], $data['cpf_cnpj'], $data['id_sufixo'] ?? null, $cliId]);
                } else {
                    $pdo->prepare("INSERT INTO clientes (identificador_eletronico, nome, logradouro, bairro, cidade, uf, cep, matricula, cpf_cnpj, id_sufixo) VALUES (?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$data['id'], $data['nome'], $data['logradouro'], $data['bairro'], $data['cidade'], $data['uf'], $data['cep'], $data['matricula'], $data['cpf_cnpj'], $data['id_sufixo'] ?? null]);
                    $cliId = (int)$pdo->lastInsertId();
                }

                // 2) Localiza ou cria boleto da referência
                $check = $pdo->prepare("SELECT id FROM boletos WHERE cliente_id = ? AND referencia_mes = ? AND referencia_ano = ?");
                $check->execute([$cliId, $data['referencia_mes'], $data['referencia_ano']]);
                $bol = $check->fetch();

                if ($bol) {
                    $bolId = $bol['id'];
                    $pdo->prepare("UPDATE boletos SET data_vencimento=?, valor_total=?, leitura_anterior=?, leitura_atual=?, consumo_m3=?, codigo_barras=?, linha_digitavel=?, arquivo_origem=? WHERE id=?")
                        ->execute([$data['vencimento'], $data['valor'], $data['leitura_anterior'], $data['leitura_atual'], $data['consumo_m3'], $data['codigo_barras'], $data['linha_digitavel'], $name, $bolId]);
                    $stats['boletos']++;
                } else {
                    $pdo->prepare("INSERT INTO boletos (cliente_id, referencia_mes, referencia_ano, data_vencimento, valor_total, leitura_anterior, leitura_atual, consumo_m3, codigo_barras, linha_digitavel, arquivo_origem) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$cliId, $data['referencia_mes'], $data['referencia_ano'], $data['vencimento'], $data['valor'], $data['leitura_anterior'], $data['leitura_atual'], $data['consumo_m3'], $data['codigo_barras'], $data['linha_digitavel'], $name]);
                    $bolId = (int)$pdo->lastInsertId();
                    $stats['criados']++;
                }

                // 3) Itens (limpa e reinsere)
                $pdo->prepare("DELETE FROM boleto_itens WHERE boleto_id = ?")->execute([$bolId]);
                foreach ($data['itens'] as $item) {
                    $pdo->prepare("INSERT INTO boleto_itens (boleto_id, descricao, valor) VALUES (?,?,?)")
                        ->execute([$bolId, $item['desc'], $item['valor']]);
                }

                $contasPdf[] = mapearParaPdf($data);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $stats['detalhes'][] = $e->getMessage();
                $stats['erros']++;
            }
        }
        $stats['sucesso']++;
    }

    if (!empty($contasPdf)) {
        try {
            if (count($contasPdf) === 1) {
                $conta = $contasPdf[0];
                $gerador = new InvoicePdfGenerator();
                $pdf = $gerador->gerar($conta);
                $tmpFinal = tempnam(sys_get_temp_dir(), 'inv_');
                $pdf->Output($tmpFinal, 'F');
                $downloadName = sanitizeFilename($conta['cliente_nome']) . '.pdf';
                $mime = 'application/pdf';
            } else {
                $tmpPdfs = [];
                $usados = [];
                foreach ($contasPdf as $conta) {
                    $gerador = new InvoicePdfGenerator();
                    $pdf = $gerador->gerar($conta);
                    $tmpPdf = tempnam(sys_get_temp_dir(), 'inv_');
                    $pdf->Output($tmpPdf, 'F');

                    $base = sanitizeFilename($conta['cliente_nome']);
                    $nomeArq = $base . '.pdf';
                    $idx = 2;
                    while (isset($usados[$nomeArq])) {
                        $nomeArq = $base . '_' . $idx++ . '.pdf';
                    }
                    $usados[$nomeArq] = true;
                    $tmpPdfs[$nomeArq] = $tmpPdf;
                }

                $tmpFinal = tempnam(sys_get_temp_dir(), 'inv_zip_');
                $zip = new ZipArchive();
                if ($zip->open($tmpFinal, ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException('Falha ao criar o ZIP.');
                }
                foreach ($tmpPdfs as $nomeArq => $caminho) {
                    $zip->addFile($caminho, $nomeArq);
                }
                $zip->close();
                foreach ($tmpPdfs as $caminho) {
                    @unlink($caminho);
                }
                $downloadName = 'boletos_' . date('Ymd_His') . '.zip';
                $mime = 'application/zip';
            }

            $token = bin2hex(random_bytes(16));
            $_SESSION['downloads'][$token] = ['path' => $tmpFinal, 'name' => $downloadName, 'mime' => $mime];
            $pdfUrl = 'download.php?token=' . $token;
        } catch (Throwable $e) {
            $stats['detalhes'][] = 'Erro ao gerar PDF: ' . $e->getMessage();
        }
    }

    $relatorio = $stats;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boleto Batch Importer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --sn-blue: #102c49; --brand-color: #de7838; --sn-bg: #f3f4f6; --sn-text: #1f2937; --sn-border: #d1d5db; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--sn-bg); color: var(--sn-text); line-height: 1.5; padding-bottom: 50px; }
        .container { max-width: 800px; margin: 80px auto; padding: 0 20px; }
        .logo-center { text-align: center; margin-bottom: 40px; }
        .logo-center img { max-width: 250px; height: auto; }
        .logo-center p { color: var(--brand-color); font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 10px; }
        .card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border: 1px solid var(--sn-border); position: relative; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--brand-color); border-radius: 20px 20px 0 0; }
        .upload-area { border: 2px dashed var(--sn-border); border-radius: 12px; padding: 60px 20px; text-align: center; cursor: pointer; transition: 0.2s; background: #fafafa; position: relative; }
        .upload-area:hover { border-color: var(--brand-color); background: #fff7ed; }
        .upload-area input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .upload-icon { font-size: 40px; margin-bottom: 15px; display: block; }
        .btn { display: block; width: 100%; padding: 18px; background: var(--sn-blue); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 25px; text-decoration: none; text-align: center; }
        .btn:hover { background: var(--brand-color); transform: translateY(-1px); }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 30px; }
        .stat-item { background: #f9fafb; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid var(--sn-border); }
        .stat-val { font-size: 32px; font-weight: 800; color: var(--sn-blue); display: block; }
        .stat-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; }
        .error-list { margin-top: 25px; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 20px; text-align: left; max-height: 300px; overflow-y: auto; }
        .error-item { font-size: 13px; color: #9a3412; margin-bottom: 6px; }
        .nav-footer { margin-top: 28px; }
        .second-copy { display: block; color: #6b7280; font-size: 13px; font-weight: 600; margin-top: 2px; }
        .second-link { display: flex; align-items: center; justify-content: space-between; gap: 18px; background: #fff; border: 1px solid rgba(16, 44, 73, 0.14); border-left: 5px solid var(--brand-color); border-radius: 14px; box-shadow: 0 8px 20px rgba(16, 44, 73, 0.09); color: var(--sn-blue); padding: 18px 20px; text-decoration: none; transition: 0.2s; }
        .second-link:hover { border-color: var(--brand-color); box-shadow: 0 12px 26px rgba(16, 44, 73, 0.14); transform: translateY(-1px); }
        .second-label { display: block; font-size: 16px; font-weight: 800; }
        .second-arrow { align-items: center; background: var(--sn-blue); border-radius: 999px; color: white; display: inline-flex; flex: 0 0 36px; font-size: 20px; font-weight: 800; height: 36px; justify-content: center; line-height: 1; width: 36px; }
        @media (max-width: 560px) {
            .second-link { padding: 16px; }
            .second-label { font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-center"><a href="importador.php"><img src="assets/logo.png" alt="Logo"></a><p>CONVERSOR DE BOLETOS INTELIGENTE</p></div>
        <div class="card">
            <?php if ($relatorio): ?>
                <div style="text-align: center;">
                    <h2 style="font-size: 22px; margin-bottom: 10px; color: var(--sn-blue);">Processamento Concluído</h2>
                    <div class="stats-grid">
                        <div class="stat-item"><span class="stat-val"><?= $relatorio['total'] ?></span><span class="stat-label">Arquivos</span></div>
                        <div class="stat-item"><span class="stat-val" style="color: var(--brand-color);"><?= $relatorio['boletos'] ?></span><span class="stat-label">Atualizados</span></div>
                        <div class="stat-item"><span class="stat-val" style="color: #059669;"><?= $relatorio['criados'] ?></span><span class="stat-label">Criados</span></div>
                        <div class="stat-item"><span class="stat-val" style="color: #ef4444;"><?= $relatorio['erros'] ?></span><span class="stat-label">Erros</span></div>
                    </div>
                    <?php if (!empty($relatorio['detalhes'])): ?>
                        <div class="error-list">
                            <p style="font-size: 12px; font-weight: 700; margin-bottom: 10px; color: #9a3412;">LOG DE OCORRÊNCIAS:</p>
                            <?php foreach ($relatorio['detalhes'] as $err): ?><div class="error-item">❌ <?= htmlspecialchars($err) ?></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <a href="importador.php" class="btn">Nova Importação</a>
                </div>
            <?php else: ?>
                <form action="importador.php" method="POST" enctype="multipart/form-data">
                    <div class="upload-area"><input type="file" name="arquivos[]" multiple required onchange="this.form.submit()"><span class="upload-icon">📄</span><p style="font-weight: 600; color: var(--sn-blue);">Arraste ou selecione os arquivos para atualizar os boletos</p></div>
                </form>
            <?php endif; ?>
        </div>
        <div class="nav-footer">
            <a class="second-link" href="segunda-via.php">
                <span>
                    <span class="second-label">Acessar Segunda Via do Cliente</span>
                    <span class="second-copy">Consultar faturas pendentes por ID</span>
                </span>
                <span class="second-arrow" aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </div>
    <?php if ($pdfUrl): ?>
    <script>
        window.addEventListener('load', function () {
            var a = document.createElement('a');
            a.href = '<?= htmlspecialchars($pdfUrl, ENT_QUOTES) ?>';
            a.download = '';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });
    </script>
    <?php endif; ?>
</body>
</html>
