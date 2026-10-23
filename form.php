<?php
/**
 * Formulários Públicos Nativos — Mente em Equilíbrio (Gisele Silva Corrêa)
 * 1. Ficha de Cadastro do Cliente (18 perguntas completas)
 * 2. Anamnese Preliminar Avaliação Neuropsicológica:
 *    - Modelo Infância e Adolescência (163 perguntas)
 *    - Modelo Geral / Adulto (77 perguntas)
 *
 * Atualiza automaticamente o prontuário no sistema e marca a tarefa correspondente
 * no painel de pré-consulta da clínica.
 */
require_once __DIR__ . '/includes/helpers.php';
$pdo = getDbConnection();

$token = $_GET['token'] ?? '';
$type = $_GET['type'] ?? 'cadastro';

$stmt = $pdo->prepare("SELECT * FROM patients WHERE public_token = ?");
$stmt->execute([$token]);
$patient = $stmt->fetch();

if (!$patient) {
    http_response_code(404);
    die("Link de formulário inválido ou expirado.");
}

// Determina se a anamnese é do modelo Infantil ou Adulto
$patientAge = $patient['age'] !== null ? (int)$patient['age'] : null;
$defaultModel = ($patientAge !== null && $patientAge < 18) ? 'infantil' : 'adulto';
$anamneseModel = $_GET['model'] ?? ($patient['anamnesis_type'] ?: $defaultModel);
if (!in_array($anamneseModel, ['infantil', 'adulto'])) {
    $anamneseModel = 'adulto';
}

