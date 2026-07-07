<?php
/**
 * Generates a synthetic sample input file in the fixed-width, multi-line
 * layout consumed by BillingFileParser (see core.php).
 *
 * All names, documents and addresses are FICTITIOUS. Run:
 *   php samples/generate_sample.php
 * to (re)create samples/sample_invoices.txt
 *
 * Record layout (one invoice = 4 line types):
 *   B  customer     : 'B' + matricula(7) + name(34) + street(46) + filler + district(30)
 *   data line       : venc DDMMAAAA + filler + ref MMYYYY + prevReading(6) + currReading(6) + consumo
 *   C  charge item   : 'C' + description + 11-digit amount in cents ('-' prefix = credit/estorno)
 *   N  boleto/footer : electronic id (\d+@LETTER), city/UF/CEP, FEBRABAN payment line, document
 */

/** Build the fixed-width B (customer) line. */
function lineB(string $mat, string $name, string $street, string $district, string $route): string {
    $b  = 'B';
    $b .= str_pad($mat, 7, '0', STR_PAD_LEFT);       // pos 1-7
    $b .= str_pad(substr($name, 0, 34), 34);          // pos 8-41
    $b .= str_pad(substr($street, 0, 46), 46);        // pos 42-87
    $b .= str_pad('', 10);                            // pos 88-97 sector filler
    $b .= str_pad(substr($district, 0, 30), 30);      // pos 98-127
    $b .= '     ' . $route;                           // trailing reading code
    return $b;
}

/** Build the reading/data line (starts with the 8-digit due date). */
function lineData(string $venc, string $ref, int $prev, int $curr, int $consumo, string $meter): string {
    $d  = $venc;                                      // pos 0-7  DDMMAAAA
    $d .= str_pad('0', 14, '0');                      // pos 8-21 filler
    $d .= $ref;                                       // pos 22-27 MMYYYY
    $d .= str_pad((string)$prev, 6, '0', STR_PAD_LEFT); // pos 28-33
    $d .= str_pad((string)$curr, 6, '0', STR_PAD_LEFT); // pos 34-39
    $d .= '00';                                       // pos 40-41 filler
    $d .= str_pad((string)$consumo, 5, '0', STR_PAD_LEFT); // pos 42-46
    $d .= '09042024' . $meter . "  m3    1/2''  ";    // reading date + meter id
    return $d;
}

/** Build a charge item line. Positive = charge, negative = credit/estorno. */
function lineC(string $desc, int $cents): string {
    $sign = $cents < 0 ? '-' : '';
    $val  = str_pad((string)abs($cents), 11, '0', STR_PAD_LEFT);
    return 'C' . str_pad($desc, 60) . $sign . $val;
}

/** Build the N (footer) line: electronic id, city/UF/CEP, payment line, document. */
function lineN(string $idEletronico, string $name, string $city, string $uf, string $cep, int $totalCents, string $doc): string {
    // FEBRABAN-style payment line; last block encodes the amount in its final 10 digits.
    $block5 = '1358' . str_pad((string)$totalCents, 10, '0', STR_PAD_LEFT);
    $paymentLine = '03399.04302 29400.000336 35238.101014 5 ' . $block5;

    $n  = 'N ';
    $n .= str_pad(substr($name, 0, 34), 34);
    $n .= str_pad('SEM OCORRENCIA', 30);
    $n .= 'N' . str_pad($idEletronico, 20, '0', STR_PAD_LEFT) . '@A';
    $n .= str_pad('', 12);
    $n .= str_pad(substr($name, 0, 34), 34);
    $n .= '0000000102012026';
    $n .= str_pad($city, 20) . $uf . str_pad(preg_replace('/\D/', '', $cep), 8, '0');
    $n .= str_pad('', 30);
    $n .= $paymentLine;
    $n .= str_pad('', 20);
    $n .= preg_replace('/\D/', '', $doc); // document (CPF/CNPJ) as final digit run
    return $n;
}

