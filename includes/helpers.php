<?php
require_once __DIR__ . '/../config/database.php';

function getCurrentUser(PDO $pdo): array {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch();
        if ($u) return $u;
    }
    // Padrão inicial: Coordenação (Gisele) com seletor rápido de perfil no topo para testar permissões
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC LIMIT 1");
    $u = $stmt->fetch();
    $_SESSION['user_id'] = $u['id'];
    return $u;
}

function calculateAgeGroup(?int $age): ?string {
    if ($age === null || $age < 0) return null;
    if ($age < 6) return '<6 anos';
    if ($age <= 11) return '6 a 11 anos';
    if ($age <= 17) return '12 a 17 anos';
    if ($age < 50) return '18+anos';
    return '50+anos';
}

function cleanPhone(?string $phone): string {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '5524999999999';
    if (strlen($digits) <= 11 && !str_starts_with($digits, '55')) {
        $digits = '55' . $digits;
    }
    return $digits;
}

function buildWhatsAppUrl(?string $phone, string $message): string {
    $clean = cleanPhone($phone);
    return 'https://wa.me/' . $clean . '?text=' . rawurlencode($message);
}

function getBaseAppUrl(): string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $dir = rtrim( str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/' );
    return "{$proto}://{$host}{$dir}";
}

function logAudit(PDO $pdo, array $user, ?int $patientId, string $action): void {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_name, user_role, patient_id, action_description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['name'], $user['role'], $patientId, $action]);
}

function formatStageBadge(string $stage): array {
    return match ($stage) {
        'vai_iniciar' => ['label' => 'Pré-Consulta / Início', 'bg' => 'bg-blue-50 text-blue-800 border-blue-200'],
        'em_avaliacao' => ['label' => 'Em Avaliação', 'bg' => 'bg-indigo-50 text-indigo-800 border-indigo-200'],
        'para_devolutiva' => ['label' => 'Para Devolutiva', 'bg' => 'bg-slate-100 text-slate-800 border-slate-300'],
        'concluido' => ['label' => 'Concluído', 'bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        default => ['label' => ucfirst($stage), 'bg' => 'bg-slate-50 text-slate-600 border-slate-200'],
    };
}

function formatRoleBadge(string $role): array {
    return match ($role) {
        'admin_gisele' => ['label' => 'Gisele • Coordenação', 'bg' => 'bg-blue-100/80 text-blue-900 border-blue-200'],
        'clinico' => ['label' => 'Clínico', 'bg' => 'bg-slate-100 text-slate-700 border-slate-200'],
        default => ['label' => 'Administrativo', 'bg' => 'bg-slate-50 text-slate-600 border-slate-200'],
    };
}

/**
 * Gera link de cobrança inteligente cobrando APENAS os procedimentos pendentes da pré-consulta
 */
function buildPreConsultaReminderWhatsApp(array $patient, array $preTasks): string {
    $baseUrl = getBaseAppUrl();
    $token = $patient['public_token'] ?: 'token';
    $isInfantil = (($patient['age'] !== null && (int)$patient['age'] < 18) || ($patient['contract_model'] ?? '') === 'infantil');
    $modelParam = $isInfantil ? '&model=infantil' : '&model=adulto';
    $cadUrl = !empty($patient['google_form_cadastro_url']) ? $patient['google_form_cadastro_url'] : "{$baseUrl}/form.php?token={$token}&type=cadastro";
    $anaUrl = !empty($patient['google_form_anamnese_url']) ? $patient['google_form_anamnese_url'] : "{$baseUrl}/form.php?token={$token}&type=anamnese{$modelParam}";
    $conUrl = "{$baseUrl}/contract_print.php?id={$patient['id']}&model=" . ($isInfantil ? 'infantil' : 'adulto');

    $pendingLines = [];
    foreach ($preTasks as $t) {
        if ($t['status'] !== 'concluido') {
            if ($t['task_code'] === 'cli_cadastro') {
                $pendingLines[] = "• *Cadastro do Cliente*: {$cadUrl}";
            } elseif ($t['task_code'] === 'cli_anamnese_preliminar') {
                $pendingLines[] = "• *Anamnese Preliminar*: {$anaUrl}";
            } elseif ($t['task_code'] === 'cli_contrato_assinado') {
                $pendingLines[] = "• *Contrato de Prestação de Serviço*: {$conUrl}";
            }
        }
    }

    $resp = $patient['responsible_name'] ?: $patient['full_name'];
    if (empty($pendingLines)) {
        $msg = "Olá, {$resp}! Tudo bem? Confirmamos o recebimento de todos os procedimentos de pré-consulta de {$patient['full_name']}. Estamos prontos para a nossa sessão de anamnese!";
    } else {
        $listText = implode("\n", $pendingLines);
        $msg = "Olá, {$resp}! Tudo bem? Passando para lembrar que, para prepararmos a anamnese de *{$patient['full_name']}*, ainda precisamos da conclusão do(s) seguinte(s) item(ns) pendente(s):\n\n{$listText}\n\nQualquer dúvida no preenchimento, estamos à disposição!";
    }

    return buildWhatsAppUrl($patient['phone_whatsapp'], $msg);
}
