<?php
/**
 * Seeder Completo:
 * - Perfis de Acesso (Gisele / Secretaria / Clínico)
 * - Catálogo de Escolas e Siglas Diagnósticas da Planilha
 * - Checklist Padrão Completo das Etapas (Pré-Consulta, Vai Iniciar, Em Avaliação, Devolutiva)
 * - Todos os 91 Pacientes Históricos (2024 a Outubro/2026) fiéis à planilha enviada
 */

function getStandardChecklistDefinitions(): array {
    return [
        // 1. Painel de Acompanhamento Pré-Consulta do Cliente (3 procedimentos monitorados)
        ['group' => 'pre_consulta_cliente', 'code' => 'cli_cadastro', 'title' => 'Cadastro do cliente', 'role' => 'secretaria', 'order' => 1],
        ['group' => 'pre_consulta_cliente', 'code' => 'cli_anamnese_preliminar', 'title' => 'Anamnese preliminar', 'role' => 'secretaria', 'order' => 2],
        ['group' => 'pre_consulta_cliente', 'code' => 'cli_contrato_assinado', 'title' => 'Contrato de prestação de serviço', 'role' => 'secretaria', 'order' => 3],

        // 2. PACIENTES QUE VÃO INICIAR (Procedimentos Internos)
        ['group' => 'vai_iniciar', 'code' => 'ini_enviar_links', 'title' => 'Enviar links para preencher antes da anamnese', 'role' => 'secretaria', 'order' => 1],
        ['group' => 'vai_iniciar', 'code' => 'ini_agenda_anamnese', 'title' => 'Inserir anamnese na agenda', 'role' => 'secretaria', 'order' => 2],
        ['group' => 'vai_iniciar', 'code' => 'ini_cobrar_2_7_dias', 'title' => 'Cobrar o preenchimento 2 a 7 dias após envio', 'role' => 'secretaria', 'order' => 3],
        ['group' => 'vai_iniciar', 'code' => 'ini_verificar_preenchimento', 'title' => 'Verificar se foram preenchidos (Cadastro e Anamnese Preliminar)', 'role' => 'secretaria', 'order' => 4],
        ['group' => 'vai_iniciar', 'code' => 'ini_criar_pasta_drive', 'title' => 'Criar pasta no Google Drive', 'role' => 'secretaria', 'order' => 5],
        ['group' => 'vai_iniciar', 'code' => 'ini_info_anamnese_laudo', 'title' => 'Inserir informações da anamnese no laudo', 'role' => 'clinico', 'order' => 6],
        ['group' => 'vai_iniciar', 'code' => 'ini_cronograma_sessoes', 'title' => 'Criar cronograma das sessões', 'role' => 'secretaria', 'order' => 7],
        ['group' => 'vai_iniciar', 'code' => 'ini_emitir_boletos', 'title' => 'Emitir boletos', 'role' => 'secretaria', 'order' => 8],
        ['group' => 'vai_iniciar', 'code' => 'ini_editar_contrato', 'title' => 'Editar contrato (inserir datas das parcelas dos boletos)', 'role' => 'secretaria', 'order' => 9],
        ['group' => 'vai_iniciar', 'code' => 'ini_gisele_conferir_contrato', 'title' => 'Gisele conferir contrato', 'role' => 'admin_gisele', 'order' => 10],
        ['group' => 'vai_iniciar', 'code' => 'ini_enviar_boletos_contrato', 'title' => 'Enviar boletos e contrato para assinatura', 'role' => 'secretaria', 'order' => 11],
        ['group' => 'vai_iniciar', 'code' => 'ini_montar_pasta_fisica', 'title' => 'Montar pasta física', 'role' => 'secretaria', 'order' => 12],
        ['group' => 'vai_iniciar', 'code' => 'ini_envelope_escalas_casa', 'title' => 'Preparar envelope com escalas para preencher em casa (ver modelo de laudo)', 'role' => 'clinico', 'order' => 13],
        ['group' => 'vai_iniciar', 'code' => 'ini_confirmar_2dias_antes', 'title' => 'Confirmar agendamento 2 dias antes da anamnese', 'role' => 'secretaria', 'order' => 14],
        ['group' => 'vai_iniciar', 'code' => 'ini_tabela_controle_nf', 'title' => 'Inserir dados do paciente na tabela de controle para emissão de NF (nome, CPF, email)', 'role' => 'secretaria', 'order' => 15],

        // 3. PACIENTES EM AVALIAÇÃO (Procedimento Padrão)
        ['group' => 'em_avaliacao', 'code' => 'ava_check_iniciais', 'title' => 'Conferir Início: pasta física, pasta no drive, cronograma, boletos e contrato', 'role' => 'secretaria', 'order' => 1],
        ['group' => 'em_avaliacao', 'code' => 'ava_corrigir_pasta_fisica', 'title' => 'Corrigir e lançar testes aplicados: na pasta física', 'role' => 'clinico', 'order' => 2],
        ['group' => 'em_avaliacao', 'code' => 'ava_lancar_excel_drive', 'title' => 'Corrigir e lançar testes aplicados: nos links de Excel do Drive', 'role' => 'clinico', 'order' => 3],
        ['group' => 'em_avaliacao', 'code' => 'ava_lancar_plataforma_vol', 'title' => 'Corrigir e lançar testes aplicados: na plataforma VOL', 'role' => 'clinico', 'order' => 4],
        ['group' => 'em_avaliacao', 'code' => 'ava_guia_analise_qualitativa', 'title' => 'Incluir funções cognitivas na tabela do Guia de Análise Qualitativa ao final do laudo', 'role' => 'clinico', 'order' => 5],
        ['group' => 'em_avaliacao', 'code' => 'ava_rev_referencias', 'title' => 'Revisar laudo: referências', 'role' => 'clinico', 'order' => 6],
        ['group' => 'em_avaliacao', 'code' => 'ava_rev_paginacao', 'title' => 'Revisar laudo: numeração de páginas', 'role' => 'clinico', 'order' => 7],
        ['group' => 'em_avaliacao', 'code' => 'ava_rev_tabelas', 'title' => 'Revisar laudo: formatação de tabelas', 'role' => 'clinico', 'order' => 8],
        ['group' => 'em_avaliacao', 'code' => 'ava_rev_ortografia', 'title' => 'Revisar laudo: texto (erros de escrita, ortografia, pontuação)', 'role' => 'clinico', 'order' => 9],
        ['group' => 'em_avaliacao', 'code' => 'ava_rev_pronomes', 'title' => 'Revisar laudo: pronomes', 'role' => 'clinico', 'order' => 10],
        ['group' => 'em_avaliacao', 'code' => 'ava_rev_datas', 'title' => 'Revisar laudo: datas', 'role' => 'clinico', 'order' => 11],
        ['group' => 'em_avaliacao', 'code' => 'ava_ok_gisele_laudo', 'title' => 'Aguardar Gisele dar OK para imprimir laudo', 'role' => 'admin_gisele', 'order' => 12],
        ['group' => 'em_avaliacao', 'code' => 'ava_imprimir_laudo', 'title' => 'Imprimir laudo (após OK da Gisele)', 'role' => 'secretaria', 'order' => 13],
        ['group' => 'em_avaliacao', 'code' => 'ava_elaborar_mapa', 'title' => 'Elaborar mapa neuropsicológico', 'role' => 'clinico', 'order' => 14],
        ['group' => 'em_avaliacao', 'code' => 'ava_imprimir_mapa', 'title' => 'Imprimir mapa neuropsicológico para entregar junto com a versão impressa do laudo', 'role' => 'secretaria', 'order' => 15],
        ['group' => 'em_avaliacao', 'code' => 'ava_apresentacao_gamma', 'title' => 'Criar apresentação no Gamma', 'role' => 'clinico', 'order' => 16],
        ['group' => 'em_avaliacao', 'code' => 'ava_escanear_pasta_fisica', 'title' => 'Escanear e desocupar pasta física e colocar o arquivo escaneado no Drive do paciente', 'role' => 'secretaria', 'order' => 17],
        ['group' => 'em_avaliacao', 'code' => 'ava_incluir_dados_tabela', 'title' => 'Incluir dados na tabela estatística/relatórios', 'role' => 'clinico', 'order' => 18],

        // 4. PACIENTES QUE COMPLETARAM A ETAPA DE TESTAGEM E VÃO PARA DEVOLUTIVA
        ['group' => 'para_devolutiva', 'code' => 'dev_questionarios_pais_escola', 'title' => 'Entregar questionários aos pais e à escola', 'role' => 'clinico', 'order' => 1],
        ['group' => 'para_devolutiva', 'code' => 'dev_entrevista_escola', 'title' => 'Fazer entrevista com ESCOLA', 'role' => 'clinico', 'order' => 2],
        ['group' => 'para_devolutiva', 'code' => 'dev_entrevista_informantes', 'title' => 'Fazer entrevista com informantes (familiares ou terapeutas)', 'role' => 'clinico', 'order' => 3],
        ['group' => 'para_devolutiva', 'code' => 'dev_revisao_anamnese_pais', 'title' => 'Enviar anamnese para pais/paciente revisarem', 'role' => 'secretaria', 'order' => 4],
        ['group' => 'para_devolutiva', 'code' => 'dev_imprimir_laudo_qrcode', 'title' => 'Imprimir laudo com QR Code', 'role' => 'secretaria', 'order' => 5],
        ['group' => 'para_devolutiva', 'code' => 'dev_preparar_apresentacao', 'title' => 'Preparar apresentação para devolutiva', 'role' => 'clinico', 'order' => 6],
        ['group' => 'para_devolutiva', 'code' => 'dev_agendar_email_laudo_mapa', 'title' => 'Agendar e-mail para envio de laudo e mapa', 'role' => 'secretaria', 'order' => 7],
        ['group' => 'para_devolutiva', 'code' => 'dev_agendar_devolutiva', 'title' => 'Agendar sessão de devolutiva', 'role' => 'secretaria', 'order' => 8],
        ['group' => 'para_devolutiva', 'code' => 'dev_realizar_entrega_encerrar', 'title' => 'Realizar devolutiva, registrar entrega segura do laudo e encerrar processo', 'role' => 'clinico', 'order' => 9],
    ];
}

