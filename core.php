<?php
// core.php - Parser robusto para arquivos TXT da AquaFlow

require_once __DIR__ . '/conexao.php';

class BillingFileParser {
    public static function parseFile($content, $filename = '') {
        // Mantém o conteúdo bruto em ISO-8859-1 para extração por posição de bytes;
        // cada linha é convertida para UTF-8 individualmente antes de regex/banco.
        $lines = preg_split('/\r\n|\r|\n/', $content);

        $boletos = [];
        $current = null;
        $expectaDataLine = false;

        foreach ($lines as $rawLine) {
            $line    = rtrim($rawLine);                                          // ISO-8859-1
            $lineU8  = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');       // UTF-8
            if ($line === '') continue;

            $type = substr($line, 0, 1);

            // ── Registro B: novo cliente ──────────────────────────────
            if ($type === 'B') {
                if ($current && isset($current['id'])) $boletos[] = $current;
                // parseRegistroB recebe a linha ISO-8859-1 para extrair campos por posição
                $current = self::parseRegistroB($line, $filename);
                if (strlen($line) > 400) {
                    // Formato estendido: todos os dados estão na própria linha B (UTF-8)
                    self::parseDataFromExtendedB($lineU8, $current);
                    self::parseRegistroN($lineU8, $current);
                    if (preg_match('/[NS]\s\d+\s{15,}(.{5,42}?)\s{10,}[NS]\d+@/', $lineU8, $mMsg)) {
                        $current['mensagem'] = trim($mMsg[1]);
                    }
                    $expectaDataLine = false;
                } else {
                    $expectaDataLine = true;
                }
                continue;
            }

            if (!$current) continue;

            // ── Linha de dados (logo após B): começa com 8 dígitos ────
            if ($expectaDataLine && preg_match('/^\d{8}/', $line)) {
                self::parseDataLine($lineU8, $current);
                $expectaDataLine = false;
                continue;
            }
            $expectaDataLine = false;

            // ── Registro C: item cobrado ──────────────────────────────
            if ($type === 'C') {
                $item = self::parseRegistroC($lineU8);
                if ($item) $current['itens'][] = $item;
                continue;
            }

            // ── Registro N: ID, endereço completo, linha digitável ────
            if ($type === 'N' || $type === 'S') {
                self::parseRegistroN($lineU8, $current);
                continue;
            }
        }
        if ($current && isset($current['id'])) $boletos[] = $current;

        return $boletos;
    }

    private static function parseRegistroB(string $line, string $filename): array {
        // Pos 0:    'B'
        // Pos 1-7:  matrícula numérica (7 dígitos)
        // Pos 8-41: nome (34 chars)
        // Pos 42-87: logradouro (46 chars, pode ter código de setor no final)
        // Pos 98-127: bairro (30 chars)
        // Extração por posição de bytes na linha ISO-8859-1 (single-byte), depois converte
        $conv = fn(string $s): string => mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');

        $matricula  = trim(substr($line, 1, 7));
        $nome       = trim($conv(substr($line, 8, 34)));
        $logradouro = trim($conv(substr($line, 42, 46)));
        $bairro     = trim($conv(substr($line, 98, 30)));

        // Remove código de setor que aparece no final do logradouro (4 dígitos com padding)
        $logradouro = preg_replace('/\s+\d{4}\s*$/', '', $logradouro);

        return [
            'id'               => null,
            'matricula'        => $matricula,
            'nome'             => $nome,
            'logradouro'       => $logradouro,
            'bairro'           => $bairro,
            'cidade'           => '',
            'uf'               => '',
            'cep'              => '',
            'cpf_cnpj'         => '',
            'vencimento'       => null,
            'leitura_anterior' => 0,
            'leitura_atual'    => 0,
            'consumo_m3'       => 0,
            'referencia_mes'   => 0,
            'referencia_ano'   => 0,
            'data_leitura'     => '',
            'numero_hidrometro'=> '',
            'mensagem'         => '',
            'linha_digitavel'  => '',
            'codigo_barras'    => '',
            'valor'            => 0.0,
            'itens'            => [],
            'arquivo_origem'   => $filename,
        ];
    }

