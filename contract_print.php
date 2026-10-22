<?php
/**
 * Gerador Automático de Contrato de Prestação de Serviço
 * Incluindo inserção automática das datas e valores das parcelas dos boletos
 * e selo de conferência obrigatória da Gisele.
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

// Permite ao cliente assinar eletronicamente ou à Gisele aprovar direto da tela
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve_gisele') {
        $pdo->prepare("UPDATE patients SET contract_gisele_approved=1, contract_gisele_approved_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
        $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Gisele (Coordenação)' WHERE patient_id=? AND task_code='ini_gisele_conferir_contrato'")->execute([$id]);
        logAudit($pdo, $currentUser, $id, 'Gisele conferiu e aprovou o Contrato de Prestação de Serviço');
    } elseif ($action === 'sign_contract') {
        $pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Assinatura Confirmada' WHERE patient_id=? AND task_code='cli_contrato_assinado'")->execute([$id]);
        logAudit($pdo, $currentUser, $id, 'Contrato de Prestação de Serviço marcado como Assinado');
    }
    header("Location: contract_print.php?id={$id}");
    exit;
}

$installments = !empty($patient['contract_installments_json']) ? json_decode($patient['contract_installments_json'], true) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Avaliação Neuropsicológica — <?= htmlspecialchars($patient['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-slate-100 py-8 px-4">
    <!-- Barra de Controle (Oculta na impressão/PDF) -->
    <div class="max-w-4xl mx-auto mb-6 bg-white p-4 rounded-xl shadow border border-slate-200 flex flex-wrap items-center justify-between gap-3 no-print">
        <div>
            <span class="text-xs font-bold uppercase px-2.5 py-1 rounded-full <?= $patient['contract_gisele_approved'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                <?= $patient['contract_gisele_approved'] ? '✓ Conferido e Aprovado por Gisele' : '⏳ Aguardando Conferência da Gisele' ?>
            </span>
            <p class="text-xs text-slate-500 mt-1">Dica: Você pode clicar em qualquer trecho do contrato abaixo para editar antes de imprimir/salvar em PDF.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="index.php?tab=clientes&patient_id=<?= $patient['id'] ?>" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50">
                ← Voltar ao Prontuário
            </a>
            <?php if (!$patient['contract_gisele_approved']): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="approve_gisele">
                    <button type="submit" class="px-3 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold">
                        ✓ Dar OK da Gisele no Contrato
                    </button>
                </form>
            <?php endif; ?>
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="sign_contract">
                <button type="submit" class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                    ✍️ Confirmar Assinatura do Cliente
                </button>
            </form>
            <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow">
                🖨️ Imprimir / Salvar PDF
            </button>
        </div>
    </div>

    <!-- Folha de Contrato Editável -->
    <div contenteditable="true" class="max-w-4xl mx-auto bg-white p-10 md:p-14 rounded-xl shadow-lg border border-slate-200 text-slate-800 leading-relaxed text-sm">
        <div class="text-center border-b border-slate-300 pb-6 mb-6">
            <h1 class="text-xl font-bold uppercase tracking-wide text-slate-900">Contrato de Prestação de Serviços de Avaliação Neuropsicológica</h1>
            <p class="text-xs text-slate-500 mt-1">Clínica Mente em Equilíbrio — Gestão e Avaliação Neuropsicológica</p>
        </div>

        <p class="mb-4">
            <strong>CONTRATANTE / RESPONSÁVEL LEGAL:</strong> <?= htmlspecialchars($patient['responsible_name'] ?: $patient['full_name']) ?>,
            inscrito(a) no CPF sob o nº <strong><?= htmlspecialchars($patient['cpf_nf'] ?: '___.___.___-__') ?></strong>,
            residente e domiciliado(a) em <strong><?= htmlspecialchars($patient['address_full'] ?: '____________________________________________________') ?></strong>,
            e-mail <strong><?= htmlspecialchars($patient['email_nf'] ?: '______________________') ?></strong>,
            telefone/WhatsApp <strong><?= htmlspecialchars($patient['phone_whatsapp'] ?: '________________') ?></strong>.
        </p>

        <p class="mb-4">
            <strong>PACIENTE AVALIADO(A):</strong> <strong><?= htmlspecialchars($patient['full_name']) ?></strong>
            (Idade: <?= htmlspecialchars((string)($patient['age'] ?? '__')) ?> anos | Escolaridade: <?= htmlspecialchars($patient['education_level'] ?: 'Não informada') ?> | Escola: <?= htmlspecialchars($patient['school_name'] ?: 'Não se aplica') ?>).
        </p>

        <h2 class="font-bold text-slate-900 mt-6 mb-2 uppercase text-xs tracking-wider">Cláusula 1ª — Do Objeto</h2>
        <p class="mb-4">
            O presente contrato tem por objeto a prestação de serviços profissionais de <strong>Avaliação Neuropsicológica</strong> ao(à) paciente acima qualificado(a), compreendendo entrevista de anamnese, aplicação de bateria de testes e escalas padronizadas, entrevistas com informantes/escola quando aplicável, elaboração do Laudo Neuropsicológico com Mapa Neuropsicológico e sessão de Devolutiva.
        </p>

        <h2 class="font-bold text-slate-900 mt-6 mb-2 uppercase text-xs tracking-wider">Cláusula 2ª — Dos Honorários e Datas das Parcelas dos Boletos</h2>
        <p class="mb-3">
            Pelos serviços prestados, o(a) CONTRATANTE pagará o valor total de
            <strong>R$ <?= number_format((float)$patient['contract_total_value'], 2, ',', '.') ?></strong>,
            dividido em <strong><?= (int)$patient['contract_installments'] ?> parcela(s)</strong> via boleto bancário, conforme cronograma de vencimentos abaixo:
        </p>

        <table class="w-full border-collapse border border-slate-300 my-4 text-xs">
            <thead>
                <tr class="bg-slate-100">
                    <th class="border border-slate-300 px-3 py-2 text-left">Parcela</th>
                    <th class="border border-slate-300 px-3 py-2 text-left">Data de Vencimento do Boleto</th>
                    <th class="border border-slate-300 px-3 py-2 text-left">Valor da Parcela (R$)</th>
                    <th class="border border-slate-300 px-3 py-2 text-left">Situação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($installments)): ?>
                    <?php foreach ($installments as $inst): ?>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2 font-semibold"><?= htmlspecialchars((string)$inst['parcela']) ?>ª Parcela</td>
                            <td class="border border-slate-300 px-3 py-2"><?= htmlspecialchars(date('d/m/Y', strtotime($inst['vencimento']))) ?></td>
                            <td class="border border-slate-300 px-3 py-2">R$ <?= htmlspecialchars((string)$inst['valor']) ?></td>
                            <td class="border border-slate-300 px-3 py-2"><?= htmlspecialchars((string)($inst['status'] ?? 'Emitido')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="border border-slate-300 px-3 py-2 text-center">Parcela única / A definir no cadastro de boletos</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2 class="font-bold text-slate-900 mt-6 mb-2 uppercase text-xs tracking-wider">Cláusula 3ª — Do Agendamento, Faltas e Sigilo (LGPD)</h2>
        <p class="mb-4">
            A sessão de anamnese está agendada para <strong><?= $patient['anamnesis_date'] ? date('d/m/Y', strtotime($patient['anamnesis_date'])) : '___/___/2026' ?></strong>. Eventuais reagendamentos deverão ser comunicados com antecedência mínima de 24 horas. Todos os dados clínicos e resultados são protegidos pelo Código de Ética Profissional do Psicólogo e pela Lei Geral de Proteção de Dados (Lei nº 13.709/2018).
        </p>

        <div class="mt-12 pt-6 border-t border-slate-200 flex flex-col md:flex-row justify-between gap-8 text-center text-xs">
            <div class="flex-1">
                <div class="border-b border-slate-400 h-10 mb-2"></div>
                <p class="font-bold"><?= htmlspecialchars($patient['responsible_name'] ?: $patient['full_name']) ?></p>
                <p class="text-slate-500">CPF: <?= htmlspecialchars($patient['cpf_nf'] ?: '___.___.___-__') ?> (Contratante)</p>
            </div>
            <div class="flex-1">
                <div class="border-b border-slate-400 h-10 mb-2"></div>
                <p class="font-bold">Mente em Equilíbrio — Avaliação Neuropsicológica</p>
                <p class="text-slate-500">Coordenação: Gisele <?= $patient['contract_gisele_approved'] ? '(Conferido em ' . date('d/m/Y', strtotime($patient['contract_gisele_approved_at'] ?? 'now')) . ')' : '' ?></p>
            </div>
        </div>
    </div>
</body>
</html>
