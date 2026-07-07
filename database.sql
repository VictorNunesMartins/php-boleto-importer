-- Schema for the water-billing importer (customers, invoices, invoice items).
-- Optional seed rows at the bottom carry OUTDATED values so you can watch them
-- being updated when you import samples/sample_invoices.txt.

CREATE DATABASE IF NOT EXISTS water_billing;
USE water_billing;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS boleto_itens;
DROP TABLE IF EXISTS boletos;
DROP TABLE IF EXISTS clientes;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Tabela de Clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identificador_eletronico VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    cpf_cnpj VARCHAR(20),
    logradouro VARCHAR(100),
    bairro VARCHAR(50),
    cidade VARCHAR(50),
    uf CHAR(2),
    cep VARCHAR(10),
    matricula VARCHAR(20),
    id_sufixo CHAR(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabela de Boletos
CREATE TABLE boletos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    referencia_mes INT NOT NULL,
    referencia_ano INT NOT NULL,
    data_vencimento DATE NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    leitura_anterior INT,
    leitura_atual INT,
    consumo_m3 INT,
    codigo_barras VARCHAR(100),
    linha_digitavel VARCHAR(100),
    arquivo_origem VARCHAR(50),
    status ENUM('pendente', 'pago') DEFAULT 'pendente',
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabela de Itens (Tarifas/Multas)
CREATE TABLE boleto_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boleto_id INT NOT NULL,
    descricao VARCHAR(100) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (boleto_id) REFERENCES boletos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- OPTIONAL SEED (OUTDATED INVOICES) ---
-- Fictitious rows whose ids match samples/sample_invoices.txt. Importing the
-- sample file updates these values in place, demonstrating the upsert logic.

INSERT INTO clientes (id, identificador_eletronico, nome, cpf_cnpj, logradouro, bairro, cidade, uf, cep, matricula) VALUES
(1, '5011001', 'JOHN DOE',               '111.222.333-44',    'MAPLE STREET 100',    'DOWNTOWN', 'SANTA RITA', 'SP', '12345-000', '0001001'),
(2, '5011002', 'JANE SMITH',             '222.333.444-55',    'OAK AVENUE 240',      'DOWNTOWN', 'SANTA RITA', 'SP', '12345-000', '0001002'),
(3, '3011003', 'ACME CONDOMINIUM ASSOC', '00.000.000/0001-00','PRINCESS AVENUE 400', 'LAKESIDE', 'SANTA RITA', 'SP', '12345-100', '0001003'),
(4, '3011004', 'MARIA GARCIA',           '333.444.555-66',    'REGENT ROAD 185',     'LAKESIDE', 'SANTA RITA', 'SP', '12345-200', '0001004');

INSERT INTO boletos (id, cliente_id, referencia_mes, referencia_ano, data_vencimento, valor_total, leitura_anterior, leitura_atual, consumo_m3, codigo_barras, linha_digitavel, status) VALUES
(1, 1, 1, 2026, '2026-02-15', 50.00, 190, 196,  6, '000', 'OUTDATED LINE', 'pendente'),
(2, 2, 1, 2026, '2026-02-15', 50.00, 530, 558, 20, '000', 'OUTDATED LINE', 'pendente'),
(3, 3, 1, 2026, '2026-02-15', 50.00,  70,  96, 22, '000', 'OUTDATED LINE', 'pendente'),
(4, 4, 1, 2026, '2026-02-10', 50.00,1500,1523, 23, '000', 'OUTDATED LINE', 'pendente');

INSERT INTO boleto_itens (boleto_id, descricao, valor) VALUES
(1, 'OUTDATED VALUE', 50.00),
(2, 'OUTDATED VALUE', 50.00),
(3, 'OUTDATED VALUE', 50.00),
(4, 'OUTDATED VALUE', 50.00);
