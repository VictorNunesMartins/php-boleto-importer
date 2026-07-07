<?php

require_once __DIR__ . '/../tcpdf/tcpdf.php';

class InvoicePdfGenerator
{
    // Cores
    const COR_VERDE      = [26,  122,  74];  // headers verdes
    const COR_AZUL       = [26,   82, 118];  // labels azuis
    const COR_BRANCO     = [255, 255, 255];
    const COR_CINZA_CLARO = [240, 244, 248]; // fundo linhas alternadas
    const COR_BORDA      = [180, 200, 210];  // bordas suaves

    // Dados fixos da empresa
    const EMPRESA = [
        'razao'   => 'AQUAFLOW WATER UTILITY LTD.',
        'end1'    => 'Av. Central, nº 100 - Distrito Modelo',
        'end2'    => 'Santa Rita - SP - CEP: 12.345-000',
        'cnpj'    => 'CNPJ 00.000.000/0001-00',
        'fone'    => 'Fone: 11 4000 0000',
        'site'    => 'www.aquaflow.example',
    ];

    private TCPDF $pdf;
    private array $dados;

    public function gerar(array $dados): TCPDF
    {
        return $this->gerarMultiplas([$dados]);
    }

    public function gerarMultiplas(array $contas): TCPDF
    {
        $primeiraConta = $contas[0] ?? [];

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('AquaFlow');
        $this->pdf->SetAuthor('AquaFlow Water Utility');
        $this->pdf->SetTitle('Conta de Água e Esgoto - ' . ($primeiraConta['numero_guia'] ?? ''));
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(8, 8, 8);
        $this->pdf->SetAutoPageBreak(false);

        foreach ($contas as $dados) {
            $this->dados = $dados;
            $this->pdf->AddPage();
            $this->renderizarCorpo();
            $this->renderizarSeparador();
            $this->renderizarCanhoto();
        }

        return $this->pdf;
    }

    // ─────────────────────────────────────────────
    // CORPO PRINCIPAL
    // ─────────────────────────────────────────────

    private function renderizarCorpo(): void
    {
        $this->blocoCabecalho(8);
        $this->blocoCliente(38);
        $this->blocoItens(78);
        $this->blocoLeituraVencimento(138);
        $this->blocoMedicoes(150);
        $this->blocoHistoricoMensagem(162);
        $this->blocoRodapeCorpo(207);
    }

