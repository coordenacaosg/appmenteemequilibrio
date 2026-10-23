-- Script SQL de Criação das Tabelas para MySQL / MariaDB (Hostinger phpMyAdmin)
-- Observação: A aplicação PHP também cria as tabelas e importa os 91 pacientes automaticamente no primeiro acesso!

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'secretaria',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schools_catalog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS diagnoses_catalog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    acronym VARCHAR(60) NOT NULL UNIQUE,
    full_name VARCHAR(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_number INT DEFAULT NULL,
    lead_id INT DEFAULT NULL,
    full_name VARCHAR(160) NOT NULL,
    responsible_name VARCHAR(160) DEFAULT NULL,
    responsible_name_2 VARCHAR(160) DEFAULT NULL,
    responsible_cpf_2 VARCHAR(30) DEFAULT NULL,
    responsible_phone_2 VARCHAR(40) DEFAULT NULL,
    responsible_kinship_1 VARCHAR(60) DEFAULT NULL,
    responsible_kinship_2 VARCHAR(60) DEFAULT NULL,
    responsible_address_2 TEXT DEFAULT NULL,
    contract_model VARCHAR(30) DEFAULT 'adulto',
    contract_payment_method VARCHAR(40) DEFAULT 'boleto_3x',
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
    anamnesis_type VARCHAR(30) DEFAULT NULL,
    anamnesis_answers_json TEXT DEFAULT NULL,
    client_form_answers_json TEXT DEFAULT NULL,
    contract_total_value DECIMAL(10,2) DEFAULT 4500.00,
    contract_installments INT DEFAULT 3,
    contract_installments_json TEXT DEFAULT NULL,
    contract_gisele_approved TINYINT(1) DEFAULT 0,
    contract_gisele_approved_at DATETIME DEFAULT NULL,
    laudo_gisele_approved TINYINT(1) DEFAULT 0,
    laudo_gisele_approved_at DATETIME DEFAULT NULL,
    nf_issued TINYINT(1) DEFAULT 0,
    nf_issued_at DATETIME DEFAULT NULL,
    public_token VARCHAR(64) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patient_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patient_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    instrument_name VARCHAR(150) NOT NULL,
    scored_physical_folder TINYINT(1) NOT NULL DEFAULT 0,
    entered_drive_excel TINYINT(1) NOT NULL DEFAULT 0,
    entered_vol_platform TINYINT(1) NOT NULL DEFAULT 0,
    inserted_in_report TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(120) NOT NULL,
    user_role VARCHAR(40) NOT NULL,
    patient_id INT DEFAULT NULL,
    action_description TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
