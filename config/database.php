<?php
/**
 * Conexão Híbrida de Banco de Dados (MySQL Hostinger + Fallback Automático SQLite)
 * Inicializa as tabelas e importa os 91 registros históricos automaticamente no primeiro acesso.
 */

date_default_timezone_set('America/Sao_Paulo');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function loadEnvFile(string $path): array {
    if (!file_exists($path)) {
        return [];
    }
    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $vars[trim($parts[0])] = trim($parts[1], " \t\n\r\0\x0B\"'");
        }
    }
    return $vars;
}

$env = loadEnvFile(__DIR__ . '/../.env');

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    global $env;
    $driver = $env['DB_CONNECTION'] ?? 'sqlite';

    if ($driver === 'mysql' && !empty($env['DB_DATABASE']) && $env['DB_DATABASE'] !== 'u123456789_menteequilibrio') {
        try {
            $host = $env['DB_HOST'] ?? 'localhost';
            $port = $env['DB_PORT'] ?? '3306';
            $dbname = $env['DB_DATABASE'];
            $user = $env['DB_USERNAME'] ?? 'root';
            $pass = $env['DB_PASSWORD'] ?? '';
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            initializeDatabase($pdo, 'mysql');
            return $pdo;
        } catch (Throwable $e) {
            // Em caso de falha local no MySQL, usa SQLite para não travar a operação
        }
    }

    $sqlitePath = __DIR__ . '/../database/app.sqlite';
    $isNew = !file_exists($sqlitePath);
    $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    initializeDatabase($pdo, 'sqlite');
    return $pdo;
}

function initializeDatabase(PDO $pdo, string $driver): void {
    // Verifica se a tabela users já existe
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $row = $stmt->fetch();
        if ($row && (int)$row['total'] > 0) {
            return;
        }
    } catch (Throwable $e) {
        // Tabelas ainda não criadas
    }

    $autoInc = $driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id {$autoInc},
            name VARCHAR(120) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(30) NOT NULL DEFAULT 'secretaria',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS leads (
            id {$autoInc},
            contact_name VARCHAR(150) NOT NULL,
            patient_name VARCHAR(150) NOT NULL,
            phone_whatsapp VARCHAR(40) NOT NULL,
            email VARCHAR(150) DEFAULT NULL,
            patient_age INT DEFAULT NULL,
            origin VARCHAR(60) DEFAULT 'WhatsApp',
            main_complaint TEXT,
            status VARCHAR(40) NOT NULL DEFAULT 'novo',
            first_contact_date DATE NOT NULL,
            next_followup_date DATE DEFAULT NULL,
            loss_reason TEXT DEFAULT NULL,
            history_notes TEXT DEFAULT NULL,
            converted_patient_id INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS schools_catalog (
            id {$autoInc},
            name VARCHAR(160) NOT NULL UNIQUE
        )",
        "CREATE TABLE IF NOT EXISTS diagnoses_catalog (
            id {$autoInc},
            acronym VARCHAR(60) NOT NULL UNIQUE,
            full_name VARCHAR(200) NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS patients (
            id {$autoInc},
            code_number INT DEFAULT NULL,
            lead_id INT DEFAULT NULL,
            full_name VARCHAR(160) NOT NULL,
            responsible_name VARCHAR(160) DEFAULT NULL,
            cpf_nf VARCHAR(30) DEFAULT NULL,
            email_nf VARCHAR(150) DEFAULT NULL,
            phone_whatsapp VARCHAR(40) DEFAULT NULL,
            birth_date DATE DEFAULT NULL,
            age INT DEFAULT NULL,
            age_group VARCHAR(30) DEFAULT NULL,
            school_name VARCHAR(160) DEFAULT NULL,
            education_level VARCHAR(120) DEFAULT NULL,
            address_full TEXT DEFAULT NULL,
            reference_year VARCHAR(30) DEFAULT NULL,
            start_month VARCHAR(30) DEFAULT NULL,
            end_month VARCHAR(30) DEFAULT NULL,
            total_sessions VARCHAR(20) DEFAULT NULL,
            diag_1 VARCHAR(80) DEFAULT NULL,
            diag_2 VARCHAR(80) DEFAULT NULL,
            diag_3 VARCHAR(80) DEFAULT NULL,
            diag_4 VARCHAR(80) DEFAULT NULL,
            unlisted_comorbidity TEXT DEFAULT NULL,
            final_diagnosis VARCHAR(80) DEFAULT NULL,
            current_stage VARCHAR(40) NOT NULL DEFAULT 'vai_iniciar',
            anamnesis_date DATE DEFAULT NULL,
            devolutiva_date DATE DEFAULT NULL,
            drive_folder_url TEXT DEFAULT NULL,
            gamma_url TEXT DEFAULT NULL,
            google_form_cadastro_url TEXT DEFAULT NULL,
            google_form_anamnese_url TEXT DEFAULT NULL,
            home_scales_notes TEXT DEFAULT NULL,
            school_interview_notes TEXT DEFAULT NULL,
            informant_interview_notes TEXT DEFAULT NULL,
            anamnesis_answers_json TEXT DEFAULT NULL,
            contract_total_value DECIMAL(10,2) DEFAULT 2800.00,
            contract_installments INT DEFAULT 4,
            contract_installments_json TEXT DEFAULT NULL,
            contract_gisele_approved TINYINT(1) DEFAULT 0,
            contract_gisele_approved_at DATETIME DEFAULT NULL,
            laudo_gisele_approved TINYINT(1) DEFAULT 0,
            laudo_gisele_approved_at DATETIME DEFAULT NULL,
            nf_issued TINYINT(1) DEFAULT 0,
            nf_issued_at DATETIME DEFAULT NULL,
            public_token VARCHAR(64) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS patient_tasks (
            id {$autoInc},
            patient_id INT NOT NULL,
            stage_group VARCHAR(40) NOT NULL,
            task_code VARCHAR(60) NOT NULL,
            task_title VARCHAR(255) NOT NULL,
            responsible_role VARCHAR(30) NOT NULL DEFAULT 'secretaria',
            status VARCHAR(30) NOT NULL DEFAULT 'pendente',
            sent_at DATE DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            completed_by VARCHAR(100) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 1
        )",
        "CREATE TABLE IF NOT EXISTS patient_tests (
            id {$autoInc},
            patient_id INT NOT NULL,
            instrument_name VARCHAR(150) NOT NULL,
            scored_physical_folder TINYINT(1) NOT NULL DEFAULT 0,
            entered_drive_excel TINYINT(1) NOT NULL DEFAULT 0,
            entered_vol_platform TINYINT(1) NOT NULL DEFAULT 0,
            inserted_in_report TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id {$autoInc},
            user_name VARCHAR(120) NOT NULL,
            user_role VARCHAR(40) NOT NULL,
            patient_id INT DEFAULT NULL,
            action_description TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($queries as $q) {
        $pdo->exec($q);
    }

    require_once __DIR__ . '/../database/seed_data.php';
    seedInitialDatabase($pdo);
}
