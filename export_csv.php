<?php
/**
 * Exportador CSV compatível com Microsoft Excel (UTF-8 com BOM):
 * - type=nf: Tabela de Controle para Emissão de Nota Fiscal (Nome, CPF, Email, Valor, Status)
 * - type=epidemiologico: Planilha completa de Pacientes e Diagnósticos (2024 a 2026)
 */
require_once __DIR__ . '/includes/helpers.php';
$pdo = getDbConnection();
$type = $_GET['type'] ?? 'epidemiologico';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="relatorio_' . $type . '_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
// BOM UTF-8 para abrir acentos perfeitamente no Excel do Windows
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type === 'nf') {
    fputcsv($out, ['Código', 'Paciente', 'Responsável (NF)', 'CPF', 'E-mail', 'Valor Total (R$)', 'Parcelas', 'NF Emitida?', 'Data Emissão NF'], ';');
    $rows = $pdo->query("SELECT * FROM patients ORDER BY code_number DESC, id DESC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['code_number'] ?: $r['id'],
            $r['full_name'],
            $r['responsible_name'] ?: $r['full_name'],
            $r['cpf_nf'],
            $r['email_nf'],
            number_format((float)$r['contract_total_value'], 2, ',', '.'),
            $r['contract_installments'],
            $r['nf_issued'] ? 'Sim' : 'Pendente',
            $r['nf_issued_at'] ?: '-'
        ], ';');
    }
} else {
    fputcsv($out, [
        'paciente', 'nome', 'ano', 'mês de início', 'mês de fim', 'idade', 'faixa etária',
        'escola', 'escolaridade', 'n° de sessões com paciente',
        'diagnóstico 1', 'diagnóstico 2', 'diagnóstico 3', 'diagnóstico 4',
        'descrever a comorbidade que não consta na lista', 'etapa atual'
    ], ';');
    $rows = $pdo->query("SELECT * FROM patients ORDER BY code_number ASC, id ASC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['code_number'] ?: $r['id'],
            $r['full_name'],
            $r['reference_year'],
            $r['start_month'],
            $r['end_month'],
            $r['age'],
            $r['age_group'],
            $r['school_name'],
            $r['education_level'],
            $r['total_sessions'],
            $r['diag_1'],
            $r['diag_2'],
            $r['diag_3'],
            $r['diag_4'],
            $r['unlisted_comorbidity'],
            $r['current_stage']
        ], ';');
    }
}
fclose($out);
exit;
