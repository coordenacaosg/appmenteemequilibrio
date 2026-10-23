<?php
/**
 * Gerador Automático de Contratos Oficiais de Avaliação Neuropsicológica
 * Suporte aos 2 Modelos Oficiais da Clínica Mente em Equilíbrio:
 * 1. Modelo Infância e Adolescência (com Representantes Legais 1 e 2 e Cláusula de Acompanhamento Presencial)
 * 2. Modelo Geral / Adulto
 *
 * Inclui:
 * - Tabela dinâmica de parcelas e vencimentos de boletos
 * - Dados bancários oficiais (Banco Inter, PIX CNPJ)
 * - Selo de conferência da Gisele e confirmação de assinatura
 * - Impressão otimizada A4 e edição inline (contenteditable)
 */
require_once __DIR__ . '/includes/helpers.php';
$pdo = getDbConnection();
$currentUser = getCurrentUser($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    die("Paciente não encontrado.");
}

// Determina o modelo ativo: 'infantil' ou 'adulto'
$model = $_GET['model'] ?? ($patient['contract_model'] ?: ((!empty($patient['age']) && (int)$patient['age'] < 18) ? 'infantil' : 'adulto'));
if (!in_array($model, ['infantil', 'adulto'])) {
    $model = 'adulto';
}

// Ações POST (Aprovação Gisele, Assinatura, Salvar Dados Rápidos dos Representantes)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve_gisele') {
        $pdo->prepare("UPDATE patients SET contract_gisele_approved=1, contract_gisele_approved_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
        $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Gisele (Coordenação)' WHERE patient_id=? AND task_code='ini_gisele_conferir_contrato'")->execute([$id]);
        logAudit($pdo, $currentUser, $id, 'Gisele conferiu e aprovou o Contrato de Prestação de Serviço');
    } elseif ($action === 'sign_contract') {
        $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Assinatura Confirmada' WHERE patient_id=? AND task_code='cli_contrato_assinado'")->execute([$id]);
        logAudit($pdo, $currentUser, $id, 'Contrato de Prestação de Serviço marcado como Assinado');
    } elseif ($action === 'update_contract_parties') {
        $contractModel = $_POST['contract_model'] ?? $model;
        $respName1 = trim($_POST['responsible_name'] ?? '');
        $respKinship1 = trim($_POST['responsible_kinship_1'] ?? '');
        $cpfNf = trim($_POST['cpf_nf'] ?? '');
        $phone = trim($_POST['phone_whatsapp'] ?? '');
        $address = trim($_POST['address_full'] ?? '');
        $email = trim($_POST['email_nf'] ?? '');

        $respName2 = trim($_POST['responsible_name_2'] ?? '');
        $respKinship2 = trim($_POST['responsible_kinship_2'] ?? '');
        $respCpf2 = trim($_POST['responsible_cpf_2'] ?? '');
        $respPhone2 = trim($_POST['responsible_phone_2'] ?? '');
        $respAddress2 = trim($_POST['responsible_address_2'] ?? '');

        $pdo->prepare("UPDATE patients SET contract_model=?, responsible_name=?, responsible_kinship_1=?, cpf_nf=?, phone_whatsapp=?, address_full=?, email_nf=?, responsible_name_2=?, responsible_kinship_2=?, responsible_cpf_2=?, responsible_phone_2=?, responsible_address_2=? WHERE id=?")
            ->execute([$contractModel, $respName1, $respKinship1, $cpfNf, $phone, $address, $email, $respName2, $respKinship2, $respCpf2, $respPhone2, $respAddress2, $id]);

        logAudit($pdo, $currentUser, $id, "Atualizou dados dos representantes legais no contrato ({$contractModel})");
        header("Location: contract_print.php?id={$id}&model={$contractModel}&saved=1");
        exit;
    }
    header("Location: contract_print.php?id={$id}&model={$model}");
    exit;
}

$installments = !empty($patient['contract_installments_json']) ? json_decode($patient['contract_installments_json'], true) : [];
$totalValue = (float)($patient['contract_total_value'] ?: 4500.00);

// Gera parcelas padrão se vazio
if (empty($installments)) {
    $installments = [
        ['parcela' => 1, 'vencimento' => date('Y-m-d', strtotime('+5 days')), 'valor' => '1.500,00', 'status' => 'Emitido'],
        ['parcela' => 2, 'vencimento' => date('Y-m-d', strtotime('+35 days')), 'valor' => '1.500,00', 'status' => 'Emitido'],
        ['parcela' => 3, 'vencimento' => date('Y-m-d', strtotime('+65 days')), 'valor' => '1.500,00', 'status' => 'Emitido'],
    ];
}