// ---------------------------------------------------------------------------
// Fictitious dataset — 4 invoices, two of them carrying a credit (estorno).
// ---------------------------------------------------------------------------
$records = [
    [
        'mat' => '0001001', 'id' => '5011001', 'name' => 'JOHN DOE',
        'street' => 'MAPLE STREET 100', 'district' => 'DOWNTOWN', 'route' => 'A010010',
        'venc' => '15022026', 'ref' => '012026', 'prev' => 196, 'curr' => 202, 'consumo' => 6,
        'meter' => 'A24R4078863', 'city' => 'SANTA RITA', 'uf' => 'SP', 'cep' => '12345-000',
        'doc' => '11122233344', // fictitious CPF
        'items' => [
            ['WATER TARIFF', 8450],
            ['WATER RESOURCE FEE', 202],
        ],
    ],
    [
        'mat' => '0001002', 'id' => '5011002', 'name' => 'JANE SMITH',
        'street' => 'OAK AVENUE 240', 'district' => 'DOWNTOWN', 'route' => 'A010020',
        'venc' => '15022026', 'ref' => '012026', 'prev' => 538, 'curr' => 558, 'consumo' => 20,
        'meter' => 'A23R1877723', 'city' => 'SANTA RITA', 'uf' => 'SP', 'cep' => '12345-000',
        'doc' => '22233344455',
        'items' => [
            ['WATER TARIFF', 6428],
            ['SEWAGE TARIFF', 5428],
            ['LATE FEE REVERSAL (ESTORNO)', -1000], // credit
        ],
    ],
    [
        'mat' => '0001003', 'id' => '3011003', 'name' => 'ACME CONDOMINIUM ASSOC',
        'street' => 'PRINCESS AVENUE 400', 'district' => 'LAKESIDE', 'route' => 'B010023',
        'venc' => '15022026', 'ref' => '012026', 'prev' => 74, 'curr' => 96, 'consumo' => 22,
        'meter' => 'A24BR04860', 'city' => 'SANTA RITA', 'uf' => 'SP', 'cep' => '12345-100',
        'doc' => '00000000000100', // fictitious CNPJ
        'items' => [
            ['WATER TARIFF', 15263],
            ['SEWAGE TARIFF', 15263],
            ['WATER RESOURCE FEE', 202],
        ],
    ],
    [
        'mat' => '0001004', 'id' => '3011004', 'name' => 'MARIA GARCIA',
        'street' => 'REGENT ROAD 185', 'district' => 'LAKESIDE', 'route' => 'D010074',
        'venc' => '10022026', 'ref' => '012026', 'prev' => 1500, 'curr' => 1523, 'consumo' => 23,
        'meter' => 'A25BR00510', 'city' => 'SANTA RITA', 'uf' => 'SP', 'cep' => '12345-200',
        'doc' => '33344455566',
        'items' => [
            ['WATER TARIFF', 4161],
            ['WATER RESOURCE FEE', 202],
            ['INTERNAL LEAK CREDIT', -2000], // credit
        ],
    ],
];

$lines = [];
foreach ($records as $r) {
    $total = 0;
    foreach ($r['items'] as $it) $total += $it[1];

    $lines[] = lineB($r['mat'], $r['name'], $r['street'], $r['district'], $r['route']);
    $lines[] = lineData($r['venc'], $r['ref'], $r['prev'], $r['curr'], $r['consumo'], $r['meter']);
    foreach ($r['items'] as $it) $lines[] = lineC($it[0], $it[1]);
    $lines[] = lineN($r['id'], $r['name'], $r['city'], $r['uf'], $r['cep'], $total, $r['doc']);
}

$out = __DIR__ . '/sample_invoices.txt';
file_put_contents($out, implode("\r\n", $lines) . "\r\n");
echo "Wrote " . count($records) . " invoices to " . $out . PHP_EOL;
