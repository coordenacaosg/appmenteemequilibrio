<?php
/**
 * Webhook receptor para integração com Google Forms / Google Sheets (Apps Script)
 * Permite dar baixa automática em "Cadastro do cliente" ou "Anamnese preliminar"
 * quando o cliente responde pelo Google Forms.
 */
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$patientId = (int)($data['patient_id'] ?? 0);
$email = trim($data['email'] ?? '');
$formType = trim($data['form_type'] ?? 'cadastro'); // 'cadastro' ou 'anamnese'

if ($patientId <= 0 && $email !== '') {
    $st = $pdo->prepare("SELECT id FROM patients WHERE email_nf = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$email]);
    $row = $st->fetch();
    if ($row) $patientId = (int)$row['id'];
}

if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Paciente não identificado (informe patient_id ou email).']);
    exit;
}

$taskCode = ($formType === 'anamnese') ? 'cli_anamnese_preliminar' : 'cli_cadastro';
$pdo->prepare("UPDATE patient_tasks SET status='concluido', completed_at=CURRENT_TIMESTAMP, completed_by='Google Forms (Webhook)' WHERE patient_id=? AND task_code=?")
    ->execute([$patientId, $taskCode]);

logAudit($pdo, ['name' => 'Google Forms Webhook', 'role' => 'integracao'], $patientId, "Recebeu submissão efetiva do Google Forms ({$formType})");

echo json_encode(['ok' => true, 'patient_id' => $patientId, 'task_completed' => $taskCode]);