    private static function parseDataLine(string $line, array &$current): void {
        // Pos 0-7: vencimento DDMMAAAA
        $venc = substr($line, 0, 8);
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $venc, $m)) {
            $current['vencimento'] = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Referência MMAAAA - pos 22-27 (após 8 vencimento + 14 filler)
        $ref = substr($line, 22, 6);
        if (preg_match('/^(\d{2})(\d{4})$/', $ref, $m)) {
            $current['referencia_mes'] = (int)$m[1];
            $current['referencia_ano'] = (int)$m[2];
        }

        // Leituras: pos 28-33 (anterior) e 34-39 (atual), 6 dígitos cada
        $current['leitura_anterior'] = (int)substr($line, 28, 6);
        $current['leitura_atual']    = (int)substr($line, 34, 6);

        // Consumo m3: pos 42-46 (5 dígitos, após 2 chars de filler em 40-41)
        $current['consumo_m3'] = (int)substr($line, 42, 5);

        // Data da leitura + hidrômetro: regex tolerante a posição variável
        // Padrão: 8 dígitos da data + identificador (letra+2 dígitos+letras+dígitos)
        if (preg_match('/(\d{2})(\d{2})(\d{4})([A-Z]\d{2}[A-Z]+\d+)/', $line, $m)) {
            if ($m[3] >= '1900' && (int)$m[1] >= 1 && (int)$m[1] <= 31 && (int)$m[2] >= 1 && (int)$m[2] <= 12) {
                $current['data_leitura'] = "{$m[1]}/{$m[2]}/{$m[3]}";
            }
            $current['numero_hidrometro'] = $m[4];
        }
    }

    private static function parseRegistroC(string $line): ?array {
        // Valor em centavos: últimos 11 dígitos da linha, com sinal opcional ('-' colado).
        // Créditos (estorno, vazamento, limpeza de caixa etc.) vêm como '-00000024190'.
        if (!preg_match('/(-?)(\d{11})\s*$/', $line, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $sinal   = $m[1][0];                 // '' ou '-'
        $valStr  = $m[2][0];
        $valOffset = $m[1][1];               // início do valor (posição do sinal ou do 1º dígito)

        // Descrição: tudo entre o C inicial e o início do valor
        $desc = trim(substr($line, 1, $valOffset - 1));
        if ($desc === '') return null;

        $valor = (float)((int)$valStr / 100);

        if ($sinal === '-') {
            // Sinal explícito no arquivo é a fonte da verdade
            $valor = -$valor;
        } elseif (stripos($desc, 'ESTORNO') !== false) {
            // Rede de segurança: formato que omita o sinal mas traga "ESTORNO" na descrição
            $valor = -$valor;
        }

        return [
            'desc'  => $desc,
            'valor' => $valor,
        ];
    }

    private static function parseRegistroN(string $line, array &$current): void {
        // ID eletrônico: número seguido de @ e letra
        if (preg_match('/(\d+)@([A-Z])/', $line, $m)) {
            $current['id'] = ltrim($m[1], '0') ?: $m[1];
            $current['id_sufixo'] = $m[2];
        } else {
            return; // sem ID, ignora linha
        }

        // Cidade + UF + CEP: padrão "NOMECIDADE   MG34018012"
        if (preg_match('/([A-Z][A-Z ]{2,})\s+([A-Z]{2})(\d{8})/', $line, $m)) {
            $current['cidade'] = trim($m[1]);
            $current['uf']     = $m[2];
            $cep = $m[3];
            $current['cep'] = substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
        }

        // Linha digitável FEBRABAN (formato com pontos e espaços)
        if (preg_match('/(\d{5}\.\d{5})\s+(\d{5}\.\d{6})\s+(\d{5}\.\d{6})\s+(\d)\s+(\d{14})/', $line, $m)) {
            $current['linha_digitavel'] = "{$m[1]} {$m[2]} {$m[3]} {$m[4]} {$m[5]}";
            $current['codigo_barras']   = self::montarCodigoBarras($current['linha_digitavel']);
            // Valor: últimos 10 dígitos do bloco final de 14
            $valor = (int)substr($m[5], -10);
            $current['valor'] = $valor / 100;
        }

        // Mensagem: texto alfabético entre os blocos de dígitos iniciais
        if (preg_match('/([A-Z][A-Z\.\s\-]{5,40}?)\s{2,}/', $line, $m)) {
            $msg = trim($m[1]);
            if (strpos($msg, 'SANTA RITA') === false) {
                $current['mensagem'] = $msg;
            }
        }

        // CPF/CNPJ: últimos 11 ou 14 dígitos não-zero da linha trimmed
        $trimmed = rtrim($line);
        if (preg_match('/(\d{11,14})$/', $trimmed, $m)) {
            $doc = $m[1];
            // Aceita só se não for sequência de zeros
            if (ltrim($doc, '0') !== '') {
                $current['cpf_cnpj'] = $doc;
            }
        }
    }

    private static function parseDataFromExtendedB(string $line, array &$current): void {
        // No formato estendido o código do hidrômetro (letra + 4-7 dígitos + espaços) precede a
        // seção de dados que segue o mesmo layout da linha de dados do formato antigo
        if (preg_match('/[A-Z]\d{4,7}\s+(\d{8}.+)/', $line, $m)) {
            self::parseDataLine($m[1], $current);
        }
    }

    private static function montarCodigoBarras(string $linhaDigitavel): string {
        $digitos = preg_replace('/\D/', '', $linhaDigitavel);
        if (strlen($digitos) !== 47) return '';

        // Recombina os 47 dígitos da linha digitável → 44 dígitos do código de barras FEBRABAN
        return substr($digitos, 0, 4)
             . substr($digitos, 32, 1)
             . substr($digitos, 33, 14)
             . substr($digitos, 4, 5)
             . substr($digitos, 10, 10)
             . substr($digitos, 21, 10);
    }
}
