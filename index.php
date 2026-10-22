<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/actions.php';
require_once __DIR__ . '/includes/metrics.php';

$pdo = getDbConnection();
$currentUser = getCurrentUser($pdo);
$flashMsg = handlePostActions($pdo, $currentUser);
if (isset($_GET['msg']) && $_GET['msg'] === 'converteu') {
    $flashMsg = "Lead convertido em Paciente com sucesso! Os procedimentos de pré-consulta e o checklist operacional foram gerados.";
}

$metrics = computeSystemMetrics($pdo);
$patients = $metrics['patients'];
$leads = $metrics['leads'];
$allUsers = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();
$schoolsCatalog = $pdo->query("SELECT name FROM schools_catalog ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$diagnosesCatalog = $pdo->query("SELECT * FROM diagnoses_catalog ORDER BY id ASC")->fetchAll();

$activeTab = $_GET['tab'] ?? 'clientes';
$stageFilter = $_GET['stage'] ?? 'ativos';
$subView = $_GET['sub'] ?? 'checklists'; // 'checklists', 'testes', 'prontuario'

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

$pendingGiseleContracts = array_filter($patients, fn($p) => $p['current_stage'] === 'vai_iniciar' && !(int)$p['contract_gisele_approved']);
$pendingGiseleLaudos = array_filter($patients, fn($p) => in_array($p['current_stage'], ['em_avaliacao', 'para_devolutiva'], true) && !(int)$p['laudo_gisele_approved']);
$totalAlertsCount = count($pendingGiseleContracts) + count($pendingGiseleLaudos);
$baseUrl = getBaseAppUrl();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gisele Silva Corrêa | Gestão da Avaliação Neuropsicológica</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        serif: ['"Cormorant Garamond"', 'Georgia', 'serif'],
                    },
                    colors: {
                        royal: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans min-h-screen flex flex-col md:flex-row">

    <!-- =========================================================================
         MENU LATERAL ESQUERDO (ESTILO CLEAN GISELENEUROPSICOLOGA.COM)
         ========================================================================= -->
    <aside class="w-full md:w-72 bg-white border-b md:border-b-0 md:border-r border-slate-200/80 flex flex-col justify-between shrink-0 md:sticky md:top-0 md:h-screen z-30">
        <div>
            <!-- Identidade Visual da Marca -->
            <div class="px-6 py-6 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-royal-700 text-white flex items-center justify-center font-serif font-bold text-xl shadow-sm">
                        G
                    </div>
                    <div>
                        <h1 class="font-serif font-bold text-xl text-slate-900 leading-none tracking-tight">
                            Gisele Silva Corrêa
                        </h1>
                        <p class="text-[11px] font-medium text-royal-700 tracking-wide uppercase mt-1">
                            Avaliação Neuropsicológica
                        </p>
                    </div>
                </div>
            </div>

            <!-- Botões de Acesso aos Módulos na Lateral Esquerda -->
            <nav class="p-4 space-y-1.5">
                <p class="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                    Módulos do Sistema
                </p>
                <?php
                $navItems = [
                    'clientes' => [
                        'label' => 'Por Cliente',
                        'desc' => 'Pré-consulta e checklists',
                        'badge' => $metrics['stage_counts']['vai_iniciar'] + $metrics['stage_counts']['em_avaliacao'] + $metrics['stage_counts']['para_devolutiva'],
                        'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>'
                    ],
                    'processos' => [
                        'label' => 'Por Processos Geral',
                        'desc' => 'Pendências por etapa',
                        'badge' => null,
                        'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>'
                    ],
                    'crm' => [
                        'label' => 'CRM de Leads',
                        'desc' => 'Captação e remarketing',
                        'badge' => count($leads),
                        'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>'
                    ],
                    'alertas' => [
                        'label' => 'Alertas & Aprovações',
                        'desc' => 'Prazos D+2/D+7 e OK Gisele',
                        'badge' => $totalAlertsCount,
                        'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>'
                    ],
                    'financeiro' => [
                        'label' => 'Contratos & Notas (NF)',
                        'desc' => 'Boletos, PDF e planilha NF',
                        'badge' => null,
                        'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                    ],
                    'epidemiologico' => [
                        'label' => 'Painel Epidemiológico',
                        'desc' => 'Estatísticas 2024–2026',
                        'badge' => count($patients),
                        'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>'
                    ],
                    'integracoes' => [
                        'label' => 'Configurações & Links',
                        'desc' => 'Google Forms e Hostinger',
                        'badge' => null,
                        'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'
                    ],
                ];

                foreach ($navItems as $tKey => $item):
                    $isActive = ($activeTab === $tKey);
                ?>
                    <a href="?tab=<?= $tKey ?>&patient_id=<?= $selectedPatientId ?>"
                       class="group flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all duration-200 <?= $isActive ? 'bg-royal-700 text-white shadow-sm' : 'text-slate-600 hover:bg-blue-50/80 hover:text-royal-800' ?>">
                        <div class="flex items-center gap-3">
                            <span class="<?= $isActive ? 'text-white' : 'text-slate-400 group-hover:text-royal-700' ?>">
                                <?= $item['icon'] ?>
                            </span>
                            <div>
                                <p class="text-xs font-semibold leading-tight"><?= $item['label'] ?></p>
                                <p class="text-[10px] <?= $isActive ? 'text-blue-100' : 'text-slate-400' ?>"><?= $item['desc'] ?></p>
                            </div>
                        </div>
                        <?php if ($item['badge'] !== null): ?>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $isActive ? 'bg-royal-900 text-blue-100' : 'bg-slate-100 text-slate-600' ?>">
                                <?= $item['badge'] ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Rodapé da Lateral Esquerda: Resumo de Etapas & Perfil de Acesso -->
        <div class="p-4 border-t border-slate-100 space-y-3 bg-slate-50/60">
            <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                <div class="bg-white px-2.5 py-2 rounded-lg border border-slate-200/70">
                    <span class="text-slate-400 block text-[10px]">Pré-Consulta</span>
                    <strong class="text-slate-800"><?= $metrics['stage_counts']['vai_iniciar'] ?></strong>
                </div>
                <div class="bg-white px-2.5 py-2 rounded-lg border border-slate-200/70">
                    <span class="text-slate-400 block text-[10px]">Em Avaliação</span>
                    <strong class="text-royal-700"><?= $metrics['stage_counts']['em_avaliacao'] ?></strong>
                </div>
                <div class="bg-white px-2.5 py-2 rounded-lg border border-slate-200/70">
                    <span class="text-slate-400 block text-[10px]">Devolutiva</span>
                    <strong class="text-slate-800"><?= $metrics['stage_counts']['para_devolutiva'] ?></strong>
                </div>
                <div class="bg-white px-2.5 py-2 rounded-lg border border-slate-200/70">
                    <span class="text-slate-400 block text-[10px]">Concluídos</span>
                    <strong class="text-emerald-600"><?= $metrics['stage_counts']['concluido'] ?></strong>
                </div>
            </div>

            <form method="POST" class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                <input type="hidden" name="action" value="switch_user">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    Perfil de Acesso Ativo
                </label>
                <select name="user_id" onchange="this.form.submit()" class="w-full bg-slate-50 text-slate-800 text-xs font-semibold rounded-lg px-2.5 py-1.5 border border-slate-200 focus:outline-none focus:border-royal-600">
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (int)$u['id'] === (int)$currentUser['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </aside>

    <!-- =========================================================================
         ÁREA DE CONTEÚDO PRINCIPAL (DIREITA)
         ========================================================================= -->
    <div class="flex-1 flex flex-col min-w-0">

        <!-- Cabeçalho Superior Clean -->
        <header class="bg-white/80 backdrop-blur-md border-b border-slate-200/80 px-6 py-4 sticky top-0 z-20 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-serif font-bold text-2xl text-slate-900 tracking-tight">
                    <?= htmlspecialchars($navItems[$activeTab]['label'] ?? 'Gestão Clínica') ?>
                </h2>
                <p class="text-xs text-slate-500">
                    <?= htmlspecialchars($navItems[$activeTab]['desc'] ?? '') ?>
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <button onclick="document.getElementById('modalNovoPaciente').classList.remove('hidden')"
                        class="bg-royal-700 hover:bg-royal-800 text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-sm transition flex items-center gap-1.5">
                    <span>+ Novo Paciente</span>
                </button>
            </div>
        </header>

        <!-- Notificação Flash -->
        <?php if ($flashMsg): ?>
            <div class="px-6 pt-4">
                <div class="bg-blue-50 border border-blue-200 text-royal-900 px-4 py-3 rounded-xl text-xs font-medium flex items-center justify-between">
                    <span>✓ <?= htmlspecialchars($flashMsg) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-royal-700 font-bold ml-4">✕</button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Conteúdo Dinâmico do Módulo -->
        <main class="p-6 flex-1">

            <?php if ($activeTab === 'clientes'): ?>
                <!-- =====================================================================
                     MÓDULO 1: VISÃO POR CLIENTE (LAYOUT CLEAN COM SUB-ABAS)
                     ===================================================================== -->
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

                    <!-- Lista de Clientes (3 colunas) -->
                    <div class="xl:col-span-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-4 flex flex-col max-h-[82vh]">
                        <div class="flex flex-wrap gap-1 mb-3 pb-3 border-b border-slate-100 text-[11px] font-medium">
                            <?php
                            $sFilters = [
                                'ativos' => 'Ativos (' . ($metrics['stage_counts']['vai_iniciar'] + $metrics['stage_counts']['em_avaliacao'] + $metrics['stage_counts']['para_devolutiva']) . ')',
                                'vai_iniciar' => 'Início (' . $metrics['stage_counts']['vai_iniciar'] . ')',
                                'em_avaliacao' => 'Avaliação (' . $metrics['stage_counts']['em_avaliacao'] . ')',
                                'para_devolutiva' => 'Devolutiva (' . $metrics['stage_counts']['para_devolutiva'] . ')',
                                'concluido' => 'Concluídos (' . $metrics['stage_counts']['concluido'] . ')'
                            ];
                            foreach ($sFilters as $sfKey => $sfLabel):
                            ?>
                                <a href="?tab=clientes&stage=<?= $sfKey ?>&patient_id=<?= $selectedPatientId ?>&sub=<?= urlencode($subView) ?>"
                                   class="px-2.5 py-1 rounded-lg transition <?= $stageFilter === $sfKey ? 'bg-royal-700 text-white font-semibold' : 'bg-slate-50 text-slate-600 hover:bg-blue-50 hover:text-royal-700' ?>">
                                    <?= $sfLabel ?>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <div class="overflow-y-auto space-y-1.5 pr-1 flex-1">
                            <?php
                            $filteredPatients = array_filter($patients, function($p) use ($stageFilter) {
                                if ($stageFilter === 'ativos') return $p['current_stage'] !== 'concluido';
                                return $p['current_stage'] === $stageFilter;
                            });
                            $filteredPatients = array_reverse($filteredPatients);
                            foreach ($filteredPatients as $pItem):
                                $isSelected = ((int)$pItem['id'] === $selectedPatientId);
                                $badge = formatStageBadge($pItem['current_stage']);
                            ?>
                                <a href="?tab=clientes&stage=<?= urlencode($stageFilter) ?>&patient_id=<?= $pItem['id'] ?>&sub=<?= urlencode($subView) ?>"
                                   class="block p-3 rounded-xl border transition <?= $isSelected ? 'bg-blue-50/60 border-royal-600 shadow-2xs' : 'bg-white border-slate-100 hover:border-slate-300' ?>">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-semibold text-xs text-slate-900 truncate"><?= htmlspecialchars($pItem['full_name']) ?></span>
                                        <span class="text-[10px] font-medium px-2 py-0.5 rounded-full border shrink-0 <?= $badge['bg'] ?>">
                                            <?= $badge['label'] ?>
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                                        <span><?= htmlspecialchars((string)($pItem['age'] ?? '-')) ?> anos • <?= htmlspecialchars($pItem['age_group'] ?? '-') ?></span>
                                        <span><?= htmlspecialchars($pItem['start_month'] ?? '') ?>/<?= htmlspecialchars($pItem['reference_year'] ?? '') ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Painel do Paciente Selecionado (8 colunas) -->
                    <div class="xl:col-span-8 space-y-5">
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
                            <!-- Card Superior do Paciente -->
                            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] font-semibold text-royal-700 bg-blue-50 px-2.5 py-0.5 rounded-md border border-blue-100">
                                                Paciente #<?= htmlspecialchars((string)($selectedPatient['code_number'] ?: $selectedPatient['id'])) ?>
                                            </span>
                                            <?php $stBadge = formatStageBadge($selectedPatient['current_stage']); ?>
                                            <span class="text-[11px] font-medium px-2.5 py-0.5 rounded-full border <?= $stBadge['bg'] ?>">
                                                <?= $stBadge['label'] ?>
                                            </span>
                                        </div>
                                        <h3 class="font-serif font-bold text-2xl text-slate-900 mt-1.5">
                                            <?= htmlspecialchars($selectedPatient['full_name']) ?>
                                        </h3>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            Responsável: <strong class="text-slate-700"><?= htmlspecialchars($selectedPatient['responsible_name'] ?: $selectedPatient['full_name']) ?></strong> •
                                            Anamnese: <strong class="text-slate-700"><?= $selectedPatient['anamnesis_date'] ? date('d/m/Y', strtotime($selectedPatient['anamnesis_date'])) : 'A definir' ?></strong>
                                        </p>
                                    </div>

                                    <!-- Ações Rápidas Clean -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="<?= htmlspecialchars($waReminderUrl) ?>" target="_blank"
                                           class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-3.5 py-2 rounded-xl transition">
                                            WhatsApp Pré-Consulta
                                        </a>
                                        <a href="<?= htmlspecialchars($contractUrl) ?>" target="_blank"
                                           class="bg-royal-700 hover:bg-royal-800 text-white text-xs font-semibold px-3.5 py-2 rounded-xl transition">
                                            Contrato PDF
                                        </a>
                                        <a href="laudo_qrcode.php?id=<?= $selectedPatient['id'] ?>" target="_blank"
                                           class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-3 py-2 rounded-xl transition">
                                            QR Code
                                        </a>
                                        <?php if (!empty($selectedPatient['drive_folder_url'])): ?>
                                            <a href="<?= htmlspecialchars($selectedPatient['drive_folder_url']) ?>" target="_blank"
                                               class="bg-blue-50 hover:bg-blue-100 text-royal-700 border border-blue-200 text-xs font-semibold px-3 py-2 rounded-xl transition">
                                                Drive ↗
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Sub-navegação Interna da Ficha do Cliente -->
                                <div class="flex gap-2 mt-5 pt-4 border-t border-slate-100 text-xs font-semibold">
                                    <?php
                                    $subTabs = [
                                        'checklists' => '1. Pré-Consulta & Checklists por Etapa',
                                        'testes' => '2. Bateria de Testes (Pasta / Excel / VOL)',
                                        'prontuario' => '3. Dados Cadastrais, Links & Diagnósticos'
                                    ];
                                    foreach ($subTabs as $sKey => $sLabel):
                                    ?>
                                        <a href="?tab=clientes&stage=<?= urlencode($stageFilter) ?>&patient_id=<?= $selectedPatient['id'] ?>&sub=<?= $sKey ?>"
                                           class="px-3.5 py-2 rounded-xl transition <?= $subView === $sKey ? 'bg-royal-700 text-white' : 'bg-slate-50 text-slate-600 hover:bg-blue-50 hover:text-royal-700' ?>">
                                            <?= $sLabel ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php if ($subView === 'checklists'): ?>
                                <!-- Painel de Acompanhamento Pré-Consulta (0/3) -->
                                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-serif font-bold text-xl text-slate-900">Painel de acompanhamento pré-consulta</h4>
                                        <span class="text-[11px] font-semibold bg-blue-50 text-royal-700 px-3 py-0.5 rounded-full border border-blue-100">
                                            Simulação / Monitoramento
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
                                        <span>Procedimentos concluídos</span>
                                        <span class="font-bold text-slate-900 text-sm"><?= $preDoneCount ?>/<?= $preTotalCount ?></span>
                                    </div>

                                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden mb-4">
                                        <div class="h-full bg-royal-700 transition-all duration-300" style="width: <?= $prePct ?>%"></div>
                                    </div>

                                    <div class="divide-y divide-slate-100">
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
                                            <div class="py-3.5 flex flex-wrap items-center justify-between gap-4">
                                                <div>
                                                    <p class="font-semibold text-slate-900 text-sm"><?= htmlspecialchars($pt['task_title']) ?></p>
                                                    <div class="flex flex-wrap items-center gap-3 mt-0.5 text-xs">
                                                        <?php if ($isDone): ?>
                                                            <span class="text-emerald-600 font-medium">Concluído em <?= date('d/m/Y H:i', strtotime($pt['completed_at'] ?? 'now')) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-slate-400">Pendente</span>
                                                        <?php endif; ?>
                                                        <a href="<?= htmlspecialchars($itemLink) ?>" target="_blank" class="text-royal-700 hover:underline font-medium">
                                                            Abrir formulário ↗
                                                        </a>
                                                    </div>
                                                </div>

                                                <form method="POST">
                                                    <input type="hidden" name="action" value="toggle_task">
                                                    <input type="hidden" name="task_id" value="<?= $pt['id'] ?>">
                                                    <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                                                    <button type="submit" class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl border text-xs font-medium transition <?= $isDone ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-white border-slate-200 text-slate-600 hover:border-royal-600' ?>">
                                                        <span class="w-4 h-4 rounded-full border flex items-center justify-center text-[10px] <?= $isDone ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-300' ?>">
                                                            <?= $isDone ? '✓' : '' ?>
                                                        </span>
                                                        Concluído
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
                                        <span>🔔 Após 2 dias, gerar lembretes apenas para os 3 procedimentos pendentes.</span>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="reset_pre_consulta">
                                            <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                                            <button type="submit" class="text-slate-500 hover:text-royal-700 font-medium">
                                                Reiniciar simulação
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Checklists Operacionais (Acordeões Clean) -->
                                <?php
                                $stageBlocks = [
                                    'vai_iniciar' => 'Etapa 1 — Pacientes que vão Iniciar (Pré-Consulta e Contrato)',
                                    'em_avaliacao' => 'Etapa 2 — Pacientes em Avaliação (Testes, Revisão do Laudo, Mapa e Gamma)',
                                    'para_devolutiva' => 'Etapa 3 — Pós-Testagem e Preparação para Devolutiva'
                                ];
                                foreach ($stageBlocks as $groupKey => $blockTitle):
                                    $tasks = $patientTasksByGroup[$groupKey] ?? [];
                                    $doneCnt = count(array_filter($tasks, fn($t) => $t['status'] === 'concluido'));
                                    $isOpen = ($selectedPatient['current_stage'] === $groupKey) || ($groupKey === 'vai_iniciar' && $selectedPatient['current_stage'] === 'concluido');
                                ?>
                                    <details <?= $isOpen ? 'open' : '' ?> class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden group">
                                        <summary class="px-6 py-4 cursor-pointer list-none flex items-center justify-between hover:bg-slate-50/70 transition">
                                            <div class="flex items-center gap-3">
                                                <span class="w-2 h-2 rounded-full bg-royal-700"></span>
                                                <h4 class="font-semibold text-sm text-slate-900"><?= $blockTitle ?></h4>
                                            </div>
                                            <span class="text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-700">
                                                <?= $doneCnt ?> / <?= count($tasks) ?>
                                            </span>
                                        </summary>

                                        <div class="divide-y divide-slate-100 px-6 pb-4 pt-1 border-t border-slate-100">
                                            <?php foreach ($tasks as $tk):
                                                $isDone = ($tk['status'] === 'concluido');
                                                $roleBadge = formatRoleBadge($tk['responsible_role']);
                                            ?>
                                                <div class="py-2.5 flex items-center justify-between gap-3">
                                                    <div class="flex items-start gap-3">
                                                        <form method="POST" class="mt-0.5">
                                                            <input type="hidden" name="action" value="toggle_task">
                                                            <input type="hidden" name="task_id" value="<?= $tk['id'] ?>">
                                                            <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                                                            <button type="submit" class="w-4 h-4 rounded border flex items-center justify-center text-[10px] transition <?= $isDone ? 'bg-royal-700 border-royal-700 text-white' : 'bg-white border-slate-300 hover:border-royal-600' ?>">
                                                                <?= $isDone ? '✓' : '' ?>
                                                            </button>
                                                        </form>
                                                        <div>
                                                            <p class="text-xs font-medium <?= $isDone ? 'line-through text-slate-400' : 'text-slate-700' ?>">
                                                                <?= htmlspecialchars($tk['task_title']) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full border shrink-0 <?= $roleBadge['bg'] ?>">
                                                        <?= $roleBadge['label'] ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>

                            <?php elseif ($subView === 'testes'): ?>
                                <!-- Sub-aba 2: Controle de Testes -->
                                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
                                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                                        <div>
                                            <h4 class="font-serif font-bold text-xl text-slate-900">Correção e Lançamento de Testes</h4>
                                            <p class="text-xs text-slate-500">Acompanhe o lançamento na pasta física, nas planilhas Excel do Drive, na plataforma VOL e na minuta do laudo.</p>
                                        </div>
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="action" value="add_test">
                                            <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                                            <input type="text" name="instrument_name" required placeholder="Ex: WISC-IV, BPA-2, SRS-2..." class="border border-slate-200 rounded-xl px-3 py-2 text-xs">
                                            <button type="submit" class="bg-royal-700 hover:bg-royal-800 text-white text-xs font-semibold px-4 py-2 rounded-xl">
                                                + Adicionar
                                            </button>
                                        </form>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs border-collapse">
                                            <thead>
                                                <tr class="bg-slate-50 text-slate-600 border-b border-slate-200">
                                                    <th class="p-3 text-left">Instrumento</th>
                                                    <th class="p-3 text-center">Pasta Física</th>
                                                    <th class="p-3 text-center">Excel Drive</th>
                                                    <th class="p-3 text-center">Plataforma VOL</th>
                                                    <th class="p-3 text-center">No Laudo</th>
                                                    <th class="p-3 text-right">Ação</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <?php foreach ($patientTests as $tItem): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="update_test_flags">
                                                        <input type="hidden" name="test_id" value="<?= $tItem['id'] ?>">
                                                        <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                                                        <tr class="hover:bg-slate-50/70">
                                                            <td class="p-3 font-medium text-slate-800"><?= htmlspecialchars($tItem['instrument_name']) ?></td>
                                                            <td class="p-3 text-center"><input type="checkbox" name="scored_physical_folder" value="1" <?= $tItem['scored_physical_folder'] ? 'checked' : '' ?> class="w-4 h-4 accent-royal-700"></td>
                                                            <td class="p-3 text-center"><input type="checkbox" name="entered_drive_excel" value="1" <?= $tItem['entered_drive_excel'] ? 'checked' : '' ?> class="w-4 h-4 accent-royal-700"></td>
                                                            <td class="p-3 text-center"><input type="checkbox" name="entered_vol_platform" value="1" <?= $tItem['entered_vol_platform'] ? 'checked' : '' ?> class="w-4 h-4 accent-royal-700"></td>
                                                            <td class="p-3 text-center"><input type="checkbox" name="inserted_in_report" value="1" <?= $tItem['inserted_in_report'] ? 'checked' : '' ?> class="w-4 h-4 accent-royal-700"></td>
                                                            <td class="p-3 text-right">
                                                                <button type="submit" class="bg-royal-700 hover:bg-royal-800 text-white px-3 py-1 rounded-lg text-[11px] font-medium">Salvar</button>
                                                            </td>
                                                        </tr>
                                                    </form>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            <?php else: ?>
                                <!-- Sub-aba 3: Prontuário Administrativo, Links e Diagnósticos -->
                                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
                                    <h4 class="font-serif font-bold text-xl text-slate-900 mb-4">Dados Cadastrais, Links Externos e Diagnósticos</h4>
                                    <form method="POST" class="space-y-4 text-xs">
                                        <input type="hidden" name="action" value="update_patient_details">
                                        <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Nome do Paciente</label>
                                                <input type="text" name="full_name" value="<?= htmlspecialchars($selectedPatient['full_name'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Responsável (NF / Contrato)</label>
                                                <input type="text" name="responsible_name" value="<?= htmlspecialchars($selectedPatient['responsible_name'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Etapa Atual</label>
                                                <select name="current_stage" class="w-full border border-slate-200 rounded-xl px-3 py-2 font-semibold">
                                                    <option value="vai_iniciar" <?= $selectedPatient['current_stage'] === 'vai_iniciar' ? 'selected' : '' ?>>Pré-Consulta / Vai Iniciar</option>
                                                    <option value="em_avaliacao" <?= $selectedPatient['current_stage'] === 'em_avaliacao' ? 'selected' : '' ?>>Em Avaliação</option>
                                                    <option value="para_devolutiva" <?= $selectedPatient['current_stage'] === 'para_devolutiva' ? 'selected' : '' ?>>Vai para Devolutiva</option>
                                                    <option value="concluido" <?= $selectedPatient['current_stage'] === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">CPF (Nota Fiscal)</label>
                                                <input type="text" name="cpf_nf" value="<?= htmlspecialchars($selectedPatient['cpf_nf'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">E-mail (Nota Fiscal)</label>
                                                <input type="email" name="email_nf" value="<?= htmlspecialchars($selectedPatient['email_nf'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">WhatsApp</label>
                                                <input type="text" name="phone_whatsapp" value="<?= htmlspecialchars($selectedPatient['phone_whatsapp'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 pt-3 border-t border-slate-100">
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Ano</label>
                                                <input type="text" name="reference_year" value="<?= htmlspecialchars($selectedPatient['reference_year'] ?? '2026') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Mês Início</label>
                                                <input type="text" name="start_month" value="<?= htmlspecialchars($selectedPatient['start_month'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Mês Fim</label>
                                                <input type="text" name="end_month" value="<?= htmlspecialchars($selectedPatient['end_month'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Idade</label>
                                                <input type="number" name="age" value="<?= htmlspecialchars((string)($selectedPatient['age'] ?? '')) ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Nº Sessões</label>
                                                <input type="text" name="total_sessions" value="<?= htmlspecialchars((string)($selectedPatient['total_sessions'] ?? '')) ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Escola</label>
                                                <select name="school_name" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                                    <option value="">-- Não se aplica / Adulto --</option>
                                                    <?php foreach ($schoolsCatalog as $sc): ?>
                                                        <option value="<?= htmlspecialchars($sc) ?>" <?= ($selectedPatient['school_name'] === $sc) ? 'selected' : '' ?>><?= htmlspecialchars($sc) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Escolaridade</label>
                                                <input type="text" name="education_level" value="<?= htmlspecialchars($selectedPatient['education_level'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Anamnese / Devolutiva</label>
                                                <div class="flex gap-1.5">
                                                    <input type="date" name="anamnesis_date" value="<?= htmlspecialchars($selectedPatient['anamnesis_date'] ?? '') ?>" class="w-1/2 border border-slate-200 rounded-xl px-2 py-2">
                                                    <input type="date" name="devolutiva_date" value="<?= htmlspecialchars($selectedPatient['devolutiva_date'] ?? '') ?>" class="w-1/2 border border-slate-200 rounded-xl px-2 py-2">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-3 border-t border-slate-100">
                                            <?php for ($dIdx = 1; $dIdx <= 4; $dIdx++):
                                                $col = "diag_{$dIdx}";
                                            ?>
                                                <div>
                                                    <label class="block font-medium text-slate-500 mb-1">Diagnóstico <?= $dIdx ?></label>
                                                    <select name="<?= $col ?>" class="w-full border border-slate-200 rounded-xl px-2.5 py-2">
                                                        <option value="">-- Nenhum --</option>
                                                        <?php foreach ($diagnosesCatalog as $dc): ?>
                                                            <option value="<?= htmlspecialchars($dc['acronym']) ?>" <?= ($selectedPatient[$col] === $dc['acronym']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($dc['acronym']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            <?php endfor; ?>
                                        </div>

                                        <div>
                                            <label class="block font-medium text-slate-500 mb-1">Comorbidade que não consta na lista</label>
                                            <input type="text" name="unlisted_comorbidity" value="<?= htmlspecialchars($selectedPatient['unlisted_comorbidity'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-3 border-t border-slate-100">
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Link da Pasta no Google Drive</label>
                                                <input type="url" name="drive_folder_url" value="<?= htmlspecialchars($selectedPatient['drive_folder_url'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                            <div>
                                                <label class="block font-medium text-slate-500 mb-1">Link da Apresentação no Gamma</label>
                                                <input type="url" name="gamma_url" value="<?= htmlspecialchars($selectedPatient['gamma_url'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                            </div>
                                        </div>

                                        <div class="pt-3 flex justify-end">
                                            <button type="submit" class="bg-royal-700 hover:bg-royal-800 text-white font-semibold px-6 py-2.5 rounded-xl shadow-sm transition">
                                                Salvar Alterações
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($activeTab === 'processos'): ?>
                <!-- =====================================================================
                     MÓDULO 2: VISÃO POR PROCESSOS GERAL (MATRIZ DE GARGALOS CLEAN)
                     ===================================================================== -->
                <div class="space-y-6">
                    <?php $selectedTaskCode = $_GET['task_code'] ?? 'cli_cadastro'; ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <?php
                        $groupTitles = [
                            'pre_consulta_cliente' => 'Pré-Consulta (Cliente)',
                            'vai_iniciar' => 'Pacientes que vão Iniciar',
                            'em_avaliacao' => 'Pacientes em Avaliação',
                            'para_devolutiva' => 'Vão para Devolutiva'
                        ];
                        foreach ($groupTitles as $gKey => $gLabel):
                            $items = array_filter($metrics['process_bottlenecks'], fn($b) => $b['stage_group'] === $gKey);
                        ?>
                            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-4">
                                <h3 class="font-serif font-bold text-base text-slate-900 mb-3 pb-2 border-b border-slate-100"><?= $gLabel ?></h3>
                                <div class="space-y-1.5 max-h-72 overflow-y-auto pr-1">
                                    <?php foreach ($items as $bItem):
                                        $isSel = ($selectedTaskCode === $bItem['task_code']);
                                    ?>
                                        <a href="?tab=processos&task_code=<?= urlencode($bItem['task_code']) ?>"
                                           class="flex items-center justify-between gap-2 p-2.5 rounded-xl text-xs transition <?= $isSel ? 'bg-royal-700 text-white font-semibold' : 'bg-slate-50 hover:bg-blue-50 text-slate-700' ?>">
                                            <span class="line-clamp-2"><?= htmlspecialchars($bItem['task_title']) ?></span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isSel ? 'bg-royal-900 text-white' : 'bg-white text-royal-700 border border-slate-200' ?>">
                                                <?= (int)$bItem['pending_count'] ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    $pendStmt = $pdo->prepare("
                        SELECT t.*, p.full_name, p.responsible_name, p.phone_whatsapp, p.current_stage
                        FROM patient_tasks t
                        INNER JOIN patients p ON p.id = t.patient_id
                        WHERE t.task_code = ? AND t.status != 'concluido' AND p.current_stage != 'concluido'
                        ORDER BY p.id DESC
                    ");
                    $pendStmt->execute([$selectedTaskCode]);
                    $pendingPatientsForTask = $pendStmt->fetchAll();
                    $taskTitleSelected = !empty($pendingPatientsForTask) ? $pendingPatientsForTask[0]['task_title'] : $selectedTaskCode;
                    ?>
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
                        <div class="mb-4">
                            <h3 class="font-serif font-bold text-xl text-slate-900">
                                Pacientes pendentes em: <span class="text-royal-700"><?= htmlspecialchars($taskTitleSelected) ?></span>
                            </h3>
                            <p class="text-xs text-slate-400"><?= count($pendingPatientsForTask) ?> paciente(s) aguardando conclusão desta etapa.</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                                        <th class="p-3 text-left">Paciente</th>
                                        <th class="p-3 text-left">Fase Atual</th>
                                        <th class="p-3 text-left">Responsável</th>
                                        <th class="p-3 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($pendingPatientsForTask as $row):
                                        $stBadge = formatStageBadge($row['current_stage']);
                                        $rBadge = formatRoleBadge($row['responsible_role']);
                                        $waQuick = buildWhatsAppUrl($row['phone_whatsapp'], "Olá! Passando para falar sobre a etapa '{$row['task_title']}' referente à avaliação de {$row['full_name']}.");
                                    ?>
                                        <tr class="hover:bg-slate-50/70">
                                            <td class="p-3 font-semibold text-slate-900">
                                                <a href="?tab=clientes&patient_id=<?= $row['patient_id'] ?>" class="hover:text-royal-700"><?= htmlspecialchars($row['full_name']) ?></a>
                                            </td>
                                            <td class="p-3"><span class="px-2.5 py-0.5 rounded-full border text-[10px] <?= $stBadge['bg'] ?>"><?= $stBadge['label'] ?></span></td>
                                            <td class="p-3"><span class="px-2.5 py-0.5 rounded-full border text-[10px] <?= $rBadge['bg'] ?>"><?= $rBadge['label'] ?></span></td>
                                            <td class="p-3 text-right flex items-center justify-end gap-2">
                                                <a href="<?= htmlspecialchars($waQuick) ?>" target="_blank" class="px-3 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-medium hover:bg-emerald-100">WhatsApp</a>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="toggle_task">
                                                    <input type="hidden" name="task_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="patient_id" value="<?= $row['patient_id'] ?>">
                                                    <button type="submit" class="px-3 py-1 rounded-lg bg-royal-700 hover:bg-royal-800 text-white font-medium">Concluir</button>
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
                <!-- =====================================================================
                     MÓDULO 3: CRM DE LEADS & REMARKETING
                     ===================================================================== -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
                        <h3 class="font-serif font-bold text-xl text-slate-900 mb-1">Novo Contato (Lead)</h3>
                        <p class="text-xs text-slate-400 mb-4">Registra a queixa inicial e agenda lembrete de 7 dias.</p>

                        <form method="POST" class="space-y-3 text-xs">
                            <input type="hidden" name="action" value="create_lead">
                            <div>
                                <label class="block font-medium text-slate-600 mb-1">Nome do Contato / Responsável *</label>
                                <input type="text" name="contact_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2">
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-2">
                                    <label class="block font-medium text-slate-600 mb-1">Paciente *</label>
                                    <input type="text" name="patient_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                                <div>
                                    <label class="block font-medium text-slate-600 mb-1">Idade</label>
                                    <input type="number" name="patient_age" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-medium text-slate-600 mb-1">WhatsApp *</label>
                                    <input type="text" name="phone_whatsapp" required placeholder="5524999999999" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                                <div>
                                    <label class="block font-medium text-slate-600 mb-1">Origem</label>
                                    <select name="origin" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                                        <option value="WhatsApp">WhatsApp</option>
                                        <option value="Indicação">Indicação</option>
                                        <option value="Site / Google">Site / Google</option>
                                        <option value="Instagram">Instagram</option>
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="first_contact_date" value="<?= date('Y-m-d') ?>">
                            <input type="hidden" name="status" value="aguardando_decisao">
                            <div>
                                <label class="block font-medium text-slate-600 mb-1">Dor Relatada / Queixa Principal *</label>
                                <textarea name="main_complaint" rows="3" required class="w-full border border-slate-200 rounded-xl px-3 py-2"></textarea>
                            </div>
                            <button type="submit" class="w-full bg-royal-700 hover:bg-royal-800 text-white font-semibold py-2.5 rounded-xl shadow-xs transition">
                                Registrar Lead
                            </button>
                        </form>
                    </div>

                    <div class="lg:col-span-8 space-y-4">
                        <?php foreach ($leads as $ld):
                            $isRem = ($ld['status'] === 'remarketing');
                            $waRemarketingMsg = "Olá, {$ld['contact_name']}! Tudo bem? Passando para saber se ficou alguma dúvida sobre como funciona a Avaliação Neuropsicológica para {$ld['patient_name']}. Caso deseje verificar os horários disponíveis para a anamnese, estou à disposição!";
                        ?>
                            <div class="bg-white rounded-2xl border <?= $isRem ? 'border-blue-300' : 'border-slate-200/80' ?> shadow-xs p-5">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <span class="font-semibold text-sm text-slate-900"><?= htmlspecialchars($ld['patient_name']) ?> (<?= htmlspecialchars((string)($ld['patient_age'] ?? '-')) ?> anos)</span>
                                        <span class="text-xs text-slate-400 ml-2">Contato: <?= htmlspecialchars($ld['contact_name']) ?></span>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold <?= $isRem ? 'bg-blue-100 text-royal-800' : 'bg-slate-100 text-slate-600' ?>">
                                        <?= $isRem ? 'Remarketing 7+ dias' : htmlspecialchars(ucfirst($ld['status'])) ?>
                                    </span>
                                </div>

                                <p class="text-xs text-slate-600 mt-2.5 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <strong class="text-slate-800">Queixa relatada:</strong> <?= htmlspecialchars($ld['main_complaint']) ?>
                                </p>

                                <?php if ($ld['status'] !== 'convertido'): ?>
                                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs">
                                        <a href="<?= htmlspecialchars(buildWhatsAppUrl($ld['phone_whatsapp'], $waRemarketingMsg)) ?>" target="_blank"
                                           class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                                            WhatsApp / Remarketing
                                        </a>
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="action" value="convert_lead_to_patient">
                                            <input type="hidden" name="lead_id" value="<?= $ld['id'] ?>">
                                            <input type="date" name="anamnesis_date" required value="<?= date('Y-m-d', strtotime('+4 days')) ?>" class="border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs">
                                            <button type="submit" class="bg-royal-700 hover:bg-royal-800 text-white font-semibold px-3.5 py-2 rounded-xl">
                                                Confirmar Anamnese
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($activeTab === 'alertas'): ?>
                <!-- =====================================================================
                     MÓDULO 4: ALERTAS AUTOMÁTICOS & APROVAÇÕES DA GISELE
                     ===================================================================== -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
                        <h3 class="font-serif font-bold text-xl text-slate-900">Aprovações Pendentes — Coordenação (Gisele)</h3>

                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Contratos para Conferir (<?= count($pendingGiseleContracts) ?>)</p>
                        <div class="space-y-2">
                            <?php foreach ($pendingGiseleContracts as $pc): ?>
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center justify-between gap-2 text-xs">
                                    <div>
                                        <p class="font-semibold text-slate-900"><?= htmlspecialchars($pc['full_name']) ?></p>
                                        <p class="text-slate-400">R$ <?= number_format((float)$pc['contract_total_value'], 2, ',', '.') ?> (<?= (int)$pc['contract_installments'] ?>x)</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="contract_print.php?id=<?= $pc['id'] ?>" target="_blank" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white font-medium">Ver</a>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="gisele_quick_approve">
                                            <input type="hidden" name="patient_id" value="<?= $pc['id'] ?>">
                                            <input type="hidden" name="approve_type" value="contrato">
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-royal-700 text-white font-semibold">Aprovar Contrato</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider pt-2">Laudos Aguardando OK para Impressão (<?= count($pendingGiseleLaudos) ?>)</p>
                        <div class="space-y-2 max-h-72 overflow-y-auto">
                            <?php foreach ($pendingGiseleLaudos as $pl): ?>
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center justify-between gap-2 text-xs">
                                    <span class="font-semibold text-slate-900"><?= htmlspecialchars($pl['full_name']) ?></span>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="gisele_quick_approve">
                                        <input type="hidden" name="patient_id" value="<?= $pl['id'] ?>">
                                        <input type="hidden" name="approve_type" value="laudo">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-semibold">Autorizar Impressão</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
                        <h3 class="font-serif font-bold text-xl text-slate-900">Lembretes de Pré-Consulta (D+2) e Anamnese (D-2)</h3>
                        <?php
                        $startingPatients = array_filter($patients, fn($p) => $p['current_stage'] === 'vai_iniciar');
                        foreach ($startingPatients as $sp):
                            $tSt = $pdo->prepare("SELECT * FROM patient_tasks WHERE patient_id=? AND stage_group='pre_consulta_cliente'");
                            $tSt->execute([$sp['id']]);
                            $spTasks = $tSt->fetchAll();
                            $waUrl = buildPreConsultaReminderWhatsApp($sp, $spTasks);
                        ?>
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/70 text-xs flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900"><?= htmlspecialchars($sp['full_name']) ?></p>
                                    <p class="text-slate-400">Anamnese: <?= $sp['anamnesis_date'] ? date('d/m/Y', strtotime($sp['anamnesis_date'])) : 'A definir' ?></p>
                                </div>
                                <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="px-3.5 py-2 rounded-xl bg-royal-700 hover:bg-royal-800 text-white font-semibold">
                                    Cobrar Pendências no WhatsApp
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($activeTab === 'financeiro'): ?>
                <!-- =====================================================================
                     MÓDULO 5: CONTRATOS, BOLETOS E CONTROLE DE NF
                     ===================================================================== -->
                <div class="space-y-6">
                    <?php if ($selectedPatient):
                        $instList = !empty($selectedPatient['contract_installments_json']) ? json_decode($selectedPatient['contract_installments_json'], true) : [];
                    ?>
                        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
                            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                                <div>
                                    <h3 class="font-serif font-bold text-xl text-slate-900">Datas das Parcelas dos Boletos — <?= htmlspecialchars($selectedPatient['full_name']) ?></h3>
                                    <p class="text-xs text-slate-400">As datas salvas abaixo são inseridas automaticamente na Cláusula 2ª do Contrato.</p>
                                </div>
                                <a href="contract_print.php?id=<?= $selectedPatient['id'] ?>" target="_blank" class="bg-royal-700 hover:bg-royal-800 text-white text-xs font-semibold px-4 py-2.5 rounded-xl">
                                    Abrir Contrato para Impressão / PDF ↗
                                </a>
                            </div>

                            <form method="POST" class="space-y-4 text-xs">
                                <input type="hidden" name="action" value="save_contract_boletos">
                                <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block font-medium text-slate-500 mb-1">Valor Total (R$)</label>
                                        <input type="text" name="contract_total_value" value="<?= htmlspecialchars((string)$selectedPatient['contract_total_value']) ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2 font-semibold">
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-2 font-medium text-slate-700 bg-slate-50 px-3.5 py-2 rounded-xl border border-slate-200 w-full">
                                            <input type="checkbox" name="nf_issued" value="1" <?= $selectedPatient['nf_issued'] ? 'checked' : '' ?> class="w-4 h-4 accent-royal-700">
                                            Nota Fiscal (NF) já emitida
                                        </label>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                    <?php for ($i = 0; $i < 4; $i++):
                                        $rowInst = $instList[$i] ?? ['parcela' => $i+1, 'vencimento' => '', 'valor' => '700,00', 'status' => 'Emitido'];
                                    ?>
                                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 space-y-2">
                                            <span class="font-semibold text-slate-800"><?= $i + 1 ?>ª Parcela</span>
                                            <input type="date" name="boleto_date[]" value="<?= htmlspecialchars($rowInst['vencimento'] ?? '') ?>" class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5">
                                            <input type="text" name="boleto_val[]" value="<?= htmlspecialchars($rowInst['valor'] ?? '') ?>" class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5">
                                            <input type="hidden" name="boleto_status[]" value="<?= htmlspecialchars($rowInst['status'] ?? 'Emitido') ?>">
                                        </div>
                                    <?php endfor; ?>
                                </div>

                                <button type="submit" class="bg-royal-700 hover:bg-royal-800 text-white font-semibold px-5 py-2.5 rounded-xl">
                                    Salvar Parcelas no Contrato
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
                        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                            <h3 class="font-serif font-bold text-xl text-slate-900">Tabela de Controle para Emissão de Nota Fiscal (NF)</h3>
                            <a href="export_csv.php?type=nf" class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold px-4 py-2 rounded-xl">
                                Exportar CSV / Excel
                            </a>
                        </div>
                        <div class="overflow-x-auto max-h-96">
                            <table class="w-full text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200 sticky top-0">
                                        <th class="p-3 text-left">Paciente</th>
                                        <th class="p-3 text-left">Responsável (NF)</th>
                                        <th class="p-3 text-left">CPF</th>
                                        <th class="p-3 text-left">E-mail</th>
                                        <th class="p-3 text-left">Valor</th>
                                        <th class="p-3 text-left">Status NF</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach (array_reverse($patients) as $p): ?>
                                        <tr class="hover:bg-slate-50/70">
                                            <td class="p-3 font-semibold"><a href="?tab=financeiro&patient_id=<?= $p['id'] ?>" class="text-royal-700 hover:underline"><?= htmlspecialchars($p['full_name']) ?></a></td>
                                            <td class="p-3"><?= htmlspecialchars($p['responsible_name'] ?: $p['full_name']) ?></td>
                                            <td class="p-3 font-mono text-slate-500"><?= htmlspecialchars($p['cpf_nf'] ?: '-') ?></td>
                                            <td class="p-3 text-slate-500"><?= htmlspecialchars($p['email_nf'] ?: '-') ?></td>
                                            <td class="p-3">R$ <?= number_format((float)$p['contract_total_value'], 2, ',', '.') ?></td>
                                            <td class="p-3">
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold <?= $p['nf_issued'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                                                    <?= $p['nf_issued'] ? 'Emitida' : 'Pendente' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab === 'epidemiologico'): ?>
                <!-- =====================================================================
                     MÓDULO 6: PAINEL EPIDEMIOLÓGICO (2024–2026)
                     ===================================================================== -->
                <div class="space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5">
                            <h3 class="font-serif font-bold text-lg text-slate-900 mb-3">Distribuição por Faixa Etária</h3>
                            <table class="w-full text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                                        <th class="p-2 text-left">Faixa Etária</th>
                                        <th class="p-2 text-right">Até Dez/25</th>
                                        <th class="p-2 text-right">Jan-Abr 26</th>
                                        <th class="p-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($metrics['age_stats'] as $agLabel => $agRow): ?>
                                        <tr>
                                            <td class="p-2 font-medium"><?= htmlspecialchars($agLabel) ?></td>
                                            <td class="p-2 text-right"><?= $agRow['ate_dez25'] ?></td>
                                            <td class="p-2 text-right"><?= $agRow['jan_abr26'] ?></td>
                                            <td class="p-2 text-right font-bold text-royal-700"><?= $agRow['total'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5">
                            <h3 class="font-serif font-bold text-lg text-slate-900 mb-3">Pacientes por Escola</h3>
                            <div class="max-h-56 overflow-y-auto">
                                <table class="w-full text-xs border-collapse">
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($metrics['school_stats'] as $scName => $scCount): ?>
                                            <tr>
                                                <td class="p-2"><?= htmlspecialchars($scName) ?></td>
                                                <td class="p-2 text-right font-bold text-royal-700"><?= $scCount ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5">
                            <h3 class="font-serif font-bold text-lg text-slate-900 mb-3">Incidência de Diagnósticos</h3>
                            <div class="max-h-56 overflow-y-auto">
                                <table class="w-full text-xs border-collapse">
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($metrics['diag_stats'] as $dName => $dCount): ?>
                                            <tr>
                                                <td class="p-2 font-medium"><?= htmlspecialchars($dName) ?></td>
                                                <td class="p-2 text-right font-bold text-royal-700"><?= $dCount ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
                        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                            <h3 class="font-serif font-bold text-xl text-slate-900">Base Histórica Completa (<?= count($patients) ?> Pacientes)</h3>
                            <a href="export_csv.php?type=epidemiologico" class="bg-royal-700 hover:bg-royal-800 text-white text-xs font-semibold px-4 py-2 rounded-xl">
                                Exportar Planilha (.CSV)
                            </a>
                        </div>
                        <div class="overflow-x-auto max-h-[480px]">
                            <table class="w-full text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200 sticky top-0">
                                        <th class="p-2.5 text-left">#</th>
                                        <th class="p-2.5 text-left">Ano</th>
                                        <th class="p-2.5 text-left">Início / Fim</th>
                                        <th class="p-2.5 text-center">Idade</th>
                                        <th class="p-2.5 text-left">Escola / Escolaridade</th>
                                        <th class="p-2.5 text-center">Sessões</th>
                                        <th class="p-2.5 text-left">Diagnósticos</th>
                                        <th class="p-2.5 text-left">Comorbidade Especificada</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($patients as $p):
                                        $diags = array_filter([$p['diag_1'], $p['diag_2'], $p['diag_3'], $p['diag_4']]);
                                    ?>
                                        <tr class="hover:bg-slate-50/70">
                                            <td class="p-2.5 font-semibold"><a href="?tab=clientes&patient_id=<?= $p['id'] ?>" class="text-royal-700 hover:underline">#<?= htmlspecialchars((string)($p['code_number'] ?: $p['id'])) ?></a></td>
                                            <td class="p-2.5"><?= htmlspecialchars($p['reference_year'] ?? '') ?></td>
                                            <td class="p-2.5 text-slate-500"><?= htmlspecialchars($p['start_month'] ?? '') ?> – <?= htmlspecialchars($p['end_month'] ?? '') ?></td>
                                            <td class="p-2.5 text-center font-medium"><?= htmlspecialchars((string)($p['age'] ?? '')) ?></td>
                                            <td class="p-2.5"><?= htmlspecialchars($p['school_name'] ?: ($p['education_level'] ?? '')) ?></td>
                                            <td class="p-2.5 text-center"><?= htmlspecialchars((string)($p['total_sessions'] ?? '')) ?></td>
                                            <td class="p-2.5 font-semibold text-royal-700"><?= htmlspecialchars(implode(', ', $diags)) ?></td>
                                            <td class="p-2.5 text-slate-500"><?= htmlspecialchars($p['unlisted_comorbidity'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab === 'integracoes'): ?>
                <!-- =====================================================================
                     MÓDULO 7: INTEGRAÇÕES & CONFIGURAÇÕES
                     ===================================================================== -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-3">
                        <h3 class="font-serif font-bold text-xl text-slate-900">Webhook Google Forms (Opcional)</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Os formulários nativos do sistema já dão baixa automática no checklist. Se desejar integrar também planilhas do Google Forms, adicione este script no menu <em>Extensões &rarr; Apps Script</em> da planilha:
                        </p>
                        <pre class="bg-slate-900 text-blue-200 p-4 rounded-xl text-[11px] overflow-x-auto">function onFormSubmit(e) {
  var payload = {
    "email": e.namedValues["E-mail"] ? e.namedValues["E-mail"][0] : "",
    "form_type": "anamnese"
  };
  UrlFetchApp.fetch("<?= htmlspecialchars($baseUrl) ?>/api/webhook_google_forms.php", {
    "method": "post",
    "contentType": "application/json",
    "payload": JSON.stringify(payload)
  });
}</pre>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-3">
                        <h3 class="font-serif font-bold text-xl text-slate-900">Atualização Contínua via GitHub</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Repositório conectado: <code>https://github.com/coordenacaosg/appmenteemequilibrio</code>.<br>
                            Para atualizar o subdomínio na Hostinger após novas alterações, acesse <strong>hPanel &rarr; Avançado &rarr; Git</strong> e clique em <strong>Implantar (Deploy)</strong>.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Modal Novo Paciente -->
    <div id="modalNovoPaciente" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 border border-slate-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-serif font-bold text-xl text-slate-900">Novo Paciente em Avaliação</h3>
                <button onclick="document.getElementById('modalNovoPaciente').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <form method="POST" class="space-y-3 text-xs">
                <input type="hidden" name="action" value="create_patient">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Nome do Paciente *</label>
                        <input type="text" name="full_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Responsável Legal</label>
                        <input type="text" name="responsible_name" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">CPF (Para NF)</label>
                        <input type="text" name="cpf_nf" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">E-mail (Para NF)</label>
                        <input type="email" name="email_nf" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">WhatsApp</label>
                        <input type="text" name="phone_whatsapp" placeholder="5524999999999" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Idade</label>
                        <input type="number" name="age" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Escola</label>
                        <select name="school_name" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                            <option value="">-- Não se aplica / Adulto --</option>
                            <?php foreach ($schoolsCatalog as $sc): ?>
                                <option value="<?= htmlspecialchars($sc) ?>"><?= htmlspecialchars($sc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Data da Anamnese</label>
                        <input type="date" name="anamnesis_date" value="<?= date('Y-m-d', strtotime('+4 days')) ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                    </div>
                </div>
                <input type="hidden" name="current_stage" value="vai_iniciar">
                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('modalNovoPaciente').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600">Cancelar</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-royal-700 hover:bg-royal-800 text-white font-semibold">Salvar Paciente</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