$submitted = false;
$submittedType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedType = $_POST['form_type'] ?? $type;

    if ($submittedType === 'cadastro') {
        $fullName = trim($_POST['full_name'] ?? $patient['full_name']);
        $respName = trim($_POST['responsible_name'] ?? '');
        $cpf = trim($_POST['cpf_nf'] ?? '');
        $email = trim($_POST['email_nf'] ?? '');
        $phone = trim($_POST['phone_whatsapp'] ?? '');
        $birthDate = $_POST['birth_date'] ?: ($patient['birth_date'] ?? null);
        $age = $_POST['age'] !== '' ? (int)$_POST['age'] : $patient['age'];
        $ageGroup = calculateAgeGroup($age) ?? $patient['age_group'];

        $bairro = trim($_POST['address_bairro'] ?? '');
        $cidadeUf = trim($_POST['address_cidade_uf'] ?? '');
        $cep = trim($_POST['address_cep'] ?? '');
        $street = trim($_POST['address_street'] ?? '');
        $addressFull = trim("{$street}, {$bairro}, {$cidadeUf} - CEP {$cep}", ", - CEP ");
        if (empty($addressFull)) {
            $addressFull = trim($_POST['address_full'] ?? $patient['address_full'] ?? '');
        }

        $cadAnswers = [
            'email' => $email,
            'paciente_nome' => $fullName,
            'paciente_idade' => $age,
            'paciente_whatsapp' => $phone,
            'paciente_cpf' => $cpf,
            'paciente_nascimento' => $birthDate,
            'resp_financeiro_tipo' => $_POST['resp_tipo'] ?? 'proprio',
            'resp_financeiro_nome' => $respName,
            'resp_financeiro_cpf' => trim($_POST['resp_cpf'] ?? ''),
            'resp_financeiro_vinculo' => trim($_POST['resp_vinculo'] ?? ''),
            'resp_financeiro_email' => trim($_POST['resp_email'] ?? $email),
            'resp_financeiro_whatsapp' => trim($_POST['resp_whatsapp'] ?? $phone),
            'endereco_rua' => $street,
            'endereco_bairro' => $bairro,
            'endereco_cidade_uf' => $cidadeUf,
            'endereco_cep' => $cep,
            'tipo_atendimento' => trim($_POST['tipo_atendimento'] ?? 'Avaliação neuropsicológica'),
            'como_conheceu' => trim($_POST['como_conheceu'] ?? ''),
            'indicacao_detalhe' => trim($_POST['indicacao_detalhe'] ?? ''),
            'data_envio' => date('d/m/Y H:i:s')
        ];

        $up = $pdo->prepare("UPDATE patients SET full_name=?, responsible_name=?, cpf_nf=?, email_nf=?, phone_whatsapp=?, birth_date=?, age=?, age_group=?, address_full=?, client_form_answers_json=? WHERE id=?");
        $up->execute([$fullName, $respName, $cpf, $email, $phone, $birthDate, $age, $ageGroup, $addressFull, json_encode($cadAnswers, JSON_UNESCAPED_UNICODE), $patient['id']]);

        // Baixa automática na tarefa de pré-consulta 'cli_cadastro'
        $tUp = $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Paciente (Formulário Online)' WHERE patient_id=? AND task_code='cli_cadastro'");
        $tUp->execute([$patient['id']]);

        logAudit($pdo, ['name' => 'Paciente/Responsável', 'role' => 'externo'], (int)$patient['id'], 'Preencheu o Formulário de Cadastro do Cliente');
        $submitted = true;

    } elseif ($submittedType === 'anamnese') {
        $answers = $_POST['a'] ?? [];
        $answers['_meta'] = [
            'modelo' => $anamneseModel,
            'data_envio' => date('d/m/Y H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        // Atualiza campos cadastrais relevantes que vieram na anamnese (se preenchidos)
        $cpf = trim($answers['cpf'] ?? $patient['cpf_nf']);
        $birthDate = !empty($answers['data_nascimento']) ? $answers['data_nascimento'] : $patient['birth_date'];
        $age = !empty($answers['idade']) ? (int)$answers['idade'] : $patient['age'];
        $ageGroup = calculateAgeGroup($age) ?? $patient['age_group'];
        $school = trim($answers['escola'] ?? $patient['school_name'] ?? '');
        $edu = trim($answers['serie_escolaridade'] ?? $patient['education_level'] ?? '');

        $up = $pdo->prepare("UPDATE patients SET anamnesis_type=?, anamnesis_answers_json=?, cpf_nf=COALESCE(NULLIF(?, ''), cpf_nf), birth_date=COALESCE(?, birth_date), age=COALESCE(?, age), age_group=COALESCE(?, age_group), school_name=COALESCE(NULLIF(?, ''), school_name), education_level=COALESCE(NULLIF(?, ''), education_level) WHERE id=?");
        $up->execute([$anamneseModel, json_encode($answers, JSON_UNESCAPED_UNICODE), $cpf, $birthDate, $age, $ageGroup, $school, $edu, $patient['id']]);

        // Baixa automática na tarefa de pré-consulta 'cli_anamnese_preliminar'
        $tUp = $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Paciente (Formulário Online)' WHERE patient_id=? AND task_code='cli_anamnese_preliminar'");
        $tUp->execute([$patient['id']]);

        logAudit($pdo, ['name' => 'Paciente/Responsável', 'role' => 'externo'], (int)$patient['id'], "Preencheu o Formulário de Anamnese Preliminar ({$anamneseModel})");
        $submitted = true;
    }

    // Verifica se todos os 3 procedimentos de pré-consulta foram concluídos
    $chk = $pdo->prepare("SELECT COUNT(*) as pend FROM patient_tasks WHERE patient_id=? AND stage_group='pre_consulta_cliente' AND status != 'concluido'");
    $chk->execute([$patient['id']]);
    if ((int)$chk->fetch()['pend'] === 0) {
        $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Automação do Sistema' WHERE patient_id=? AND task_code='ini_verificar_preenchimento'")->execute([$patient['id']]);
    }

    $stmt->execute([$token]);
    $patient = $stmt->fetch();
}

$schools = $pdo->query("SELECT name FROM schools_catalog ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$existingAnamnesis = !empty($patient['anamnesis_answers_json']) ? json_decode($patient['anamnesis_answers_json'], true) : [];
$existingCadastro = !empty($patient['client_form_answers_json']) ? json_decode($patient['client_form_answers_json'], true) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $type === 'anamnese' ? 'Anamnese Preliminar' : 'Cadastro do Cliente' ?> — Gisele Silva Corrêa</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .serif-font {
            font-family: 'Cormorant Garamond', Georgia, serif;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-8 px-3 sm:px-6">

    <div class="max-w-3xl mx-auto bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- CABEÇALHO TIMBRADO DA CLÍNICA -->
        <div class="bg-[#1d4ed8] text-white px-6 sm:px-8 py-7 relative">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-[11px] uppercase tracking-widest bg-[#1e40af] px-3 py-1 rounded-full font-semibold border border-blue-400/30">
                    Gisele Silva Corrêa • Avaliação Neuropsicológica
                </span>
                <span class="text-xs text-blue-200 font-medium">CRP 05/40880</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-bold mt-3 serif-font">
                <?= $type === 'anamnese'
                    ? ($anamneseModel === 'infantil' ? 'Anamnese Preliminar — Infância e Adolescência' : 'Anamnese Preliminar — Avaliação Geral / Adulto')
                    : 'Ficha de Cadastro do Cliente (Contrato e NF)' ?>
            </h1>

            <p class="text-blue-100 text-xs sm:text-sm mt-1">
                Paciente: <strong class="text-white"><?= htmlspecialchars($patient['full_name']) ?></strong>
                <?php if ($patient['age']): ?>
                    • Idade: <strong class="text-white"><?= (int)$patient['age'] ?> anos</strong>
                <?php endif; ?>
            </p>

            <!-- Alternador de Abas de Formulário / Modelos -->
            <div class="flex flex-wrap items-center gap-2 mt-5 pt-4 border-t border-blue-500/30 text-xs">
                <a href="?token=<?= urlencode($token) ?>&type=cadastro"
                   class="px-3.5 py-1.5 rounded-xl font-semibold transition <?= $type === 'cadastro' ? 'bg-white text-blue-800 shadow-sm' : 'bg-blue-800/60 text-blue-100 hover:bg-blue-800' ?>">
                    📝 1. Cadastro do Cliente
                </a>
                <a href="?token=<?= urlencode($token) ?>&type=anamnese&model=<?= $patientAge && $patientAge < 18 ? 'infantil' : 'adulto' ?>"
                   class="px-3.5 py-1.5 rounded-xl font-semibold transition <?= $type === 'anamnese' ? 'bg-white text-blue-800 shadow-sm' : 'bg-blue-800/60 text-blue-100 hover:bg-blue-800' ?>">
                    🧠 2. Anamnese Preliminar
                </a>
                <?php if ($type === 'anamnese'): ?>
                    <div class="ml-auto flex items-center gap-1 bg-blue-900/40 p-1 rounded-xl text-[11px]">
                        <a href="?token=<?= urlencode($token) ?>&type=anamnese&model=infantil" class="px-2.5 py-1 rounded-lg transition <?= $anamneseModel === 'infantil' ? 'bg-white text-blue-900 font-bold' : 'text-blue-200 hover:text-white' ?>">👶 Infantil</a>
                        <a href="?token=<?= urlencode($token) ?>&type=anamnese&model=adulto" class="px-2.5 py-1 rounded-lg transition <?= $anamneseModel === 'adulto' ? 'bg-white text-blue-900 font-bold' : 'text-blue-200 hover:text-white' ?>">👤 Adulto</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-6 sm:p-8">
            <?php if ($submitted): ?>
                <!-- TELA DE SUCESSO APÓS SUBMISSÃO -->
                <div class="bg-emerald-50 border border-emerald-300 text-emerald-950 rounded-2xl p-8 text-center space-y-4">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto shadow-xs">
                        ✓
                    </div>
                    <h2 class="text-2xl font-bold text-slate-900">Formulário Enviado com Sucesso!</h2>
                    <p class="text-sm text-slate-600 max-w-md mx-auto">
                        Agradecemos pelo preenchimento cuidadoso. As informações foram recebidas e o status correspondente já foi atualizado automaticamente como <strong>Concluído</strong> no prontuário da clínica.
                    </p>

                    <div class="bg-white p-4 rounded-xl border border-emerald-200 text-xs text-left max-w-md mx-auto text-slate-700 space-y-1">
                        <p><strong>Dados para Pagamento / PIX:</strong></p>
                        <p>Banco Inter - 077 • Agência: 0001 • Conta: 32761183-9</p>
                        <p>Chave PIX (CNPJ): <strong>53.076.815/0001-10</strong></p>
                        <p class="text-slate-500">Razão Social: Mente em Equilíbrio Serviços de Psicologia LTDA</p>
                        <p class="text-emerald-700 font-semibold pt-1">Envio do comprovante via WhatsApp: (24) 99964-2123</p>
                    </div>

                    <div class="pt-4 flex flex-wrap justify-center gap-3">
                        <a href="?token=<?= urlencode($token) ?>&type=<?= $type === 'cadastro' ? 'anamnese' : 'cadastro' ?>"
                           class="bg-[#1d4ed8] hover:bg-[#1e40af] text-white px-5 py-2.5 rounded-xl text-xs font-semibold shadow-sm transition">
                            Ir para <?= $type === 'cadastro' ? 'Anamnese Preliminar' : 'Cadastro do Cliente' ?> →
                        </a>
                    </div>
                </div>

            <?php elseif ($type === 'cadastro'): ?>

                <!-- FORMULÁRIO OFICIAL: CADASTRO DE CLIENTES (18 PERGUNTAS) -->
                <form method="POST" class="space-y-8" x-data="{ respTipo: '<?= !empty($patient['responsible_name']) && $patient['responsible_name'] !== $patient['full_name'] ? 'outro' : 'proprio' ?>' }">
                    <input type="hidden" name="form_type" value="cadastro">

                    <!-- SEÇÃO 1: PESSOA ATENDIDA -->
                    <div class="space-y-4">
                        <div class="border-b border-slate-200 pb-2">
                            <h2 class="text-base font-bold text-slate-900 uppercase tracking-wide">1. Informações sobre a pessoa que será atendida</h2>
                            <p class="text-xs text-slate-500">A Nota Fiscal será emitida no CPF da pessoa que será atendida.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail Principal de Contato *</label>
                                <input type="email" name="email_nf" required value="<?= htmlspecialchars($patient['email_nf'] ?? '') ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="seuemail@exemplo.com">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome Completo do Paciente *</label>
                                <input type="text" name="full_name" required value="<?= htmlspecialchars($patient['full_name'] ?? '') ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Idade do Paciente (apenas o número) *</label>
                                <input type="number" name="age" required value="<?= htmlspecialchars((string)($patient['age'] ?? '')) ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Ex: 8">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Data de Nascimento *</label>
                                <input type="date" name="birth_date" required value="<?= htmlspecialchars($patient['birth_date'] ?? '') ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">WhatsApp do Paciente</label>
                                <input type="text" name="phone_whatsapp" value="<?= htmlspecialchars($patient['phone_whatsapp'] ?? '') ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="(24) 99999-9999">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">CPF do Paciente *</label>
                                <input type="text" name="cpf_nf" required value="<?= htmlspecialchars($patient['cpf_nf'] ?? '') ?>" placeholder="000.000.000-00" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>
                    </div>

                    <!-- SEÇÃO 2: RESPONSÁVEL FINANCEIRO -->
                    <div class="space-y-4 pt-4 border-t border-slate-200">
                        <div class="border-b border-slate-200 pb-2">
                            <h2 class="text-base font-bold text-slate-900 uppercase tracking-wide">2. Informações sobre o Responsável Financeiro</h2>
                            <p class="text-xs text-slate-500">Quem responderá pelo contrato e pagamentos do atendimento.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-2">Quem é o Responsável Financeiro? *</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 bg-slate-50 cursor-pointer hover:bg-slate-100">
                                    <input type="radio" name="resp_tipo" value="proprio" x-model="respTipo" class="text-blue-600">
                                    <span>O próprio paciente</span>
                                </label>
                                <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 bg-slate-50 cursor-pointer hover:bg-slate-100">
                                    <input type="radio" name="resp_tipo" value="outro" x-model="respTipo" class="text-blue-600">
                                    <span>Outro (Pai, Mãe, Cônjuge, etc.)</span>
                                </label>
                            </div>
                        </div>

                        <div x-show="respTipo === 'outro'" x-cloak class="space-y-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome Completo do Responsável Financeiro *</label>
                                <input type="text" name="responsible_name" value="<?= htmlspecialchars($patient['responsible_name'] ?? '') ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2 text-sm bg-white">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">CPF do Responsável</label>
                                    <input type="text" name="resp_cpf" value="<?= htmlspecialchars($existingCadastro['resp_financeiro_cpf'] ?? '') ?>" placeholder="000.000.000-00" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Vínculo com o Paciente</label>
                                    <select name="resp_vinculo" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white">
                                        <option value="Mãe">Mãe</option>
                                        <option value="Pai">Pai</option>
                                        <option value="Cônjuge">Cônjuge</option>
                                        <option value="Tutor(a) Legal">Tutor(a) Legal</option>
                                        <option value="Outro">Outro</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">WhatsApp do Responsável</label>
                                    <input type="text" name="resp_whatsapp" value="<?= htmlspecialchars($existingCadastro['resp_financeiro_whatsapp'] ?? '') ?>" placeholder="(24) 99999-9999" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail para Envio da Nota Fiscal</label>
                                <input type="email" name="resp_email" value="<?= htmlspecialchars($existingCadastro['resp_financeiro_email'] ?? '') ?>" placeholder="financeiro@exemplo.com" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white">
                            </div>
                        </div>
                    </div>

                    <!-- SEÇÃO 3: ENDEREÇO -->
                    <div class="space-y-4 pt-4 border-t border-slate-200">
                        <div class="border-b border-slate-200 pb-2">
                            <h2 class="text-base font-bold text-slate-900 uppercase tracking-wide">3. Endereço Residencial</h2>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Endereço (Rua, nº, complemento) *</label>
                                <input type="text" name="address_street" required value="<?= htmlspecialchars($existingCadastro['endereco_rua'] ?? '') ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm" placeholder="Rua das Flores, 123, Apto 402">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Bairro *</label>
                                <input type="text" name="address_bairro" required value="<?= htmlspecialchars($existingCadastro['endereco_bairro'] ?? '') ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm" placeholder="Centro">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Cidade / Estado *</label>
                                <input type="text" name="address_cidade_uf" required value="<?= htmlspecialchars($existingCadastro['endereco_cidade_uf'] ?? 'Resende/RJ') ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm" placeholder="Resende/RJ">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">CEP *</label>
                                <input type="text" name="address_cep" required value="<?= htmlspecialchars($existingCadastro['endereco_cep'] ?? '') ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm" placeholder="27511-300">
                            </div>
                        </div>
                    </div>

                    <!-- SEÇÃO 4: SERVIÇO & COMO CONHECEU -->
                    <div class="space-y-4 pt-4 border-t border-slate-200">
                        <div class="border-b border-slate-200 pb-2">
                            <h2 class="text-base font-bold text-slate-900 uppercase tracking-wide">4. Informações sobre o Atendimento e Origem</h2>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-2">Tipo de Atendimento *</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                                <?php
                                $servicos = [
                                    'Avaliação neuropsicológica',
                                    'Terapia cognitivo comportamental',
                                    'Grupo terapêutico ou para treino de habilidades',
                                    'Supervisão Clínica',
                                    'Outro'
                                ];
                                foreach ($servicos as $sv):
                                ?>
                                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-slate-50 cursor-pointer hover:bg-slate-100">
                                        <input type="radio" name="tipo_atendimento" value="<?= htmlspecialchars($sv) ?>" <?= ($sv === 'Avaliação neuropsicológica') ? 'checked' : '' ?> class="text-blue-600">
                                        <span><?= htmlspecialchars($sv) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Como conheceu a Psicóloga Gisele Silva Corrêa? *</label>
                                <select name="como_conheceu" required class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm">
                                    <option value="">Selecione...</option>
                                    <option value="Indicação de pessoas conhecidas">Indicação de pessoas conhecidas</option>
                                    <option value="Indicação de médico/profissional">Indicação de médico/profissional</option>
                                    <option value="Instagram">Instagram</option>
                                    <option value="Anúncio do Google">Anúncio do Google</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Se foi indicado por alguém, informe o nome e WhatsApp:</label>
                                <input type="text" name="indicacao_detalhe" value="<?= htmlspecialchars($existingCadastro['indicacao_detalhe'] ?? '') ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm" placeholder="Nome e telefone da pessoa (para enviarmos um agradecimento/bônus)">
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 text-xs text-blue-900">
                        🔒 <strong>LGPD e Sigilo Profissional:</strong> Seus dados são confidenciais e protegidos pela Lei Geral de Proteção de Dados (Lei nº 13.709/2018) e pelo Código de Ética Profissional do Psicólogo, utilizados unicamente para emissão de contrato, nota fiscal e condução do processo clínico.
                    </div>

                    <button type="submit" class="w-full bg-[#1d4ed8] hover:bg-[#1e40af] text-white font-bold py-3.5 px-6 rounded-2xl shadow-sm transition text-sm">
                        Confirmar e Enviar Cadastro do Cliente
                    </button>
                </form>

            <?php else: ?>

                <!-- FORMULÁRIO OFICIAL DE ANAMNESE PRELIMINAR (INFANTIL OU ADULTO) -->
                <form method="POST" class="space-y-8" x-data="{
                    activeSection: 1,
                    init() {
                        const saved = localStorage.getItem('anamnese_draft_<?= $patient['id'] ?>_<?= $anamneseModel ?>');
                        if (saved) {
                            try {
                                const data = JSON.parse(saved);
                                // Popula campos se houver rascunho
                            } catch(e) {}
                        }
                    }
                }">
                    <input type="hidden" name="form_type" value="anamnese">
                    <input type="hidden" name="a[modelo]" value="<?= htmlspecialchars($anamneseModel) ?>">

                    <!-- AVISO DAS CONDIÇÕES GERAIS DE PRESTAÇÃO DE SERVIÇO -->
                    <div class="bg-amber-50/70 border border-amber-200 rounded-2xl p-5 text-xs text-amber-950 space-y-2">
                        <span class="font-bold uppercase tracking-wider text-[11px] text-amber-900">Informações Gerais — Leia com Atenção</span>
                        <p class="text-justify leading-relaxed">
                            O número e a duração das sessões serão determinados pela Neuropsicóloga responsável, conforme a necessidade de cada caso em particular (em média, 1 a 2 sessões de anamnese, 10 a 14 sessões de aplicação e 1 sessão para entrega do laudo neuropsicológico, com duração entre 30 e 60 minutos cada).
                            O valor corresponde a todos os procedimentos necessários. Nos casos em que forem necessárias mais sessões, não será cobrado valor adicional. O laudo é entregue de 3 a 4 semanas após a conclusão das sessões.
                        </p>
                        <div class="pt-2">
                            <label class="flex items-center gap-2 font-bold cursor-pointer text-slate-900">
                                <input type="checkbox" name="a[termo_aceite]" value="Sim" required class="rounded text-blue-600">
                                <span>LI E ESTOU DE ACORDO COM AS CONDIÇÕES DA PRESTAÇÃO DE SERVIÇO. *</span>
                            </label>
                        </div>
                    </div>

                    <!-- DADOS DE QUEM PREENCHE E PAGAMENTO -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                        <div>
                            <label class="block font-semibold text-slate-700 uppercase mb-1">Quem está preenchendo? *</label>
                            <input type="text" name="a[quem_preenche]" required class="w-full border border-slate-300 rounded-xl p-2.5" placeholder="Ex: A própria paciente / Mãe: Maria da Silva" value="<?= htmlspecialchars($existingAnamnesis['quem_preenche'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 uppercase mb-1">E-mail para Contato *</label>
                            <input type="email" name="a[email]" required class="w-full border border-slate-300 rounded-xl p-2.5" value="<?= htmlspecialchars($existingAnamnesis['email'] ?? $patient['email_nf']) ?>">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 uppercase mb-1">Forma de Pagamento Preferida *</label>
                            <select name="a[forma_pagamento]" required class="w-full border border-slate-300 rounded-xl p-2.5 bg-white">
                                <option value="À vista">À vista (PIX/Transferência com desconto)</option>
                                <option value="Parcelado em 3x no boleto">Parcelado em 3x no boleto (no dia da anamnese, 30 e 60 dias)</option>
                                <option value="Parcelamento no cartão de crédito">Parcelamento em até 10x no cartão com juros</option>
                            </select>
                        </div>
                    </div>

                    <?php if ($anamneseModel === 'infantil'): ?>
                        <!-- ============================================================== -->
                        <!-- MODELO 1: ANAMNESE INFÂNCIA E ADOLESCÊNCIA (163 PERGUNTAS)   -->
                        <!-- ============================================================== -->

                        <!-- PARTE 1: DADOS ESCOLARES & ROTINA DA PANDEMIA 2020 -->
                        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/60">
                            <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide border-b border-slate-200 pb-2">
                                📚 Informações Escolares e Rotina da Pandemia (COVID-19)
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Série / Ano em que está estudando *</label>
                                    <input type="text" name="a[serie_escolaridade]" required class="w-full border border-slate-300 rounded-lg p-2 bg-white" placeholder="Ex: 4º ano do Fundamental" value="<?= htmlspecialchars($existingAnamnesis['serie_escolaridade'] ?? $patient['education_level']) ?>">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Escola em que estuda *</label>
                                    <input type="text" name="a[escola]" required class="w-full border border-slate-300 rounded-lg p-2 bg-white" placeholder="Nome da escola" value="<?= htmlspecialchars($existingAnamnesis['escola'] ?? $patient['school_name']) ?>">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Telefone da Escola</label>
                                    <input type="text" name="a[telefone_escola]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Orientador(a) Pedagógico(a)</label>
                                    <input type="text" name="a[orientador_escola]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Tipo de Escola *</label>
                                    <select name="a[tipo_escola]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                        <option value="Particular">Particular</option>
                                        <option value="Pública">Pública</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Período de Estudo *</label>
                                    <select name="a[periodo_estudo]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                        <option value="Manhã">Manhã</option>
                                        <option value="Tarde">Tarde</option>
                                        <option value="Integral">Integral</option>
                                        <option value="Noite">Noite</option>
                                    </select>
                                </div>
                            </div>

                            <div class="pt-2 text-xs">
                                <label class="block font-semibold text-slate-700 mb-1">Comportamento e Desempenho Escolar ANTES da Pandemia (Marque os que se aplicavam):</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
                                    <?php
                                    $opcoesPrePandemia = [
                                        'Conseguia aprender os conteúdos sem necessitar de suporte extra escolar',
                                        'Apresentava dificuldade para aprender os conteúdos ensinados',
                                        'Apresentava resistência ou recusa em realizar as atividades escolares',
                                        'Apresentava dificuldade de concentração',
                                        'Apresentava comportamento agitado',
                                        'Apresentava dificuldade de interagir com colegas de turma',
                                        'Falava pouco ou não falava em sala de aula',
                                        'Era participativo nas atividades em sala',
                                        'Apresentava comportamento opositivo em sala de aula',
                                        'Não conseguia terminar as atividades no mesmo ritmo da média da turma',
                                        'Demonstrava prazer em ir para a escola para participar da aula'
                                    ];
                                    foreach ($opcoesPrePandemia as $op):
                                    ?>
                                        <label class="flex items-start gap-2 bg-white p-2 rounded-lg border border-slate-200">
                                            <input type="checkbox" name="a[desempenho_pre_pandemia][]" value="<?= htmlspecialchars($op) ?>" class="mt-0.5 rounded text-blue-600">
                                            <span><?= htmlspecialchars($op) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- PARTE 2: COMPOSIÇÃO FAMILIAR, CONCEPÇÃO, GESTAÇÃO E PARTO -->
                        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/60">
                            <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide border-b border-slate-200 pb-2">
                                👶 Concepção, Gestação, Parto e Família
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Nome da Mãe *</label>
                                    <input type="text" name="a[mae_nome]" required class="w-full border border-slate-300 rounded-lg p-2 bg-white" value="<?= htmlspecialchars($existingAnamnesis['mae_nome'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Idade da Mãe na Gestação *</label>
                                    <input type="number" name="a[mae_idade_gestacao]" required class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Profissão da Mãe</label>
                                    <input type="text" name="a[mae_profissao]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>

                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Nome do Pai *</label>
                                    <input type="text" name="a[pai_nome]" required class="w-full border border-slate-300 rounded-lg p-2 bg-white" value="<?= htmlspecialchars($existingAnamnesis['pai_nome'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Idade do Pai</label>
                                    <input type="number" name="a[pai_idade]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Profissão do Pai</label>
                                    <input type="text" name="a[pai_profissao]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>

                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Gestação Planejada? *</label>
                                    <select name="a[gestacao_planejada]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                        <option value="Sim">Sim</option>
                                        <option value="Não">Não</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Tipo de Parto *</label>
                                    <select name="a[tipo_parto]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                        <option value="Cesárea">Cesárea</option>
                                        <option value="Normal">Normal</option>
                                        <option value="Fórceps">Fórceps</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Duração da Gestação (semanas)</label>
                                    <input type="number" name="a[semanas_gestacao]" placeholder="Ex: 39" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs pt-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">APGAR ao nascer (na caderneta)</label>
                                    <input type="text" name="a[apgar]" placeholder="Ex: 9/10" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Peso ao Nascer (gramas)</label>
                                    <input type="text" name="a[peso_nascer]" placeholder="Ex: 3.250g" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Perímetro Cefálico (cm)</label>
                                    <input type="text" name="a[perimetro_cefalico]" placeholder="Ex: 34 cm" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                            </div>
                        </div>

                        <!-- PARTE 3: TRIAGEM MÉDICA, SONO E APNEIA -->
                        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/60">
                            <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide border-b border-slate-200 pb-2">
                                🩺 Triagem Médica, Crises de Ausência e Qualidade do Sono
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                <div class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-2">
                                    <span class="font-bold text-slate-900 block">Triagem para Crises de Ausência / Consciência:</span>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="a[ausencia_olhar_fixo]" value="Sim"> Já apresentou episódios breves de "olhar fixo" sem responder?</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="a[ausencia_desliga_segundos]" value="Sim"> "Desliga" por alguns segundos durante conversas?</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="a[ausencia_desmaio]" value="Sim"> Já teve desmaios ou perda de consciência?</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="a[ausencia_piscamento]" value="Sim"> Piscamento repetitivo ou movimentos de boca durante esses episódios?</label>
                                </div>

                                <div class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-2">
                                    <span class="font-bold text-slate-900 block">Qualidade do Sono & Apneia:</span>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="a[sono_menos_7h]" value="Sim"> Dorme menos de 7-8 horas regularmente?</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="a[sono_demora_30min]" value="Sim"> Demora mais de 30 minutos para adormecer?</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="a[sono_acorda_noite]" value="Sim"> Acorda frequentemente durante a noite?</label>
                                    <label class="flex items-center gap-2 text-rose-700 font-semibold"><input type="checkbox" name="a[sono_presenca_adulto]" value="Sim"> Precisa da presença de um adulto para conseguir dormir?</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="a[apneia_ronca_alto]" value="Sim"> Ronca alto com frequência ou respira pela boca?</label>
                                </div>
                            </div>
                        </div>

                        <!-- PARTE 4: COMPORTAMENTOS E INTERVENÇÕES ATUAIS -->
                        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/60">
                            <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide border-b border-slate-200 pb-2">
                                🧩 Intervenções Atuais e Primeiros Sinais
                            </h3>
                            <div class="text-xs space-y-2">
                                <label class="block font-semibold text-slate-700">Intervenções / Acompanhamentos que a criança realiza atualmente:</label>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <?php foreach (['Psicológico', 'Fonoaudiológico', 'Psicopedagógico', 'Terapia Ocupacional', 'Terapia ABA', 'Integração Sensorial de Ayres', 'Reforço Escolar', 'Nenhum acompanhamento'] as $intv): ?>
                                        <label class="flex items-center gap-2 bg-white p-2 rounded-lg border border-slate-200">
                                            <input type="checkbox" name="a[intervencoes][]" value="<?= htmlspecialchars($intv) ?>" class="text-blue-600">
                                            <span><?= htmlspecialchars($intv) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="text-xs pt-2">
                                <label class="block font-semibold text-slate-700 mb-1">Idade em que notou pela primeira vez que algo não estava bem no desenvolvimento:</label>
                                <select name="a[idade_primeiros_sinais]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                    <option value="Antes de completar 2 anos">Antes de completar 2 anos</option>
                                    <option value="Entre 2 e 3 anos">Entre 2 e 3 anos de idade</option>
                                    <option value="Entre 3 e 4 anos">Entre 3 e 4 anos de idade</option>
                                    <option value="Entre 4 e 5 anos">Entre 4 e 5 anos de idade</option>
                                    <option value="Entre 5 e 6 anos">Entre 5 e 6 anos de idade</option>
                                    <option value="Entre 6 e 12 anos">Entre 6 e 12 anos de idade</option>
                                    <option value="Acima de 12 anos">Acima de 12 anos de idade</option>
                                </select>
                            </div>
                        </div>

                        <!-- PARTE 5: PREJUÍZOS FUNCIONAIS POR DOMÍNIO COGNITIVO -->
                        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/60">
                            <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide border-b border-slate-200 pb-2">
                                🔍 Prejuízos Funcionais Observados no Dia a Dia (Exemplos Práticos)
                            </h3>
                            <p class="text-xs text-slate-500">Descreva situações recentes onde as dificuldades foram observadas e como prejudicam a criança:</p>

                            <div class="space-y-4 text-xs">
                                <div class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-1.5">
                                    <label class="font-bold text-slate-800 block">1. Atenção, Concentração e Vigília</label>
                                    <p class="text-slate-500 text-[11px]">Falhas de atenção, esquecimentos, distração com facilidade, manter o foco em tarefas longas.</p>
                                    <textarea name="a[prejuizo_atencao]" rows="2" class="w-full border border-slate-300 rounded-lg p-2 text-xs" placeholder="Exemplos práticos de situações observadas..."><?= htmlspecialchars($existingAnamnesis['prejuizo_atencao'] ?? '') ?></textarea>
                                </div>

                                <div class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-1.5">
                                    <label class="font-bold text-slate-800 block">2. Memória e Aprendizagem</label>
                                    <p class="text-slate-500 text-[11px]">Lembrança de fatos recentes, instruções ouvidas há pouco tempo, cálculos de cabeça.</p>
                                    <textarea name="a[prejuizo_memoria]" rows="2" class="w-full border border-slate-300 rounded-lg p-2 text-xs" placeholder="Exemplos práticos de situações observadas..."><?= htmlspecialchars($existingAnamnesis['prejuizo_memoria'] ?? '') ?></textarea>
                                </div>

                                <div class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-1.5">
                                    <label class="font-bold text-slate-800 block">3. Linguagem, Fala e Comunicação</label>
                                    <p class="text-slate-500 text-[11px]">Compreensão de ordens, trocas na fala, conversação início-meio-fim, pistas sociais e ironia.</p>
                                    <textarea name="a[prejuizo_linguagem]" rows="2" class="w-full border border-slate-300 rounded-lg p-2 text-xs" placeholder="Exemplos práticos de situações observadas..."><?= htmlspecialchars($existingAnamnesis['prejuizo_linguagem'] ?? '') ?></textarea>
                                </div>

                                <div class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-1.5">
                                    <label class="font-bold text-slate-800 block">4. Funções Executivas e Autocontrole</label>
                                    <p class="text-slate-500 text-[11px]">Planejamento, impulsividade, controle da raiva/frustração, flexibilidade para mudar de ideia.</p>
                                    <textarea name="a[prejuizo_executivas]" rows="2" class="w-full border border-slate-300 rounded-lg p-2 text-xs" placeholder="Exemplos práticos de situações observadas..."><?= htmlspecialchars($existingAnamnesis['prejuizo_executivas'] ?? '') ?></textarea>
                                </div>

                                <div class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-1.5">
                                    <label class="font-bold text-slate-800 block">5. Leitura, Escrita e Alfabetização</label>
                                    <p class="text-slate-500 text-[11px]">Formação de sílabas, sons das letras, fluência na leitura, compreensão de textos.</p>
                                    <textarea name="a[prejuizo_leitura_escrita]" rows="2" class="w-full border border-slate-300 rounded-lg p-2 text-xs" placeholder="Exemplos práticos de situações observadas..."><?= htmlspecialchars($existingAnamnesis['prejuizo_leitura_escrita'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- ============================================================== -->
                        <!-- MODELO 2: ANAMNESE GERAL / ADULTO (77 PERGUNTAS)               -->
                        <!-- ============================================================== -->

                        <!-- PARTE 1: INFORMAÇÕES PESSOAIS, ESCOLARIDADE E TRABALHO -->
                        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/60">
                            <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide border-b border-slate-200 pb-2">
                                👤 Informações Pessoais, Escolaridade e Trabalho
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Dominância Lateral *</label>
                                    <select name="a[dominancia_lateral]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                        <option value="Destro">Destro</option>
                                        <option value="Canhoto">Canhoto</option>
                                        <option value="Ambidestro">Ambidestro</option>
                                        <option value="Desconheço">Desconheço</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Estado Civil *</label>
                                    <select name="a[estado_civil]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                        <option value="Solteiro(a)">Solteiro(a)</option>
                                        <option value="Casado(a)">Casado(a)</option>
                                        <option value="União estável">União estável</option>
                                        <option value="Divorciado(a)">Divorciado(a)</option>
                                        <option value="Viúvo(a)">Viúvo(a)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Possui Filhos?</label>
                                    <input type="text" name="a[filhos_idades]" placeholder="Ex: Sim (12 e 16 anos)" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="block font-semibold text-slate-700 mb-1">Pessoas com quem mora atualmente:</label>
                                    <input type="text" name="a[moradia_familia]" placeholder="Ex: Cônjuge e dois filhos / Sozinho" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Escolaridade Máxima *</label>
                                    <select name="a[serie_escolaridade]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                        <option value="Ensino Superior Completo">Ensino Superior Completo</option>
                                        <option value="Ensino Superior Incompleto">Ensino Superior Incompleto</option>
                                        <option value="Pós-graduado">Pós-graduado</option>
                                        <option value="Ensino Médio Completo">Ensino Médio Completo</option>
                                        <option value="Ensino Fundamental Completo">Ensino Fundamental Completo</option>
                                        <option value="Outro">Outro</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs pt-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Situação de Trabalho Atual *</label>
                                    <select name="a[trabalho_situacao]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                        <option value="Sim, economicamente ativo">Sim, economicamente ativo</option>
                                        <option value="Não, está de licença médica">Não, está de licença médica</option>
                                        <option value="Não, está aposentado">Não, está aposentado</option>
                                        <option value="Não, desempregado">Não, desempregado</option>
                                        <option value="Estudante">Estudante</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Profissão / Ocupação Atual</label>
                                    <input type="text" name="a[profissao_atual]" placeholder="Ex: Analista de Sistemas" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                            </div>
                        </div>

                        <!-- PARTE 2: HÁBITOS DE VIDA E LAZER -->
                        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/60">
                            <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide border-b border-slate-200 pb-2">
                                🏃 Hábitos de Vida, Sono e Lazer
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Exercícios Físicos Regulares (mínimo 30min, 2x/sem):</label>
                                    <select name="a[exercicios_fisicos]" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                        <option value="Não">Não realiza</option>
                                        <option value="Sim">Sim, realiza regularmente</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Uso de Substâncias Psicoativas (álcool, tabaco, etc.):</label>
                                    <input type="text" name="a[substancias_psicoativas]" placeholder="Ex: Não utiliza / Café diário / Álcool social" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Horários de Sono (Dias de Semana):</label>
                                    <input type="text" name="a[horarios_sono]" placeholder="Ex: Dorme 23h30 e acorda 06h30" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Queixas de Sono e Apneia:</label>
                                    <input type="text" name="a[sono_queixas]" placeholder="Ex: Insônia inicial, sonolência diurna, ronco" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                                </div>
                            </div>
                        </div>

                        <!-- PARTE 3: HISTÓRICO MÉDICO, FAMILIAR E PSIQUIÁTRICO -->
                        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/60">
                            <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide border-b border-slate-200 pb-2">
                                🧬 Histórico Médico e Antecedentes Familiares (1º Grau)
                            </h3>
                            <div class="text-xs space-y-2">
                                <label class="block font-semibold text-slate-700">Familiares de 1º Grau (Pais, Irmãos, Filhos) com diagnóstico ou histórico de:</label>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <?php
                                    $condicoesFamiliares = ['TDAH', 'TEA (Autismo)', 'Depressão', 'Ansiedade (TAG)', 'Bipolaridade', 'Esquizofrenia', 'Epilepsia', 'Demência/Alzheimer', 'AVC', 'Uso de Álcool/Drogas'];
                                    foreach ($condicoesFamiliares as $cf):
                                    ?>
                                        <label class="flex items-center gap-2 bg-white p-2 rounded-lg border border-slate-200">
                                            <input type="checkbox" name="a[familiares_1grau][]" value="<?= htmlspecialchars($cf) ?>" class="text-blue-600">
                                            <span><?= htmlspecialchars($cf) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs pt-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Medicações em Uso Contínuo (nome e dose):</label>
                                    <textarea name="a[medicacoes_uso]" rows="2" class="w-full border border-slate-300 rounded-lg p-2 bg-white" placeholder="Ex: Ritalina 10mg pela manhã, etc."></textarea>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Acompanhamentos Atuais ou Histórico (Psicólogo/Psiquiatra):</label>
                                    <textarea name="a[acompanhamentos_historico]" rows="2" class="w-full border border-slate-300 rounded-lg p-2 bg-white" placeholder="Ex: Já fez psicoterapia por 2 anos, consulta com psiquiatra..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- PARTE 4: MOTIVAÇÃO DA BUSCA E IMPACTOS ATUAIS -->
                        <div class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/60">
                            <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide border-b border-slate-200 pb-2">
                                🎯 Hipóteses Diagnósticas e Impacto das Dificuldades
                            </h3>
                            <div class="text-xs space-y-2">
                                <label class="block font-semibold text-slate-700">Hipótese diagnóstica que motivou a busca:</label>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <?php
                                    $hipoteses = [
                                        'TDAH - Déficit de Atenção e Hiperatividade',
                                        'TEA - Espectro Autista (Asperger)',
                                        'Superdotação / Altas Habilidades',
                                        'Transtorno de Aprendizagem',
                                        'Comprometimento Cognitivo / Memória',
                                        'Avaliação após trauma (AVC, TCE)'
                                    ];
                                    foreach ($hipoteses as $hp):
                                    ?>
                                        <label class="flex items-center gap-2 bg-white p-2 rounded-lg border border-slate-200">
                                            <input type="checkbox" name="a[hipoteses_busca][]" value="<?= htmlspecialchars($hp) ?>" class="text-blue-600">
                                            <span><?= htmlspecialchars($hp) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="space-y-3 text-xs pt-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Há quanto tempo as dificuldades começaram a ser percebidas e quais foram as primeiras? *</label>
                                    <textarea name="a[tempo_inicio_dificuldades]" rows="2" required class="w-full border border-slate-300 rounded-lg p-2 bg-white" placeholder="Descreva os primeiros sinais percebidos..."><?= htmlspecialchars($existingAnamnesis['tempo_inicio_dificuldades'] ?? '') ?></textarea>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Descreva o impacto das dificuldades atuais no dia a dia (trabalho, estudos, relacionamentos): *</label>
                                    <textarea name="a[impacto_dia_a_dia]" rows="3" required class="w-full border border-slate-300 rounded-lg p-2 bg-white" placeholder="Como isso afeta a rotina e gera prejuízos práticos..."><?= htmlspecialchars($existingAnamnesis['impacto_dia_a_dia'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>

                    <!-- DADOS PARA O PAGAMENTO (RODAPÉ OFICIAL) -->
                    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 text-xs space-y-1 text-slate-700">
                        <span class="font-bold uppercase tracking-wider text-[11px] text-slate-900 block mb-1">Dados para Pagamento</span>
                        <p>Banco: <strong>Inter - 077</strong> • Agência: <strong>0001</strong> • Conta: <strong>32761183-9</strong></p>
                        <p>Razão Social: <strong>Mente em Equilíbrio Serviços de Psicologia LTDA</strong></p>
                        <p>Chave PIX (CNPJ): <strong>53.076.815/0001-10</strong></p>
                        <p class="text-slate-500 pt-1">O pagamento se inicia no dia agendado para a anamnese. Enviar comprovante para o WhatsApp: <strong>(24) 99964-2123</strong>.</p>
                    </div>

                    <button type="submit" class="w-full bg-[#1d4ed8] hover:bg-[#1e40af] text-white font-bold py-4 px-6 rounded-2xl shadow-sm transition text-sm">
                        Confirmar e Enviar Anamnese Preliminar
                    </button>
                </form>

            <?php endif; ?>
        </div>
    </div>

</body>
</html>
