# Boleto Batch Importer

A small PHP application that ingests fixed-width utility billing files (the kind
produced by water/sewage concessionaires for bank collection), parses them into
customers, invoices and line items, upserts everything into MySQL, and renders
printable Brazilian **boletos** (bank payment slips) as PDF — individually or in
a batch ZIP.

> All sample data in this repository is **fictitious**. It ships with a generator
> (`samples/generate_sample.php`) that produces a valid input file so you can run
> the whole pipeline without any real customer data.

## Features

- **Tolerant fixed-width parser** (`BillingFileParser` in `core.php`) — reads the
  multi-line record layout (customer `B`, reading line, charge items `C`,
  footer `N`) and extracts the electronic id, address, meter reading, FEBRABAN
  payment line and document number.
- **Signed charge items** — charges add, while credits/reversals (*estorno*)
  encoded with a leading `-` are correctly subtracted from the invoice total.
- **Idempotent upsert** — a customer/invoice that already exists is updated in
  place; otherwise it is inserted. Re-importing the same file is safe.
- **PDF generation** via TCPDF (`InvoicePdfGenerator`) — one slip per invoice,
  bundled into a ZIP for batch downloads.
- **Secure downloads** — generated files live only in the system temp dir and are
  served once via a session token (`download.php`), never written into the web root.
- **Invoice lookup** (`segunda-via.php`) — search issued invoices by id or name.

## Stack

- PHP 8.x (no framework, no Composer)
- MySQL / MariaDB
- [TCPDF](https://tcpdf.org/) (vendored under `tcpdf/`)

## Getting started

1. **Create the database**

   ```sql
   SOURCE database.sql;   -- creates `water_billing` + optional seed rows
   ```

2. **Configure the connection**

   ```bash
   cp config/.env.example config/.env
   # edit config/.env with your DB credentials
   ```

3. **Generate a sample input file** (optional — one is already committed)

   ```bash
   php samples/generate_sample.php
   ```

4. **Serve the app** with Apache/WAMP/XAMPP (document root at the project folder)
   and open `importador.php`, or run PHP's built-in server:

   ```bash
   php -S localhost:8000
   ```

5. **Import** `samples/sample_invoices.txt` through the upload form. The seed rows
   (with outdated values) will be updated to the amounts in the file.

## Input file format

Each invoice is four line types:

| Line | Meaning | Key fields |
|------|---------|-----------|
| `B…` | Customer | matrícula (7), name (34), street (46), district (30) |
| `NNNNNNNN…` | Reading line | due date `DDMMYYYY`, reference `MMYYYY`, previous/current reading, consumption |
| `C…` | Charge item | description + 11-digit amount in cents; leading `-` = credit |
| `N…` | Footer | electronic id (`digits@LETTER`), city/UF/CEP, FEBRABAN payment line, CPF/CNPJ |

See `samples/generate_sample.php` for an executable specification of the exact
byte positions.

## Configuration

`config/.env` (never committed):

```
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=water_billing
DB_USERNAME=root
DB_PASSWORD=
```

## License

[MIT](LICENSE)
