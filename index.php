<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/actions.php';
require_once __DIR__ . '/includes/metrics.php';

$pdo = getDbConnection();
$currentUser = getCurrentUser($pdo);
$flashMsg = handlePostActions($pdo, $currentUser);
if (isset($_GET['msg']) && $_GET['msg'] === 'converteu') {
    $flashMsg = "Lead convertido em Paciente com sucesso! Os 3 procedimentos de pré-consulta e o checklist operacional foram gerados automaticamente.";
}

$metrics = computeSystemMetrics($pdo);
$patients = $metrics['patients'];
$leads = $metrics['leads'];
$allUsers = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();
$schoolsCatalog = $pdo->query("SELECT name FROM schools_catalog ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$diagnosesCatalog = $pdo->query("SELECT * FROM diagnoses_catalog ORDER BY id ASC")->fetchAll();

$activeTab = $_GET['tab'] ?? 'clientes';
$stageFilter = $_GET['stage'] ?? 'ativos';

// Seleciona paciente ativo para a Visão 1 (Por Cliente)
$selectedPatientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if ($selectedPatientId <= 0) {
    foreach ($patients as $p) {
        if ($p['current_stage'] === 'vai_iniciar') {
            $selectedPatientId = (int)$p['id'];
            break;
        }
    }
    if ($selectedPatientId <= 0 && !empty($patients)) {
        $selectedPatientId = (int)end($patients)['id'];
    }
}

$selectedPatient = null;
foreach ($patients as $p) {
    if ((int)$p['id'] === $selectedPatientId) {
        $selectedPatient = $p;
        break;
    }
}

$patientTasksByGroup = [
    'pre_consulta_cliente' => [],
    'vai_iniciar' => [],
    'em_avaliacao' => [],
    'para_devolutiva' => []
];
$patientTests = [];

if ($selectedPatient) {
    $tStmt = $pdo->prepare("SELECT * FROM patient_tasks WHERE patient_id = ? ORDER BY sort_order ASC, id ASC");
    $tStmt->execute([$selectedPatient['id']]);
    foreach ($tStmt->fetchAll() as $tRow) {
        $patientTasksByGroup[$tRow['stage_group']][] = $tRow;
    }

    $testStmt = $pdo->prepare("SELECT * FROM patient_tests WHERE patient_id = ? ORDER BY id ASC");
    $testStmt->execute([$selectedPatient['id']]);
    $patientTests = $testStmt->fetchAll();
}

// Filtra pacientes com pendências de aprovação da Gisele
$pendingGiseleContracts = array_filter($patients, fn($p) => $p['current_stage'] === 'vai_iniciar' && !(int)$p['contract_gisele_approved']);
$pendingGiseleLaudos = array_filter($patients, fn($p) => in_array($p['current_stage'], ['em_avaliacao', 'para_devolutiva'], true) && !(int)$p['laudo_gisele_approved']);
$remarketingLeads = array_filter($leads, fn($l) => in_array($l['status'], ['remarketing', 'aguardando_decisao'], true));
$baseUrl = getBaseAppUrl();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mente em Equilíbrio — Sistema de Gestão da Avaliação Neuropsicológica</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col">

    <!-- Topbar Principal -->
    <header class="bg-teal-900 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-teal-700 flex items-center justify-center font-bold text-lg shadow-inner border border-teal-500">
                    🧠
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight">App Mente em Equilíbrio</h1>
                    <p class="text-xs text-teal-200">CRM • Gestão Operacional de Avaliação Neuropsicológica • Painel Epidemiológico</p>
                </div>
            </div>

            <!-- Indicadores Rápidos & Seletor de Perfil RBAC -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="hidden lg:flex items-center gap-2 text-xs bg-teal-800/80 px-3 py-1.5 rounded-lg border border-teal-700">
                    <span>Vai Iniciar: <strong class="text-amber-300"><?= $metrics['stage_counts']['vai_iniciar'] ?></strong></span>
                    <span>•</span>
                    <span>Em Avaliação: <strong class="text-sky-300"><?= $metrics['stage_counts']['em_avaliacao'] ?></strong></span>
                    <span>•</span>
                    <span>Devolutiva: <strong class="text-purple-300"><?= $metrics['stage_counts']['para_devolutiva'] ?></strong></span>
                    <span>•</span>
                    <span>Concluídos: <strong class="text-emerald-300"><?= $metrics['stage_counts']['concluido'] ?></strong></span>
                </div>

                <form method="POST" class="flex items-center gap-2 bg-teal-950/70 px-3 py-1.5 rounded-xl border border-teal-700">
                    <input type="hidden" name="action" value="switch_user">
                    <span class="text-[11px] text-teal-300 uppercase font-semibold">Perfil Ativo:</span>
                    <select name="user_id" onchange="this.form.submit()" class="bg-teal-800 text-white text-xs font-semibold rounded-lg px-2.5 py-1 border border-teal-600 focus:outline-none">
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (int)$u['id'] === (int)$currentUser['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Barra de Navegação entre os Módulos -->
        <div class="bg-teal-800/90 border-t border-teal-700">
            <div class="max-w-7xl mx-auto px-4 flex overflow-x-auto gap-1 py-1.5 text-xs font-semibold">
                <?php
                $navTabs = [
                    'clientes' => '👤 Visão 1: Por Cliente (Checklists & Pré-Consulta)',
                    'processos' => '📊 Visão 2: Por Processos Geral (Gargalos em Lote)',
                    'crm' => '💬 CRM de Leads & Remarketing (WhatsApp)',
                    'alertas' => '🔔 Alertas (D+2 / D+7 / D-2) & Fila da Gisele (' . (count($pendingGiseleContracts) + count($pendingGiseleLaudos)) . ')',
                    'financeiro' => '📄 Contratos, Boletos & Controle de NF',
                    'epidemiologico' => '📈 Relatórios & Epidemiológico (91+ Pacientes)',
                    'integracoes' => '⚙️ Formulários, Google Forms & Hostinger'
                ];
                foreach ($navTabs as $tKey => $tLabel):
                    $isActive = ($activeTab === $tKey);
                ?>
                    <a href="?tab=<?= $tKey ?>&patient_id=<?= $selectedPatientId ?>"
                       class="whitespace-nowrap px-3.5 py-2 rounded-lg transition <?= $isActive ? 'bg-white text-teal-900 shadow-sm font-bold' : 'text-teal-100 hover:bg-teal-700/60' ?>">
                        <?= $tLabel ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </header>

    <!-- Mensagem de Confirmação -->
    <?php if ($flashMsg): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4 w-full">
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-xl text-sm flex items-center justify-between shadow-sm">
                <span>✅ <strong><?= htmlspecialchars($flashMsg) ?></strong></span>
                <button onclick="this.parentElement.remove()" class="text-emerald-700 font-bold text-xs">Fechar ✕</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Conteúdo Principal -->
    <main class="max-w-7xl mx-auto px-4 py-6 flex-1 w-full">

        <?php if ($activeTab === 'clientes'): ?>
            <!-- =========================================================================
                 VISÃO 1: POR CLIENTE (PAINEL PRÉ-CONSULTA 0/3 + CHECKLISTS POR ETAPA)
                 ========================================================================= -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Coluna Lateral: Lista de Clientes -->
                <div class="lg:col-span-4 bg-white rounded-2xl shadow-sm border border-slate-200 p-4 flex flex-col max-h-[88vh]">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-bold text-slate-900 text-sm uppercase tracking-wider">Clientes em Acompanhamento</h2>
                        <button onclick="document.getElementById('modalNovoPaciente').classList.remove('hidden')" class="bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold px-3 py-1.5 rounded-lg">
                            + Novo Paciente
                        </button>
                    </div>

                    <!-- Filtro Rápido por Etapa -->
                    <div class="flex flex-wrap gap-1 mb-3 text-[11px] font-semibold">
                        <?php
                        $sFilters = [
                            'ativos' => 'Ativos (' . ($metrics['stage_counts']['vai_iniciar'] + $metrics['stage_counts']['em_avaliacao'] + $metrics['stage_counts']['para_devolutiva']) . ')',
                            'vai_iniciar' => 'Vão Iniciar (' . $metrics['stage_counts']['vai_iniciar'] . ')',
                            'em_avaliacao' => 'Em Avaliação (' . $metrics['stage_counts']['em_avaliacao'] . ')',
                            'para_devolutiva' => 'Devolutiva (' . $metrics['stage_counts']['para_devolutiva'] . ')',
                            'concluido' => 'Concluídos (' . $metrics['stage_counts']['concluido'] . ')'
                        ];
                        foreach ($sFilters as $sfKey => $sfLabel):
                        ?>
                            <a href="?tab=clientes&stage=<?= $sfKey ?>&patient_id=<?= $selectedPatientId ?>"
                               class="px-2.5 py-1 rounded-md border <?= $stageFilter === $sfKey ? 'bg-teal-700 text-white border-teal-700' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' ?>">
                                <?= $sfLabel ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Lista Rolável de Pacientes -->
                    <div class="overflow-y-auto space-y-2 pr-1 flex-1">
                        <?php
                        $filteredPatients = array_filter($patients, function($p) use ($stageFilter) {
                            if ($stageFilter === 'ativos') return $p['current_stage'] !== 'concluido';
                            return $p['current_stage'] === $stageFilter;
                        });
                        // Ordena mais recentes no topo
                        $filteredPatients = array_reverse($filteredPatients);
                        foreach ($filteredPatients as $pItem):
                            $isSelected = ((int)$pItem['id'] === $selectedPatientId);
                            $badge = formatStageBadge($pItem['current_stage']);
                        ?>
                            <a href="?tab=clientes&stage=<?= urlencode($stageFilter) ?>&patient_id=<?= $pItem['id'] ?>"
                               class="block p-3 rounded-xl border transition <?= $isSelected ? 'bg-teal-50/80 border-teal-600 ring-1 ring-teal-600' : 'bg-white border-slate-200 hover:border-teal-400' ?>">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-sm text-slate-900"><?= htmlspecialchars($pItem['full_name']) ?></span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border <?= $badge['bg'] ?>">
                                        <?= $badge['label'] ?>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
                                    <span>🎂 Idade: <?= htmlspecialchars((string)($pItem['age'] ?? '-')) ?> (<?= htmlspecialchars($pItem['age_group'] ?? '-') ?>)</span>
                                    <?php if ($pItem['school_name']): ?>
                                        <span>🏫 <?= htmlspecialchars($pItem['school_name']) ?></span>
                                    <?php endif; ?>
                                    <span>📅 <?= htmlspecialchars($pItem['start_month'] ?? '') ?>/<?= htmlspecialchars($pItem['reference_year'] ?? '') ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Coluna Principal: Painel Pré-Consulta + Checklists + Prontuário -->
                <div class="lg:col-span-8 space-y-6">
                    <?php if ($selectedPatient):
                        $preTasks = $patientTasksByGroup['pre_consulta_cliente'];
                        $preDoneCount = count(array_filter($preTasks, fn($t) => $t['status'] === 'concluido'));
                        $preTotalCount = count($preTasks) ?: 3;
                        $prePct = round(($preDoneCount / $preTotalCount) * 100);
                        $waReminderUrl = buildPreConsultaReminderWhatsApp($selectedPatient, $preTasks);
                        $token = $selectedPatient['public_token'] ?: 'token';
                        $nativeCadastroUrl = "{$baseUrl}/form.php?token={$token}&type=cadastro";
                        $nativeAnamneseUrl = "{$baseUrl}/form.php?token={$token}&type=anamnese";
                        $contractUrl = "{$baseUrl}/contract_print.php?id={$selectedPatient['id']}";
                    ?>
                        <!-- Cabeçalho do Cliente Selecionado + Ações Rápidas -->
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold bg-slate-100 text-slate-700 px-2.5 py-0.5 rounded-md border border-slate-300">
                                            Registro #<?= htmlspecialchars((string)($selectedPatient['code_number'] ?: $selectedPatient['id'])) ?>
                                        </span>
                                        <?php $stBadge = formatStageBadge($selectedPatient['current_stage']); ?>
                                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full border <?= $stBadge['bg'] ?>">
                                            <?= $stBadge['label'] ?>
                                        </span>
                                    </div>
                                    <h2 class="text-xl font-bold text-slate-900 mt-1"><?= htmlspecialchars($selectedPatient['full_name']) ?></h2>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        Responsável: <strong><?= htmlspecialchars($selectedPatient['responsible_name'] ?: $selectedPatient['full_name']) ?></strong> •
                                        CPF (NF): <strong><?= htmlspecialchars($selectedPatient['cpf_nf'] ?: 'Não preenchido') ?></strong> •
                                        Anamnese: <strong><?= $selectedPatient['anamnesis_date'] ? date('d/m/Y', strtotime($selectedPatient['anamnesis_date'])) : 'A definir' ?></strong>
                                    </p>
                                </div>

                                <!-- Botões de Links Rápidos do Paciente -->
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="<?= htmlspecialchars($waReminderUrl) ?>" target="_blank"
                                       class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-3 py-2 rounded-xl shadow-sm flex items-center gap-1.5">
                                        💬 Cobrar Pendências no WhatsApp
                                    </a>
                                    <a href="<?= htmlspecialchars($contractUrl) ?>" target="_blank"
                                       class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-3 py-2 rounded-xl shadow-sm">
                                        📄 Gerar/Ver Contrato
                                    </a>
                                    <a href="laudo_qrcode.php?id=<?= $selectedPatient['id'] ?>" target="_blank"
                                       class="bg-purple-700 hover:bg-purple-800 text-white text-xs font-semibold px-3 py-2 rounded-xl shadow-sm">
                                        🔳 QR Code do Laudo
                                    </a>
                                    <?php if (!empty($selectedPatient['drive_folder_url'])): ?>
                                        <a href="<?= htmlspecialchars($selectedPatient['drive_folder_url']) ?>" target="_blank"
                                           class="bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 text-xs font-semibold px-3 py-2 rounded-xl">
                                            📁 Pasta Drive
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($selectedPatient['gamma_url'])): ?>
                                        <a href="<?= htmlspecialchars($selectedPatient['gamma_url']) ?>" target="_blank"
                                           class="bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 text-xs font-semibold px-3 py-2 rounded-xl">
                                            📊 Apresentação Gamma
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- =================================================================
                             PAINEL DE ACOMPANHAMENTO PRÉ-CONSULTA (IDÊNTICO À IMAGEM ENVIADA)
                             ================================================================= -->
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-bold text-slate-900 text-base">Painel de acompanhamento pré-consulta</h3>
                                <span class="text-xs font-semibold bg-slate-100 text-slate-600 px-3 py-1 rounded-full border border-slate-200">
                                    Monitoramento Individual (3 Procedimentos)
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-sm text-slate-600 mb-2">
                                <span>Procedimentos concluídos</span>
                                <span class="font-bold text-slate-900 text-base"><?= $preDoneCount ?>/<?= $preTotalCount ?></span>
                            </div>

                            <!-- Barra de Progresso -->
                            <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden mb-5">
                                <div class="h-full bg-teal-600 transition-all duration-300" style="width: <?= $prePct ?>%"></div>
                            </div>

                            <!-- Os 3 Procedimentos Pré-Consulta -->
                            <div class="divide-y divide-slate-200">
                                <?php foreach ($preTasks as $pt):
                                    $isDone = ($pt['status'] === 'concluido');
                                    $itemLink = $nativeCadastroUrl;
                                    if ($pt['task_code'] === 'cli_anamnese_preliminar') {
                                        $itemLink = !empty($selectedPatient['google_form_anamnese_url']) ? $selectedPatient['google_form_anamnese_url'] : $nativeAnamneseUrl;
                                    } elseif ($pt['task_code'] === 'cli_contrato_assinado') {
                                        $itemLink = $contractUrl;
                                    } else {
                                        $itemLink = !empty($selectedPatient['google_form_cadastro_url']) ? $selectedPatient['google_form_cadastro_url'] : $nativeCadastroUrl;
                                    }
                                ?>
                                    <div class="py-4 flex flex-wrap items-center justify-between gap-4">
                                        <div>
                                            <p class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($pt['task_title']) ?></p>
                                            <div class="flex flex-wrap items-center gap-3 mt-1 text-xs">
                                                <?php if ($isDone): ?>
                                                    <span class="text-emerald-700 font-semibold">✓ Concluído em <?= date('d/m/Y H:i', strtotime($pt['completed_at'] ?? 'now')) ?> (<?= htmlspecialchars($pt['completed_by'] ?? '') ?>)</span>
                                                <?php else: ?>
                                                    <span class="text-amber-700 font-medium">Pendente (Enviado em <?= $pt['sent_at'] ? date('d/m/Y', strtotime($pt['sent_at'])) : date('d/m/Y') ?>)</span>
                                                <?php endif; ?>
                                                <a href="<?= htmlspecialchars($itemLink) ?>" target="_blank" class="text-teal-700 hover:underline font-medium">
                                                    🔗 Abrir link do procedimento ↗
                                                </a>
                                            </div>
                                        </div>

                                        <form method="POST">
                                            <input type="hidden" name="action" value="toggle_task">
                                            <input type="hidden" name="task_id" value="<?= $pt['id'] ?>">
                                            <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                                            <button type="submit" class="flex items-center gap-2 px-3.5 py-2 rounded-xl border text-xs font-semibold transition <?= $isDone ? 'bg-emerald-50 border-emerald-400 text-emerald-800' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50' ?>">
                                                <span class="w-4 h-4 rounded-full border flex items-center justify-center <?= $isDone ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-400' ?>">
                                                    <?= $isDone ? '✓' : '' ?>
                                                </span>
                                                Concluído
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
                                <span>🔔 Após 2 dias do envio, o sistema gera lembretes apenas para os procedimentos pendentes acima.</span>
                                <form method="POST">
                                    <input type="hidden" name="action" value="reset_pre_consulta">
                                    <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                                    <button type="submit" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 font-medium">
                                        Reiniciar simulação (0/3)
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- =================================================================
                             CHECKLISTS OPERACIONAIS COMPLETOS POR ETAPA DO PROCESSO
                             ================================================================= -->
                        <?php
                        $stageBlocks = [
                            'vai_iniciar' => [
                                'title' => '1. PACIENTES QUE VÃO INICIAR (Rotina de Pré-Consulta e Contrato)',
                                'subtitle' => 'Desde o envio dos links, pasta no Drive, boletos e conferência do contrato pela Gisele até o controle de NF.',
                                'header_bg' => 'bg-amber-50 border-amber-200 text-amber-900'
                            ],
                            'em_avaliacao' => [
                                'title' => '2. PACIENTES EM AVALIAÇÃO (Procedimento Padrão, Testes, Revisão e Mapa)',
                                'subtitle' => 'Correção e lançamento de testes (Pasta Física, Excel Drive, VOL), Guia Qualitativo, Revisão do Laudo, OK da Gisele, Mapa e Gamma.',
                                'header_bg' => 'bg-blue-50 border-blue-200 text-blue-900'
                            ],
                            'para_devolutiva' => [
                                'title' => '3. PACIENTES QUE COMPLETARAM TESTAGEM E VÃO PARA DEVOLUTIVA',
                                'subtitle' => 'Questionários e entrevistas com escola/informantes, revisão da anamnese pelos pais, impressão com QR Code e entrega segura.',
                                'header_bg' => 'bg-purple-50 border-purple-200 text-purple-900'
                            ]
                        ];

                        foreach ($stageBlocks as $groupKey => $meta):
                            $tasks = $patientTasksByGroup[$groupKey] ?? [];
                            $doneCnt = count(array_filter($tasks, fn($t) => $t['status'] === 'concluido'));
                            $totCnt = count($tasks) ?: 1;
                        ?>
                            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                <div class="px-5 py-4 border-b <?= $meta['header_bg'] ?> flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h3 class="font-bold text-sm"><?= $meta['title'] ?></h3>
                                        <p class="text-xs opacity-80"><?= $meta['subtitle'] ?></p>
                                    </div>
                                    <span class="text-xs font-bold px-3 py-1 rounded-full bg-white/80 border border-slate-300">
                                        <?= $doneCnt ?> / <?= count($tasks) ?> concluídos
                                    </span>
                                </div>

                                <div class="divide-y divide-slate-100 p-4">
                                    <?php foreach ($tasks as $tk):
                                        $isDone = ($tk['status'] === 'concluido');
                                        $roleBadge = formatRoleBadge($tk['responsible_role']);
                                    ?>
                                        <div class="py-2.5 flex items-center justify-between gap-3 hover:bg-slate-50/80 px-2 rounded-lg">
                                            <div class="flex items-start gap-3">
                                                <form method="POST" class="mt-0.5">
                                                    <input type="hidden" name="action" value="toggle_task">
                                                    <input type="hidden" name="task_id" value="<?= $tk['id'] ?>">
                                                    <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                                                    <button type="submit" class="w-5 h-5 rounded border flex items-center justify-center text-xs font-bold transition <?= $isDone ? 'bg-teal-600 border-teal-600 text-white' : 'bg-white border-slate-300 hover:border-teal-500' ?>">
                                                        <?= $isDone ? '✓' : '' ?>
                                                    </button>
                                                </form>
                                                <div>
                                                    <p class="text-xs font-semibold <?= $isDone ? 'line-through text-slate-400' : 'text-slate-800' ?>">
                                                        <?= htmlspecialchars($tk['task_title']) ?>
                                                    </p>
                                                    <?php if ($isDone && $tk['completed_at']): ?>
                                                        <span class="text-[11px] text-emerald-600">
                                                            Concluído em <?= date('d/m/Y H:i', strtotime($tk['completed_at'])) ?> por <?= htmlspecialchars($tk['completed_by'] ?? '') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border whitespace-nowrap <?= $roleBadge['bg'] ?>">
                                                <?= $roleBadge['label'] ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- =================================================================
                             CONTROLE DE TESTES APLICADOS (PASTA FÍSICA / EXCEL DRIVE / VOL / LAUDO)
                             ================================================================= -->
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="font-bold text-slate-900 text-sm">🧪 Controle de Instrumentos e Testes Aplicados ao Paciente</h3>
                                    <p class="text-xs text-slate-500">Controle individual do que já foi aplicado para corrigir e lançar na Pasta Física, no Excel do Drive, na Plataforma VOL e no Laudo.</p>
                                </div>
                                <form method="POST" class="flex items-center gap-2">
                                    <input type="hidden" name="action" value="add_test">
                                    <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                                    <input type="text" name="instrument_name" required placeholder="Ex: WISC-IV, BPA-2, SRS-2, Ravlt..." class="border border-slate-300 rounded-lg px-3 py-1.5 text-xs">
                                    <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold px-3 py-1.5 rounded-lg">
                                        + Adicionar Teste
                                    </button>
                                </form>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100 text-slate-700 border-b border-slate-200">
                                            <th class="p-2.5 text-left">Instrumento / Escala Aplicada</th>
                                            <th class="p-2.5 text-center">1. Na Pasta Física</th>
                                            <th class="p-2.5 text-center">2. Nos Links Excel Drive</th>
                                            <th class="p-2.5 text-center">3. Na Plataforma VOL</th>
                                            <th class="p-2.5 text-center">4. Lançado no Laudo</th>
                                            <th class="p-2.5 text-center">Salvar</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <?php if (empty($patientTests)): ?>
                                            <tr><td colspan="6" class="p-4 text-center text-slate-400">Nenhum teste registrado ainda para este paciente. Adicione acima.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($patientTests as $tItem): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="update_test_flags">
                                                    <input type="hidden" name="test_id" value="<?= $tItem['id'] ?>">
                                                    <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                                                    <tr class="hover:bg-slate-50">
                                                        <td class="p-2.5 font-semibold text-slate-800"><?= htmlspecialchars($tItem['instrument_name']) ?></td>
                                                        <td class="p-2.5 text-center"><input type="checkbox" name="scored_physical_folder" value="1" <?= $tItem['scored_physical_folder'] ? 'checked' : '' ?> class="w-4 h-4 accent-teal-600"></td>
                                                        <td class="p-2.5 text-center"><input type="checkbox" name="entered_drive_excel" value="1" <?= $tItem['entered_drive_excel'] ? 'checked' : '' ?> class="w-4 h-4 accent-teal-600"></td>
                                                        <td class="p-2.5 text-center"><input type="checkbox" name="entered_vol_platform" value="1" <?= $tItem['entered_vol_platform'] ? 'checked' : '' ?> class="w-4 h-4 accent-teal-600"></td>
                                                        <td class="p-2.5 text-center"><input type="checkbox" name="inserted_in_report" value="1" <?= $tItem['inserted_in_report'] ? 'checked' : '' ?> class="w-4 h-4 accent-teal-600"></td>
                                                        <td class="p-2.5 text-center">
                                                            <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-2.5 py-1 rounded text-[11px] font-semibold">Salvar</button>
                                                        </td>
                                                    </tr>
                                                </form>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =================================================================
                             FICHA COMPLETA DO PACIENTE (LINKS, ENTREVISTAS E DADOS ESTATÍSTICOS)
                             ================================================================= -->
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                            <h3 class="font-bold text-slate-900 text-sm mb-4">📝 Prontuário Administrativo, Links Externos e Dados para Tabela Estatística</h3>
                            <form method="POST" class="space-y-4 text-xs">
                                <input type="hidden" name="action" value="update_patient_details">
                                <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Nome do Paciente</label>
                                        <input type="text" name="full_name" value="<?= htmlspecialchars($selectedPatient['full_name'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Responsável (Contrato e NF)</label>
                                        <input type="text" name="responsible_name" value="<?= htmlspecialchars($selectedPatient['responsible_name'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Etapa Atual no Processo</label>
                                        <select name="current_stage" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 font-semibold">
                                            <option value="vai_iniciar" <?= $selectedPatient['current_stage'] === 'vai_iniciar' ? 'selected' : '' ?>>1. Vai Iniciar (Pré-Consulta)</option>
                                            <option value="em_avaliacao" <?= $selectedPatient['current_stage'] === 'em_avaliacao' ? 'selected' : '' ?>>2. Em Avaliação</option>
                                            <option value="para_devolutiva" <?= $selectedPatient['current_stage'] === 'para_devolutiva' ? 'selected' : '' ?>>3. Vai para Devolutiva</option>
                                            <option value="concluido" <?= $selectedPatient['current_stage'] === 'concluido' ? 'selected' : '' ?>>4. Concluído / Laudo Entregue</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">CPF (Tabela Emissão NF)</label>
                                        <input type="text" name="cpf_nf" value="<?= htmlspecialchars($selectedPatient['cpf_nf'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">E-mail (Tabela Emissão NF)</label>
                                        <input type="email" name="email_nf" value="<?= htmlspecialchars($selectedPatient['email_nf'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">WhatsApp</label>
                                        <input type="text" name="phone_whatsapp" value="<?= htmlspecialchars($selectedPatient['phone_whatsapp'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 pt-2 border-t border-slate-100">
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Ano Ref.</label>
                                        <input type="text" name="reference_year" value="<?= htmlspecialchars($selectedPatient['reference_year'] ?? '2026') ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Mês Início</label>
                                        <input type="text" name="start_month" value="<?= htmlspecialchars($selectedPatient['start_month'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Mês Fim</label>
                                        <input type="text" name="end_month" value="<?= htmlspecialchars($selectedPatient['end_month'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Idade</label>
                                        <input type="number" name="age" value="<?= htmlspecialchars((string)($selectedPatient['age'] ?? '')) ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Nº de Sessões</label>
                                        <input type="text" name="total_sessions" value="<?= htmlspecialchars((string)($selectedPatient['total_sessions'] ?? '')) ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Escola (Padronizada)</label>
                                        <select name="school_name" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                            <option value="">-- Sem escola / Adulto --</option>
                                            <?php foreach ($schoolsCatalog as $sc): ?>
                                                <option value="<?= htmlspecialchars($sc) ?>" <?= ($selectedPatient['school_name'] === $sc) ? 'selected' : '' ?>><?= htmlspecialchars($sc) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Escolaridade</label>
                                        <input type="text" name="education_level" value="<?= htmlspecialchars($selectedPatient['education_level'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Data da Anamnese / Devolutiva</label>
                                        <div class="flex gap-1">
                                            <input type="date" name="anamnesis_date" value="<?= htmlspecialchars($selectedPatient['anamnesis_date'] ?? '') ?>" class="w-1/2 border border-slate-300 rounded-lg px-2 py-1.5" title="Data da Anamnese">
                                            <input type="date" name="devolutiva_date" value="<?= htmlspecialchars($selectedPatient['devolutiva_date'] ?? '') ?>" class="w-1/2 border border-slate-300 rounded-lg px-2 py-1.5" title="Data da Devolutiva">
                                        </div>
                                    </div>
                                </div>

                                <!-- Diagnósticos 1 a 4 e Comorbidade Descritiva (Idêntico à Planilha) -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-2 border-t border-slate-100">
                                    <?php for ($dIdx = 1; $dIdx <= 4; $dIdx++):
                                        $col = "diag_{$dIdx}";
                                    ?>
                                        <div>
                                            <label class="block font-semibold text-slate-600 mb-1">Diagnóstico <?= $dIdx ?></label>
                                            <select name="<?= $col ?>" class="w-full border border-slate-300 rounded-lg px-2 py-1.5">
                                                <option value="">-- Nenhum --</option>
                                                <?php foreach ($diagnosesCatalog as $dc): ?>
                                                    <option value="<?= htmlspecialchars($dc['acronym']) ?>" <?= ($selectedPatient[$col] === $dc['acronym']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($dc['acronym']) ?> (<?= htmlspecialchars($dc['full_name']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endfor; ?>
                                </div>

                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Descrever a comorbidade que não consta na lista (Planilha Epidemiológica)</label>
                                    <input type="text" name="unlisted_comorbidity" value="<?= htmlspecialchars($selectedPatient['unlisted_comorbidity'] ?? '') ?>" placeholder="Ex: Transtorno Específico da Aprendizagem com prejuízo em leitura e escrita (Dislexia)..." class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                </div>

                                <!-- Links do Google Drive, Gamma e Google Forms Externo -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Link da Pasta no Google Drive do Paciente</label>
                                        <input type="url" name="drive_folder_url" value="<?= htmlspecialchars($selectedPatient['drive_folder_url'] ?? '') ?>" placeholder="https://drive.google.com/..." class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Link da Apresentação no Gamma (Devolutiva)</label>
                                        <input type="url" name="gamma_url" value="<?= htmlspecialchars($selectedPatient['gamma_url'] ?? '') ?>" placeholder="https://gamma.app/..." class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Link Google Forms Cadastro (Opcional — se vazio usa o Nativo)</label>
                                        <input type="url" name="google_form_cadastro_url" value="<?= htmlspecialchars($selectedPatient['google_form_cadastro_url'] ?? '') ?>" placeholder="Deixe em branco para usar o Formulário Nativo do sistema" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Link Google Forms Anamnese (Opcional — se vazio usa o Nativo)</label>
                                        <input type="url" name="google_form_anamnese_url" value="<?= htmlspecialchars($selectedPatient['google_form_anamnese_url'] ?? '') ?>" placeholder="Deixe em branco para usar o Formulário Nativo do sistema" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                                    </div>
                                </div>

                                <!-- Notas de Envelope de Escalas, Entrevista Escola e Entrevista Informantes -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2 border-t border-slate-100">
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Escalas no Envelope para Casa</label>
                                        <textarea name="home_scales_notes" rows="2" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5" placeholder="Listar escalas entregues conforme modelo de laudo..."><?= htmlspecialchars($selectedPatient['home_scales_notes'] ?? '') ?></textarea>
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Registro da Entrevista com a ESCOLA</label>
                                        <textarea name="school_interview_notes" rows="2" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5" placeholder="Data, coordenadora/professora e observações..."><?= htmlspecialchars($selectedPatient['school_interview_notes'] ?? '') ?></textarea>
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-600 mb-1">Entrevista com Informantes (Família/Terapeutas)</label>
                                        <textarea name="informant_interview_notes" rows="2" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5" placeholder="Fonoaudióloga, TO, psiquiatra, familiares..."><?= htmlspecialchars($selectedPatient['informant_interview_notes'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white font-semibold px-5 py-2.5 rounded-xl shadow">
                                        💾 Salvar Dados do Prontuário e Estatísticas
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($activeTab === 'processos'): ?>
            <!-- =========================================================================
                 VISÃO 2: POR PROCESSOS GERAL (MATRIZ DE GARGALOS E EXECUÇÃO EM LOTE)
                 ========================================================================= -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-teal-700 bg-teal-50 px-2.5 py-1 rounded-full border border-teal-200">
                                Visão Operacional 2 — Por Processos Geral
                            </span>
                            <h2 class="text-xl font-bold text-slate-900 mt-2">Painel Geral de Gargalos por Etapa Específica</h2>
                            <p class="text-xs text-slate-500 mt-1">
                                Visualize rapidamente quantos pacientes faltam preencher formulários ou concluir cada procedimento específico para atacar pendências em lote.
                            </p>
                        </div>
                    </div>

                    <?php
                    $selectedTaskCode = $_GET['task_code'] ?? 'cli_cadastro';
                    ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
                        <?php
                        $groupTitles = [
                            'pre_consulta_cliente' => '📋 Pré-Consulta (Cliente)',
                            'vai_iniciar' => '🚀 Pacientes que vão Iniciar',
                            'em_avaliacao' => '🧠 Pacientes em Avaliação',
                            'para_devolutiva' => '🎯 Vão para Devolutiva'
                        ];
                        foreach ($groupTitles as $gKey => $gLabel):
                            $items = array_filter($metrics['process_bottlenecks'], fn($b) => $b['stage_group'] === $gKey);
                        ?>
                            <div class="bg-slate-50 rounded-xl border border-slate-200 p-4">
                                <h3 class="font-bold text-xs uppercase tracking-wider text-slate-700 mb-3 pb-2 border-b border-slate-200"><?= $gLabel ?></h3>
                                <div class="space-y-1.5 max-h-80 overflow-y-auto pr-1">
                                    <?php foreach ($items as $bItem):
                                        $isSel = ($selectedTaskCode === $bItem['task_code']);
                                    ?>
                                        <a href="?tab=processos&task_code=<?= urlencode($bItem['task_code']) ?>"
                                           class="flex items-center justify-between gap-2 p-2 rounded-lg text-xs transition <?= $isSel ? 'bg-teal-700 text-white font-bold shadow-sm' : 'bg-white hover:bg-teal-50 text-slate-700 border border-slate-200' ?>">
                                            <span class="line-clamp-2"><?= htmlspecialchars($bItem['task_title']) ?></span>
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?= $isSel ? 'bg-teal-900 text-white' : 'bg-amber-100 text-amber-800' ?>">
                                                <?= (int)$bItem['pending_count'] ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Lista de Pacientes Pendentes na Etapa Selecionada -->
                <?php
                $pendStmt = $pdo->prepare("
                    SELECT t.*, p.full_name, p.responsible_name, p.phone_whatsapp, p.current_stage, p.anamnesis_date, p.public_token, p.code_number
                    FROM patient_tasks t
                    INNER JOIN patients p ON p.id = t.patient_id
                    WHERE t.task_code = ? AND t.status != 'concluido' AND p.current_stage != 'concluido'
                    ORDER BY p.id DESC
                ");
                $pendStmt->execute([$selectedTaskCode]);
                $pendingPatientsForTask = $pendStmt->fetchAll();
                $taskTitleSelected = !empty($pendingPatientsForTask) ? $pendingPatientsForTask[0]['task_title'] : $selectedTaskCode;
                ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                        <div>
                            <h3 class="font-bold text-slate-900 text-base">
                                Pacientes Pendentes na Etapa: <span class="text-teal-700">"<?= htmlspecialchars($taskTitleSelected) ?>"</span>
                            </h3>
                            <p class="text-xs text-slate-500">Total de <?= count($pendingPatientsForTask) ?> paciente(s) aguardando esta etapa específica.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 border-b border-slate-200">
                                    <th class="p-3 text-left">Paciente</th>
                                    <th class="p-3 text-left">Etapa Atual</th>
                                    <th class="p-3 text-left">Responsável pela Tarefa</th>
                                    <th class="p-3 text-left">Enviado em</th>
                                    <th class="p-3 text-right">Ações Rápidas em Lote</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php foreach ($pendingPatientsForTask as $row):
                                    $stBadge = formatStageBadge($row['current_stage']);
                                    $rBadge = formatRoleBadge($row['responsible_role']);
                                    $waQuick = buildWhatsAppUrl($row['phone_whatsapp'], "Olá! Passando para falar sobre a etapa '{$row['task_title']}' referente à avaliação de {$row['full_name']}.");
                                ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-3 font-bold text-slate-900">
                                            <a href="?tab=clientes&patient_id=<?= $row['patient_id'] ?>" class="hover:text-teal-700 hover:underline">
                                                <?= htmlspecialchars($row['full_name']) ?>
                                            </a>
                                        </td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded-full border text-[10px] font-semibold <?= $stBadge['bg'] ?>">
                                                <?= $stBadge['label'] ?>
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded-full border text-[10px] font-semibold <?= $rBadge['bg'] ?>">
                                                <?= $rBadge['label'] ?>
                                            </span>
                                        </td>
                                        <td class="p-3 text-slate-500"><?= $row['sent_at'] ? date('d/m/Y', strtotime($row['sent_at'])) : '-' ?></td>
                                        <td class="p-3 text-right flex items-center justify-end gap-2">
                                            <a href="<?= htmlspecialchars($waQuick) ?>" target="_blank" class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold hover:bg-emerald-100">
                                                💬 WhatsApp
                                            </a>
                                            <a href="?tab=clientes&patient_id=<?= $row['patient_id'] ?>" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">
                                                Abrir Ficha
                                            </a>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="toggle_task">
                                                <input type="hidden" name="task_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="patient_id" value="<?= $row['patient_id'] ?>">
                                                <button type="submit" class="px-3 py-1 rounded-lg bg-teal-700 hover:bg-teal-800 text-white font-semibold">
                                                    ✓ Concluir Agora
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTab === 'crm'): ?>
            <!-- =========================================================================
                 MÓDULO CRM DE LEADS (CAPTAÇÃO WHATSAPP, ORÇAMENTO E REMARKETING D+7)
                 ========================================================================= -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Formulário de Novo Lead (Primeiro Contato WhatsApp) -->
                <div class="lg:col-span-4 bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <h2 class="font-bold text-slate-900 text-base mb-1">📲 Registrar Primeiro Contato (Lead)</h2>
                    <p class="text-xs text-slate-500 mb-4">Gatilho obrigatório: registra data, origem, contato e queixa principal, programando lembrete de 7 dias caso aguarde decisão.</p>

                    <form method="POST" class="space-y-3 text-xs">
                        <input type="hidden" name="action" value="create_lead">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nome do Contato / Responsável *</label>
                            <input type="text" name="contact_name" required placeholder="Ex: Patrícia Silva (Mãe)" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label class="block font-semibold text-slate-700 mb-1">Nome do Paciente *</label>
                                <input type="text" name="patient_name" required placeholder="Ex: Lucas Silva" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Idade</label>
                                <input type="number" name="patient_age" placeholder="9" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">WhatsApp *</label>
                                <input type="text" name="phone_whatsapp" required placeholder="5524999999999" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Origem</label>
                                <select name="origin" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                                    <option value="WhatsApp">WhatsApp</option>
                                    <option value="Indicação Médica">Indicação Médica</option>
                                    <option value="Indicação Escolar">Indicação Escolar</option>
                                    <option value="Instagram">Instagram</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Data 1º Contato</label>
                                <input type="date" name="first_contact_date" value="<?= date('Y-m-d') ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Classificação Inicial</label>
                                <select name="status" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                                    <option value="novo">Novo Contato</option>
                                    <option value="aguardando_decisao">Solicitou Orçamento / Aguardando Decisão (Alerta 7 dias)</option>
                                    <option value="remarketing">Em Remarketing (7+ dias)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Dor Relatada / Queixa Principal *</label>
                            <textarea name="main_complaint" rows="3" required placeholder="Descreva a dor/queixa relatada pelo familiar ou paciente..." class="w-full border border-slate-300 rounded-lg px-3 py-2"></textarea>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Histórico de Atendimento / Observações</label>
                            <textarea name="history_notes" rows="2" placeholder="Informações sobre valores enviados, dúvidas..." class="w-full border border-slate-300 rounded-lg px-3 py-2"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-2.5 rounded-xl shadow">
                            + Salvar Lead no Funil CRM
                        </button>
                    </form>
                </div>

                <!-- Lista de Leads e Conversão em Agendamento -->
                <div class="lg:col-span-8 space-y-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                        <h2 class="font-bold text-slate-900 text-base mb-1">Funil de Captação, Orçamentos e Remarketing (Etapas 1 a 3)</h2>
                        <p class="text-xs text-slate-500 mb-4">
                            Quando o lead confirma o agendamento da anamnese, clique em <strong>"Confirmar Anamnese & Gerar Pré-Consulta"</strong> para criar automaticamente o paciente e os checklists.
                        </p>

                        <div class="space-y-4">
                            <?php foreach ($leads as $ld):
                                $isRem = ($ld['status'] === 'remarketing');
                                $waInfoMsg = "Olá, {$ld['contact_name']}! Tudo bem? Aqui é da equipe da clínica Mente em Equilíbrio. Seguem as informações sobre a Avaliação Neuropsicológica de {$ld['patient_name']}, nossos diferenciais e formas de pagamento...";
                                // Mensagem de Remarketing D+7 respeitando a LGPD (educativa e acolhedora sem expor diagnóstico)
                                $waRemarketingMsg = "Olá, {$ld['contact_name']}! Tudo bem? Passando para saber se ficou alguma dúvida sobre como funciona o processo de Avaliação Neuropsicológica para {$ld['patient_name']} e compartilhar um material informativo sobre como mapeamos as habilidades cognitivas e escolares. Caso deseje verificar os horários disponíveis na agenda para a anamnese, estou à disposição!";
                            ?>
                                <div class="p-4 rounded-xl border <?= $isRem ? 'bg-rose-50/60 border-rose-300' : 'bg-slate-50 border-slate-200' ?>">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-sm text-slate-900"><?= htmlspecialchars($ld['patient_name']) ?> (<?= htmlspecialchars((string)($ld['patient_age'] ?? '-')) ?> anos)</span>
                                                <span class="text-xs text-slate-500">• Contato: <?= htmlspecialchars($ld['contact_name']) ?></span>
                                                <?php if ($isRem): ?>
                                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-600 text-white">
                                                        ⏰ 7+ Dias sem Conversão — Tarefa de Remarketing
                                                    </span>
                                                <?php elseif ($ld['status'] === 'convertido'): ?>
                                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                                        ✓ Convertido em Paciente
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">
                                                        <?= htmlspecialchars(strtoupper($ld['status'])) ?> (Retorno: <?= date('d/m/Y', strtotime($ld['next_followup_date'] ?? 'now')) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs text-slate-700 mt-2 bg-white p-2.5 rounded-lg border border-slate-200">
                                                <strong class="text-rose-800">Dor Relatada / Queixa Principal:</strong> <?= htmlspecialchars($ld['main_complaint']) ?>
                                            </p>
                                            <p class="text-[11px] text-slate-500 mt-1">
                                                <strong>Histórico:</strong> <?= htmlspecialchars($ld['history_notes'] ?? '') ?> (1º Contato: <?= date('d/m/Y', strtotime($ld['first_contact_date'])) ?> via <?= htmlspecialchars($ld['origin']) ?>)
                                            </p>
                                        </div>
                                    </div>

                                    <?php if ($ld['status'] !== 'convertido'): ?>
                                        <div class="mt-3 pt-3 border-t border-slate-200/80 flex flex-wrap items-center justify-between gap-2 text-xs">
                                            <div class="flex flex-wrap gap-2">
                                                <a href="<?= htmlspecialchars(buildWhatsAppUrl($ld['phone_whatsapp'], $waInfoMsg)) ?>" target="_blank"
                                                   class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-semibold">
                                                    💬 Enviar Info / Orçamento
                                                </a>
                                                <a href="<?= htmlspecialchars(buildWhatsAppUrl($ld['phone_whatsapp'], $waRemarketingMsg)) ?>" target="_blank"
                                                   class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-semibold shadow-sm">
                                                    🎯 Disparar Remarketing Educativo (WhatsApp)
                                                </a>
                                            </div>

                                            <!-- Converter em Paciente / Confirmar Data da Anamnese -->
                                            <form method="POST" class="flex items-center gap-2">
                                                <input type="hidden" name="action" value="convert_lead_to_patient">
                                                <input type="hidden" name="lead_id" value="<?= $ld['id'] ?>">
                                                <label class="text-[11px] font-semibold text-slate-600">Data Anamnese:</label>
                                                <input type="date" name="anamnesis_date" required value="<?= date('Y-m-d', strtotime('+4 days')) ?>" class="border border-slate-300 rounded-lg px-2 py-1 text-xs">
                                                <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-3 py-1.5 rounded-lg shadow-sm">
                                                    📅 Confirmar Anamnese & Criar Pré-Consulta
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTab === 'alertas'): ?>
            <!-- =========================================================================
                 CENTRAL DE REGRAS DE AUTOMAÇÃO (D+2, D+7, D-2) & FILA DE APROVAÇÃO DA GISELE
                 ========================================================================= -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Fila Exclusiva da Gisele (Contratos e Impressão de Laudos) -->
                <div class="bg-white rounded-2xl shadow-sm border border-rose-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <span class="text-xs font-bold uppercase bg-rose-100 text-rose-800 px-2.5 py-1 rounded-full">Coordenação — Gisele</span>
                            <h2 class="text-lg font-bold text-slate-900 mt-1">Fila de Conferência de Contratos e OK de Laudos</h2>
                        </div>
                    </div>

                    <h3 class="text-xs font-bold uppercase text-slate-500 mb-2">1. Contratos Aguardando Conferência da Gisele (<?= count($pendingGiseleContracts) ?>)</h3>
                    <div class="space-y-2 mb-6">
                        <?php foreach ($pendingGiseleContracts as $pc): ?>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-2 text-xs">
                                <div>
                                    <p class="font-bold text-slate-900"><?= htmlspecialchars($pc['full_name']) ?></p>
                                    <p class="text-slate-500">Valor: R$ <?= number_format((float)$pc['contract_total_value'], 2, ',', '.') ?> em <?= (int)$pc['contract_installments'] ?>x no boleto</p>
                                </div>
                                <div class="flex gap-2">
                                    <a href="contract_print.php?id=<?= $pc['id'] ?>" target="_blank" class="px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-semibold">Ver Contrato</a>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="gisele_quick_approve">
                                        <input type="hidden" name="patient_id" value="<?= $pc['id'] ?>">
                                        <input type="hidden" name="approve_type" value="contrato">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-semibold">✓ Dar OK no Contrato</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h3 class="text-xs font-bold uppercase text-slate-500 mb-2">2. Laudos Aguardando OK da Gisele para Impressão (<?= count($pendingGiseleLaudos) ?>)</h3>
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        <?php foreach ($pendingGiseleLaudos as $pl): ?>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-2 text-xs">
                                <div>
                                    <p class="font-bold text-slate-900"><?= htmlspecialchars($pl['full_name']) ?></p>
                                    <p class="text-slate-500">Etapa: <?= htmlspecialchars($pl['current_stage']) ?> • Escola: <?= htmlspecialchars($pl['school_name'] ?: 'Adulto') ?></p>
                                </div>
                                <div class="flex gap-2">
                                    <a href="?tab=clientes&patient_id=<?= $pl['id'] ?>" class="px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-semibold">Revisar Checklist</a>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="gisele_quick_approve">
                                        <input type="hidden" name="patient_id" value="<?= $pl['id'] ?>">
                                        <input type="hidden" name="approve_type" value="laudo">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">🖨️ Autorizar Impressão</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Alertas Automáticos de Prazos (D+2 Pré-Consulta, D-2 Confirmação Anamnese e D+7 Remarketing) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
                    <div>
                        <span class="text-xs font-bold uppercase bg-amber-100 text-amber-800 px-2.5 py-1 rounded-full">Regras de Automação Ativas</span>
                        <h2 class="text-lg font-bold text-slate-900 mt-1">Alertas de Cobrança (D+2 a D+7) e Confirmação (D-2)</h2>
                    </div>

                    <?php
                    $startingPatients = array_filter($patients, fn($p) => $p['current_stage'] === 'vai_iniciar');
                    ?>
                    <div class="space-y-3">
                        <?php foreach ($startingPatients as $sp):
                            $tSt = $pdo->prepare("SELECT * FROM patient_tasks WHERE patient_id=? AND stage_group='pre_consulta_cliente'");
                            $tSt->execute([$sp['id']]);
                            $spTasks = $tSt->fetchAll();
                            $pendNames = [];
                            foreach ($spTasks as $stItem) {
                                if ($stItem['status'] !== 'concluido') $pendNames[] = $stItem['task_title'];
                            }
                            $waUrl = buildPreConsultaReminderWhatsApp($sp, $spTasks);
                            $waConfirmAnamnesis = buildWhatsAppUrl($sp['phone_whatsapp'], "Olá! Passando para confirmar o agendamento da nossa sessão de Anamnese de {$sp['full_name']} prevista para " . ($sp['anamnesis_date'] ? date('d/m/Y', strtotime($sp['anamnesis_date'])) : 'esta semana') . ". Podemos confirmar sua presença?");
                        ?>
                            <div class="p-4 rounded-xl bg-amber-50/70 border border-amber-200 text-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($sp['full_name']) ?></span>
                                    <span class="font-semibold text-amber-900">Anamnese: <?= $sp['anamnesis_date'] ? date('d/m/Y', strtotime($sp['anamnesis_date'])) : 'A definir' ?></span>
                                </div>
                                <p class="text-slate-700">
                                    <strong>Pendências Pré-Consulta (Regra D+2):</strong>
                                    <?= empty($pendNames) ? '<span class="text-emerald-700 font-bold">Tudo concluído (3/3)!</span>' : '<span class="text-rose-700 font-semibold">' . htmlspecialchars(implode(', ', $pendNames)) . '</span>' ?>
                                </p>
                                <div class="flex flex-wrap gap-2 pt-1">
                                    <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                                        💬 Cobrar Itens Pendentes (D+2)
                                    </a>
                                    <a href="<?= htmlspecialchars($waConfirmAnamnesis) ?>" target="_blank" class="px-3 py-1.5 rounded-lg bg-teal-700 hover:bg-teal-800 text-white font-semibold">
                                        📅 Confirmar Agendamento 2 Dias Antes (D-2)
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTab === 'financeiro'): ?>
            <!-- =========================================================================
                 MÓDULO DE CONTRATOS, DATAS DOS BOLETOS E TABELA DE CONTROLE DE NF
                 ========================================================================= -->
            <div class="space-y-6">
                <?php if ($selectedPatient):
                    $instList = !empty($selectedPatient['contract_installments_json']) ? json_decode($selectedPatient['contract_installments_json'], true) : [];
                ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                            <div>
                                <h2 class="font-bold text-slate-900 text-base">📄 Configurar Datas das Parcelas dos Boletos no Contrato — <?= htmlspecialchars($selectedPatient['full_name']) ?></h2>
                                <p class="text-xs text-slate-500">
                                    Regra do processo: <em>"inserir nas informações de pagamento do contrato as datas das parcelas dos boletos -> Gisele conferir contrato -> enviar boletos e contrato para assinatura"</em>.
                                </p>
                            </div>
                            <a href="contract_print.php?id=<?= $selectedPatient['id'] ?>" target="_blank" class="bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow">
                                🖨️ Abrir Contrato Preenchido (PDF / Impressão) ↗
                            </a>
                        </div>

                        <form method="POST" class="space-y-4 text-xs">
                            <input type="hidden" name="action" value="save_contract_boletos">
                            <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Valor Total da Avaliação (R$)</label>
                                    <input type="text" name="contract_total_value" value="<?= htmlspecialchars((string)$selectedPatient['contract_total_value']) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 font-bold">
                                </div>
                                <div class="flex items-end">
                                    <label class="flex items-center gap-2 font-semibold text-slate-700 bg-slate-100 px-3 py-2 rounded-lg border border-slate-200 w-full">
                                        <input type="checkbox" name="nf_issued" value="1" <?= $selectedPatient['nf_issued'] ? 'checked' : '' ?> class="w-4 h-4 accent-teal-600">
                                        Nota Fiscal (NF) já emitida para este paciente
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <?php for ($i = 0; $i < 4; $i++):
                                    $rowInst = $instList[$i] ?? ['parcela' => $i+1, 'vencimento' => '', 'valor' => '700,00', 'status' => 'Emitido'];
                                ?>
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                                        <span class="font-bold text-slate-800"><?= $i + 1 ?>ª Parcela do Boleto</span>
                                        <div>
                                            <label class="block text-[11px] text-slate-500">Data de Vencimento</label>
                                            <input type="date" name="boleto_date[]" value="<?= htmlspecialchars($rowInst['vencimento'] ?? '') ?>" class="w-full border border-slate-300 rounded px-2 py-1">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-slate-500">Valor (R$)</label>
                                            <input type="text" name="boleto_val[]" value="<?= htmlspecialchars($rowInst['valor'] ?? '') ?>" class="w-full border border-slate-300 rounded px-2 py-1">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-slate-500">Status do Boleto</label>
                                            <select name="boleto_status[]" class="w-full border border-slate-300 rounded px-2 py-1">
                                                <option value="Emitido" <?= ($rowInst['status'] ?? '') === 'Emitido' ? 'selected' : '' ?>>Emitido</option>
                                                <option value="Pago" <?= ($rowInst['status'] ?? '') === 'Pago' ? 'selected' : '' ?>>Pago</option>
                                                <option value="Aberto" <?= ($rowInst['status'] ?? '') === 'Aberto' ? 'selected' : '' ?>>Aberto</option>
                                            </select>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-5 py-2.5 rounded-xl">
                                💾 Salvar Datas dos Boletos e Atualizar Contrato
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Tabela de Controle para Emissão de Nota Fiscal (Nome, CPF, E-mail) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                        <div>
                            <h2 class="font-bold text-slate-900 text-base">🧾 Tabela de Controle para Emissão de Nota Fiscal (NF)</h2>
                            <p class="text-xs text-slate-500">Consolida automaticamente Nome, CPF, E-mail, Valor e Status da NF de cada cliente.</p>
                        </div>
                        <a href="export_csv.php?type=nf" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2 rounded-xl shadow">
                            📥 Exportar Tabela NF para Excel (.CSV)
                        </a>
                    </div>

                    <div class="overflow-x-auto max-h-96">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 border-b border-slate-200 sticky top-0">
                                    <th class="p-2.5 text-left">#</th>
                                    <th class="p-2.5 text-left">Paciente</th>
                                    <th class="p-2.5 text-left">Nome para NF (Responsável)</th>
                                    <th class="p-2.5 text-left">CPF</th>
                                    <th class="p-2.5 text-left">E-mail</th>
                                    <th class="p-2.5 text-left">Valor / Parcelas</th>
                                    <th class="p-2.5 text-left">Contrato (Gisele)</th>
                                    <th class="p-2.5 text-left">Status NF</th>
                                    <th class="p-2.5 text-right">Contrato</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php foreach (array_reverse($patients) as $p): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-2.5 font-bold"><?= htmlspecialchars((string)($p['code_number'] ?: $p['id'])) ?></td>
                                        <td class="p-2.5 font-semibold">
                                            <a href="?tab=financeiro&patient_id=<?= $p['id'] ?>" class="text-teal-700 hover:underline"><?= htmlspecialchars($p['full_name']) ?></a>
                                        </td>
                                        <td class="p-2.5"><?= htmlspecialchars($p['responsible_name'] ?: $p['full_name']) ?></td>
                                        <td class="p-2.5 font-mono"><?= htmlspecialchars($p['cpf_nf'] ?: '-') ?></td>
                                        <td class="p-2.5"><?= htmlspecialchars($p['email_nf'] ?: '-') ?></td>
                                        <td class="p-2.5">R$ <?= number_format((float)$p['contract_total_value'], 2, ',', '.') ?> (<?= (int)$p['contract_installments'] ?>x)</td>
                                        <td class="p-2.5">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $p['contract_gisele_approved'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                                <?= $p['contract_gisele_approved'] ? 'OK Gisele' : 'Pendente' ?>
                                            </span>
                                        </td>
                                        <td class="p-2.5">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $p['nf_issued'] ? 'bg-teal-100 text-teal-800' : 'bg-rose-100 text-rose-800' ?>">
                                                <?= $p['nf_issued'] ? 'NF Emitida' : 'Emitir NF' ?>
                                            </span>
                                        </td>
                                        <td class="p-2.5 text-right">
                                            <a href="contract_print.php?id=<?= $p['id'] ?>" target="_blank" class="text-slate-700 hover:text-teal-700 font-semibold">📄 Ver PDF</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTab === 'epidemiologico'): ?>
            <!-- =========================================================================
                 PAINEL EPIDEMIOLÓGICO E ESTATÍSTICO (FIEL À PLANILHA 2024 A 2026)
                 ========================================================================= -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <div>
                            <span class="text-xs font-bold uppercase bg-teal-50 text-teal-700 px-2.5 py-1 rounded-full border border-teal-200">
                                Base Histórica Integrada (2024 – 2026)
                            </span>
                            <h2 class="text-xl font-bold text-slate-900 mt-1">Painel Epidemiológico: Faixa Etária, Escolas e Diagnósticos</h2>
                            <p class="text-xs text-slate-500">Alimentado automaticamente pelos pacientes concluídos e pelos 91 registros importados da sua planilha.</p>
                        </div>
                        <a href="export_csv.php?type=epidemiologico" class="bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow">
                            📥 Baixar Planilha Completa (.CSV / Excel)
                        </a>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Tabela 1: FAIXA ETÁRIA (Idêntica à aba 2 da planilha) -->
                        <div class="bg-slate-50 rounded-xl border border-slate-200 p-4">
                            <h3 class="font-bold text-slate-900 text-xs uppercase tracking-wider mb-3">1. Distribuição por Faixa Etária</h3>
                            <table class="w-full text-xs border-collapse bg-white rounded-lg overflow-hidden">
                                <thead>
                                    <tr class="bg-slate-200 text-slate-700">
                                        <th class="p-2 text-left">FAIXA ETÁRIA</th>
                                        <th class="p-2 text-right">ATÉ DEZ/25</th>
                                        <th class="p-2 text-right">%</th>
                                        <th class="p-2 text-right">JAN-ABR 26</th>
                                        <th class="p-2 text-right">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <?php foreach ($metrics['age_stats'] as $agLabel => $agRow):
                                        $pctDez25 = round(($agRow['ate_dez25'] / $metrics['total_ate_dez25']) * 100, 1);
                                    ?>
                                        <tr>
                                            <td class="p-2 font-semibold"><?= htmlspecialchars($agLabel) ?></td>
                                            <td class="p-2 text-right"><?= $agRow['ate_dez25'] ?></td>
                                            <td class="p-2 text-right text-slate-500"><?= $pctDez25 ?>%</td>
                                            <td class="p-2 text-right"><?= $agRow['jan_abr26'] ?></td>
                                            <td class="p-2 text-right font-bold text-teal-800"><?= $agRow['total'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="bg-slate-100 font-bold">
                                        <td class="p-2">TOTAL</td>
                                        <td class="p-2 text-right"><?= $metrics['total_ate_dez25'] ?></td>
                                        <td class="p-2 text-right">100%</td>
                                        <td class="p-2 text-right"><?= $metrics['total_jan_abr26'] ?></td>
                                        <td class="p-2 text-right text-teal-900"><?= $metrics['total_geral_age'] ?></td>
                                    </tr>
                                    <tr class="bg-teal-50 font-semibold text-teal-900">
                                        <td class="p-2" colspan="2">MÉDIA POR MÊS (Até Dez/25)</td>
                                        <td class="p-2 text-right" colspan="3"><?= number_format(47 / 12, 2, ',', '.') ?> pac./mês</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Tabela 2: ESCOLAS -->
                        <div class="bg-slate-50 rounded-xl border border-slate-200 p-4">
                            <h3 class="font-bold text-slate-900 text-xs uppercase tracking-wider mb-3">2. Pacientes por Escola (2024–2026)</h3>
                            <div class="max-h-64 overflow-y-auto bg-white rounded-lg border border-slate-200">
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-slate-200 text-slate-700 sticky top-0">
                                            <th class="p-2 text-left">ESCOLA</th>
                                            <th class="p-2 text-right">PACIENTES</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <?php foreach ($metrics['school_stats'] as $scName => $scCount): ?>
                                            <tr>
                                                <td class="p-2 font-medium"><?= htmlspecialchars($scName) ?></td>
                                                <td class="p-2 text-right font-bold text-teal-800"><?= $scCount ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tabela 3: DIAGNÓSTICOS -->
                        <div class="bg-slate-50 rounded-xl border border-slate-200 p-4">
                            <h3 class="font-bold text-slate-900 text-xs uppercase tracking-wider mb-3">3. Incidência de Diagnósticos e Comorbidades</h3>
                            <div class="max-h-64 overflow-y-auto bg-white rounded-lg border border-slate-200">
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-slate-200 text-slate-700 sticky top-0">
                                            <th class="p-2 text-left">DIAGNÓSTICO / SIGLA</th>
                                            <th class="p-2 text-right">OCORRÊNCIAS</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <?php foreach ($metrics['diag_stats'] as $dName => $dCount): ?>
                                            <tr>
                                                <td class="p-2 font-semibold"><?= htmlspecialchars($dName) ?></td>
                                                <td class="p-2 text-right font-bold text-indigo-700"><?= $dCount ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela Geral dos 91+ Pacientes (Idêntica à Aba 1 da Planilha Google Sheets) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="font-bold text-slate-900 text-base mb-3">📋 Tabela Completa de Pacientes (Visualização Planilha — <?= count($patients) ?> Registros)</h3>
                    <div class="overflow-x-auto max-h-[540px]">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 border-b border-slate-300 sticky top-0">
                                    <th class="p-2 text-left">Pac.</th>
                                    <th class="p-2 text-left">Ano</th>
                                    <th class="p-2 text-left">Início</th>
                                    <th class="p-2 text-left">Fim</th>
                                    <th class="p-2 text-center">Idade</th>
                                    <th class="p-2 text-left">Faixa Etária</th>
                                    <th class="p-2 text-left">Escola</th>
                                    <th class="p-2 text-left">Escolaridade</th>
                                    <th class="p-2 text-center">Sessões</th>
                                    <th class="p-2 text-left">Diag. 1</th>
                                    <th class="p-2 text-left">Diag. 2</th>
                                    <th class="p-2 text-left">Diag. 3</th>
                                    <th class="p-2 text-left">Diag. 4</th>
                                    <th class="p-2 text-left">Comorbidade fora da lista</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php foreach ($patients as $p): ?>
                                    <tr class="hover:bg-teal-50/40">
                                        <td class="p-2 font-bold">
                                            <a href="?tab=clientes&patient_id=<?= $p['id'] ?>" class="text-teal-700 hover:underline">
                                                #<?= htmlspecialchars((string)($p['code_number'] ?: $p['id'])) ?>
                                            </a>
                                        </td>
                                        <td class="p-2"><?= htmlspecialchars($p['reference_year'] ?? '') ?></td>
                                        <td class="p-2"><?= htmlspecialchars($p['start_month'] ?? '') ?></td>
                                        <td class="p-2"><?= htmlspecialchars($p['end_month'] ?? '') ?></td>
                                        <td class="p-2 text-center font-semibold"><?= htmlspecialchars((string)($p['age'] ?? '')) ?></td>
                                        <td class="p-2"><?= htmlspecialchars($p['age_group'] ?? '') ?></td>
                                        <td class="p-2"><?= htmlspecialchars($p['school_name'] ?? '') ?></td>
                                        <td class="p-2"><?= htmlspecialchars($p['education_level'] ?? '') ?></td>
                                        <td class="p-2 text-center"><?= htmlspecialchars((string)($p['total_sessions'] ?? '')) ?></td>
                                        <td class="p-2 font-semibold text-teal-800"><?= htmlspecialchars($p['diag_1'] ?? '') ?></td>
                                        <td class="p-2 text-indigo-700"><?= htmlspecialchars($p['diag_2'] ?? '') ?></td>
                                        <td class="p-2 text-purple-700"><?= htmlspecialchars($p['diag_3'] ?? '') ?></td>
                                        <td class="p-2 text-rose-700"><?= htmlspecialchars($p['diag_4'] ?? '') ?></td>
                                        <td class="p-2 text-slate-600"><?= htmlspecialchars($p['unlisted_comorbidity'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTab === 'integracoes'): ?>
            <!-- =========================================================================
                 MÓDULO DE INTEGRAÇÕES (FORMULÁRIOS NATIVOS + GOOGLE FORMS + HOSTINGER)
                 ========================================================================= -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h2 class="font-bold text-slate-900 text-base">🔗 Integração Híbrida: Formulários Nativos & Google Forms</h2>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        O sistema já possui <strong>Formulários Nativos próprios</strong> (Cadastro do Cliente e Anamnese Preliminar) que dão baixa automática no checklist assim que o paciente envia as respostas.
                        Caso você também queira vincular um <strong>Google Forms externo</strong>, basta colar o script abaixo em <em>Extensões &rarr; Apps Script</em> na planilha do seu Google Forms para que ele avise o sistema automaticamente a cada resposta:
                    </p>
                    <pre class="bg-slate-900 text-teal-300 p-4 rounded-xl text-[11px] overflow-x-auto">// Google Apps Script (colar na Planilha de Respostas do Google Forms)
function onFormSubmit(e) {
  var payload = {
    "email": e.namedValues["E-mail"] ? e.namedValues["E-mail"][0] : "",
    "form_type": "anamnese" // ou "cadastro"
  };
  UrlFetchApp.fetch("<?= htmlspecialchars($baseUrl) ?>/api/webhook_google_forms.php", {
    "method": "post",
    "contentType": "application/json",
    "payload": JSON.stringify(payload)
  });
}</pre>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h2 class="font-bold text-slate-900 text-base">🚀 Implantação na Hostinger via GitHub (`coordenacaosg/appmenteemequilibrio`)</h2>
                    <ol class="list-decimal list-inside text-xs text-slate-700 space-y-2 leading-relaxed">
                        <li>No <strong>hPanel da Hostinger</strong>, acesse <strong>Bancos de Dados MySQL</strong> e crie o banco do sistema.</li>
                        <li>Renomeie o arquivo <code>.env.example</code> para <code>.env</code> com os dados do banco MySQL da Hostinger (no primeiro acesso o sistema criará todas as tabelas e importará os 91 pacientes automaticamente!).</li>
                        <li>Em <strong>hPanel &rarr; Avançado &rarr; Git</strong>, conecte o repositório <code>https://github.com/coordenacaosg/appmenteemequilibrio</code> no ramo <code>main</code> para deploy automático.</li>
                        <li>Em <strong>hPanel &rarr; Avançado &rarr; Cron Jobs</strong>, adicione a tarefa diária apontando para <code>cron/check_alerts.php</code>.</li>
                    </ol>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Modal para Cadastro Direto de Novo Paciente -->
    <div id="modalNovoPaciente" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-900 text-base">Cadastrar Novo Paciente em Avaliação</h3>
                <button onclick="document.getElementById('modalNovoPaciente').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
            </div>
            <form method="POST" class="space-y-3 text-xs">
                <input type="hidden" name="action" value="create_patient">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nome Completo do Paciente *</label>
                        <input type="text" name="full_name" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Responsável Legal/Financeiro</label>
                        <input type="text" name="responsible_name" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">CPF (Para NF e Contrato)</label>
                        <input type="text" name="cpf_nf" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">E-mail (Para NF)</label>
                        <input type="email" name="email_nf" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">WhatsApp</label>
                        <input type="text" name="phone_whatsapp" placeholder="5524999999999" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Idade</label>
                        <input type="number" name="age" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Escola</label>
                        <select name="school_name" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                            <option value="">-- Não se aplica / Adulto --</option>
                            <?php foreach ($schoolsCatalog as $sc): ?>
                                <option value="<?= htmlspecialchars($sc) ?>"><?= htmlspecialchars($sc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Escolaridade</label>
                        <input type="text" name="education_level" placeholder="Ex: 3º ano EF" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Data da Anamnese</label>
                        <input type="date" name="anamnesis_date" value="<?= date('Y-m-d', strtotime('+4 days')) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Etapa Inicial</label>
                        <select name="current_stage" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                            <option value="vai_iniciar">Pacientes que vão Iniciar (Pré-Consulta)</option>
                            <option value="em_avaliacao">Pacientes em Avaliação</option>
                            <option value="para_devolutiva">Vão para Devolutiva</option>
                        </select>
                    </div>
                </div>
                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('modalNovoPaciente').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600">Cancelar</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-teal-700 hover:bg-teal-800 text-white font-bold">Criar Paciente & Gerar Checklists</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
