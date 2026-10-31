<?php
/**
 * Formulários Públicos Nativos — Mente em Equilíbrio (Gisele Silva Corrêa - CRP 05/40880)
 * 
 * Totalmente alinhados com os formulários oficiais:
 * 1. Cadastro de Clientes: https://forms.gle/UjcZLoLEzDyCm4dq5
 * 2. Anamnese Preliminar Criança: https://forms.gle/NZGiCRSuTpJDmJDr6
 * 3. Anamnese Preliminar Adulto: https://forms.gle/ZehnrCVvNX9cNYCa6
 *
 * Atualiza automaticamente o prontuário no sistema e marca a tarefa correspondente
 * no painel de pré-consulta da clínica.
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/form_renderer.php';

$pdo = getDbConnection();

$token = $_GET['token'] ?? '';
$type = $_GET['type'] ?? 'cadastro';

$stmt = $pdo->prepare("SELECT * FROM patients WHERE public_token = ?");
$stmt->execute([$token]);
$patient = $stmt->fetch();

if (!$patient) {
    http_response_code(404);
    die("Link de formulário inválido ou expirado. Por favor, solicite um novo link à clínica.");
}

// Determina se a anamnese é do modelo Infantil ou Adulto
$patientAge = $patient['age'] !== null ? (int)$patient['age'] : null;
$defaultModel = ($patientAge !== null && $patientAge < 18) ? 'infantil' : 'adulto';
$anamneseModel = $_GET['model'] ?? ($patient['anamnesis_type'] ?: $defaultModel);
if (!in_array($anamneseModel, ['infantil', 'adulto'])) {
    $anamneseModel = 'adulto';
}

$allSchemas = getFormSchemas();
$schemaKey = ($type === 'cadastro') ? 'cadastro' : (($anamneseModel === 'infantil' || $anamneseModel === 'crianca') ? 'anamnese_crianca' : 'anamnese_adulto');
$currentSchema = $allSchemas[$schemaKey] ?? [
    'title' => ($type === 'cadastro' ? 'Cadastro de Clientes' : 'Anamnese Preliminar'),
    'description' => '',
    'sections' => []
];

$officialGoogleFormUrl = ($type === 'cadastro')
    ? 'https://forms.gle/UjcZLoLEzDyCm4dq5'
    : (($anamneseModel === 'infantil') ? 'https://forms.gle/NZGiCRSuTpJDmJDr6' : 'https://forms.gle/ZehnrCVvNX9cNYCa6');

$submitted = false;
$submittedType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedType = $_POST['form_type'] ?? $type;

    if ($submittedType === 'cadastro') {
        $fullName = trim($_POST['full_name'] ?? $patient['full_name']);
        $respTipo = trim($_POST['resp_tipo'] ?? 'proprio');
        
        $respName = ($respTipo === 'proprio' || $respTipo === 'O próprio paciente')
            ? $fullName
            : trim($_POST['responsible_name'] ?? '');
            
        if (empty($respName) && !empty($_POST['resp_tipo_outro'])) {
            $respName = trim($_POST['resp_tipo_outro']);
        }
        if (empty($respName) && $respTipo === '__outro__') {
            $respName = trim($_POST['resp_tipo_outro'] ?? '');
        }

        $cpf = trim($_POST['cpf_nf'] ?? '');
        $email = trim($_POST['email_nf'] ?? ($_POST['resp_email'] ?? ''));
        $phone = trim($_POST['phone_whatsapp'] ?? '');
        $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : ($patient['birth_date'] ?? null);
        $age = ($_POST['age'] !== '' && $_POST['age'] !== null) ? (int)$_POST['age'] : $patient['age'];
        $ageGroup = calculateAgeGroup($age) ?? $patient['age_group'];

        $street = trim($_POST['address_street'] ?? '');
        $bairro = trim($_POST['address_bairro'] ?? '');
        $cidadeUf = trim($_POST['address_cidade_uf'] ?? '');
        $cep = trim($_POST['address_cep'] ?? '');
        $addressFull = trim("{$street}, {$bairro}, {$cidadeUf} - CEP {$cep}", ", - CEP ");
        if (empty($addressFull)) {
            $addressFull = trim($_POST['address_full'] ?? $patient['address_full'] ?? '');
        }

        $tipoAtendimento = trim($_POST['tipo_atendimento'] ?? 'Avaliação neuropsicológica');
        if ($tipoAtendimento === '__outro__' || $tipoAtendimento === 'Outro') {
            $tipoAtendimento = trim($_POST['tipo_atendimento_outro'] ?? 'Outro');
        }

        $comoConheceu = trim($_POST['como_conheceu'] ?? '');
        if ($comoConheceu === '__outro__' || $comoConheceu === 'Outro') {
            $comoConheceu = trim($_POST['como_conheceu_outro'] ?? 'Outro');
        }

        $indicacaoDetalhe = trim($_POST['indicacao_detalhe'] ?? '');

        $cadAnswers = [
            'full_name' => $fullName,
            'paciente_nome' => $fullName,
            'age' => $age,
            'paciente_idade' => $age,
            'phone_whatsapp' => $phone,
            'paciente_whatsapp' => $phone,
            'cpf_nf' => $cpf,
            'paciente_cpf' => $cpf,
            'birth_date' => $birthDate,
            'paciente_nascimento' => $birthDate,
            'resp_tipo' => $respTipo,
            'responsible_name' => $respName,
            'resp_financeiro_nome' => $respName,
            'resp_cpf' => trim($_POST['resp_cpf'] ?? ''),
            'resp_financeiro_cpf' => trim($_POST['resp_cpf'] ?? ''),
            'resp_vinculo' => trim($_POST['resp_vinculo'] ?? ''),
            'resp_vinculo_outro' => trim($_POST['resp_vinculo_outro'] ?? ''),
            'resp_financeiro_vinculo' => trim($_POST['resp_vinculo'] ?? ''),
            'email_nf' => $email,
            'resp_email' => $email,
            'resp_financeiro_email' => $email,
            'resp_whatsapp' => trim($_POST['resp_whatsapp'] ?? $phone),
            'resp_financeiro_whatsapp' => trim($_POST['resp_whatsapp'] ?? $phone),
            'address_street' => $street,
            'endereco_rua' => $street,
            'address_bairro' => $bairro,
            'endereco_bairro' => $bairro,
            'address_cidade_uf' => $cidadeUf,
            'endereco_cidade_uf' => $cidadeUf,
            'address_cep' => $cep,
            'endereco_cep' => $cep,
            'address_full' => $addressFull,
            'tipo_atendimento' => $tipoAtendimento,
            'como_conheceu' => $comoConheceu,
            'indicacao_detalhe' => $indicacaoDetalhe,
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
        $cpf = trim($answers['cpf'] ?? ($answers['cpf_nf'] ?? $patient['cpf_nf']));
        $birthDate = !empty($answers['data_nasc']) ? $answers['data_nasc'] : (!empty($answers['data_nascimento']) ? $answers['data_nascimento'] : $patient['birth_date']);
        $age = (!empty($answers['idade']) && is_numeric($answers['idade'])) ? (int)$answers['idade'] : $patient['age'];
        $ageGroup = calculateAgeGroup($age) ?? $patient['age_group'];
        $school = trim($answers['escola'] ?? ($patient['school_name'] ?? ''));
        $edu = trim($answers['serie_escolaridade'] ?? ($answers['escolaridade'] ?? ($patient['education_level'] ?? '')));
        $fullName = trim($answers['nome'] ?? ($answers['full_name'] ?? $patient['full_name']));

        $up = $pdo->prepare("UPDATE patients SET anamnesis_type=?, anamnesis_answers_json=?, full_name=COALESCE(NULLIF(?, ''), full_name), cpf_nf=COALESCE(NULLIF(?, ''), cpf_nf), birth_date=COALESCE(?, birth_date), age=COALESCE(?, age), age_group=COALESCE(?, age_group), school_name=COALESCE(NULLIF(?, ''), school_name), education_level=COALESCE(NULLIF(?, ''), education_level) WHERE id=?");
        $up->execute([$anamneseModel, json_encode($answers, JSON_UNESCAPED_UNICODE), $fullName, $cpf, $birthDate, $age, $ageGroup, $school, $edu, $patient['id']]);

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

$existingAnamnesis = !empty($patient['anamnesis_answers_json']) ? json_decode($patient['anamnesis_answers_json'], true) : [];
$existingCadastro = !empty($patient['client_form_answers_json']) ? json_decode($patient['client_form_answers_json'], true) : [];

$activeExistingData = ($type === 'cadastro') ? $existingCadastro : $existingAnamnesis;
$inputPrefix = ($type === 'cadastro') ? '' : 'a';
$totalSections = count($currentSchema['sections'] ?? []);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($currentSchema['title'] ?? 'Formulário Clínico') ?> — Gisele Silva Corrêa</title>
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
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-100/90 min-h-screen py-6 sm:py-10 px-3 sm:px-6 text-slate-800">

    <div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden"
         x-data="{
             currentSection: 0,
             totalSections: <?= (int)$totalSections ?>,
             showAll: false,
             draftSaved: false,
             saveDraft() {
                 try {
                     const form = this.$refs.formEl;
                     if (!form) return;
                     const formData = new FormData(form);
                     const obj = {};
                     for (let [k, v] of formData.entries()) {
                         if (k.endsWith('[]')) {
                             const clean = k.slice(0, -2);
                             if (!obj[clean]) obj[clean] = [];
                             obj[clean].push(v);
                         } else {
                             obj[k] = v;
                         }
                     }
                     localStorage.setItem('appgisele_draft_<?= $patient['id'] ?>_<?= $type ?>_<?= $anamneseModel ?>', JSON.stringify(obj));
                     this.draftSaved = true;
                     setTimeout(() => this.draftSaved = false, 2500);
                 } catch(e) {}
             },
             init() {
                 this.$nextTick(() => {
                     try {
                         const saved = localStorage.getItem('appgisele_draft_<?= $patient['id'] ?>_<?= $type ?>_<?= $anamneseModel ?>');
                         if (saved && !<?= $submitted ? 'true' : 'false' ?>) {
                             const data = JSON.parse(saved);
                             const form = this.$refs.formEl;
                             if (!form) return;
                             for (const [key, val] of Object.entries(data)) {
                                 const inputs = form.querySelectorAll(`[name='${key}'], [name='${key}[]']`);
                                 if (inputs.length === 1 && inputs[0].type !== 'checkbox' && inputs[0].type !== 'radio') {
                                     if (!inputs[0].value) inputs[0].value = val;
                                 } else {
                                     inputs.forEach(inp => {
                                         if (inp.type === 'radio' && inp.value === val) {
                                             inp.checked = true;
                                         } else if (inp.type === 'checkbox' && Array.isArray(val) && val.includes(inp.value)) {
                                             inp.checked = true;
                                         }
                                     });
                                 }
                             }
                         }
                     } catch(e) {}
                 });
             },
             nextSection() {
                 if (this.currentSection < this.totalSections - 1) {
                     this.currentSection++;
                     window.scrollTo({ top: 0, behavior: 'smooth' });
                     this.saveDraft();
                 }
             },
             prevSection() {
                 if (this.currentSection > 0) {
                     this.currentSection--;
                     window.scrollTo({ top: 0, behavior: 'smooth' });
                 }
             },
             goToSection(idx) {
                 this.currentSection = idx;
                 window.scrollTo({ top: 0, behavior: 'smooth' });
             }
         }">

        <!-- CABEÇALHO TIMBRADO DA CLÍNICA -->
        <div class="bg-[#1d4ed8] text-white px-6 sm:px-8 py-7 relative">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-[11px] uppercase tracking-widest bg-[#1e40af] px-3.5 py-1 rounded-full font-semibold border border-blue-400/30">
                    Gisele Silva Corrêa • Avaliação Neuropsicológica
                </span>
                <span class="text-xs text-blue-200 font-medium">CRP 05/40880</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-bold mt-3 serif-font leading-tight">
                <?= htmlspecialchars($currentSchema['title']) ?>
            </h1>

            <div class="flex flex-wrap items-center justify-between gap-2 mt-2">
                <p class="text-blue-100 text-xs sm:text-sm">
                    Paciente: <strong class="text-white"><?= htmlspecialchars($patient['full_name']) ?></strong>
                    <?php if ($patient['age']): ?>
                        • Idade: <strong class="text-white"><?= (int)$patient['age'] ?> anos</strong>
                    <?php endif; ?>
                </p>

                <!-- Link oficial para responder no Google Forms -->
                <a href="<?= htmlspecialchars($officialGoogleFormUrl) ?>" target="_blank"
                   class="inline-flex items-center gap-1.5 text-[11px] bg-white/10 hover:bg-white/20 text-blue-100 hover:text-white px-3 py-1.5 rounded-xl border border-white/20 transition"
                   title="Caso prefira responder diretamente no link original do Google Forms">
                    <span>Google Forms Oficial</span>
                    <span class="text-xs">↗</span>
                </a>
            </div>

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
                        <a href="?token=<?= urlencode($token) ?>&type=anamnese&model=infantil" class="px-2.5 py-1 rounded-lg transition <?= $anamneseModel === 'infantil' ? 'bg-white text-blue-900 font-bold' : 'text-blue-200 hover:text-white' ?>">👶 Criança (179 itens)</a>
                        <a href="?token=<?= urlencode($token) ?>&type=anamnese&model=adulto" class="px-2.5 py-1 rounded-lg transition <?= $anamneseModel === 'adulto' ? 'bg-white text-blue-900 font-bold' : 'text-blue-200 hover:text-white' ?>">👤 Adulto (87 itens)</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-6 sm:p-8">
            <?php if ($submitted): ?>
                <!-- TELA DE SUCESSO APÓS SUBMISSÃO -->
                <div class="bg-emerald-50 border border-emerald-300 text-emerald-950 rounded-3xl p-8 sm:p-10 text-center space-y-5">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto shadow-xs">
                        ✓
                    </div>
                    <div class="space-y-1">
                        <h2 class="text-2xl font-bold text-slate-900">Formulário Enviado com Sucesso!</h2>
                        <p class="text-sm text-slate-600 max-w-lg mx-auto">
                            Agradecemos pelo preenchimento cuidadoso. As informações foram integradas e o status correspondente foi atualizado automaticamente como <strong>Concluído</strong> no prontuário da clínica.
                        </p>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-emerald-200 text-xs text-left max-w-md mx-auto text-slate-700 space-y-2 shadow-2xs">
                        <p class="font-bold text-slate-900 uppercase tracking-wide border-b border-slate-100 pb-1">
                            💳 Dados para Pagamento / PIX:
                        </p>
                        <p>Banco: <strong>Inter - 077</strong> • Agência: <strong>0001</strong> • Conta: <strong>32761183-9</strong></p>
                        <p>Chave PIX (CNPJ): <strong class="text-blue-700 font-mono text-sm">53.076.815/0001-10</strong></p>
                        <p class="text-slate-500">Razão Social: Mente em Equilíbrio Serviços de Psicologia LTDA</p>
                        <p class="text-emerald-700 font-semibold pt-1 border-t border-slate-100">
                            📱 Envio do comprovante via WhatsApp: <strong>(24) 99964-2123</strong>
                        </p>
                    </div>

                    <div class="pt-4 flex flex-wrap justify-center gap-3">
                        <a href="?token=<?= urlencode($token) ?>&type=<?= $type === 'cadastro' ? 'anamnese' : 'cadastro' ?>&model=<?= urlencode($anamneseModel) ?>"
                           class="bg-[#1d4ed8] hover:bg-[#1e40af] text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-sm transition">
                            Ir para <?= $type === 'cadastro' ? 'Anamnese Preliminar' : 'Cadastro do Cliente' ?> →
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <!-- BARRA DE ETAPAS & PROGRESSO -->
                <div class="mb-6 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900" x-text="'Etapa ' + (currentSection + 1) + ' de ' + totalSections"></span>
                            <span class="text-slate-400">•</span>
                            <span class="text-slate-600 truncate max-w-xs" x-text="showAll ? 'Exibindo todas as etapas juntas' : (<?= json_encode(array_column($currentSchema['sections'], 'title')) ?>[currentSection] || '')"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showAll = !showAll"
                                    class="text-[11px] font-semibold text-blue-700 hover:text-blue-800 bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-200 transition">
                                <span x-text="showAll ? 'Modo Passo a Passo' : 'Ver Todas as Etapas'"></span>
                            </button>
                            <span x-show="draftSaved" x-cloak class="text-[11px] text-emerald-600 font-semibold flex items-center gap-1">
                                💾 Salvo
                            </span>
                        </div>
                    </div>

                    <!-- Linha de progresso -->
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                             :style="'width: ' + ((currentSection + 1) / totalSections * 100) + '%'"></div>
                    </div>

                    <!-- Pílulas de Navegação de Seção -->
                    <div class="flex items-center gap-1.5 overflow-x-auto py-1 scrollbar-thin">
                        <?php foreach ($currentSchema['sections'] as $sIdx => $sec): ?>
                            <button type="button" @click="goToSection(<?= $sIdx ?>)"
                                    class="flex-shrink-0 px-3 py-1 rounded-xl text-[11px] font-medium transition"
                                    :class="currentSection === <?= $sIdx ?> ? 'bg-blue-600 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                                <?= ($sIdx + 1) ?>. <?= htmlspecialchars(mb_strimwidth($sec['title'], 0, 22, '...')) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- FORMULÁRIO PRINCIPAL -->
                <form method="POST" x-ref="formEl" @input.debounce.500ms="saveDraft()" @change="saveDraft()" class="space-y-6">
                    <input type="hidden" name="form_type" value="<?= htmlspecialchars($type) ?>">
                    <?php if ($type === 'anamnese'): ?>
                        <input type="hidden" name="a[modelo]" value="<?= htmlspecialchars($anamneseModel) ?>">
                    <?php endif; ?>

                    <!-- RENDERIZAÇÃO DE TODAS AS SEÇÕES -->
                    <div class="space-y-6">
                        <?php foreach ($currentSchema['sections'] as $sIdx => $sec): ?>
                            <?= renderFormSection($sec, $sIdx, $activeExistingData, $inputPrefix, $patient) ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- BOX OFICIAL DE DADOS PARA PAGAMENTO (RODAPÉ) -->
                    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 text-xs space-y-2 text-slate-700">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <span class="font-bold uppercase tracking-wider text-[11px] text-slate-900">
                                💳 Dados para Pagamento
                            </span>
                            <span class="text-[11px] text-blue-700 font-semibold">Chave PIX (CNPJ)</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <p>Banco: <strong>Inter - 077</strong> • Agência: <strong>0001</strong></p>
                                <p>Conta Corrente: <strong>32761183-9</strong></p>
                            </div>
                            <div>
                                <p>Razão Social: <strong>Mente em Equilíbrio Serviços de Psicologia LTDA</strong></p>
                                <p>Chave PIX (CNPJ): <strong class="text-blue-800 font-mono">53.076.815/0001-10</strong></p>
                            </div>
                        </div>
                        <p class="text-slate-500 pt-1 text-[11px]">
                            O pagamento se inicia no dia agendado para a anamnese. Enviar comprovante para o WhatsApp: <strong>(24) 99964-2123</strong>.
                        </p>
                    </div>

                    <!-- TERMOS LGPD E SIGILO -->
                    <div class="bg-blue-50/70 border border-blue-200 rounded-2xl p-4 text-xs text-blue-950 flex items-start gap-2.5">
                        <span class="text-lg">🔒</span>
                        <div class="leading-relaxed">
                            <strong>LGPD e Sigilo Profissional:</strong> Seus dados são confidenciais e protegidos pela Lei Geral de Proteção de Dados (Lei nº 13.709/2018) e pelo Código de Ética Profissional do Psicólogo (CFP), utilizados unicamente para emissão de contrato, nota fiscal e condução do processo de avaliação clínica.
                        </div>
                    </div>

                    <!-- BOTÕES DE NAVEGAÇÃO ENTRE ETAPAS / FINALIZAÇÃO -->
                    <div class="pt-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                        <button type="button" @click="prevSection()" x-show="!showAll && currentSection > 0"
                                class="px-5 py-3 rounded-2xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-xs font-semibold transition">
                            ← Etapa Anterior
                        </button>

                        <div class="flex items-center gap-2 ml-auto">
                            <button type="button" @click="nextSection()" x-show="!showAll && currentSection < totalSections - 1"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-sm transition">
                                Próxima Etapa →
                            </button>

                            <button type="submit" x-show="showAll || currentSection === totalSections - 1"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3.5 rounded-2xl text-xs sm:text-sm font-bold shadow-md hover:shadow-lg transition flex items-center gap-2">
                                <span>✓</span>
                                <span>Concluir e Enviar <?= $type === 'cadastro' ? 'Cadastro do Cliente' : 'Anamnese Preliminar' ?></span>
                            </button>
                        </div>
                    </div>
                </form>

            <?php endif; ?>
        </div>
    </div>

</body>
</html>
