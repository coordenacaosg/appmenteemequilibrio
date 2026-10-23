<?php
/**
 * Controlador de Ações POST (CRM, Checklists, Testes, Aprovações da Gisele, Contratos, NF e Epidemiológico)
 */
require_once __DIR__ . '/helpers.php';

function handlePostActions(PDO $pdo, array $currentUser): ?string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return null;
    }

    $action = $_POST['action'] ?? '';

    // 1. Troca rápida de Perfil (Secretaria / Clínico / Gisele)
    if ($action === 'switch_user') {
        $uid = (int)($_POST['user_id'] ?? 1);
        $_SESSION['user_id'] = $uid;
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }

    // 2. Criar Novo Lead no CRM
    if ($action === 'create_lead') {
        $stmt = $pdo->prepare("INSERT INTO leads (contact_name, patient_name, phone_whatsapp, email, patient_age, origin, main_complaint, status, first_contact_date, next_followup_date, history_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $contactDate = $_POST['first_contact_date'] ?: date('Y-m-d');
        $followupDate = date('Y-m-d', strtotime($contactDate . ' +7 days'));
        $status = $_POST['status'] ?: 'novo';
        $stmt->execute([
            trim($_POST['contact_name'] ?? ''),
            trim($_POST['patient_name'] ?? ''),
            trim($_POST['phone_whatsapp'] ?? ''),
            trim($_POST['email'] ?? ''),
            $_POST['patient_age'] !== '' ? (int)$_POST['patient_age'] : null,
            trim($_POST['origin'] ?? 'WhatsApp'),
            trim($_POST['main_complaint'] ?? ''),
            $status,
            $contactDate,
            $followupDate,
            trim($_POST['history_notes'] ?? 'Primeiro contato registrado no sistema.')
        ]);
        logAudit($pdo, $currentUser, null, "Cadastrou novo Lead no CRM: " . ($_POST['patient_name'] ?? ''));
        return "Lead cadastrado com sucesso! Lembrete de acompanhamento de 7 dias programado automaticamente.";
    }

    // 3. Atualizar Status do Lead
    if ($action === 'update_lead_status') {
        $leadId = (int)$_POST['lead_id'];
        $status = $_POST['status'] ?? 'aguardando_decisao';
        $notes = trim($_POST['history_notes'] ?? '');
        $pdo->prepare("UPDATE leads SET status=?, history_notes=? WHERE id=?")->execute([$status, $notes, $leadId]);
        logAudit($pdo, $currentUser, null, "Atualizou status do Lead #{$leadId} para {$status}");
        return "Status do lead atualizado!";
    }

    // 4. Converter Lead em Paciente (Agendar Anamnese e Gerar Checklists)
    if ($action === 'convert_lead_to_patient') {
        $leadId = (int)$_POST['lead_id'];
        $anamnesisDate = $_POST['anamnesis_date'] ?: date('Y-m-d', strtotime('+5 days'));

        $lStmt = $pdo->prepare("SELECT * FROM leads WHERE id=?");
        $lStmt->execute([$leadId]);
        $lead = $lStmt->fetch();
        if ($lead) {
            $maxNum = (int)$pdo->query("SELECT COALESCE(MAX(code_number), 91) FROM patients")->fetchColumn() + 1;
            $age = $lead['patient_age'] !== null ? (int)$lead['patient_age'] : null;
            $ageGroup = calculateAgeGroup($age);
            $token = bin2hex(random_bytes(12));
            $contractModel = ($age !== null && $age < 18) ? 'infantil' : 'adulto';

            $monthsPt = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
            $curMonth = $monthsPt[(int)date('n')];

            // Padrão oficial: R$ 4.500,00 em 3 parcelas de R$ 1.500,00 no boleto
            $defaultInstallments = json_encode([
                ['parcela' => 1, 'vencimento' => date('Y-m-d', strtotime('+5 days')), 'valor' => '1.500,00', 'status' => 'Emitido'],
                ['parcela' => 2, 'vencimento' => date('Y-m-d', strtotime('+35 days')), 'valor' => '1.500,00', 'status' => 'Emitido'],
                ['parcela' => 3, 'vencimento' => date('Y-m-d', strtotime('+65 days')), 'valor' => '1.500,00', 'status' => 'Emitido'],
            ], JSON_UNESCAPED_UNICODE);

            $ins = $pdo->prepare("INSERT INTO patients (code_number, lead_id, full_name, responsible_name, email_nf, phone_whatsapp, age, age_group, reference_year, start_month, current_stage, anamnesis_date, contract_model, contract_total_value, contract_installments, contract_installments_json, public_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'vai_iniciar', ?, ?, 4500.00, 3, ?, ?)");
            $ins->execute([
                $maxNum,
                $leadId,
                $lead['patient_name'],
                $lead['contact_name'],
                $lead['email'],
                $lead['phone_whatsapp'],
                $age,
                $ageGroup,
                date('Y'),
                $curMonth,
                $anamnesisDate,
                $contractModel,
                $defaultInstallments,
                $token
            ]);
            $newPatientId = (int)$pdo->lastInsertId();

            require_once __DIR__ . '/../database/seed_data.php';
            createTasksForPatient($pdo, $newPatientId, 'vai_iniciar', date('Y-m-d'));

            $pdo->prepare("UPDATE leads SET status='convertido', converted_patient_id=? WHERE id=?")->execute([$newPatientId, $leadId]);
            logAudit($pdo, $currentUser, $newPatientId, "Converteu Lead #{$leadId} em Paciente #{$maxNum} com anamnese agendada para {$anamnesisDate}");
            header("Location: index.php?tab=clientes&patient_id={$newPatientId}&msg=converteu");
            exit;
        }
    }

    // 5. Criar Novo Paciente Diretamente
    if ($action === 'create_patient') {
        $maxNum = (int)$pdo->query("SELECT COALESCE(MAX(code_number), 91) FROM patients")->fetchColumn() + 1;
        $age = $_POST['age'] !== '' ? (int)$_POST['age'] : null;
        $ageGroup = calculateAgeGroup($age);
        $token = bin2hex(random_bytes(12));
        $stage = $_POST['current_stage'] ?: 'vai_iniciar';
        $contractModel = ($age !== null && $age < 18) ? 'infantil' : 'adulto';

        $defaultInstallments = json_encode([
            ['parcela' => 1, 'vencimento' => date('Y-m-d', strtotime('+5 days')), 'valor' => '1.500,00', 'status' => 'Aberto'],
            ['parcela' => 2, 'vencimento' => date('Y-m-d', strtotime('+35 days')), 'valor' => '1.500,00', 'status' => 'Aberto'],
            ['parcela' => 3, 'vencimento' => date('Y-m-d', strtotime('+65 days')), 'valor' => '1.500,00', 'status' => 'Aberto'],
        ], JSON_UNESCAPED_UNICODE);

        $ins = $pdo->prepare("INSERT INTO patients (code_number, full_name, responsible_name, cpf_nf, email_nf, phone_whatsapp, age, age_group, school_name, education_level, reference_year, start_month, current_stage, anamnesis_date, contract_model, contract_total_value, contract_installments, contract_installments_json, public_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 4500.00, 3, ?, ?)");
        $ins->execute([
            $maxNum,
            trim($_POST['full_name'] ?? "Paciente #{$maxNum}"),
            trim($_POST['responsible_name'] ?? ''),
            trim($_POST['cpf_nf'] ?? ''),
            trim($_POST['email_nf'] ?? ''),
            trim($_POST['phone_whatsapp'] ?? ''),
            $age,
            $ageGroup,
            trim($_POST['school_name'] ?? ''),
            trim($_POST['education_level'] ?? ''),
            trim($_POST['reference_year'] ?? date('Y')),
            trim($_POST['start_month'] ?? 'outubro'),
            $stage,
            $_POST['anamnesis_date'] ?: date('Y-m-d', strtotime('+4 days')),
            $contractModel,
            $defaultInstallments,
            $token
        ]);
        $newPatientId = (int)$pdo->lastInsertId();
        require_once __DIR__ . '/../database/seed_data.php';
        createTasksForPatient($pdo, $newPatientId, $stage, date('Y-m-d'));
        logAudit($pdo, $currentUser, $newPatientId, "Cadastrou novo Paciente #{$maxNum}");
        header("Location: index.php?tab=clientes&patient_id={$newPatientId}");
        exit;
    }

    // 6. Alternar Status de Item do Checklist (Concluído <-> Pendente)
    if ($action === 'toggle_task') {
        $taskId = (int)$_POST['task_id'];
        $patientId = (int)$_POST['patient_id'];
        $tStmt = $pdo->prepare("SELECT * FROM patient_tasks WHERE id=?");
        $tStmt->execute([$taskId]);
        $task = $tStmt->fetch();

        if ($task) {
            $newStatus = ($task['status'] === 'concluido') ? 'pendente' : 'concluido';
            $compAt = ($newStatus === 'concluido') ? date('Y-m-d H:i:s') : null;
            $compBy = ($newStatus === 'concluido') ? $currentUser['name'] : null;

            $pdo->prepare("UPDATE patient_tasks SET status=?, completed_at=?, completed_by=? WHERE id=?")
                ->execute([$newStatus, $compAt, $compBy, $taskId]);

            // Sincroniza flags especiais da Gisele
            if ($task['task_code'] === 'ini_gisele_conferir_contrato') {
                $pdo->prepare("UPDATE patients SET contract_gisele_approved=?, contract_gisele_approved_at=? WHERE id=?")
                    ->execute([$newStatus === 'concluido' ? 1 : 0, $compAt, $patientId]);
            } elseif ($task['task_code'] === 'ava_ok_gisele_laudo') {
                $pdo->prepare("UPDATE patients SET laudo_gisele_approved=?, laudo_gisele_approved_at=? WHERE id=?")
                    ->execute([$newStatus === 'concluido' ? 1 : 0, $compAt, $patientId]);
            }

            // Se todos os 3 procedimentos de pré-consulta foram concluídos, marca 'ini_verificar_preenchimento'
            if ($task['stage_group'] === 'pre_consulta_cliente') {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM patient_tasks WHERE patient_id=? AND stage_group='pre_consulta_cliente' AND status != 'concluido'");
                $chk->execute([$patientId]);
                if ((int)$chk->fetchColumn() === 0) {
                    $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Automação' WHERE patient_id=? AND task_code='ini_verificar_preenchimento'")->execute([$patientId]);
                }
            }

            logAudit($pdo, $currentUser, $patientId, ($newStatus === 'concluido' ? "Concluiu" : "Reabriu") . " tarefa: {$task['task_title']}");
        }
        return "Checklist atualizado com sucesso!";
    }

    // 7. Reiniciar Simulação dos 3 Procedimentos de Pré-Consulta
    if ($action === 'reset_pre_consulta') {
        $patientId = (int)$_POST['patient_id'];
        $pdo->prepare("UPDATE patient_tasks SET status='pendente', completed_at=NULL, completed_by=NULL WHERE patient_id=? AND stage_group='pre_consulta_cliente'")->execute([$patientId]);
        return "Simulação de pré-consulta reiniciada (0/3 procedimentos concluídos).";
    }

    // 8. Atualizar Dados Gerais, Links (Drive/Gamma/Google Forms), Etapa e Dados Epidemiológicos do Paciente
    if ($action === 'update_patient_details') {
        $patientId = (int)$_POST['patient_id'];
        $age = $_POST['age'] !== '' ? (int)$_POST['age'] : null;
        $ageGroup = $_POST['age_group'] ?: calculateAgeGroup($age);

        $contractModel = trim($_POST['contract_model'] ?? '');
        if ($contractModel === '') {
            $contractModel = ($age !== null && $age < 18) ? 'infantil' : 'adulto';
        }

        $up = $pdo->prepare("UPDATE patients SET
            full_name=?, responsible_name=?, cpf_nf=?, email_nf=?, phone_whatsapp=?,
            age=?, age_group=?, school_name=?, education_level=?, address_full=?,
            reference_year=?, start_month=?, end_month=?, total_sessions=?,
            diag_1=?, diag_2=?, diag_3=?, diag_4=?, unlisted_comorbidity=?, final_diagnosis=?,
            current_stage=?, anamnesis_date=?, devolutiva_date=?,
            drive_folder_url=?, gamma_url=?, google_form_cadastro_url=?, google_form_anamnese_url=?,
            home_scales_notes=?, school_interview_notes=?, informant_interview_notes=?,
            contract_model=?, responsible_kinship_1=?, responsible_phone_1=?,
            responsible_name_2=?, responsible_cpf_2=?, responsible_phone_2=?, responsible_kinship_2=?
            WHERE id=?");

        $up->execute([
            trim($_POST['full_name'] ?? ''),
            trim($_POST['responsible_name'] ?? ''),
            trim($_POST['cpf_nf'] ?? ''),
            trim($_POST['email_nf'] ?? ''),
            trim($_POST['phone_whatsapp'] ?? ''),
            $age,
            $ageGroup,
            trim($_POST['school_name'] ?? '') ?: null,
            trim($_POST['education_level'] ?? '') ?: null,
            trim($_POST['address_full'] ?? ''),
            trim($_POST['reference_year'] ?? ''),
            trim($_POST['start_month'] ?? '') ?: null,
            trim($_POST['end_month'] ?? '') ?: null,
            trim($_POST['total_sessions'] ?? '') ?: null,
            trim($_POST['diag_1'] ?? '') ?: null,
            trim($_POST['diag_2'] ?? '') ?: null,
            trim($_POST['diag_3'] ?? '') ?: null,
            trim($_POST['diag_4'] ?? '') ?: null,
            trim($_POST['unlisted_comorbidity'] ?? '') ?: null,
            trim($_POST['diag_1'] ?? '') ?: null,
            $_POST['current_stage'] ?? 'vai_iniciar',
            $_POST['anamnesis_date'] ?: null,
            $_POST['devolutiva_date'] ?: null,
            trim($_POST['drive_folder_url'] ?? ''),
            trim($_POST['gamma_url'] ?? ''),
            trim($_POST['google_form_cadastro_url'] ?? ''),
            trim($_POST['google_form_anamnese_url'] ?? ''),
            trim($_POST['home_scales_notes'] ?? ''),
            trim($_POST['school_interview_notes'] ?? ''),
            trim($_POST['informant_interview_notes'] ?? ''),
            $contractModel,
            trim($_POST['responsible_kinship_1'] ?? ''),
            trim($_POST['responsible_phone_1'] ?? ''),
            trim($_POST['responsible_name_2'] ?? ''),
            trim($_POST['responsible_cpf_2'] ?? ''),
            trim($_POST['responsible_phone_2'] ?? ''),
            trim($_POST['responsible_kinship_2'] ?? ''),
            $patientId
        ]);

        logAudit($pdo, $currentUser, $patientId, "Atualizou prontuário, links e dados epidemiológicos do paciente.");
        return "Prontuário, links e dados estatísticos salvos com sucesso!";
    }

    // 9. Adicionar ou Atualizar Instrumento de Teste (Pasta Física / Excel Drive / VOL / Laudo)
    if ($action === 'add_test') {
        $patientId = (int)$_POST['patient_id'];
        $name = trim($_POST['instrument_name'] ?? '');
        if ($name !== '') {
            $pdo->prepare("INSERT INTO patient_tests (patient_id, instrument_name, scored_physical_folder, entered_drive_excel, entered_vol_platform, inserted_in_report) VALUES (?, ?, 0, 0, 0, 0)")
                ->execute([$patientId, $name]);
            logAudit($pdo, $currentUser, $patientId, "Adicionou instrumento de teste: {$name}");
        }
        return "Instrumento adicionado à bateria do paciente!";
    }

    if ($action === 'update_test_flags') {
        $testId = (int)$_POST['test_id'];
        $patientId = (int)$_POST['patient_id'];
        $f1 = isset($_POST['scored_physical_folder']) ? 1 : 0;
        $f2 = isset($_POST['entered_drive_excel']) ? 1 : 0;
        $f3 = isset($_POST['entered_vol_platform']) ? 1 : 0;
        $f4 = isset($_POST['inserted_in_report']) ? 1 : 0;
        $pdo->prepare("UPDATE patient_tests SET scored_physical_folder=?, entered_drive_excel=?, entered_vol_platform=?, inserted_in_report=? WHERE id=?")
            ->execute([$f1, $f2, $f3, $f4, $testId]);
        return "Status de correção/lançamento do teste atualizado!";
    }

    // 10. Salvar Boletos / Parcelas do Contrato e Controle de NF
    if ($action === 'save_contract_boletos') {
        $patientId = (int)$_POST['patient_id'];
        $totalVal = (float)str_replace(['R$', ' ', '.'], ['', '', ''], str_replace(',', '.', $_POST['contract_total_value'] ?? '4500'));
        $contractModel = in_array($_POST['contract_model'] ?? '', ['adulto', 'infantil']) ? $_POST['contract_model'] : 'adulto';
        $contractPaymentMethod = trim($_POST['contract_payment_method'] ?? 'boleto_3x');

        $respName = trim($_POST['responsible_name'] ?? '');
        $respName2 = trim($_POST['responsible_name_2'] ?? '');
        $respCpf2 = trim($_POST['responsible_cpf_2'] ?? '');
        $respPhone2 = trim($_POST['responsible_phone_2'] ?? '');
        $respKinship1 = trim($_POST['responsible_kinship_1'] ?? '');
        $respKinship2 = trim($_POST['responsible_kinship_2'] ?? '');
        $respAddress2 = trim($_POST['responsible_address_2'] ?? '');

        $dates = $_POST['boleto_date'] ?? [];
        $vals = $_POST['boleto_val'] ?? [];
        $statuses = $_POST['boleto_status'] ?? [];

        $instList = [];
        foreach ($dates as $idx => $dt) {
            if (trim($dt) !== '') {
                $instList[] = [
                    'parcela' => $idx + 1,
                    'vencimento' => $dt,
                    'valor' => $vals[$idx] ?? '1.500,00',
                    'status' => $statuses[$idx] ?? 'Emitido'
                ];
            }
        }
        $count = count($instList) ?: 1;
        $nfIssued = isset($_POST['nf_issued']) ? 1 : 0;
        $nfAt = $nfIssued ? date('Y-m-d H:i:s') : null;

        $pdo->prepare("UPDATE patients SET contract_model=?, contract_payment_method=?, responsible_name=COALESCE(NULLIF(?, ''), responsible_name), responsible_name_2=?, responsible_cpf_2=?, responsible_phone_2=?, responsible_kinship_1=?, responsible_kinship_2=?, responsible_address_2=?, contract_total_value=?, contract_installments=?, contract_installments_json=?, nf_issued=?, nf_issued_at=? WHERE id=?")
            ->execute([$contractModel, $contractPaymentMethod, $respName, $respName2, $respCpf2, $respPhone2, $respKinship1, $respKinship2, $respAddress2, $totalVal, $count, json_encode($instList, JSON_UNESCAPED_UNICODE), $nfIssued, $nfAt, $patientId]);

        $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by=? WHERE patient_id=? AND task_code IN ('ini_emitir_boletos', 'ini_editar_contrato')")
            ->execute([$currentUser['name'], $patientId]);

        logAudit($pdo, $currentUser, $patientId, "Atualizou contrato (modelo: {$contractModel}), representantes legais e parcelas.");
        return "Contrato, representantes e cláusula de pagamento atualizados com sucesso!";
    }

    // 11. Aprovação Rápida da Gisele (Contrato ou Impressão de Laudo)
    if ($action === 'gisele_quick_approve') {
        $patientId = (int)$_POST['patient_id'];
        $approveType = $_POST['approve_type'] ?? 'contrato';
        if ($approveType === 'contrato') {
            $pdo->prepare("UPDATE patients SET contract_gisele_approved=1, contract_gisele_approved_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$patientId]);
            $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Gisele (Coordenação)' WHERE patient_id=? AND task_code='ini_gisele_conferir_contrato'")->execute([$patientId]);
            logAudit($pdo, $currentUser, $patientId, "Gisele aprovou o Contrato de Prestação de Serviço.");
            return "Contrato conferido e aprovado por Gisele!";
        } else {
            $pdo->prepare("UPDATE patients SET laudo_gisele_approved=1, laudo_gisele_approved_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$patientId]);
            $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Gisele (Coordenação)' WHERE patient_id=? AND task_code='ava_ok_gisele_laudo'")->execute([$patientId]);
            logAudit($pdo, $currentUser, $patientId, "Gisele deu OK para impressão do Laudo Neuropsicológico.");
            return "Impressão do laudo autorizada por Gisele!";
        }
    }

    return null;
}