// Extrai CEP do endereço, se presente
$cep1 = '';
if (!empty($patient['address_full']) && preg_match('/\b\d{5}-?\d{3}\b/', $patient['address_full'], $m)) {
    $cep1 = $m[0];
}
$cep2 = '';
if (!empty($patient['responsible_address_2']) && preg_match('/\b\d{5}-?\d{3}\b/', $patient['responsible_address_2'], $m)) {
    $cep2 = $m[0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato de Avaliação Neuropsicológica (<?= $model === 'infantil' ? 'Infância e Adolescência' : 'Adulto' ?>) — <?= htmlspecialchars($patient['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
            background-color: #f1f5f9;
        }
        .serif-font {
            font-family: 'Cormorant Garamond', Georgia, serif;
        }
        .contract-sheet {
            background: #ffffff;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.08);
            position: relative;
        }
        .contract-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 480px;
            opacity: 0.035;
            pointer-events: none;
            z-index: 0;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; padding: 0 !important; }
            .contract-sheet {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 1.5cm 1.5cm !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .page-break { page-break-after: always; }
        }
    </style>
</head>
<body class="py-8 px-4">

    <!-- BARRA SUPERIOR DE CONTROLE E SELEÇÃO DE MODELO (Oculta na Impressão/PDF) -->
    <div class="max-w-4xl mx-auto mb-6 bg-white p-4 sm:p-5 rounded-2xl shadow-sm border border-slate-200 no-print space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <a href="index.php?tab=clientes&patient_id=<?= $patient['id'] ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold transition">
                    ← Prontuário do Paciente
                </a>
                <span class="text-xs font-bold uppercase px-3 py-1.5 rounded-full <?= $patient['contract_gisele_approved'] ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' ?>">
                    <?= $patient['contract_gisele_approved'] ? '✓ Conferido e Aprovado por Gisele' : '⏳ Aguardando Conferência da Gisele' ?>
                </span>
            </div>

            <div class="flex items-center gap-2">
                <?php if (!$patient['contract_gisele_approved']): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="approve_gisele">
                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm transition">
                            ✓ OK da Gisele
                        </button>
                    </form>
                <?php endif; ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="sign_contract">
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition">
                        ✍️ Confirmar Assinatura
                    </button>
                </form>
                <button onclick="window.print()" class="px-4 py-2 rounded-xl bg-[#1d4ed8] hover:bg-[#1e40af] text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                    🖨️ Imprimir / Salvar PDF
                </button>
            </div>
        </div>

        <!-- Seletor de Modelo Oficial -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 text-xs">
            <div class="flex items-center gap-2">
                <span class="font-semibold text-slate-500 uppercase tracking-wider text-[11px]">Modelo de Contrato:</span>
                <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200">
                    <a href="?id=<?= $id ?>&model=infantil" class="px-3.5 py-1.5 rounded-lg font-semibold transition <?= $model === 'infantil' ? 'bg-white text-blue-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                        👶 Infância e Adolescência
                    </a>
                    <a href="?id=<?= $id ?>&model=adulto" class="px-3.5 py-1.5 rounded-lg font-semibold transition <?= $model === 'adulto' ? 'bg-white text-blue-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                        👤 Adulto / Geral
                    </a>
                </div>
            </div>

            <button onclick="document.getElementById('partiesModal').classList.toggle('hidden')" class="text-blue-700 hover:text-blue-800 font-semibold flex items-center gap-1 text-xs">
                ⚙️ Preencher / Editar Dados dos Representantes
            </button>
        </div>
    </div>

    <!-- MODAL / FORMULÁRIO DE DADOS DOS REPRESENTANTES (NO-PRINT) -->
    <div id="partiesModal" class="hidden max-w-4xl mx-auto mb-6 bg-white p-6 rounded-2xl shadow-md border border-slate-300 no-print">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-900 text-sm">Editar Qualificação das Partes no Contrato</h3>
            <button onclick="document.getElementById('partiesModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
        </div>
        <form method="POST" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="update_contract_parties">
            <input type="hidden" name="contract_model" value="<?= htmlspecialchars($model) ?>">

            <?php if ($model === 'infantil'): ?>
                <div class="bg-blue-50/60 p-4 rounded-xl border border-blue-100 space-y-3">
                    <span class="font-bold text-blue-900 uppercase tracking-wider text-[11px]">Representante Legal 1 (Principal)</span>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Nome Completo *</label>
                            <input type="text" name="responsible_name" value="<?= htmlspecialchars($patient['responsible_name'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">CPF *</label>
                            <input type="text" name="cpf_nf" value="<?= htmlspecialchars($patient['cpf_nf'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2" placeholder="000.000.000-00">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Grau de Parentesco *</label>
                            <input type="text" name="responsible_kinship_1" value="<?= htmlspecialchars($patient['responsible_kinship_1'] ?? 'Mãe') ?>" class="w-full border border-slate-300 rounded-lg p-2" placeholder="Ex: Mãe, Pai, Tutora">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Telefone (com DDD) *</label>
                            <input type="text" name="phone_whatsapp" value="<?= htmlspecialchars($patient['phone_whatsapp'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">E-mail</label>
                            <input type="email" name="email_nf" value="<?= htmlspecialchars($patient['email_nf'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Endereço Residencial e CEP</label>
                            <input type="text" name="address_full" value="<?= htmlspecialchars($patient['address_full'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2" placeholder="Rua, nº, bairro, cidade, CEP">
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                    <span class="font-bold text-slate-800 uppercase tracking-wider text-[11px]">Representante Legal 2 (Segundo Responsável)</span>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Nome Completo</label>
                            <input type="text" name="responsible_name_2" value="<?= htmlspecialchars($patient['responsible_name_2'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">CPF</label>
                            <input type="text" name="responsible_cpf_2" value="<?= htmlspecialchars($patient['responsible_cpf_2'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2" placeholder="000.000.000-00">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Grau de Parentesco</label>
                            <input type="text" name="responsible_kinship_2" value="<?= htmlspecialchars($patient['responsible_kinship_2'] ?? 'Pai') ?>" class="w-full border border-slate-300 rounded-lg p-2" placeholder="Ex: Pai, Mãe">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Telefone (com DDD)</label>
                            <input type="text" name="responsible_phone_2" value="<?= htmlspecialchars($patient['responsible_phone_2'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-medium text-slate-700 mb-1">Endereço Residencial e CEP (se diferente)</label>
                            <input type="text" name="responsible_address_2" value="<?= htmlspecialchars($patient['responsible_address_2'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2" placeholder="Preencha caso resida em endereço diferente">
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-blue-50/60 p-4 rounded-xl border border-blue-100 space-y-3">
                    <span class="font-bold text-blue-900 uppercase tracking-wider text-[11px]">Contratante (Avaliando/a Adulto)</span>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Nome Completo *</label>
                            <input type="text" name="responsible_name" value="<?= htmlspecialchars($patient['responsible_name'] ?: $patient['full_name']) ?>" class="w-full border border-slate-300 rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">CPF *</label>
                            <input type="text" name="cpf_nf" value="<?= htmlspecialchars($patient['cpf_nf'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2" placeholder="000.000.000-00">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Telefone (com DDD) *</label>
                            <input type="text" name="phone_whatsapp" value="<?= htmlspecialchars($patient['phone_whatsapp'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">E-mail *</label>
                            <input type="email" name="email_nf" value="<?= htmlspecialchars($patient['email_nf'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-medium text-slate-700 mb-1">Endereço Residencial Completo e CEP *</label>
                            <input type="text" name="address_full" value="<?= htmlspecialchars($patient['address_full'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg p-2">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('partiesModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-semibold">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-sm">Salvar Dados no Contrato</button>
            </div>
        </form>
    </div>

    <!-- FOLHA DO CONTRATO OFICIAL (EDITÁVEL & IMPRIMÍVEL A4) -->
    <div contenteditable="true" spellcheck="false" class="contract-sheet max-w-4xl mx-auto p-10 md:p-14 rounded-2xl text-slate-800 leading-relaxed text-[13px] border border-slate-200">

        <!-- Marca d'Água Sutil Central -->
        <svg class="contract-watermark" viewBox="0 0 200 200" fill="currentColor">
            <path d="M100 15 C60 15, 25 45, 25 90 C25 125, 45 155, 75 175 L75 185 L125 185 L125 175 C155 155, 175 125, 175 90 C175 45, 140 15, 100 15 Z M90 140 C75 140, 65 125, 65 105 C65 85, 75 70, 90 70 Z M110 140 Z"/>
        </svg>

        <!-- Faixa Azul Superior Oficial da Clínica -->
        <div class="bg-[#244082] text-white p-6 rounded-xl text-center mb-8 shadow-sm">
            <h1 class="text-lg md:text-xl font-bold uppercase tracking-wider leading-snug">
                <?= $model === 'infantil'
                    ? 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS PARA AVALIAÇÃO NEUROPSICOLÓGICA INFÂNCIA E ADOLESCÊNCIA'
                    : 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS PARA AVALIAÇÃO NEUROPSICOLÓGICA' ?>
            </h1>
        </div>

        <!-- SEÇÃO DOS DADOS -->
        <div class="mb-6">
            <h2 class="text-center font-bold text-slate-900 uppercase tracking-widest text-sm mb-4">DOS DADOS</h2>

            <?php if ($model === 'infantil'): ?>
                <!-- Dados Infância e Adolescência: 2 Representantes Legais + Avaliando -->
                <div class="space-y-3 mb-4">
                    <p><strong>REPRESENTANTE LEGAL 1:</strong></p>
                    <p class="pl-4">
                        Nome: <strong><?= htmlspecialchars($patient['responsible_name'] ?: '________________________________________________') ?></strong><br>
                        CPF nº: <strong><?= htmlspecialchars($patient['cpf_nf'] ?: '___.___.___-__') ?></strong><br>
                        Telefone (com DDD): <strong><?= htmlspecialchars($patient['phone_whatsapp'] ?: '(__) _________') ?></strong><br>
                        Grau de parentesco: <strong><?= htmlspecialchars($patient['responsible_kinship_1'] ?: 'Mãe') ?></strong><br>
                        Endereço residencial: <strong><?= htmlspecialchars($patient['address_full'] ?: '________________________________________________') ?></strong><br>
                        CEP residencial: <strong><?= htmlspecialchars($cep1 ?: '_____-___') ?></strong>
                    </p>

                    <p class="pt-2"><strong>REPRESENTANTE LEGAL 2:</strong></p>
                    <p class="pl-4">
                        Nome: <strong><?= htmlspecialchars($patient['responsible_name_2'] ?: '________________________________________________') ?></strong><br>
                        CPF nº: <strong><?= htmlspecialchars($patient['responsible_cpf_2'] ?: '___.___.___-__') ?></strong><br>
                        Telefone (com DDD): <strong><?= htmlspecialchars($patient['responsible_phone_2'] ?: '(__) _________') ?></strong><br>
                        Grau de parentesco: <strong><?= htmlspecialchars($patient['responsible_kinship_2'] ?: 'Pai') ?></strong><br>
                        Endereço residencial: <strong><?= htmlspecialchars($patient['responsible_address_2'] ?: ($patient['address_full'] ?: '________________________________________________')) ?></strong><br>
                        CEP residencial: <strong><?= htmlspecialchars($cep2 ?: ($cep1 ?: '_____-___')) ?></strong>
                    </p>

                    <p class="text-center italic text-xs py-1 text-slate-600">ambos representando neste ato o(a)</p>

                    <p><strong>AVALIANDO(A):</strong></p>
                    <p class="pl-4">
                        Nome: <strong><?= htmlspecialchars($patient['full_name']) ?></strong><br>
                        CPF nº: <strong><?= htmlspecialchars($patient['cpf_nf'] ?: '___.___.___-__') ?></strong><br>
                        Telefone (caso tenha): <strong><?= htmlspecialchars($patient['phone_whatsapp'] ?: 'Não possui') ?></strong><br>
                        Idade: <strong><?= htmlspecialchars((string)($patient['age'] ?? 'Não informada')) ?> anos</strong><br>
                        Endereço residencial: <strong><?= htmlspecialchars($patient['address_full'] ?: '________________________________________________') ?></strong><br>
                        CEP residencial: <strong><?= htmlspecialchars($cep1 ?: '_____-___') ?></strong>
                    </p>

                    <p class="italic text-xs text-slate-600 pt-2 text-justify">
                        Os adultos acima denominados declaram-se representantes legais do paciente e comprometem-se a apresentar a documentação comprobatória da guarda, sendo a sua assinatura presumida veracidade do status legal, sob pena de cometimento do crime do artigo 307 do Código Penal e demais artigos competentes do Estatuto da Criança e do Adolescente.
                    </p>
                </div>
            <?php else: ?>
                <!-- Dados Adulto: Contratante / Avaliando -->
                <div class="space-y-2 mb-4">
                    <p><strong>CONTRATANTE (AVALIANDO/A):</strong></p>
                    <p class="pl-4">
                        Nome: <strong><?= htmlspecialchars($patient['responsible_name'] ?: $patient['full_name']) ?></strong><br>
                        CPF nº: <strong><?= htmlspecialchars($patient['cpf_nf'] ?: '___.___.___-__') ?></strong><br>
                        Telefone (com DDD): <strong><?= htmlspecialchars($patient['phone_whatsapp'] ?: '(__) _________') ?></strong><br>
                        E-mail: <strong><?= htmlspecialchars($patient['email_nf'] ?: '________________________') ?></strong><br>
                        Endereço residencial: <strong><?= htmlspecialchars($patient['address_full'] ?: '________________________________________________') ?></strong><br>
                        CEP residencial: <strong><?= htmlspecialchars($cep1 ?: '_____-___') ?></strong>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Dados da Clínica -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 mt-4">
                <p><strong>CLÍNICA:</strong></p>
                <p class="pl-4 text-xs space-y-0.5 mt-1 text-slate-700">
                    Razão Social: <strong>MENTE EM EQUILÍBRIO SERVIÇOS DE PSICOLOGIA LTDA.</strong><br>
                    CNPJ: <strong>53.076.815/0001-10</strong> | CRP-Empresa: <strong>05/40880</strong><br>
                    Telefone: <strong>(24) 99964-2123</strong><br>
                    Endereço: <strong>Rua Saturnino Braga, nº 369, sala 334, bairro Centro, CEP nº 27511-300, cidade de Resende/RJ, Brasil.</strong><br>
                    Representante legal: <strong>Gisele Silva Corrêa (CRP 05/40880)</strong>
                </p>
            </div>
        </div>

        <!-- CLÁUSULA 1: A AVALIAÇÃO NEUROPSICOLÓGICA -->
        <div class="mt-6">
            <h2 class="text-center font-bold text-slate-900 uppercase tracking-widest text-sm mb-3">A AVALIAÇÃO NEUROPSICOLÓGICA</h2>
            <p class="font-bold mb-2">ITEM 1:</p>
            <p class="text-justify mb-2">
                O presente documento tem por objeto a prestação de serviço de Avaliação Neuropsicológica, a qual consistirá na realização dos trabalhos descritos no <strong>ANEXO I</strong>.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo primeiro:</strong> A avaliação neuropsicológica somente terá seu processo iniciado após a realização da triagem através do WhatsApp pela psicóloga, de modo que seja possível mensurar as necessidades da demanda apresentada.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo segundo:</strong> A avaliação neuropsicológica é um processo estruturado de investigação de fenômenos psicológicos que poderão requisitar o trabalho multidisciplinar, envolvendo outros profissionais da saúde vinculados à contratada, entrevistas psicológicas com o avaliando(a) e terceiros, anamnese, testes psicológicos e tarefas neuropsicológicas, exames, documentos técnicos, entre outros, sendo seu processo realizado de forma presencial, telepresencial e/ou híbrida.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo terceiro:</strong> A avaliação neuropsicológica, embora considere as hipóteses diagnósticas levantadas pelo avaliando(a), representantes legais ou terceiros, terá seu processo embasado em métodos e técnicas cientificamente reconhecidos, tendo a profissional que realizar o serviço plena prerrogativa sobre as conclusões técnicas que levantar durante o processo de investigação.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo quarto:</strong> As conclusões técnicas descritas no Laudo Neuropsicológico não poderão ser alteradas ou complementadas por mera vontade do avaliando(a), representantes legais ou terceiros, cientes do crime previsto no artigo 298 do Código Penal Brasileiro.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo quinto:</strong> O processo de avaliação neuropsicológica, embora seja estruturado e com tempo determinado, poderá necessitar de entrevistas, testes etc. extras para fins de conclusão diagnóstica, circunstâncias que serão devidamente informadas e explicadas ao avaliando(a) ou representante legal, podendo necessitar de serviços extras não dispostos neste contrato e em seus anexos.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo sexto:</strong> Caso a profissional identifique que serão necessárias mais sessões do que as acordadas na estimativa inicial do número de sessões para plena execução da avaliação psicológica, tal necessidade será devidamente explicada para os contratantes e não incidirá custos adicionais.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo sétimo:</strong> Caso o contratante decida suspender/encerrar a avaliação psicológica, tal circunstância poderá prejudicar o processo, estando ciente de que não será devolvida integralidade do valor pago.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo oitavo:</strong> Os protocolos dos testes e materiais produzidos durante o processo de avaliação não serão entregues a(o) avaliando(a) ou seus responsáveis como forma de resultado, tendo em vista que o Conselho Federal de Psicologia não permite tal entrega<sup>1</sup>. No caso, será elaborado o Laudo Psicológico, documento este em que serão abordados e interpretados os protocolos e materiais utilizados, buscando serem esclarecidas as questões que se fizerem pertinentes à análise do caso.
            </p>
            <?php if ($model === 'adulto'): ?>
                <p class="text-justify mb-2">
                    <strong>Parágrafo nono:</strong> As sessões telepresenciais serão realizadas através das plataformas Google Meet ou Zoom, tendo em vista que estas garantem a segurança e confidencialidade das informações protegidas pelo sigilo profissional, conforme Lei Geral de Proteção de Dados e orientação do Conselho Federal de Psicologia. Não serão realizadas sessões através do WhatsApp, por mensagem, e-mail ou outra plataforma desconhecida.
                </p>
            <?php endif; ?>
            <p class="text-justify mb-4">
                <strong>Parágrafo <?= $model === 'adulto' ? 'décimo' : 'nono' ?>:</strong> O Laudo Neuropsicológico somente será produzido após a finalização e realização das sessões previamente agendadas, sendo que a data de entrega do Laudo será ajustada junto com o contratante.
            </p>
            <p class="text-[11px] text-slate-500 border-t border-slate-200 pt-1 mb-6">
                <sup>1</sup> Resolução CFP nº 31 de dezembro de 2022.
            </p>
        </div>

        <!-- CLÁUSULA 2: RESPONSABILIDADES DO AVALIANDO / REPRESENTANTES -->
        <div class="mt-6">
            <h2 class="text-center font-bold text-slate-900 uppercase tracking-widest text-sm mb-3">
                <?= $model === 'infantil'
                    ? 'AS RESPONSABILIDADES DO(A) AVALIANDO(A) E DOS REPRESENTANTES LEGAIS'
                    : 'AS RESPONSABILIDADES DO(A) AVALIANDO(A)/CONTRATANTE' ?>
            </h2>
            <p class="text-justify mb-3">
                Para o bom andamento do processo de avaliação neuropsicológica, é importante que o(a) avaliando(a) e/ou seu responsável legal assumam alguns compromissos conjuntamente com a profissional. Abaixo estão listados alguns pontos importantes para este processo:
            </p>
            <p class="font-bold mb-2">ITEM 2:</p>
            <ul class="space-y-2 text-justify pl-4 list-none mb-6">
                <li><strong>a)</strong> Procurar a profissional sempre que tiver alguma dúvida referente à avaliação neuropsicológica;</li>
                <li><strong>b)</strong> Responder os questionários, testes, exercícios etc., solicitados pela profissional nos prazos estabelecidos, bem como, fornecer exames, materiais, documentos etc. solicitados pela profissional para pleno cumprimento do processo avaliativo;</li>
                <li><strong>c)</strong> Comparecer aos encontros/sessões agendadas <u>no horário e dia combinados previamente</u>, primando pela pontualidade e assiduidade;</li>
                <li><strong>d)</strong> Sempre informar a profissional caso não possa comparecer ao encontro/sessão com pelo menos <strong>24 horas de antecedência</strong>;</li>
                <li><strong>e)</strong> Não solicitar aceleração ou adiantamento do processo de avaliação previsto, nem antecipação dos resultados;</li>
                <li><strong>f)</strong> Caso o avaliando utilize alguma medicação ou esteja em algum tratamento em saúde deve informar previamente o/a profissional antes de iniciar o processo de avaliação;</li>
                <li><strong>g)</strong> Caso o avaliando utilize óculos ou aparelhos auditivos, precisa necessariamente estar na presença destes nos encontros da avaliação, pois caso contrário poderá prejudicar o processo de avaliação;</li>
                <li><strong>h)</strong> Não solicitará o fracionamento de nota fiscal do serviço de avaliação neuropsicológica prestado;</li>
                <li><strong>i)</strong> Entender que, em casos de pedido de reembolso ao Plano de Saúde, este é de inteira responsabilidade do representante legal e, caso o pedido não seja autorizado pelo Convênio, compreende que não será estornado o valor já pago pelo serviço prestado. A responsabilidade da Clínica e do/a profissional está tão somente no fornecimento para o representante legal de documentos que comprovem a realização do serviço prestado e que façam parte do rol de documentos profissional padronizados na Resolução CFP nº 6/2019<sup>2</sup>;</li>
                <?php if ($model === 'adulto'): ?>
                    <li><strong>j)</strong> Jamais gravar áudios ou fazer vídeos durante os encontros/sessões do processo de avaliação neuropsicológica, nem fazer capturas de tela/"prints" da tela do aparelho eletrônico, de modo a preservar o sigilo profissional e o direito de imagem da profissional;</li>
                    <li><strong>k)</strong> Nas sessões de avaliação na modalidade telepresencial, é imprescindível que o(a) examinando:
                        <ul class="list-disc pl-6 mt-1 space-y-1 text-slate-700">
                            <li>Use computador/notebook preferencialmente, e evite o uso de celular durante o atendimento;</li>
                            <li>Esteja sozinho(a) em local tranquilo e confortável, livre de interrupções;</li>
                            <li>Tenha boa conexão de internet, câmera de qualidade e fones de ouvido;</li>
                            <li>Disponha de computador/notebook com no mínimo 14 polegadas de tela;</li>
                            <li>Disponha de uma mesa, papel, lápis e impressora (desejável);</li>
                            <li>Não coma, beba álcool, fume ou use drogas, antes ou durante as sessões;</li>
                            <li>Evite atividades distrativas durante as sessões;</li>
                            <li>Feche outras abas ou aplicativos no dispositivo, e utilize o modo não perturbe;</li>
                            <li>Disponha de alguém que possa ser um apoio local, para fornecer suporte ao profissional durante as sessões (desejável).</li>
                        </ul>
                    </li>
                    <li><strong>l)</strong> Em caso de tratamento com outro profissional da saúde e caso haja necessidade de trabalho em equipe multiprofissional, o avaliado autoriza, de forma expressa, a troca de informações relevantes sobre o caso entre a psicóloga e o outro profissional;</li>
                    <li><strong>m)</strong> O avaliado autoriza, de forma expressa, o contato com escola, professores, familiares, outros profissionais que possam ser informantes relevantes para o processo de avaliação, estando ciente de que determinadas informações poderão fazer constar no Laudo Psicológico, se relevantes ao caso;</li>
                    <li><strong>n)</strong> O avaliado autoriza que, quando necessário, o/a profissional psicólogo/a conte com o auxílio de um estagiário da psicologia ou psicólogo auxiliar durante os atendimentos do processo de avaliação neuropsicológica;</li>
                    <li><strong>o)</strong> Não deve fazer refeições, lanches, fumar, ingerir bebidas alcoólicas ou utilizar qualquer tipo de droga ilícita ou substância que altere seu estado de consciência antes ou durante o atendimento, de modo a não prejudicar a avaliação;</li>
                    <li><strong>p)</strong> O avaliado autoriza previamente eventuais gravações de sessões que se fizerem necessárias para evolução da demanda, sendo sempre informado e especificada a necessidade para tal; e</li>
                    <li><strong>q)</strong> Cumprir com os pagamentos ajustados neste contrato.</li>
                <?php else: ?>
                    <li><strong>j)</strong> Em caso de tratamento com outro profissional da saúde e caso haja necessidade de trabalho em equipe multiprofissional, o representante legal autoriza, de forma expressa, a troca de informações relevantes sobre o caso entre a psicóloga e o outro profissional;</li>
                    <li><strong>k)</strong> O representante legal autoriza, de forma expressa, o contato com escola, professores, familiares, outros profissionais que possam ser informantes relevantes para o processo de avaliação, estando ciente de que determinadas informações poderão fazer constar no Laudo Psicológico, se relevantes ao caso;</li>
                    <li><strong>l)</strong> O avaliado autoriza que, quando necessário, o/a profissional psicólogo/a conte com o auxílio de um estagiário da psicologia ou psicólogo auxiliar durante os atendimentos do processo de avaliação neuropsicológica;</li>
                    <li><strong>m)</strong> Não deve fazer refeições, lanches, fumar, ingerir bebidas alcoólicas ou utilizar qualquer tipo de droga ilícita ou substância que altere seu estado de consciência antes ou durante o atendimento, de modo a não prejudicar a avaliação;</li>
                    <li><strong>n)</strong> O representante legal autoriza previamente eventuais gravações de sessões que se fizerem necessárias para evolução da demanda, sendo sempre informado e especificada a necessidade para tal; e</li>
                    <li><strong>o)</strong> Cumprir com os pagamentos ajustados neste contrato.</li>
                <?php endif; ?>
            </ul>
            <p class="text-[11px] text-slate-500 border-t border-slate-200 pt-1 mb-6">
                <sup>2</sup> Mais informações sobre direitos envolvendo Reembolso de Plano de Saúde: <span class="text-blue-700">https://www.gov.br/ans/pt-br/assuntos/consumidor/o-que-o-seu-plano-de-saude-deve-cobrir1/reembolso</span>
            </p>
        </div>

        <!-- CLÁUSULA 3: AS RESPONSABILIDADES DO PROFISSIONAL E DA CLÍNICA -->
        <div class="mt-6">
            <h2 class="text-center font-bold text-slate-900 uppercase tracking-widest text-sm mb-3">AS RESPONSABILIDADES DO PROFISSIONAL E DA CLÍNICA</h2>
            <p class="text-justify mb-3">
                O trabalho do profissional de saúde no processo de avaliação neuropsicológica será de realizar investigação de fenômenos psicológicos, neurológicos, fisiológicos, entre outros, na busca de prover informações à tomada de decisão com base em demandas, condições e finalidades específicas. Assim como o(a) avaliando(a), a(o) profissional e a clínica também possuem responsabilidades, como:
            </p>
            <p class="font-bold mb-2">ITEM 3:</p>
            <ul class="space-y-2 text-justify pl-4 list-none mb-6">
                <li><strong>a)</strong> Agir com o máximo de zelo e respeito, utilizando o melhor de sua capacidade profissional na atenção à saúde do(a) avaliando(a);</li>
                <li><strong>b)</strong> Guardar sigilo a respeito das informações de que detenha conhecimento sobre o(a) avaliando(a), com exceção dos casos previstos em lei ou quando identificado sério risco à vida do(a) avaliando(a) ou de terceiros;</li>
                <li><strong>c)</strong> Estar presente no dia e horário pré-estabelecido ao encontro/sessão agendada. Caso haja necessidade de o(a) profissional efetuar mudança no horário do atendimento, o(a) avaliando(a) será comunicado(a) com 24 horas de antecedência (excetuando-se emergências). Neste caso, o(a) profissional ofertará horários/datas substitutivos mais próximos possível aos originais do(a) avaliando(a), a fim de não comprometer com a avaliação psicológica;</li>
                <li><strong>d)</strong> Jamais fazer previsão taxativa de resultados dentro do processo de avaliação psicológica;</li>
                <li><strong>e)</strong> Proceder ao registro do serviço prestado no prontuário do avaliando(a), mantendo o arquivamento documental de modo seguro e sigiloso. As informações partilhadas durante os encontros/sessões serão mantidas em estrita confidencialidade e não serão reveladas sem o consentimento do(a) avaliando(a);</li>
                <li><strong>f)</strong> Proceder aos encaminhamentos, inclusive multiprofissionais, conforme necessidades do caso;</li>
                <li><strong>g)</strong> Jamais atuar como perito(a) ou assistente técnico de examinando(a), atual ou anterior, bem como de familiar ou terceiro vinculado ao examinando(a);</li>
                <li><strong>h)</strong> Garantir a qualidade científica, teórica, técnica e ética da avaliação psicológica;</li>
                <li><strong>i)</strong> Estar com situação regular com o Conselho Regional de Psicologia;</li>
                <li><strong>j)</strong> Prestar informações claras e objetivas do serviço prestado;</li>
                <li><strong>k)</strong> Prestar explicações judiciais, caso necessário, mediante padrão de documentos psicológicos estabelecidos pela Resolução CFP nº 6/2019, ciente de que estes precisam seguir critérios técnicos e científicos e que não podem ser alterados por vontade do avaliando(a), representantes legais ou terceiro; e</li>
                <li><strong>l)</strong> Cumprir as combinações ajustadas junto com o(a) examinando(a), valendo-se das práticas cientificamente reconhecidas, respeitados a legislação vigente e os preceitos éticos.</li>
            </ul>
        </div>

        <!-- CLÁUSULA 4: O VALOR DA AVALIAÇÃO PSICOLÓGICA -->
        <div class="mt-6">
            <h2 class="text-center font-bold text-slate-900 uppercase tracking-widest text-sm mb-3">O VALOR DA AVALIAÇÃO PSICOLÓGICA</h2>
            <p class="font-bold mb-2">ITEM 4:</p>
            <p class="text-justify mb-2">
                O(a) <?= $model === 'infantil' ? 'representante legal' : 'contratante' ?> se compromete a remunerar a contratada pelos serviços destacados no <strong>ANEXO I</strong> conforme valores e formas de pagamento descritos abaixo:
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo primeiro:</strong> O pagamento poderá ser realizado através das seguintes formas:
            </p>
            <ul class="list-disc pl-6 space-y-1 text-justify mb-3">
                <li><strong>Total R$ 4.500,00, à vista</strong>, com desconto já aplicado. Pagamento no momento da assinatura do presente contrato, por PIX ou transferência bancária;</li>
                <li><strong>Total R$ 4.500,00. Parcelado em 3 vezes de igual valor (R$ 1.500,00 – valor da parcela)</strong> com desconto já aplicado para pagamento no <strong>boleto</strong>. Pagamento a iniciar no momento da assinatura do presente contrato;</li>
                <li><strong>Total R$ 5.200,00. Parcelado em 10 vezes de igual valor (R$ 520,00 – valor da parcela) no cartão de crédito</strong>, com juros já aplicados. Pagamento a iniciar no momento da assinatura do presente contrato.</li>
            </ul>

            <!-- Cronograma Dinâmico de Boletos / Parcelas Cadastradas -->
            <div class="my-4 border border-slate-300 rounded-xl overflow-hidden shadow-xs">
                <div class="bg-slate-100 px-4 py-2 font-bold text-xs uppercase text-slate-700 tracking-wider">
                    Cronograma de Vencimento das Parcelas Acordadas
                </div>
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Parcela</th>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Data de Vencimento do Boleto</th>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Valor da Parcela</th>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-700">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach ($installments as $inst): ?>
                            <tr>
                                <td class="px-4 py-2 font-bold text-slate-900"><?= htmlspecialchars((string)$inst['parcela']) ?>ª Parcela</td>
                                <td class="px-4 py-2 text-slate-700"><?= htmlspecialchars(date('d/m/Y', strtotime($inst['vencimento']))) ?></td>
                                <td class="px-4 py-2 font-semibold text-slate-900">R$ <?= htmlspecialchars((string)$inst['valor']) ?></td>
                                <td class="px-4 py-2">
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium <?= ($inst['status'] ?? '') === 'Pago' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' ?>">
                                        <?= htmlspecialchars((string)($inst['status'] ?? 'Emitido')) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Dados Bancários Oficiais -->
            <div class="bg-blue-50/70 p-4 rounded-xl border border-blue-200 my-4 text-xs">
                <p class="font-bold text-blue-900 mb-1"><strong>Parágrafo segundo:</strong> As informações bancárias para pagamento à vista são as descritas abaixo:</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-700 mt-2">
                    <div>
                        <p>Banco: <strong>Inter - 077</strong></p>
                        <p>Agência: <strong>0001</strong> | Conta: <strong>32761183-9</strong></p>
                    </div>
                    <div>
                        <p>Razão Social: <strong>Mente em Equilíbrio Serviços de Psicologia LTDA</strong></p>
                        <p>Chave PIX (CNPJ): <strong>53076815/0001-10</strong></p>
                    </div>
                </div>
            </div>

            <p class="text-justify mb-2">
                <strong>Parágrafo terceiro:</strong> Caso não haja o pagamento até a data prevista, incidirá correção monetária pelo INPC e juros de mora de 1% ao mês, sujeito também a multa de 10% do valor do contrato.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo quarto:</strong> Ao realizar o pagamento ajustado, o representante legal deverá encaminhar o comprovante de pagamento para o número de WhatsApp <strong>(24) 99964-2123</strong>, sendo a Nota Fiscal dos serviços prestados encaminhada sempre quando finalizado o processo de avaliação neuropsicológica.
            </p>
            <p class="text-justify mb-4">
                <strong>Parágrafo quinto:</strong> Caso o pagamento seja realizado no cartão de crédito, é de ciência do representante legal que essa modalidade de pagamento está sujeita a juros estabelecidos pela própria empresa bancária, estando já evidenciados no valor total do serviço no presente contrato, não tendo a Clínica qualquer gerência ou controle. O pagamento por cartão de crédito será explicado pela Clínica como deverá ser realizado.
            </p>
        </div>

        <!-- CLÁUSULA 5: COMO CONTATAR A CLÍNICA E O/A PROFISSIONAL -->
        <div class="mt-6">
            <h2 class="text-center font-bold text-slate-900 uppercase tracking-widest text-sm mb-3">COMO CONTATAR A CLÍNICA E O/A PROFISSIONAL</h2>
            <p class="font-bold mb-2">ITEM 5:</p>
            <p class="text-justify mb-2">
                O avaliando e os representantes legais poderão encontrar o/a psicólogo/a através do WhatsApp disponibilizado por este pessoalmente sempre que tiver alguma dúvida ou necessidade sobre o tratamento, sendo as mensagens respondidas conforme a possibilidade. Em caso de contato urgente, destacar no início da mensagem o texto <strong>"URGENTE"</strong>, para que seja dada prioridade o mais rápido possível à demanda. Em casos de urgências e emergências, tentativas de suicídio, risco de vida, e quaisquer necessidades de atendimento presencial, o paciente pode informar o/a profissional, no entanto deve ser encaminhado imediatamente ao <strong>Pronto Socorro mais próximo para ATENDIMENTO PRESENCIAL, ACOMPANHADO DE MAIOR DE IDADE</strong>.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo primeiro:</strong> Para tratar sobre questões relativas a pagamento das sessões, agendamentos, reagendamentos, cancelamentos e renovações de contratos, o paciente precisa entrar em contato através do WhatsApp da Clínica <strong>(24) 99964-2123</strong>. As mensagens serão respondidas entre o horário das 8:00 às 17:00h.
            </p>
            <p class="text-justify mb-4">
                <strong>Parágrafo segundo:</strong> A depender da demanda trazida pelo paciente/representante legal, será realizado encaminhamento a um médico psiquiatra, psicólogo para tratar de demandas psicológicas que fogem ao escopo da avaliação e que demandam intervenção para tratar dificuldades e sintomas agudos.
            </p>
        </div>

        <!-- CLÁUSULA 6: CANCELAMENTO TARDIO OU NÃO COMPARECIMENTO -->
        <div class="mt-6">
            <h2 class="text-center font-bold text-slate-900 uppercase tracking-widest text-sm mb-3">O CANCELAMENTO TARDIO OU NÃO COMPARECIMENTO À SESSÃO DA AVALIAÇÃO PSICOLÓGICA</h2>
            <p class="font-bold mb-2">ITEM 6:</p>
            <p class="text-justify mb-2">
                O(A) avaliando(a)/representante legal poderá cancelar ou tentar reagendar o atendimento com <strong>até 24 horas de antecedência</strong>. Em caso de não comparecimento o avaliando fica ciente de que haverá atraso na conclusão do processo de avaliação.
            </p>
            <p class="text-justify mb-4">
                <strong>Parágrafo único:</strong> Quando houver atrasos do(a) examinando(a), o atendimento será encerrado no mesmo horário pré-agendado, de modo que não haja prejuízos nos atendimentos de outros pacientes.
            </p>
        </div>

        <!-- CLÁUSULA 7: AS REDES SOCIAIS -->
        <div class="mt-6">
            <h2 class="text-center font-bold text-slate-900 uppercase tracking-widest text-sm mb-3">AS REDES SOCIAIS</h2>
            <p class="font-bold mb-2">ITEM 7:</p>
            <p class="text-justify mb-4">
                As redes sociais são plataformas de comunicação que poderão ser utilizadas para conexão entre avaliando(a), representantes legais e profissional. Contudo, para conteúdo relativo à avaliação psicológica ou outros desdobramentos desta, deverão ser feitos única e exclusivamente através dos WhatsApp disponibilizados da profissional e da clínica. Além do mais, é importante que o(a) avaliando(a)/representante legal tenha conhecimento de que não é recomendado, pelo Conselho Federal de Psicologia<?= $model === 'infantil' ? ', nem pelo Estatuto da Criança e do Adolescente' : '' ?>, expor fotos, vídeos ou marcações em postagens do(a) avaliando(a) ou terceiros a este vinculado, de modo a proteger o sigilo profissional.
            </p>
        </div>

        <!-- CLÁUSULA EXCLUSIVA DO MODELO INFANTIL: ACOMPANHAMENTO PRESENCIAL -->
        <?php if ($model === 'infantil'): ?>
            <div class="mt-6 bg-amber-50/70 p-5 rounded-xl border border-amber-200">
                <h2 class="text-center font-bold text-amber-900 uppercase tracking-widest text-sm mb-3">O ACOMPANHAMENTO PRESENCIAL DO PACIENTE NA CLÍNICA</h2>
                <p class="font-bold mb-2 text-amber-950">ITEM 8:</p>
                <p class="text-justify mb-2 text-amber-950">
                    Durante os atendimentos presenciais, <strong>é terminantemente proibido que o representante legal ou terceiro autorizado se abstenha da Clínica durante a sessão ou que a criança saia da Clínica desacompanhada após a finalização do atendimento</strong>. Caso tais situações aconteçam, o representante legal será imediatamente acionado, podendo o processo de avaliação do paciente ser revisto.
                </p>
                <p class="text-justify text-amber-950">
                    <strong>Parágrafo único:</strong> Nos casos em que terceiros aquém dos representantes legais acompanhem o paciente, necessariamente a psicóloga da clínica precisa ser previamente informada por escrito com o nome completo e foto para identificação do sujeito.
                </p>
            </div>
        <?php endif; ?>

        <!-- CLÁUSULA 9: VIGÊNCIA E INTERRUPÇÃO DO SERVIÇO -->
        <div class="mt-6">
            <h2 class="text-center font-bold text-slate-900 uppercase tracking-widest text-sm mb-3">SOBRE A VIGÊNCIA DO PRESENTE DOCUMENTO E A INTERRUPÇÃO DO SERVIÇO</h2>
            <p class="font-bold mb-2">ITEM 9:</p>
            <p class="text-justify mb-2">
                O presente documento possui como prazo de vigência a finalização dos serviços de avaliação neuropsicológica especificada no <strong>ANEXO I</strong>. Em caso de interrupção do serviço por parte do contratante, não será devolvida a integralidade do valor, mas sim o valor proporcional frente ao serviço já realizado. Em eventuais interrupções do processo por falta de pagamento, também não será devolvido qualquer valor já devidamente pago.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo primeiro:</strong> Para formalizar o pedido de desistência dos serviços, necessariamente o representante legal precisa encaminhar mensagem por escrito para o WhatsApp da Clínica <strong>(24) 99964-2123</strong>.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo segundo:</strong> A partir do pedido de desistência dos serviços, a Clínica irá apurar os serviços prestados até o momento. Após a apuração, a Clínica irá informar o paciente/representante o valor proporcional que deverá ser devolvido, devendo este informar seus dados bancários para devolução.
            </p>
            <p class="text-justify mb-2">
                <strong>Parágrafo terceiro:</strong> A partir do pedido de desistência dos serviços, a Clínica possui o prazo de 15 dias úteis para realizar as apurações do serviço e realizar a devolução dos valores proporcionais.
            </p>
            <p class="text-justify mb-4">
                <strong>Parágrafo quarto:</strong> Serviços interrompidos, com muitos atrasos ou descumprimento de horários prejudicam a avaliação psicológica e poderão resultar em processos não concluídos relacionados à demanda trazida.
            </p>
        </div>

        <!-- FORO E ACEITE ELETRÔNICO -->
        <div class="mt-6 pt-4 border-t border-slate-200">
            <p class="text-justify mb-4">
                Fica estabelecido o Foro de <strong>Resende/RJ</strong> para fins de qualquer tratativa relacionada ao presente contrato. As partes reconhecem e concordam que este contrato poderá ser assinado por meio eletrônico, nos termos da legislação vigente, produzindo todos os efeitos legais e jurídicos de um documento físico com assinaturas manuscritas. A utilização de plataformas digitais seguras para a coleta das assinaturas será considerada meio idôneo e suficiente para comprovar a manifestação de vontade das partes, que declaram, para todos os fins de direito, reconhecer a autenticidade e validade do presente instrumento celebrado por meio eletrônico.
            </p>

            <p class="text-right font-medium mb-12">
                Resende/RJ, <?= date('d') ?> de <?= [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'][(int)date('n')] ?> de <?= date('Y') ?>.
            </p>

            <!-- ASSINATURAS -->
            <?php if ($model === 'infantil'): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-10 text-center text-xs mb-8">
                    <div>
                        <div class="border-b border-slate-400 h-12 mb-2"></div>
                        <p class="font-bold text-slate-900"><?= htmlspecialchars($patient['responsible_name'] ?: 'REPRESENTANTE LEGAL 1') ?></p>
                        <p class="text-slate-500">CPF: <?= htmlspecialchars($patient['cpf_nf'] ?: '___.___.___-__') ?> (<?= htmlspecialchars($patient['responsible_kinship_1'] ?: 'Representante Legal 1') ?>)</p>
                    </div>
                    <div>
                        <div class="border-b border-slate-400 h-12 mb-2"></div>
                        <p class="font-bold text-slate-900"><?= htmlspecialchars($patient['responsible_name_2'] ?: 'REPRESENTANTE LEGAL 2') ?></p>
                        <p class="text-slate-500">CPF: <?= htmlspecialchars($patient['responsible_cpf_2'] ?: '___.___.___-__') ?> (<?= htmlspecialchars($patient['responsible_kinship_2'] ?: 'Representante Legal 2') ?>)</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="max-w-md mx-auto text-center text-xs mb-8">
                    <div class="border-b border-slate-400 h-12 mb-2"></div>
                    <p class="font-bold text-slate-900"><?= htmlspecialchars($patient['responsible_name'] ?: $patient['full_name']) ?></p>
                    <p class="text-slate-500">CPF: <?= htmlspecialchars($patient['cpf_nf'] ?: '___.___.___-__') ?> (AVALIADO(A) / CONTRATANTE)</p>
                </div>
            <?php endif; ?>

            <div class="max-w-md mx-auto text-center text-xs pt-4">
                <div class="inline-block mb-1">
                    <span class="font-serif italic text-lg text-slate-700">Gisele Silva Corrêa</span>
                </div>
                <div class="border-b border-slate-400 h-2 mb-2"></div>
                <p class="font-bold text-slate-900">Gisele Silva Corrêa</p>
                <p class="text-slate-600">Psicóloga especialista em Neuropsicologia e em Terapia Cognitivo Comportamental</p>
                <p class="text-slate-500 font-medium">CRP 05/40880 • MENTE EM EQUILÍBRIO SERVIÇOS DE PSICOLOGIA LTDA</p>
                <?php if ($patient['contract_gisele_approved']): ?>
                    <p class="text-emerald-700 font-semibold mt-1">✓ Conferido e Aprovado pela Coordenação em <?= date('d/m/Y H:i', strtotime($patient['contract_gisele_approved_at'] ?? 'now')) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-break my-10 border-t-2 border-dashed border-slate-300"></div>

        <!-- ANEXO I: ATIVIDADES E TRABALHOS PREVISTOS -->
        <div class="pt-6">
            <h2 class="text-center font-bold text-slate-900 uppercase tracking-widest text-sm mb-4">
                ANEXO I — Atividades e Trabalhos Previstos na Avaliação Neuropsicológica
            </h2>
            <p class="text-justify mb-4 text-xs">
                As atividades a seguir representam àquelas tipicamente desenvolvidas no curso dos processos de avaliação neuropsicológica, consistindo em sessões de anamnese (1 a 2 sessões), sessões de aplicação dos instrumentos de avaliação (6 a 14 sessões) e sessão de entrega e esclarecimentos do Laudo Neuropsicológico (1 sessão) com durações médias de 30 a 60 minutos, não limitando-se a estas e nem obrigando-se o cumprimento destas em sua totalidade, haja vista o caráter dinâmico e customizado de cada processo de avaliação:
            </p>
            <ul class="list-disc pl-6 space-y-2 text-justify text-xs mb-8">
                <li>Entrevista de triagem via WhatsApp antes da contratação do serviço;</li>
                <li>Entrevistas de anamnese;</li>
                <li>Aplicação, correção e interpretação de testes psicológicos e tarefas neuropsicológicas;</li>
                <li>Aplicação de técnicas de entrevista e de observação do comportamento;</li>
                <li>Consulta a informantes;</li>
                <li>Solicitação de avaliação multidisciplinar (quando pertinente);</li>
                <li>Análise de exames, laudos prévios, e demais documentos ou materiais pertinentes à avaliação;</li>
                <li>Envio de questionários e inventários para respondentes da avaliação;</li>
                <li>Elaboração de laudo de avaliação psicológica e/ou demais documentos solicitados;</li>
                <li>Entrevista devolutiva de resultados;</li>
                <li>Encaminhamentos e orientações para seguimento;</li>
                <li>Envio de materiais informativos e psicoeducativos.</li>
            </ul>

            <!-- Rodapé da Página do Anexo -->
            <div class="pt-8 border-t border-slate-200 flex flex-wrap items-center justify-between text-[11px] text-slate-500">
                <span>Mente em Equilíbrio Serviços de Psicologia LTDA • CNPJ 53.076.815/0001-10</span>
                <span>Gisele Silva Corrêa • CRP 05/40880</span>
            </div>
        </div>

    </div>

</body>
</html>
