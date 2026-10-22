<?php
/**
 * Cálculo de Indicadores Gerenciais, Gargalos por Processo e Estatísticas Epidemiológicas (2024-2026)
 */

function computeSystemMetrics(PDO $pdo): array {
    $patients = $pdo->query("SELECT * FROM patients ORDER BY code_number ASC, id ASC")->fetchAll();
    $leads = $pdo->query("SELECT * FROM leads ORDER BY id DESC")->fetchAll();

    $ageGroupsOrder = ['<6 anos', '6 a 11 anos', '12 a 17 anos', '18+anos', '50+anos'];
    $ageStats = [];
    foreach ($ageGroupsOrder as $ag) {
        $ageStats[$ag] = ['ate_dez25' => 0, 'jan_abr26' => 0, 'ano_2026' => 0, 'total' => 0];
    }

    $schoolStats = [];
    $diagStats = [];
    $stageCounts = [
        'vai_iniciar' => 0,
        'em_avaliacao' => 0,
        'para_devolutiva' => 0,
        'concluido' => 0
    ];

    $janAbrMonths = ['janeiro', 'fevereiro', 'março', 'abril'];

    foreach ($patients as $p) {
        $st = $p['current_stage'] ?? 'concluido';
        if (isset($stageCounts[$st])) {
            $stageCounts[$st]++;
        }

        $ag = $p['age_group'] ?: calculateAgeGroup($p['age'] !== null ? (int)$p['age'] : null);
        if ($ag && isset($ageStats[$ag])) {
            $ageStats[$ag]['total']++;
            $yr = (string)$p['reference_year'];
            $mIni = mb_strtolower(trim((string)$p['start_month']));

            if (str_contains($yr, '2024') || $yr === '2025' || str_contains($yr, '2025')) {
                // Na planilha original, os primeiros 48 registros compõem "ATÉ DEZ/25"
                if ((int)$p['code_number'] <= 48) {
                    $ageStats[$ag]['ate_dez25']++;
                }
            }
            if ($yr === '2026') {
                $ageStats[$ag]['ano_2026']++;
                if (in_array($mIni, $janAbrMonths, true) && (int)$p['code_number'] >= 49 && (int)$p['code_number'] <= 59) {
                    $ageStats[$ag]['jan_abr26']++;
                }
            }
        }

        if (!empty($p['school_name'])) {
            $sc = $p['school_name'];
            $schoolStats[$sc] = ($schoolStats[$sc] ?? 0) + 1;
        }

        foreach (['diag_1', 'diag_2', 'diag_3', 'diag_4'] as $dCol) {
            if (!empty($p[$dCol])) {
                $dVal = trim($p[$dCol]);
                $diagStats[$dVal] = ($diagStats[$dVal] ?? 0) + 1;
            }
        }
    }

    arsort($schoolStats);
    arsort($diagStats);

    $totalAteDez25 = array_sum(array_column($ageStats, 'ate_dez25')) ?: 1;
    $totalJanAbr26 = array_sum(array_column($ageStats, 'jan_abr26')) ?: 1;
    $totalGeralAge = array_sum(array_column($ageStats, 'total')) ?: 1;

    // Agrupamento para Visão 2 (Por Processos Geral): quantos pacientes ativos estão pendentes em cada tarefa
    $processMatrixStmt = $pdo->query("
        SELECT
            t.stage_group,
            t.task_code,
            t.task_title,
            t.responsible_role,
            t.sort_order,
            COUNT(t.id) as pending_count
        FROM patient_tasks t
        INNER JOIN patients p ON p.id = t.patient_id
        WHERE t.status != 'concluido' AND p.current_stage != 'concluido'
        GROUP BY t.stage_group, t.task_code, t.task_title, t.responsible_role, t.sort_order
        ORDER BY t.stage_group ASC, t.sort_order ASC
    ");
    $processBottlenecks = $processMatrixStmt->fetchAll();

    return [
        'patients' => $patients,
        'leads' => $leads,
        'stage_counts' => $stageCounts,
        'age_stats' => $ageStats,
        'total_ate_dez25' => $totalAteDez25,
        'total_jan_abr26' => $totalJanAbr26,
        'total_geral_age' => $totalGeralAge,
        'school_stats' => $schoolStats,
        'diag_stats' => $diagStats,
        'process_bottlenecks' => $processBottlenecks
    ];
}
