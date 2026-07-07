<?php
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/lib/InvoicePdfGenerator.php';
require_once __DIR__ . '/pdf_helpers.php';

$boletoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$boletoId) {
    http_response_code(400);
    exit('Boleto invalido.');
}

$pdo = getDb();

$stmt = $pdo->prepare("
    SELECT
        b.*,
        b.data_vencimento AS vencimento,
        b.valor_total AS valor,
        c.identificador_eletronico,
        c.nome,
        c.logradouro,
        c.bairro,
        c.cidade,
        c.uf,
        c.cep,
        c.matricula,
        c.cpf_cnpj,
        c.id_sufixo
    FROM boletos b
    INNER JOIN clientes c ON c.id = b.cliente_id
    WHERE b.id = ?
");
$stmt->execute([$boletoId]);
$boleto = $stmt->fetch();

if (!$boleto) {
    http_response_code(404);
    exit('Boleto nao encontrado.');
}

$stmt = $pdo->prepare("SELECT descricao, valor FROM boleto_itens WHERE boleto_id = ? ORDER BY id");
$stmt->execute([$boletoId]);
$boleto['itens'] = $stmt->fetchAll();
$boleto['id'] = $boleto['identificador_eletronico'];

if ((float)$boleto['valor'] == 0.0 && !empty($boleto['itens'])) {
    $boleto['valor'] = array_sum(array_column($boleto['itens'], 'valor'));
}

$dadosPdf = mapearParaPdf($boleto);
$gerador = new InvoicePdfGenerator();
$pdf = $gerador->gerar($dadosPdf);
$filename = sanitizeFilename($dadosPdf['cliente_nome'] . '_' . $dadosPdf['mes_ano']) . '.pdf';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Disposition: inline; filename="' . $filename . '"');
$pdf->Output($filename, 'I');
