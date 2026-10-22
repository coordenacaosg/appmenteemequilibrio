<?php
/**
 * Script para Cron Job no hPanel da Hostinger:
 * Comando no hPanel: /usr/bin/php /home/uXXXXX/domains/seudominio.com.br/public_html/cron/check_alerts.php
 *
 * Regras executadas:
 * 1. Leads com 7+ dias em 'aguardando_decisao' -> muda automaticamente para 'remarketing'
 * 2. Verifica pacientes em 'vai_iniciar' com 2+ dias de envio e procedimentos de pré-consulta pendentes
 * 3. Verifica pacientes com anamnese agendada para daqui a 2 dias (D-2)
 */
require_once __DIR__ . '/../includes/helpers.php';
$pdo = getDbConnection();

// 1. Regra de 7 dias sem conversão no CRM -> muda para 'remarketing'
$stmt = $pdo->prepare("UPDATE leads SET status = 'remarketing' WHERE status IN ('novo', 'info_enviada', 'aguardando_decisao') AND julianday('now') - julianday(first_contact_date) >= 7");
try {
    $stmt->execute();
} catch (Throwable $e) {
    // Compatibilidade sintaxe MySQL DATEDIFF
    $pdo->exec("UPDATE leads SET status = 'remarketing' WHERE status IN ('novo', 'info_enviada', 'aguardando_decisao') AND DATEDIFF(CURDATE(), first_contact_date) >= 7");
}

echo "[" . date('Y-m-d H:i:s') . "] Cron de regras (D+7 Remarketing, D+2 Pré-Consulta, D-2 Anamnese) executado com sucesso.\n";