function createTasksForPatient(PDO $pdo, int $patientId, string $currentStage, string $sentDate): void {
    $defs = getStandardChecklistDefinitions();
    $stmt = $pdo->prepare("INSERT INTO patient_tasks (patient_id, stage_group, task_code, task_title, responsible_role, status, sent_at, completed_at, completed_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($defs as $d) {
        $status = 'pendente';
        $completedAt = null;
        $completedBy = null;

        if ($currentStage === 'concluido') {
            $status = 'concluido';
            $completedAt = $sentDate . ' 16:00:00';
            $completedBy = 'Sistema (Histórico)';
        } elseif ($currentStage === 'para_devolutiva') {
            if (in_array($d['group'], ['pre_consulta_cliente', 'vai_iniciar'], true)) {
                $status = 'concluido';
                $completedAt = $sentDate . ' 14:00:00';
                $completedBy = 'Secretaria';
            } elseif ($d['group'] === 'em_avaliacao' && $d['order'] <= 11) {
                $status = 'concluido';
                $completedAt = $sentDate . ' 15:00:00';
                $completedBy = 'Equipe Clínica';
            }
        } elseif ($currentStage === 'em_avaliacao') {
            if (in_array($d['group'], ['pre_consulta_cliente', 'vai_iniciar'], true)) {
                $status = 'concluido';
                $completedAt = $sentDate . ' 11:00:00';
                $completedBy = 'Secretaria';
            } elseif ($d['group'] === 'em_avaliacao' && $d['order'] <= 3) {
                $status = 'concluido';
                $completedAt = $sentDate . ' 14:30:00';
                $completedBy = 'Equipe Clínica';
            }
        } elseif ($currentStage === 'vai_iniciar') {
            if ($d['code'] === 'ini_enviar_links' || $d['code'] === 'ini_agenda_anamnese') {
                $status = 'concluido';
                $completedAt = $sentDate . ' 09:30:00';
                $completedBy = 'Secretaria';
            }
        }

        $stmt->execute([
            $patientId,
            $d['group'],
            $d['code'],
            $d['title'],
            $d['role'],
            $status,
            $sentDate,
            $completedAt,
            $completedBy,
            $d['order']
        ]);
    }
}

