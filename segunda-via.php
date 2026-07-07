<?php
require_once __DIR__ . '/core.php';
$pdo = getDb();
$query = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
$cliente = null;
$boletos = [];

if ($query !== '') {
    // Aceita "1012089" ou "1012089@M" — usa só a parte numérica para a busca
    $identificador = preg_replace('/\D/', '', explode('@', $query)[0]);

    if ($identificador !== '') {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE identificador_eletronico = ?");
        $stmt->execute([$identificador]);
        $cliente = $stmt->fetch();

        if ($cliente) {
            $stmt = $pdo->prepare("SELECT b.*, (SELECT GROUP_CONCAT(CONCAT('<div class=\"item-row\"><span>', descricao, '</span><b>R$ ', REPLACE(valor, '.', ','), '</b></div>') SEPARATOR '') FROM boleto_itens WHERE boleto_id = b.id) as itens_html, (SELECT SUM(valor) FROM boleto_itens WHERE boleto_id = b.id) as soma_itens
                                   FROM boletos b WHERE b.cliente_id = ? AND b.status = 'pendente' ORDER BY b.data_vencimento DESC");
            $stmt->execute([$cliente['id']]);
            $boletos = $stmt->fetchAll();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Lookup</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sn-blue: #102c49;
            --brand-color: #de7838;
            --sn-bg: #f3f4f6;
            --sn-text: #1f2937;
            --sn-border: #d1d5db;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--sn-bg); color: var(--sn-text); padding: 40px 20px; }

        .container { max-width: 600px; margin: 0 auto; }
        .logo-header { text-align: center; margin-bottom: 30px; }
        .logo-header img { max-width: 200px; height: auto; }

        .search-box { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid var(--sn-border); margin-bottom: 25px; }
        .input-group { display: flex; gap: 10px; margin-top: 10px; }
        input[type="text"] { flex: 1; padding: 14px 18px; border-radius: 8px; border: 1px solid var(--sn-border); font-size: 15px; outline: none; }
        input[type="text"]:focus { border-color: var(--brand-color); }
        .btn-search { padding: 0 25px; background: var(--sn-blue); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-search:hover { background: var(--brand-color); }

        .bill-card { background: white; border-radius: 16px; padding: 25px; margin-bottom: 20px; border: 1px solid var(--sn-border); box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: relative; }
        .bill-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--brand-color); border-radius: 16px 0 0 16px; }
        .ref-badge { display: inline-block; background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 4px; margin-bottom: 15px; }
        .grid-main { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .label { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-size: 20px; font-weight: 700; color: var(--sn-blue); }
        .value.orange { color: var(--brand-color); }

        .consumption { background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #f3f4f6; }
        .cons-val { font-size: 18px; font-weight: 700; color: #059669; }
        .details { border-top: 1px solid #f3f4f6; padding-top: 15px; margin-bottom: 20px; }
        .item-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; }
        .btn-copy { width: 100%; padding: 15px; background: var(--sn-blue); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-copy:hover { background: var(--brand-color); }
        .btn-pdf { display: block; width: 100%; padding: 15px; margin-top: 10px; background: white; color: var(--sn-blue); border: 1px solid var(--sn-blue); border-radius: 8px; font-weight: 700; text-align: center; text-decoration: none; transition: 0.2s; }
        .btn-pdf:hover { background: var(--sn-blue); color: white; }

        .empty { text-align: center; padding: 40px; color: #6b7280; font-size: 14px; }
        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #1f2937; color: white; padding: 12px 25px; border-radius: 8px; font-size: 13px; display: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-header"><a href="importador.php"><img src="assets/logo.png" alt="Logo"></a></div>

        <div class="search-box">
            <form method="GET">
                <span class="label">Consulta de Fatura</span>
                <div class="input-group">
                    <input type="text" name="id" value="<?= htmlspecialchars($query) ?>" placeholder="Ex: 1231234 ou 1231234@M" required>
                    <button type="submit" class="btn-search">BUSCAR</button>
                </div>
            </form>
        </div>

        <?php if ($query !== ''): ?>
            <?php if ($cliente): ?>
                <div style="margin-bottom: 20px; padding-left: 5px;">
                    <h2 style="font-size: 18px; color: var(--sn-blue);"><?= $cliente['nome'] ?></h2>
                    <p style="font-size: 13px; color: #6b7280;"><?= $cliente['logradouro'] ?>, <?= $cliente['bairro'] ?></p>
                </div>
                
                <?php foreach ($boletos as $b): ?>
                    <div class="bill-card">
                        <span class="ref-badge">REFERÊNCIA: <?= sprintf("%02d/%d", $b['referencia_mes'], $b['referencia_ano']) ?></span>
                        <div class="grid-main">
                            <div>
                                <span class="label">Vencimento</span>
                                <span class="value"><?= $b['data_vencimento'] ? date('d/m/Y', strtotime($b['data_vencimento'])) : '--/--/----' ?></span>
                            </div>
                            <div style="text-align: right;">
                                <span class="label">Valor Total</span>
                                <?php $valorExibido = $b['valor_total'] > 0 ? $b['valor_total'] : ($b['soma_itens'] ?? 0); ?>
                                <span class="value orange">R$ <?= number_format($valorExibido, 2, ',', '.') ?></span>
                            </div>
                        </div>
                        <div class="consumption">
                            <div>
                                <span class="label">Consumo do Mês</span>
                                <span style="font-size: 12px; color: #6b7280;">Leituras: <?= $b['leitura_anterior'] ?> a <?= $b['leitura_atual'] ?></span>
                            </div>
                            <div class="cons-val"><?= $b['consumo_m3'] ?> m³</div>
                        </div>
                        <div class="details">
                            <span class="label" style="margin-bottom: 8px;">Detalhamento</span>
                            <?= $b['itens_html'] ?>
                        </div>
                        <button class="btn-copy" onclick="copyCode('<?= $b['codigo_barras'] ?>')">COPIAR LINHA DIGITÁVEL</button>
                        <a class="btn-pdf" href="boleto-pdf.php?id=<?= (int)$b['id'] ?>" target="_blank" rel="noopener">VISUALIZAR BOLETO</a>
                    </div>
                <?php endforeach; ?>
                <?php if (!$boletos): ?><div class="empty">Nenhuma fatura pendente.</div><?php endif; ?>
            <?php else: ?>
                <div class="empty">Nenhuma fatura encontrada para o ID "<?= htmlspecialchars($query) ?>".</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div id="toast" class="toast">Código copiado com sucesso!</div>
    <script>
        function copyCode(txt) {
            navigator.clipboard.writeText(txt).then(() => {
                const t = document.getElementById('toast');
                t.style.display = 'block';
                setTimeout(() => t.style.display = 'none', 3000);
            });
        }
    </script>
</body>
</html>
