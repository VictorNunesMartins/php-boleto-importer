<?php

function carregarEnv(string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $linhas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        $linha = trim($linha);

        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }

        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);

        if ($chave === '') {
            continue;
        }

        if (
            (str_starts_with($valor, '"') && str_ends_with($valor, '"')) ||
            (str_starts_with($valor, "'") && str_ends_with($valor, "'"))
        ) {
            $valor = substr($valor, 1, -1);
        }

        $_ENV[$chave] = $valor;
        putenv($chave . '=' . $valor);
    }
}

function envValue(string $chave): string {
    $valor = $_ENV[$chave] ?? getenv($chave);

    if ($valor === false || $valor === null || $valor === '') {
        throw new RuntimeException("Variavel de ambiente {$chave} nao configurada.");
    }

    return (string)$valor;
}

function getDb(): PDO {
    carregarEnv(__DIR__ . '/config/.env');

    $host = envValue('DB_HOST');
    $port = envValue('DB_PORT');
    $db = envValue('DB_DATABASE');
    $user = envValue('DB_USERNAME');
    $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
    $pass = $pass === false || $pass === null ? '' : (string)$pass;
    $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        throw new PDOException('Falha ao conectar ao banco de dados.', (int)$e->getCode(), $e);
    }
}