    private function blocoCabecalho(float $y): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::COR_BORDA);
        $pdf->SetLineWidth(0.3);

        // Caixa geral do cabeçalho
        $pdf->Rect(8, $y, 194, 29, 'D');

        // Divisões internas verticais
        $pdf->Line(50, $y, 50, $y + 29);
        $pdf->Line(130, $y, 130, $y + 29);
        $pdf->Line(158, $y, 158, $y + 29);

        // Coluna 1 — NOTA FISCAL / FATURA DE SERVIÇOS
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(9, $y + 3);
        $pdf->MultiCell(40, 4, "NOTA FISCAL /\nFATURA DE SERVIÇOS", 0, 'L');
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetXY(9, $y + 13);
        $pdf->MultiCell(40, 3.5, "Saneamento eficiente\né aquele que fazemos juntos", 0, 'L');

        // Coluna 2 — dados da empresa
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetXY(52, $y + 2);
        $pdf->Cell(77, 4, self::EMPRESA['razao'], 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 6.5);
        foreach ([self::EMPRESA['end1'], self::EMPRESA['end2'], self::EMPRESA['cnpj'], self::EMPRESA['fone'], self::EMPRESA['site']] as $linha) {
            $pdf->SetX(52);
            $pdf->Cell(77, 3.2, $linha, 0, 1, 'C');
        }

        // Coluna 3 — NÚMERO GUIA
        $pdf->SetFont('helvetica', 'B', 6.5);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(131, $y + 2);
        $pdf->Cell(26, 4, 'NÚMERO GUIA', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetX(131);
        $pdf->Cell(26, 4, $this->dados['numero_guia'] ?? '', 0, 0, 'C');

        // Coluna 4 — Logo (texto fallback)
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(159, $y + 6);
        $pdf->Cell(42, 6, 'AQUAFLOW', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetX(159);
        $pdf->Cell(42, 4, 'SANEAMENTO', 0, 0, 'C');

        $pdf->SetTextColor(0, 0, 0);
    }

    private function blocoCliente(float $y): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::COR_BORDA);

        // Caixa esquerda — dados do cliente
        $pdf->Rect(8, $y, 130, 38, 'D');

        // Caixa direita superior — MÊS/ANO
        $pdf->Rect(138, $y, 64, 10, 'D');

        // Header MÊS/ANO
        $pdf->SetFillColor(...self::COR_AZUL);
        $pdf->Rect(138, $y, 64, 5, 'F');
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(...self::COR_BRANCO);
        $pdf->SetXY(138, $y + 0.5);
        $pdf->Cell(64, 4, 'MÊS / ANO', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(138, $y + 5);
        $pdf->Cell(64, 4.5, $this->dados['mes_ano'] ?? '', 0, 0, 'C');

        // Caixa direita — CATEGORIA/QUANTIDADE
        $pdf->Rect(138, $y + 10, 64, 28, 'D');

        $pdf->SetFillColor(...self::COR_VERDE);
        $pdf->Rect(138, $y + 10, 64, 6, 'F');
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(...self::COR_BRANCO);
        $pdf->SetXY(138, $y + 11);
        $pdf->Cell(64, 4, 'CATEGORIA / QUANTIDADE', 0, 0, 'C');

        // Sub-headers
        $pdf->SetFillColor(...self::COR_AZUL);
        $pdf->Rect(138, $y + 16, 64, 6, 'F');
        $largCat = 16;
        $labels  = ['RESIDENCIAL', 'COMERCIAL', 'INDUSTRIAL', 'PÚBLICA'];
        $pdf->SetFont('helvetica', 'B', 5.5);
        $pdf->SetTextColor(...self::COR_BRANCO);
        foreach ($labels as $i => $label) {
            $pdf->SetXY(138 + $i * $largCat, $y + 17);
            $pdf->Cell($largCat, 4, $label, 0, 0, 'C');
        }

        // Valores categoria
        $cat    = $this->dados['categoria'] ?? [];
        $vals   = [
            $cat['residencial'] ?? 0,
            $cat['comercial']   ?? 0,
            $cat['industrial']  ?? 0,
            $cat['publica']     ?? 0,
        ];
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        foreach ($vals as $i => $v) {
            $pdf->SetXY(138 + $i * $largCat, $y + 23);
            $pdf->Cell($largCat, 6, (string)$v, 0, 0, 'C');
        }

        // Dados do cliente
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(10, $y + 2);
        $pdf->MultiCell(126, 5, $this->dados['cliente_nome'] ?? '', 0, 'L');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(10, $y + 9);
        $pdf->MultiCell(126, 4.5, ($this->dados['cliente_endereco'] ?? '') . ' - ' . ($this->dados['cliente_cep_cidade'] ?? ''), 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $linhas = [];
        if (!empty($this->dados['id_eletronico'])) {
            $linhas[] = 'ID. Eletrônico: ' . $this->dados['id_eletronico'];
        }
        if (!empty($this->dados['codigo_debito_automatico'])) {
            $linhas[] = 'Código Identificador para Débito Automático: ' . $this->dados['codigo_debito_automatico'];
        }
        if (!empty($this->dados['mapa_cad'])) {
            $linhas[] = 'Mapa Cad: ' . $this->dados['mapa_cad'];
        }
        $yLinha = $y + 18;
        foreach ($linhas as $l) {
            $pdf->SetXY(10, $yLinha);
            $pdf->Cell(126, 4.5, $l, 0, 0, 'L');
            $yLinha += 5;
        }
    }

    private function blocoItens(float $y): void
    {
        $pdf = $this->pdf;
        $altura = 58;

        // Borda externa
        $pdf->SetDrawColor(...self::COR_BORDA);
        $pdf->Rect(8, $y, 194, $altura, 'D');

        // Header verde
        $pdf->SetFillColor(...self::COR_VERDE);
        $pdf->Rect(8, $y, 194, 7, 'F');
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetTextColor(...self::COR_BRANCO);
        $pdf->SetXY(8, $y + 1);
        $pdf->Cell(150, 5, 'DESCRIÇÃO', 0, 0, 'C');
        $pdf->Cell(44, 5, 'VALOR', 0, 0, 'C');

        // Linhas de itens
        $itens  = $this->dados['descricao_itens'] ?? [];
        $yItem  = $y + 7;
        $altLinha = 7.5;

        foreach ($itens as $i => $item) {
            if ($i % 2 === 0) {
                $pdf->SetFillColor(...self::COR_CINZA_CLARO);
                $pdf->Rect(8, $yItem, 194, $altLinha, 'F');
            }

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(12, $yItem + 1.5);
            $pdf->Cell(146, 5, $item['descricao'] ?? '', 0, 0, 'L');

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY(158, $yItem + 1.5);
            $pdf->Cell(40, 5, number_format($item['valor'] ?? 0, 2, ',', '.'), 0, 0, 'R');

            $yItem += $altLinha;
        }

        // Linha divisória vertical Descrição/Valor
        $pdf->SetDrawColor(...self::COR_BORDA);
        $pdf->Line(155, $y, 155, $y + $altura);
    }

    private function blocoLeituraVencimento(float $y): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::COR_BORDA);
        $pdf->Rect(8, $y, 194, 11, 'D');

        // Divisões
        $pdf->Line(72, $y, 72, $y + 11);
        $pdf->Line(136, $y, 136, $y + 11);

        // Labels
        $pdf->SetFillColor(...self::COR_AZUL);
        $pdf->Rect(8,   $y, 64, 5, 'F');
        $pdf->Rect(72,  $y, 64, 5, 'F');
        $pdf->Rect(136, $y, 66, 5, 'F');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(...self::COR_BRANCO);
        foreach ([
            [9,   'DATA DA LEITURA', 62],
            [73,  'VENCIMENTO',      62],
            [137, 'VALOR A PAGAR',   64],
        ] as [$x, $txt, $w]) {
            $pdf->SetXY($x, $y + 0.5);
            $pdf->Cell($w, 4, $txt, 0, 0, 'L');
        }

        // Valores
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetXY(9, $y + 6);
        $pdf->Cell(62, 4, $this->dados['data_leitura'] ?? '', 0, 0, 'L');

        $pdf->SetXY(73, $y + 6);
        $pdf->Cell(62, 4, $this->dados['vencimento'] ?? '', 0, 0, 'L');

        // Valor a pagar em destaque
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(...self::COR_VERDE);
        $pdf->SetXY(137, $y + 5.5);
        $pdf->Cell(64, 5, 'R$ ' . number_format($this->dados['valor_a_pagar'] ?? 0, 2, ',', '.'), 0, 0, 'L');
        $pdf->SetTextColor(0, 0, 0);
    }

    private function blocoMedicoes(float $y): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::COR_BORDA);
        $pdf->Rect(8, $y, 194, 11, 'D');

        // 5 colunas
        $larguras = [38, 38, 38, 38, 42];
        $xPos     = 8;
        $campos   = ['LEITURA ANTERIOR', 'LEITURA ATUAL', 'CONSUMO FATURADO', 'CONSUMO MÉDIO', 'Nº DO HIDRÔMETRO'];
        $valores  = [
            ($this->dados['leitura_anterior'] ?? '') . ' m³',
            ($this->dados['leitura_atual']    ?? '') . ' m³',
            ($this->dados['consumo_faturado'] ?? '') . ' m³',
            ($this->dados['consumo_medio']    ?? '') . ' m³',
            $this->dados['numero_hidrometro'] ?? '',
        ];

        foreach ($larguras as $i => $larg) {
            $pdf->SetFillColor(...self::COR_AZUL);
            $pdf->Rect($xPos, $y, $larg, 5, 'F');
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetTextColor(...self::COR_BRANCO);
            $pdf->SetXY($xPos + 1, $y + 0.5);
            $pdf->Cell($larg - 2, 4, $campos[$i], 0, 0, 'L');

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($xPos + 1, $y + 6);
            $pdf->Cell($larg - 2, 4, $valores[$i], 0, 0, 'L');

            if ($i < count($larguras) - 1) {
                $pdf->SetDrawColor(...self::COR_BORDA);
                $pdf->Line($xPos + $larg, $y, $xPos + $larg, $y + 11);
            }
            $xPos += $larg;
        }
    }

    private function blocoHistoricoMensagem(float $y): void
    {
        $pdf    = $this->pdf;
        $altura = 44;

        $pdf->SetDrawColor(...self::COR_BORDA);

        // Caixa esquerda — DEMONSTRATIVO
        $pdf->Rect(8, $y, 90, $altura, 'D');

        // Caixa direita — MENSAGEM + INFORMATIVO
        $pdf->Rect(98, $y, 104, $altura, 'D');

        // Header demonstrativo
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(9, $y + 1.5);
        $pdf->Cell(88, 4, 'DEMONSTRATIVO DE CONSUMO', 0, 0, 'C');

        // Sub-header colunas
        $pdf->SetFont('helvetica', 'B', 6.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(9, $y + 6);
        $pdf->Cell(28, 4, 'MÊS/ANO', 0, 0, 'L');
        $pdf->Cell(32, 4, 'VOLUME FATURA M³', 0, 0, 'C');
        $pdf->Cell(28, 4, 'DIAS ENTRE MEDIÇÕES', 0, 0, 'C');

        // Linhas do histórico: o quadro comporta 6 linhas sem invadir o rodapé.
        $historico = array_slice($this->dados['historico'] ?? [], 0, 6);
        $yHist     = $y + 11;
        foreach ($historico as $i => $h) {
            if ($i % 2 === 0) {
                $pdf->SetFillColor(...self::COR_CINZA_CLARO);
                $pdf->Rect(8, $yHist, 90, 5, 'F');
            }
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(9, $yHist + 0.5);
            $pdf->Cell(28, 4, $h['mes_ano']  ?? '', 0, 0, 'L');
            $pdf->Cell(32, 4, (string)($h['volume'] ?? ''), 0, 0, 'C');
            $pdf->Cell(28, 4, (string)($h['dias']   ?? ''), 0, 0, 'C');
            $yHist += 5;
        }

        // Lado direito: MENSAGEM
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(100, $y + 1.5);
        $pdf->Cell(100, 4, 'MENSAGEM', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(100, $y + 7);
        $pdf->MultiCell(100, 4, $this->dados['mensagem'] ?? '', 0, 'L');

        // Linha separando mensagem/informativo
        $pdf->SetDrawColor(...self::COR_BORDA);
        $pdf->Line(98, $y + 25, 202, $y + 25);

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(100, $y + 26);
        $pdf->Cell(100, 4, 'INFORMATIVO DE DÉBITO', 0, 0, 'L');

        if (!empty($this->dados['informativo_debito'])) {
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(100, $y + 32);
            $pdf->MultiCell(100, 4, $this->dados['informativo_debito'], 0, 'L');
        }
    }

    private function blocoRodapeCorpo(float $y): void
    {
        $pdf = $this->pdf;
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetXY(8, $y);
        $pdf->Cell(194, 4, '- Conforme Lei nº 14.026/2020, evite a suspensão no fornecimento dos serviços pagando sua conta de consumo até a data do vencimento.', 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 6.2);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(8, $y + 4.2);
        $pdf->Cell(95, 3.5, 'FAVOR AUTENTICAR NO VERSO - DEVOLVER AO USUÁRIO', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 5.2);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetXY(105, $y + 4);
        $pdf->MultiCell(97, 2.6, 'APÓS O VENCIMENTO, COBRANÇA DE MULTA DE 2%, JUROS DE MORA E ATUALIZAÇÃO MONETÁRIA PELO IGP-M, NA PRÓXIMA CONTA', 0, 'R');
    }

    // ─────────────────────────────────────────────
    // SEPARADOR
    // ─────────────────────────────────────────────

    private function renderizarSeparador(): void
    {
        $pdf = $this->pdf;
        $y   = 216;

        $pdf->SetDrawColor(150, 150, 150);
        $pdf->SetLineStyle(['dash' => '3,2']);
        $pdf->Line(8, $y, 202, $y);
        $pdf->SetLineStyle(['dash' => 0]);

        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY(8, $y + 1);
        $pdf->Cell(194, 3, 'Corte na linha pontilhada', 0, 0, 'C');
    }

    // ─────────────────────────────────────────────
    // CANHOTO
    // ─────────────────────────────────────────────

    private function renderizarCanhoto(): void
    {
        $y = 221;

        $this->canhotoCabecalho($y);
        $this->canhotoDadosCliente($y + 18);
        $this->canhotoVencimentoValor($y + 42);
        $this->canhotoBarcodeArea($y + 55);
    }

    private function canhotoCabecalho(float $y): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::COR_BORDA);
        $pdf->Rect(8, $y, 194, 17, 'D');
        $pdf->Line(46, $y, 46, $y + 17);
        $pdf->Line(126, $y, 126, $y + 17);
        $pdf->Line(152, $y, 152, $y + 17);

        // Col 1
        $pdf->SetFont('helvetica', 'B', 5.8);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(9, $y + 1.5);
        $pdf->MultiCell(36, 2.8, "NOTA FISCAL /\nFATURA DE\nSERVIÇOS", 0, 'L');
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetXY(9, $y + 10.5);
        $pdf->MultiCell(36, 2.4, "Saneamento eficiente\né aquele que fazemos juntos", 0, 'L');

        // Col 2
        $pdf->SetFont('helvetica', 'B', 5.8);
        $pdf->SetXY(48, $y + 1.2);
        $pdf->Cell(77, 3, self::EMPRESA['razao'], 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 5.2);
        foreach ([self::EMPRESA['end1'], self::EMPRESA['end2'], self::EMPRESA['cnpj'], self::EMPRESA['fone'], self::EMPRESA['site']] as $linha) {
            $pdf->SetX(48);
            $pdf->Cell(77, 2.5, $linha, 0, 1, 'C');
        }

        // Col 3 — NÚMERO GUIA
        $pdf->SetFont('helvetica', 'B', 5.7);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(127, $y + 1.5);
        $pdf->Cell(24, 3, 'NÚMERO GUIA', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 5.8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetX(127);
        $pdf->MultiCell(24, 3, $this->dados['numero_guia'] ?? '', 0, 'C');

        // Col 4 — Logo fallback
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(153, $y + 4);
        $pdf->Cell(48, 4, 'AQUAFLOW', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 5.8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetX(153);
        $pdf->Cell(48, 3, 'SANEAMENTO', 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }

    private function canhotoDadosCliente(float $y): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::COR_BORDA);
        $pdf->Rect(8, $y, 130, 23, 'D');
        $pdf->Rect(138, $y, 64, 9, 'D');

        // MÊS/ANO
        $pdf->SetFillColor(...self::COR_AZUL);
        $pdf->Rect(138, $y, 64, 4.5, 'F');
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetTextColor(...self::COR_BRANCO);
        $pdf->SetXY(138, $y + 0.5);
        $pdf->Cell(64, 3.5, 'MÊS / ANO', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(138, $y + 4.6);
        $pdf->Cell(64, 4, $this->dados['mes_ano'] ?? '', 0, 0, 'C');

        // CATEGORIA
        $pdf->Rect(138, $y + 9, 64, 14, 'D');
        $pdf->SetFillColor(...self::COR_VERDE);
        $pdf->Rect(138, $y + 9, 64, 4.2, 'F');
        $pdf->SetFont('helvetica', 'B', 5.2);
        $pdf->SetTextColor(...self::COR_BRANCO);
        $pdf->SetXY(138, $y + 9.3);
        $pdf->Cell(64, 3.4, 'CATEGORIA / QUANTIDADE', 0, 0, 'C');

        $pdf->SetFillColor(...self::COR_AZUL);
        $pdf->Rect(138, $y + 13.2, 64, 4.3, 'F');
        $largCat = 16;
        $labels  = ['RESIDENCIAL', 'COMERCIAL', 'INDUSTRIAL', 'PÚBLICA'];
        $pdf->SetFont('helvetica', 'B', 4.4);
        foreach ($labels as $i => $label) {
            $pdf->SetXY(138 + $i * $largCat, $y + 13.6);
            $pdf->Cell($largCat, 3.4, $label, 0, 0, 'C');
        }
        $cat  = $this->dados['categoria'] ?? [];
        $vals = [$cat['residencial'] ?? 0, $cat['comercial'] ?? 0, $cat['industrial'] ?? 0, $cat['publica'] ?? 0];
        $pdf->SetFont('helvetica', '', 6.2);
        $pdf->SetTextColor(0, 0, 0);
        foreach ($vals as $i => $v) {
            $pdf->SetXY(138 + $i * $largCat, $y + 18.2);
            $pdf->Cell($largCat, 3.5, (string)$v, 0, 0, 'C');
        }

        // Dados cliente no canhoto
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY(10, $y + 1.5);
        $pdf->MultiCell(126, 4, $this->dados['cliente_nome'] ?? '', 0, 'L');
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetXY(10, $y + 7.5);
        $pdf->MultiCell(126, 3.4, ($this->dados['cliente_endereco'] ?? '') . ' - ' . ($this->dados['cliente_cep_cidade'] ?? ''), 0, 'L');
        if (!empty($this->dados['id_eletronico'])) {
            $pdf->SetXY(10, $y + 17.5);
            $pdf->Cell(126, 3.5, 'ID. Eletrônico: ' . $this->dados['id_eletronico'], 0, 0, 'L');
        }
    }

    private function canhotoVencimentoValor(float $y): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::COR_BORDA);
        $pdf->Rect(8, $y, 194, 8, 'D');
        $pdf->Line(50, $y, 50, $y + 8);
        $pdf->Line(152, $y, 152, $y + 8);

        // Labels
        $pdf->SetFillColor(...self::COR_AZUL);
        $pdf->Rect(8, $y, 42, 4, 'F');
        $pdf->Rect(152, $y, 50, 4, 'F');
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetTextColor(...self::COR_BRANCO);
        $pdf->SetXY(9, $y + 0.5);
        $pdf->Cell(41, 3, 'VENCIMENTO', 0, 0, 'L');
        $pdf->SetXY(153, $y + 0.5);
        $pdf->Cell(49, 3, 'VALOR A PAGAR', 0, 0, 'L');

        // Valores
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(9, $y + 4.5);
        $pdf->Cell(41, 3, $this->dados['vencimento'] ?? '', 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(...self::COR_VERDE);
        $pdf->SetXY(153, $y + 4.2);
        $pdf->Cell(49, 3.5, 'R$ ' . number_format($this->dados['valor_a_pagar'] ?? 0, 2, ',', '.'), 0, 0, 'L');
        $pdf->SetTextColor(0, 0, 0);

        // Texto legal
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetXY(51, $y + 0.7);
        $pdf->MultiCell(100, 2.7, 'APÓS O VENCIMENTO, COBRANÇA DE MULTA DE 2%, JUROS DE MORA E ATUALIZAÇÃO MONETÁRIA PELO IGP-M, NA PRÓXIMA CONTA', 0, 'C');

        // Rodapé canhoto
        $pdf->SetFont('helvetica', 'B', 6.2);
        $pdf->SetTextColor(...self::COR_AZUL);
        $pdf->SetXY(8, $y + 9);
        $pdf->Cell(194, 3.5, 'FAVOR AUTENTICAR NO VERSO - DEVOLVER À AQUAFLOW', 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }

    private function canhotoBarcodeArea(float $y): void
    {
        $pdf            = $this->pdf;
        $linhaDigitavel = $this->dados['linha_digitavel'] ?? '';
        $codigoBarras   = $this->dados['codigo_barras']   ?? '';

        // Linha digitável
        $pdf->SetFont('courier', '', 8.2);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(8, $y);
        $pdf->Cell(194, 4.5, $linhaDigitavel, 0, 1, 'C');

        // Código de barras
        $codigoBarrasNumerico = preg_replace('/\D/', '', $codigoBarras);

        if (strlen($codigoBarrasNumerico) === 44) {
            $pdf->write1DBarcode(
                $codigoBarrasNumerico,
                'I25',
                8,
                $y + 5.5,
                194,
                12,
                0.4,
                ['stretch' => true, 'fgcolor' => [0, 0, 0], 'bgcolor' => [255, 255, 255]],
                'N'
            );
        }
    }
}

class BillingTxtParser
{
    public function parseFile(string $arquivo): array
    {
        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($linhas === false) {
            throw new RuntimeException('Não foi possível ler o arquivo TXT.');
        }

        return $this->parseLines($linhas);
    }

    public function parseLines(array $linhas): array
    {
        $contas = [];
        $contaAtual = null;

        foreach ($linhas as $linha) {
            if ($linha === '') {
                continue;
            }

            $tipo = $linha[0] ?? '';

            if ($tipo === 'B') {
                if ($contaAtual !== null) {
                    $contas[] = $contaAtual;
                }

                $contaAtual = $this->parseRegistroB($linha);
                continue;
            }

            if ($tipo === 'C' && $contaAtual !== null) {
                $item = $this->parseRegistroC($linha);
                if ($item !== null) {
                    $contaAtual['descricao_itens'][] = $item;
                }
            }
        }

        if ($contaAtual !== null) {
            $contas[] = $contaAtual;
        }

        return $contas;
    }

    public function filtrar(array $contas, string $termo): array
    {
        $termo = $this->normalizarBusca($termo);
        if ($termo === '') {
            return $contas;
        }

        return array_values(array_filter($contas, function (array $conta) use ($termo): bool {
            $campos = [
                $conta['numero_guia'] ?? '',
                $conta['cliente_nome'] ?? '',
                $conta['cliente_endereco'] ?? '',
                $conta['cliente_cep_cidade'] ?? '',
                $conta['id_eletronico'] ?? '',
                $conta['documento'] ?? '',
                $conta['linha_digitavel'] ?? '',
            ];

            return strpos($this->normalizarBusca(implode(' ', $campos)), $termo) !== false;
        }));
    }

    private function parseRegistroB(string $linha): array
    {
        $codigo = $this->somenteDigitos($this->slice($linha, 1, 7));
        $nome = $this->texto($this->slice($linha, 8, 34));
        $endereco = $this->texto($this->slice($linha, 42, 47));
        $bairro = $this->texto($this->slice($linha, 99, 84));
        $tipoCategoria = $this->texto($this->slice($linha, 196, 1));
        $mapaRaw = $this->somenteDigitos($this->slice($linha, 197, 6));
        $vencimentoRaw = $this->somenteDigitos($this->slice($linha, 213, 8));
        $referenciaRaw = $this->somenteDigitos($this->slice($linha, 233, 8));
        $dataLeituraRaw = $this->somenteDigitos($this->slice($linha, 832, 8));
        $linhaDigitavel = $this->extrairLinhaDigitavel($linha);
        $historico = $this->parseHistorico($linha);

        if ($bairro !== '') {
            $endereco .= ' - ' . $bairro;
        }

        return [
            'numero_guia'              => $this->formatarNumeroGuia($codigo, $referenciaRaw),
            'mes_ano'                  => $this->formatarReferencia($referenciaRaw),
            'cliente_nome'             => $nome,
            'cliente_endereco'         => $endereco,
            'cliente_cep_cidade'       => $this->formatarCepCidade($linha),
            'id_eletronico'            => $this->extrairIdEletronico($linha),
            'codigo_debito_automatico' => ltrim($codigo, '0') ?: $codigo,
            'mapa_cad'                 => $this->formatarMapa($tipoCategoria, $mapaRaw),
            'categoria'                => [
                'residencial' => $this->inteiro($this->slice($linha, 221, 3)),
                'comercial'   => $this->inteiro($this->slice($linha, 224, 3)),
                'industrial'  => $this->inteiro($this->slice($linha, 227, 3)),
                'publica'     => $this->inteiro($this->slice($linha, 230, 3)),
            ],
            'descricao_itens'          => [],
            'data_leitura'             => $this->formatarData($dataLeituraRaw),
            'vencimento'               => $this->formatarData($vencimentoRaw),
            'valor_a_pagar'            => $this->valorDaLinhaDigitavel($linhaDigitavel),
            'leitura_anterior'         => $this->inteiro($this->slice($linha, 241, 6)),
            'leitura_atual'            => $this->inteiro($this->slice($linha, 247, 6)),
            'consumo_faturado'         => $this->inteiro($this->slice($linha, 260, 5)),
            'consumo_medio'            => $this->mediaHistorico($historico),
            'numero_hidrometro'        => $this->texto($this->slice($linha, 278, 10)),
            'historico'                => $historico,
            'mensagem'                 => $this->texto($this->slice($linha, 636, 40)),
            'informativo_debito'       => $this->extrairInformativoDebito($linha),
            'linha_digitavel'          => $linhaDigitavel,
            'codigo_barras'            => $this->codigoBarrasDaLinhaDigitavel($linhaDigitavel),
            'documento'                => $this->somenteDigitos($this->slice($linha, 1377, 14)),
        ];
    }

    private function parseRegistroC(string $linha): ?array
    {
        if (!preg_match('/(\d{11})\s*$/', $linha, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $valorCentavos = (int)$match[1][0];
        $descricao = $this->texto(substr($linha, 1, $match[1][1] - 1));

        if ($descricao === '') {
            return null;
        }

        return [
            'descricao' => $descricao,
            'valor'     => $valorCentavos / 100,
        ];
    }

    private function parseHistorico(string $linha): array
    {
        $historico = [];
        $meses = $this->slice($linha, 304, 72);
        $volumes = $this->slice($linha, 376, 72);
        $dias = $this->slice($linha, 448, 36);

        for ($i = 0; $i < 12; $i++) {
            $mesRaw = $this->somenteDigitos(substr($meses, $i * 6, 6));
            if (strlen($mesRaw) !== 6) {
                continue;
            }

            $historico[] = [
                'mes_ano' => substr($mesRaw, 0, 2) . '/' . substr($mesRaw, 2, 4),
                'volume'  => $this->inteiro(substr($volumes, $i * 6, 6)),
                'dias'    => $this->inteiro(substr($dias, $i * 3, 3)),
            ];
        }

        return $historico;
    }

    private function formatarNumeroGuia(string $codigo, string $referenciaRaw): string
    {
        $sufixo = $this->formatarReferencia($referenciaRaw);

        return ($codigo !== '' ? $codigo : 'SEM-CODIGO') . ($sufixo !== '' ? ' - ' . $sufixo : '');
    }

    private function formatarReferencia(string $raw): string
    {
        if (strlen($raw) < 8) {
            return '';
        }

        return substr($raw, 0, 2) . '/' . substr($raw, 4, 4);
    }

    private function formatarMapa(string $tipo, string $mapaRaw): string
    {
        if ($tipo === '' || strlen($mapaRaw) < 6) {
            return '';
        }

        return $tipo . '-' . substr($mapaRaw, 0, 2) . '-' . str_pad(ltrim(substr($mapaRaw, 2), '0'), 5, '0', STR_PAD_LEFT);
    }

    private function formatarCepCidade(string $linha): string
    {
        $cidade = $this->texto($this->slice($linha, 772, 20));
        $uf = $this->texto($this->slice($linha, 792, 2));
        $cep = $this->somenteDigitos($this->slice($linha, 794, 8));

        if (strlen($cep) === 8) {
            $cep = substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
        }

        return trim($cep . ' - ' . $cidade . ' - ' . $uf, ' -');
    }

    private function formatarData(string $raw): string
    {
        if (strlen($raw) !== 8) {
            return '';
        }

        return substr($raw, 0, 2) . '/' . substr($raw, 2, 2) . '/' . substr($raw, 4, 4);
    }

    private function extrairLinhaDigitavel(string $linha): string
    {
        if (preg_match('/\d{5}\.\d{5}\s+\d{5}\.\d{6}\s+\d{5}\.\d{6}\s+\d\s+\d{14}/', $linha, $match)) {
            return trim($match[0]);
        }

        return '';
    }

    private function extrairIdEletronico(string $linha): string
    {
        if (preg_match('/0+(\d+@[A-Z])/', $linha, $match)) {
            return $match[1];
        }

        return '';
    }

    private function extrairInformativoDebito(string $linha): string
    {
        $texto = $this->texto($this->slice($linha, 1000, 360));
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim($texto ?? '');
    }

    private function valorDaLinhaDigitavel(string $linhaDigitavel): float
    {
        $digitos = $this->somenteDigitos($linhaDigitavel);
        if (strlen($digitos) < 10) {
            return 0.0;
        }

        return ((int)substr($digitos, -10)) / 100;
    }

    private function codigoBarrasDaLinhaDigitavel(string $linhaDigitavel): string
    {
        $digitos = $this->somenteDigitos($linhaDigitavel);
        if (strlen($digitos) !== 47) {
            return '';
        }

        return substr($digitos, 0, 4)
            . substr($digitos, 32, 1)
            . substr($digitos, 33, 14)
            . substr($digitos, 4, 5)
            . substr($digitos, 10, 10)
            . substr($digitos, 21, 10);
    }

    private function mediaHistorico(array $historico): int
    {
        $volumes = array_values(array_filter(array_map(
            fn (array $item): int => (int)($item['volume'] ?? 0),
            $historico
        )));

        if (count($volumes) === 0) {
            return 0;
        }

        return (int)round(array_sum($volumes) / count($volumes));
    }

    private function normalizarBusca(string $texto): string
    {
        $texto = $this->paraUtf8($texto);
        $texto = function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);

        if (function_exists('iconv')) {
            $semAcento = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
            if ($semAcento !== false) {
                $texto = $semAcento;
            }
        }

        return preg_replace('/\s+/', ' ', trim($texto));
    }

    private function texto(string $valor): string
    {
        return trim($this->paraUtf8($valor));
    }

    private function paraUtf8(string $valor): string
    {
        if ($valor === '') {
            return '';
        }

        if (preg_match('//u', $valor) === 1) {
            return $valor;
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($valor, 'UTF-8')) {
            return $valor;
        }

        if (function_exists('iconv')) {
            $convertido = iconv('Windows-1252', 'UTF-8//IGNORE', $valor);
            if ($convertido !== false) {
                return $convertido;
            }
        }

        return utf8_encode($valor);
    }

    private function slice(string $linha, int $inicio, int $tamanho): string
    {
        if (strlen($linha) <= $inicio) {
            return '';
        }

        return substr($linha, $inicio, $tamanho);
    }

    private function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    private function inteiro(string $valor): int
    {
        $digitos = $this->somenteDigitos($valor);

        return $digitos === '' ? 0 : (int)$digitos;
    }
}
