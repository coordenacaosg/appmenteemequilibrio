<?php
/**
 * Formulários Públicos Nativos (Cadastro do Cliente e Anamnese Preliminar)
 * Quando preenchidos pelo paciente/responsável, atualizam automaticamente o prontuário
 * e marcam o item correspondente como CONCLUÍDO no painel de pré-consulta!
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

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($type === 'cadastro') {
        $fullName = trim($_POST['full_name'] ?? $patient['full_name']);
        $respName = trim($_POST['responsible_name'] ?? '');
        $cpf = trim($_POST['cpf_nf'] ?? '');
        $email = trim($_POST['email_nf'] ?? '');
        $phone = trim($_POST['phone_whatsapp'] ?? '');
        $age = $_POST['age'] !== '' ? (int)$_POST['age'] : $patient['age'];
        $ageGroup = calculateAgeGroup($age) ?? $patient['age_group'];
        $school = trim($_POST['school_name'] ?? '');
        $edu = trim($_POST['education_level'] ?? '');
        $address = trim($_POST['address_full'] ?? '');

        $up = $pdo->prepare("UPDATE patients SET full_name=?, responsible_name=?, cpf_nf=?, email_nf=?, phone_whatsapp=?, age=?, age_group=?, school_name=?, education_level=?, address_full=? WHERE id=?");
        $up->execute([$fullName, $respName, $cpf, $email, $phone, $age, $ageGroup, $school, $edu, $address, $patient['id']]);

        // Baixa automática na tarefa de pré-consulta 'cli_cadastro'
        $tUp = $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Paciente (Formulário Online)' WHERE patient_id=? AND task_code='cli_cadastro'");
        $tUp->execute([$patient['id']]);

        logAudit($pdo, ['name' => 'Paciente/Responsável', 'role' => 'externo'], (int)$patient['id'], 'Preencheu o Formulário Nativo de Cadastro do Cliente');
        $submitted = true;
    } elseif ($type === 'anamnese') {
        $answers = [
            'queixa_principal' => trim($_POST['queixa_principal'] ?? ''),
            'historico_gestacional_desenvolvimento' => trim($_POST['historico_desenvolvimento'] ?? ''),
            'historico_escolar' => trim($_POST['historico_escolar'] ?? ''),
            'sono_alimentacao_rotina' => trim($_POST['sono_alimentacao'] ?? ''),
            'medicacoes_acompanhamentos' => trim($_POST['medicacoes'] ?? ''),
            'antecedentes_familiares' => trim($_POST['antecedentes_familiares'] ?? ''),
            'data_envio' => date('d/m/Y H:i')
        ];
        $up = $pdo->prepare("UPDATE patients SET anamnesis_answers_json=? WHERE id=?");
        $up->execute([json_encode($answers, JSON_UNESCAPED_UNICODE), $patient['id']]);

        // Baixa automática na tarefa de pré-consulta 'cli_anamnese_preliminar'
        $tUp = $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Paciente (Formulário Online)' WHERE patient_id=? AND task_code='cli_anamnese_preliminar'");
        $tUp->execute([$patient['id']]);

        logAudit($pdo, ['name' => 'Paciente/Responsável', 'role' => 'externo'], (int)$patient['id'], 'Preencheu o Formulário Nativo de Anamnese Preliminar');
        $submitted = true;
    }

    // Verifica se todos os 3 itens de pré-consulta foram concluídos
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $type === 'anamnese' ? 'Anamnese Preliminar' : 'Cadastro do Cliente' ?> — Mente em Equilíbrio</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen py-8 px-4">
    <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
        <div class="bg-teal-700 text-white px-6 py-5">
            <span class="text-xs uppercase tracking-wider bg-teal-800 px-2.5 py-1 rounded-full font-semibold">Pré-Consulta Neuropsicológica</span>
            <h1 class="text-2xl font-bold mt-2">
                <?= $type === 'anamnese' ? 'Formulário de Anamnese Preliminar' : 'Ficha de Cadastro do Cliente (Contrato e NF)' ?>
            </h1>
            <p class="text-teal-100 text-sm mt-1">Paciente: <strong><?= htmlspecialchars($patient['full_name']) ?></strong></p>
        </div>

        <div class="p-6">
            <?php if ($submitted): ?>
                <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 rounded-xl p-6 text-center">
                    <div class="text-4xl mb-2">✅</div>
                    <h2 class="text-xl font-bold">Formulário recebido com sucesso!</h2>
                    <p class="text-sm text-emerald-700 mt-1">
                        O status no painel da clínica já foi atualizado automaticamente como <strong>Concluído</strong>.
                    </p>
                    <div class="mt-5 flex justify-center gap-3">
                        <a href="?token=<?= urlencode($token) ?>&type=<?= $type === 'cadastro' ? 'anamnese' : 'cadastro' ?>" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            Ir para <?= $type === 'cadastro' ? 'Anamnese Preliminar' : 'Cadastro do Cliente' ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" class="space-y-4">
                    <?php if ($type === 'cadastro'): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome Completo do Paciente *</label>
                                <input type="text" name="full_name" required value="<?= htmlspecialchars($patient['full_name'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome do Responsável Financeiro/Legal *</label>
                                <input type="text" name="responsible_name" required value="<?= htmlspecialchars($patient['responsible_name'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">CPF (Para Contrato e Nota Fiscal) *</label>
                                <input type="text" name="cpf_nf" required value="<?= htmlspecialchars($patient['cpf_nf'] ?? '') ?>" placeholder="000.000.000-00" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail (Para envio de NF e Laudo) *</label>
                                <input type="email" name="email_nf" required value="<?= htmlspecialchars($patient['email_nf'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">WhatsApp de Contato *</label>
                                <input type="text" name="phone_whatsapp" required value="<?= htmlspecialchars($patient['phone_whatsapp'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Idade Atual do Paciente *</label>
                                <input type="number" name="age" required value="<?= htmlspecialchars((string)($patient['age'] ?? '')) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Escola / Instituição</label>
                                <select name="school_name" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                                    <option value=""> Não se aplica / Adulto </option>
                                    <?php foreach ($schools as $s): ?>
                                        <option value="<?= htmlspecialchars($s) ?>" <?= ($patient['school_name'] === $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Escolaridade / Série Atual *</label>
                                <input type="text" name="education_level" required value="<?= htmlspecialchars($patient['education_level'] ?? '') ?>" placeholder="Ex: 3º ano do ensino fundamental" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Endereço Completo (Rua, Número, Bairro, Cidade, CEP) *</label>
                            <input type="text" name="address_full" required value="<?= htmlspecialchars($patient['address_full'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    <?php else: ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">1. Principais Queixas e Motivo da Avaliação Neuropsicológica *</label>
                            <textarea name="queixa_principal" rows="3" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Descreva os principais sinais observados em casa, na escola ou no trabalho..."><?= htmlspecialchars($existingAnamnesis['queixa_principal'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">2. Histórico Gestacional e Marcos do Desenvolvimento (fala, marcha, autonomia)</label>
                            <textarea name="historico_desenvolvimento" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"><?= htmlspecialchars($existingAnamnesis['historico_gestacional_desenvolvimento'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">3. Histórico Escolar / Acadêmico / Adaptação e Socialização</label>
                            <textarea name="historico_escolar" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"><?= htmlspecialchars($existingAnamnesis['historico_escolar'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">4. Qualidade do Sono, Alimentação e Rotina Diária</label>
                            <textarea name="sono_alimentacao" rows="2" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"><?= htmlspecialchars($existingAnamnesis['sono_alimentacao_rotina'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">5. Medicações em Uso e Acompanhamentos Atuais (Fono, TO, Psicologia, Médico)</label>
                            <textarea name="medicacoes" rows="2" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"><?= htmlspecialchars($existingAnamnesis['medicacoes_acompanhamentos'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">6. Antecedentes Familiares Relevantes (TDAH, TEA, Dislexia, Ansiedade, etc.)</label>
                            <textarea name="antecedentes_familiares" rows="2" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"><?= htmlspecialchars($existingAnamnesis['antecedentes_familiares'] ?? '') ?></textarea>
                        </div>
                    <?php endif; ?>

                    <div class="bg-slate-100 rounded-lg p-3 text-xs text-slate-600">
                        🔒 <strong>Proteção de Dados (LGPD):</strong> As informações fornecidas são estritamente confidenciais e protegidas pelo sigilo profissional psicológico, destinando-se exclusivamente à condução da Avaliação Neuropsicológica.
                    </div>

                    <button type="submit" class="w-full bg-teal-700 hover:bg-teal-800 text-white font-semibold py-3 rounded-xl shadow transition">
                        Confirmar e Enviar <?= $type === 'anamnese' ? 'Anamnese Preliminar' : 'Cadastro' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
