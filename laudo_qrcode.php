<?php
/**
 * Gerador de Folha/Selo de QR Code para Impressão do Laudo (Etapa de Devolutiva)
 */
require_once __DIR__ . '/includes/helpers.php';
$pdo = getDbConnection();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) die("Paciente não encontrado.");

$targetUrl = !empty($patient['gamma_url']) ? $patient['gamma_url'] : (!empty($patient['drive_folder_url']) ? $patient['drive_folder_url'] : getBaseAppUrl());
$qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" . rawurlencode($targetUrl);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>QR Code do Laudo — <?= htmlspecialchars($patient['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-slate-100 p-8">
    <div class="max-w-lg mx-auto bg-white p-8 rounded-2xl shadow border border-slate-200 text-center">
        <div class="no-print mb-6 flex justify-between">
            <a href="index.php?tab=clientes&patient_id=<?= $patient['id'] ?>" class="text-xs text-slate-600 font-semibold">← Voltar</a>
            <button onclick="window.print()" class="bg-[#1d4ed8] text-white px-4 py-1.5 rounded-lg text-xs font-semibold">🖨️ Imprimir QR Code do Laudo</button>
        </div>
        <p class="text-xs uppercase tracking-widest text-[#1d4ed8] font-bold">Gisele Silva Corrêa — Avaliação Neuropsicológica</p>
        <h1 class="text-lg font-bold text-slate-900 mt-1"><?= htmlspecialchars($patient['full_name']) ?></h1>
        <p class="text-xs text-slate-500 mb-6">Escaneie o QR Code abaixo para acessar a apresentação digital, o Mapa Neuropsicológico e a versão digital segura do laudo:</p>
        <div class="inline-block p-4 bg-white border-2 border-[#1d4ed8] rounded-2xl shadow-sm">
            <img src="<?= htmlspecialchars($qrApiUrl) ?>" alt="QR Code Laudo" class="w-48 h-48 mx-auto">
        </div>
        <p class="text-[11px] text-slate-400 mt-4 break-all"><?= htmlspecialchars($targetUrl) ?></p>
    </div>
</body>
</html>