function seedInitialDatabase(PDO $pdo): void {
    // 1. Usuários (Perfis RBAC)
    $users = [
        ['Gisele (Coordenação)', 'gisele@menteemequilibrio.com.br', password_hash('123456', PASSWORD_DEFAULT), 'admin_gisele'],
        ['Secretaria / Administrativo', 'secretaria@menteemequilibrio.com.br', password_hash('123456', PASSWORD_DEFAULT), 'secretaria'],
        ['Equipe Clínica (Neuropsicóloga)', 'clinico@menteemequilibrio.com.br', password_hash('123456', PASSWORD_DEFAULT), 'clinico'],
    ];
    $uStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    foreach ($users as $u) {
        $uStmt->execute($u);
    }

    // 2. Catálogo de Escolas da Planilha
    $schools = [
        'Colégio Dom Bosco',
        'Salesiano',
        'Escola Um',
        'Santa Ângela',
        'Arte Manhas',
        'Parque Ipiranga',
        'Colégio Aplicação',
        'SESI',
        'Anglo Americano',
        'Céu Encantado - Itatiaia',
        'Casinha da Nieta - Itatiaia',
        'Castelo Branco - Itatiaia',
        'Maple Bear - Itatiaia',
        'Escola pública',
        'Outro'
    ];
    $sStmt = $pdo->prepare("INSERT INTO schools_catalog (name) VALUES (?)");
    foreach ($schools as $s) {
        $sStmt->execute([$s]);
    }

    // 3. Catálogo de Diagnósticos e Siglas da Planilha
    $diagnoses = [
        ['DI', 'Deficiência Intelectual'],
        ['TEA', 'Transtorno do Espectro Autista'],
        ['TDAH', 'Transtorno do Déficit de Atenção e Hiperatividade'],
        ['SD/AH', 'Superdotação / Altas Habilidades'],
        ['TOD', 'Transtorno Opositivo-Desafiador'],
        ['TEAp', 'Tr. Específico de Aprendizagem'],
        ['Distúrbio do som da fala', 'Distúrbio do Som da Fala'],
        ['Transtorno de personalidade', 'Transtorno de Personalidade'],
        ['TAS', 'Tr. Ansiedade Social'],
        ['TAG', 'Tr. Ansiedade Generalizada'],
        ['Transtorno de Conduta', 'Transtorno de Conduta'],
        ['Transtorno por Uso de Substâncias', 'Transtorno por Uso de Substâncias'],
        ['Depressão', 'Episódio ou Transtorno Depressivo'],
        ['Transtorno bipolar', 'Transtorno Afetivo Bipolar'],
        ['Burnout', 'Síndrome de Burnout'],
        ['TNC Maior, Alzheimer', 'Transtorno Neurocognitivo Maior / Doença de Alzheimer'],
        ['Outro', 'Outra Condição / Comorbidade Especificada']
    ];
    $dStmt = $pdo->prepare("INSERT INTO diagnoses_catalog (acronym, full_name) VALUES (?, ?)");
    foreach ($diagnoses as $d) {
        $dStmt->execute($d);
    }

    // 4. Leads de Exemplo no Funil CRM (para demonstrar alertas D+7 e conversão)
    $leads = [
        ['Mariana Souza (Mãe)', 'Pedro Souza', '5524999112233', 'mariana.souza@email.com', 8, 'WhatsApp', 'Dificuldade de concentração na escola, troca letras na escrita e agitação em sala (encaminhado pela coordenação pedagógica).', 'remarketing', '2026-10-12', '2026-10-19', 'Solicitou orçamento para conversar com o marido. Passaram-se mais de 7 dias sem retorno.'],
        ['Carlos Eduardo Mendes', 'Carlos Eduardo Mendes', '5524998223344', 'carlos.mendes@email.com', 34, 'Instagram', 'Queixa de esquecimentos frequentes no trabalho, sobrecarga mental e suspeita de TDAH adulto.', 'aguardando_decisao', '2026-10-18', '2026-10-25', 'Enviado portfólio de diferenciais e formas de parcelamento.'],
        ['Fernanda Oliveira (Mãe)', 'Sofia Oliveira', '5524997334455', 'fernanda.oli@email.com', 5, 'Indicação Médica (Neuropediatra)', 'Atraso no desenvolvimento da fala, seletividade alimentar e baixa interação com pares na educação infantil.', 'novo', '2026-10-22', '2026-10-24', 'Primeiro contato recebido hoje pelo WhatsApp. Deseja saber horários para anamnese.'],
        ['Roberto Almeida (Filho)', 'Dona Helena Almeida', '5524996445566', 'roberto.almeida@email.com', 68, 'WhatsApp', 'Declínio de memória recente nos últimos 8 meses, repetição de perguntas e desorientação temporal leve.', 'remarketing', '2026-10-10', '2026-10-17', 'Pediu informações de valores e retorno posterior. Alerta de 7 dias ativo para envio de conteúdo educativo.']
    ];
    $lStmt = $pdo->prepare("INSERT INTO leads (contact_name, patient_name, phone_whatsapp, email, patient_age, origin, main_complaint, status, first_contact_date, next_followup_date, history_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($leads as $l) {
        $lStmt->execute($l);
    }

    // 5. Base Completa dos 91 Pacientes da Planilha (2024 a Outubro/2026)
    // Formato: [num, ano, mes_ini, mes_fim, idade, faixa, escola, escolaridade, sessoes, d1, d2, d3, d4, comorbidade]
    $rawPatients = [
        [1, '2024', 'junho', 'setembro', 3, '<6 anos', 'Casinha da Nieta - Itatiaia', 'maternal I', '8', 'TDAH', 'TEA', null, null, null],
        [2, '2024', 'agosto', 'novembro', 12, '12 a 17 anos', 'Escola pública', '6º ano do ensino fundamental', '12', 'DI', 'TEA', null, null, null],
        [3, '2024', 'agosto', 'novembro', 8, '6 a 11 anos', 'Outro', '2° ano do ensino fundamental', '14', 'DI', 'TEA', null, null, null],
        [4, '2024', 'setembro', 'novembro', 13, '12 a 17 anos', 'Outro', '7° ano do ensino fundamental', '12', 'TEA', 'SD/AH', null, null, null],
        [5, '2024 e 2025', 'setembro', 'janeiro', 15, '12 a 17 anos', 'Castelo Branco - Itatiaia', '1° ano do ensino médio', '14', 'SD/AH', 'TAS', 'Transtorno de personalidade', null, null],
        [6, '2024', 'setembro', 'novembro', 6, '6 a 11 anos', 'Céu Encantado - Itatiaia', 'jardim de infância III', '10', 'TEA', 'TDAH', 'SD/AH', null, null],
        [7, '2024', 'setembro', 'novembro', 8, '6 a 11 anos', 'Maple Bear - Itatiaia', '2° ano do ensino fundamental', '12', 'SD/AH', 'TDAH', null, null, null],
        [8, '2024', 'outubro', 'dezembro', 10, '6 a 11 anos', 'Colégio Dom Bosco', '5° ano do ensino fundamental', '9', 'SD/AH', 'TDAH', null, null, null],
        [9, '2024', 'outubro', 'dezembro', 62, '50+anos', null, 'ensino médio completo', '12', 'TNC Maior, Alzheimer', null, null, null, null],
        [10, '2024', 'outubro', 'dezembro', 16, '12 a 17 anos', 'Santa Ângela', '2° ano do ensino médio', '12', 'SD/AH', 'TEA', 'TDAH', 'Depressão', 'Transtorno de Ansiedade Social'],
        [11, '2024', 'outubro', 'dezembro', 16, '12 a 17 anos', 'Salesiano', '9° ano do ensino fundamental', '8', 'TEA', null, null, null, null],
        [12, '2024', 'outubro', 'dezembro', 12, '12 a 17 anos', 'Arte Manhas', '6° ano do ensino fundamental', '12', 'TDAH', 'Depressão', 'Transtorno de Conduta', null, null],
        [13, '2024 e 2025', 'novembro', 'fevereiro', 7, '6 a 11 anos', 'Salesiano', '3° ano do ensino fundamental', '12', 'SD/AH', 'TDAH', null, null, null],
        [14, '2024 e 2025', 'novembro', 'janeiro', 9, '6 a 11 anos', 'Casinha da Nieta - Itatiaia', '3° ano do ensino fundamental', '12', 'TDAH', null, null, null, null],
        [15, '2025', 'janeiro', 'abril', 6, '6 a 11 anos', 'Colégio Dom Bosco', '1° ano do ensino fundamental', '12', 'TDAH', 'Distúrbio do som da fala', null, null, null],
        [16, '2024 e 2025', 'dezembro', 'fevereiro', 26, '18+anos', null, 'ensino superior', '12', 'SD/AH', 'TAG', 'Transtorno por Uso de Substâncias', null, null],
        [17, '2025', 'janeiro', 'abril', 8, '6 a 11 anos', 'SESI', '3° ano do ensino fundamental', '12', 'TEA', null, null, null, null],
        [18, '2025', 'janeiro', 'fevereiro', 45, '18+anos', null, 'ensino superior', '12', 'SD/AH', 'TAG', 'Burnout', null, null],
        [19, '2025', 'fevereiro', 'abril', 30, '18+anos', null, 'ensino superior', '10', 'TEA', 'Transtorno bipolar', null, null, null],
        [20, '2025', 'fevereiro', 'abril', 7, '6 a 11 anos', 'Escola pública', '2° ano do ensino fundamental', '10', 'TDAH', null, null, null, null],
        [21, '2025', 'fevereiro', 'março', 3, '<6 anos', 'Casinha da Nieta - Itatiaia', 'maternal II', '6', 'TEA', null, null, null, null],
        [22, '2025', 'fevereiro', 'maio', 11, '6 a 11 anos', 'Colégio Dom Bosco', '6° ano do ensino fundamental', '12', 'TEA', 'SD/AH', null, null, null],
        [23, '2025', 'fevereiro', 'maio', 51, '50+anos', null, 'ensino médio completo', '10', 'TDAH', 'TAG', 'TAS', null, null],
        [24, '2025', 'março', 'julho', 6, '6 a 11 anos', 'Colégio Dom Bosco', 'jardim de infância III', '12', 'TDAH', 'SD/AH', null, null, null],
        [25, '2025', 'março', 'maio', 12, '12 a 17 anos', 'SESI', '7° ano do ensino fundamental', '10', 'TEA', 'SD/AH', null, null, null],
        [26, '2025', 'abril', 'julho', 10, '6 a 11 anos', 'Colégio Dom Bosco', '5° ano do ensino fundamental', '12', 'TDAH', 'TEAp', null, null, null],
        [27, '2025', 'abril', 'junho', 5, '<6 anos', 'Santa Ângela', 'infantil IV', '6', 'TEA', 'SD/AH', null, null, 'Transtorno da Fala'],
        [28, '2025', 'abril', 'julho', 7, '6 a 11 anos', 'Salesiano', '2° ano do ensino fundamental', '11', 'TEA', 'TDAH', 'SD/AH', null, null],
        [29, '2025', 'abril', 'junho', 11, '6 a 11 anos', 'Salesiano', '6° ano do ensino fundamental', '12', 'TEA', 'SD/AH', 'TAG', null, null],
        [30, '2025', 'maio', 'julho', 17, '12 a 17 anos', 'Escola pública', '1° ano do ensino médio', '10', 'DI', null, null, null, null],
        [31, '2025', 'maio', 'agosto', 13, '12 a 17 anos', 'Colégio Dom Bosco', '8° ano do ensino fundamental', '8', 'SD/AH', null, null, null, null],
        [32, '2025', 'maio', 'julho', 52, '50+anos', null, 'pós-graduado', '12', 'TDAH', 'TAG', null, null, null],
        [33, '2025', 'julho', 'agosto', 8, '6 a 11 anos', 'Arte Manhas', '2° ano do ensino fundamental', '11', 'TDAH', 'TOD', null, null, null],
        [34, '2025', 'julho', 'setembro', 32, '18+anos', null, 'superior incompleto', '?', 'TEA', 'TDAH', null, null, null],
        [35, '2025', 'julho', 'agosto', 13, '12 a 17 anos', 'Escola Um', '8° ano do ensino fundamental', '8', 'TDAH', 'SD/AH', null, null, null],
        [36, '2025', 'julho', '--', 8, '6 a 11 anos', 'Santa Ângela', '2° ano do ensino fundamental', '9', 'TEAp', 'TDAH', null, null, 'Transtorno Específico da Aprendizagem com prejuízo em leitura e escrita (Dislexia)'],
        [37, '2025', 'agosto', 'outubro', 5, '<6 anos', 'Casinha da Nieta - Itatiaia', 'pré I', '6', 'SD/AH', null, null, null, null],
        [38, '2025', 'agosto', 'novembro', 18, '18+anos', null, 'ensino médio completo', '12', 'TEA', null, null, null, null],
        [39, '2025', 'agosto', 'outubro', 7, '6 a 11 anos', 'Céu Encantado - Itatiaia', '1° ano do ensino fundamental', '12', 'TEA', null, null, null, null],
        [40, '2025', 'agosto', 'outubro', 14, '12 a 17 anos', 'Salesiano', null, '12', 'Outro', null, null, null, 'Transtorno do Desenvolvimento da Linguagem'],
        [41, '2025', 'setembro', 'novembro', 20, '18+anos', null, 'superior incompleto', '9', 'TEA', null, null, null, null],
        [42, '2025', 'outubro', 'dezembro', 48, '18+anos', null, 'superior', '12', 'TEA', 'Outro', null, null, 'Transtorno Misto Ansioso e Depressivo'],
        [43, '2025', 'outubro', 'dezembro', 24, '18+anos', null, 'superior', '10', 'TEA', null, null, null, null],
        [44, '2025 a 2026', 'outubro', 'fevereiro', 27, '18+anos', null, 'superior incompleto', '13', 'TDAH', 'Transtorno de personalidade', null, null, null],
        [45, '2025', 'outubro', 'dezembro', 42, '18+anos', null, 'superior incompleto', '11', 'TDAH', null, null, null, null],
        [46, '2025 e 2026', 'novembro', 'fevereiro', 40, '18+anos', null, 'pós-graduado', '10', 'TDAH', 'SD/AH', null, null, null],
        [47, '2025 e 2026', 'novembro', 'fevereiro', 34, '18+anos', null, 'pós-graduado', '15', 'Depressão', 'TAG', null, null, null],
        [48, '2025 e 2026', 'dezembro', 'fevereiro', 18, '18+anos', null, 'ensino médio completo', '10', 'Transtorno de personalidade', null, null, null, 'Transtorno de Personalidade Borderline'],
        [49, '2026', 'janeiro', 'fevereiro', 34, '18+anos', null, 'superior incompleto', '7', 'Outro', null, null, null, 'NA'],
        [50, '2026', 'fevereiro', 'março', 6, '6 a 11 anos', 'Colégio Dom Bosco', '1° ano do ensino fundamental', '9', 'SD/AH', null, null, null, null],
        [51, '2026', 'janeiro', 'março', 27, '18+anos', null, 'superior', '8', 'TEA', 'TEAp', null, null, null],
        [52, '2026', 'fevereiro', 'março', 10, '6 a 11 anos', 'Maple Bear - Itatiaia', '5° ano do ensino fundamental', '10', 'TDAH', 'TEAp', null, null, null],
        [53, '2026', 'março', 'maio', 7, '6 a 11 anos', 'Arte Manhas', '1° ano do ensino fundamental', '10', 'Outro', null, null, null, 'Transtorno do Neurodesenvolvimento Não Especificado'],
        [54, '2026', 'fevereiro', 'maio', 44, '18+anos', null, 'superior', '8', 'TEA', 'SD/AH', null, null, null],
        [55, '2026', 'abril', 'junho', 9, '6 a 11 anos', 'SESI', '4° ano do ensino fundamental', '11', 'TDAH', 'SD/AH', null, null, null],
        [56, '2026', 'abril', 'julho', 6, '6 a 11 anos', 'Outro', null, '11', 'Outro', null, null, null, 'Transtorno da comunicação social (pragmática)'],
        [57, '2026', 'abril', 'junho', 22, '18+anos', null, 'superior incompleto', '11', 'Outro', null, null, null, 'Fenótipo Ampliado do Autismo, Funcionamento cognitivo acima da média'],
        [58, '2026', 'abril', 'junho', 3, '<6 anos', 'Outro', 'creche III', '6', 'TDAH', null, null, null, 'Evidências compatíveis com TOD'],
        [59, '2026', 'abril', 'maio', 27, '18+anos', null, 'superior incompleto', '9', 'TEA', 'SD/AH', null, null, null],
        [60, '2026', 'maio', 'julho', 7, '6 a 11 anos', 'Maple Bear - Itatiaia', '2° ano do ensino fundamental', '12', 'TDAH', 'SD/AH', null, null, 'Transtorno do desenvolvimento da coordenação'],
        [61, '2026', 'maio', 'agosto', 57, '50+anos', null, 'mestrado', '14', 'TDAH', 'SD/AH', null, null, null],
        [62, '2026', 'maio', 'julho', 7, '6 a 11 anos', 'Maple Bear - Itatiaia', '2° ano do ensino fundamental', '10', 'TEAp', null, null, null, null],
        [63, '2026', 'maio', 'junho', 11, '6 a 11 anos', 'Colégio Dom Bosco', '6° ano do ensino fundamental', '8', 'TDAH', null, null, null, null],
        [64, '2026', 'maio', 'junho', 22, '18+anos', null, 'superior incompleto', '6', 'TDAH', null, null, null, null],
        [65, '2026', 'maio', 'agosto', 14, '12 a 17 anos', 'Colégio Dom Bosco', '9° ano do ensino fundamental', '10', 'TEA', null, null, null, null],
        [66, '2026', 'maio', 'julho', 9, '6 a 11 anos', 'SESI', '4° ano do ensino fundamental', '9', 'TEA', 'DI', null, null, null],
        [67, '2026', 'maio', 'agosto', 11, '6 a 11 anos', 'Santa Ângela', '6° ano do ensino fundamental', '10', 'TDAH', null, null, null, null],
        [68, '2026', 'maio', 'julho', 34, '18+anos', null, 'superior completo', '11', 'Outro', null, null, null, 'NA'],
        [69, '2026', 'junho', 'julho', 10, '6 a 11 anos', 'Santa Ângela', '5° ano do ensino fundamental', '10', 'TDAH', null, null, null, null],
        [70, '2026', 'julho', 'outubro', 49, '18+anos', null, 'pós-graduado', '10', 'SD/AH', null, null, null, null],
        [71, '2026', 'julho', 'outubro', 9, '6 a 11 anos', 'Salesiano', '3° ano do ensino fundamental', '9', 'TDAH', null, null, null, null],
        [72, '2026', 'julho', 'outubro', 8, '6 a 11 anos', 'Outro', '2° ano do ensino fundamental', null, null, null, null, null, null],
        [73, '2026', 'julho', null, null, null, null, null, null, null, null, null, null, null],
        [74, '2026', 'julho', 'setembro', 8, '6 a 11 anos', 'Salesiano', '3° ano do ensino fundamental', '12', 'TEA', null, null, null, null],
        [75, '2026', 'agosto', null, null, null, null, null, null, null, null, null, null, null],
        [76, '2026', 'agosto', null, null, null, null, null, null, null, null, null, null, null],
        [77, '2026', 'agosto', 'outubro', 19, '18+anos', null, 'superior incompleto', '8', 'TDAH', null, null, null, null],
        [78, '2026', 'agosto', null, null, null, null, null, null, null, null, null, null, null],
        [79, '2026', 'agosto', null, null, null, null, null, null, null, null, null, null, null],
        [80, '2026', 'agosto', null, null, null, null, null, null, null, null, null, null, null],
        [81, '2026', 'agosto', null, 19, '18+anos', null, 'ensino médio completo', null, null, null, null, null, null],
        [82, '2026', 'setembro', null, 7, '6 a 11 anos', 'Santa Ângela', '2° ano do ensino fundamental', null, null, null, null, null, null],
        [83, '2026', 'setembro', null, 17, '12 a 17 anos', 'Salesiano', '2° série do ensino médio', null, null, null, null, null, null],
        [84, '2026', 'setembro', null, 16, '12 a 17 anos', 'Santa Ângela', '1° ano do ensino médio', null, null, null, null, null, null],
        [85, '2026', 'setembro', null, 41, '18+anos', null, 'superior completo', null, null, null, null, null, null],
        [86, '2026', 'setembro', null, 18, '18+anos', 'Escola pública', '3° ano do ensino médio CEJA', null, null, null, null, null, null],
        [87, '2026', 'setembro', null, 77, '50+anos', null, null, null, null, null, null, null, null],
        [88, '2026', 'outubro', null, 38, '18+anos', null, 'pós-graduado', null, null, null, null, null, null],
        [89, '2026', 'outubro', null, 33, '18+anos', null, 'pós-graduado', null, null, null, null, null, null],
        [90, '2026', 'outubro', null, 7, '6 a 11 anos', 'Escola pública', '1° ano do ensino fundamental', null, null, null, null, null, null],
        [91, '2026', 'outubro', null, 16, '12 a 17 anos', 'Salesiano', '1° ano do ensino médio', null, null, null, null, null, null],
    ];

    $pStmt = $pdo->prepare("INSERT INTO patients (
        code_number, full_name, responsible_name, cpf_nf, email_nf, phone_whatsapp,
        age, age_group, school_name, education_level, address_full,
        reference_year, start_month, end_month, total_sessions,
        diag_1, diag_2, diag_3, diag_4, unlisted_comorbidity, final_diagnosis,
        current_stage, anamnesis_date, devolutiva_date, drive_folder_url, gamma_url,
        contract_total_value, contract_installments, contract_installments_json,
        contract_gisele_approved, laudo_gisele_approved, nf_issued, public_token
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $testStmt = $pdo->prepare("INSERT INTO patient_tests (patient_id, instrument_name, scored_physical_folder, entered_drive_excel, entered_vol_platform, inserted_in_report) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($rawPatients as $rp) {
        $num = $rp[0];
        $year = $rp[1];
        $mIni = $rp[2];
        $mFim = $rp[3];
        $age = $rp[4];
        $ageGroup = $rp[5];
        $school = $rp[6];
        $edu = $rp[7];
        $sessions = $rp[8];
        $d1 = $rp[9];
        $d2 = $rp[10];
        $d3 = $rp[11];
        $d4 = $rp[12];
        $comorb = $rp[13];

        // Define o estágio atual com base no preenchimento da planilha (pacientes de ago/set/out 2026 em aberto ficam nas filas operacionais ativas)
        if ($mFim !== null && $mFim !== '--') {
            $stage = 'concluido';
        } elseif ($num >= 88) {
            $stage = 'vai_iniciar';
        } elseif ($num >= 81) {
            $stage = 'em_avaliacao';
        } else {
            $stage = 'para_devolutiva';
        }

        $patientName = "Paciente #" . str_pad((string)$num, 2, '0', STR_PAD_LEFT) . ($school ? " ({$school})" : "");
        $respName = ($age !== null && $age < 18) ? "Responsável do Paciente #{$num}" : $patientName;
        $cpf = sprintf("%03d.%03d.%03d-%02d", 100 + $num, 200 + $num, 300 + $num, ($num % 90) + 10);
        $email = "paciente{$num}@exemplo.com.br";
        $phone = "5524999" . str_pad((string)(100000 + $num), 6, '0', STR_PAD_LEFT);
        $address = "Rua Exemplo, nº " . ($num * 10) . " - Centro, Itatiaia / Resende - RJ";
        $token = bin2hex(random_bytes(12));

        $installmentsJson = json_encode([
            ['parcela' => 1, 'vencimento' => '2026-10-25', 'valor' => '700,00', 'status' => $stage === 'concluido' ? 'Pago' : 'Aberto'],
            ['parcela' => 2, 'vencimento' => '2026-11-25', 'valor' => '700,00', 'status' => $stage === 'concluido' ? 'Pago' : 'Aberto'],
            ['parcela' => 3, 'vencimento' => '2026-12-25', 'valor' => '700,00', 'status' => $stage === 'concluido' ? 'Pago' : 'Aberto'],
            ['parcela' => 4, 'vencimento' => '2027-01-25', 'valor' => '700,00', 'status' => $stage === 'concluido' ? 'Pago' : 'Aberto'],
        ], JSON_UNESCAPED_UNICODE);

        $anamnesisDate = $stage === 'vai_iniciar' ? '2026-10-24' : '2026-09-15';
        $devolutivaDate = $stage === 'para_devolutiva' ? '2026-10-28' : null;
        $driveUrl = "https://drive.google.com/drive/folders/paciente-" . $num;
        $gammaUrl = ($stage === 'concluido' || $stage === 'para_devolutiva') ? "https://gamma.app/docs/devolutiva-paciente-" . $num : "";

        $contractApproved = ($stage === 'concluido' || $stage === 'em_avaliacao' || $stage === 'para_devolutiva') ? 1 : 0;
        $laudoApproved = ($stage === 'concluido') ? 1 : 0;
        $nfIssued = ($stage === 'concluido') ? 1 : 0;

        $pStmt->execute([
            $num, $patientName, $respName, $cpf, $email, $phone,
            $age, $ageGroup, $school, $edu, $address,
            $year, $mIni, $mFim, $sessions,
            $d1, $d2, $d3, $d4, $comorb, $d1,
            $stage, $anamnesisDate, $devolutivaDate, $driveUrl, $gammaUrl,
            2800.00, 4, $installmentsJson,
            $contractApproved, $laudoApproved, $nfIssued, $token
        ]);

        $patientId = (int)$pdo->lastInsertId();
        $sentDate = $stage === 'vai_iniciar' ? '2026-10-18' : '2026-09-10';
        createTasksForPatient($pdo, $patientId, $stage, $sentDate);

        // Adiciona instrumentos de teste de exemplo para pacientes em avaliação ou devolutiva
        if ($stage !== 'vai_iniciar') {
            $done = $stage === 'concluido' ? 1 : 0;
            $testStmt->execute([$patientId, 'WISC-IV / WAIS-III (Escala Wechsler)', 1, 1, 1, $done]);
            $testStmt->execute([$patientId, 'BPA-2 (Bateria Psicológica para Atenção)', 1, 1, $done, $done]);
            $testStmt->execute([$patientId, 'SRS-2 / ETDAH-AD (Escalas na Plataforma VOL)', 1, $done, $done, $done]);
        }
    }
}
