<?php
/**
 * Motor de Renderização de Formulários Clínicos
 * Mente em Equilíbrio — Psicóloga Gisele Silva Corrêa (CRP 05/40880)
 *
 * Suporta:
 * - Cadastro de Clientes (Contrato e NF)
 * - Anamnese Infância e Adolescência (179 itens)
 * - Anamnese Geral / Adulto (87 itens)
 */

function getFormSchemas(): array {
    static $schemas = null;
    if ($schemas === null) {
        $jsonPath = __DIR__ . '/form_schemas.json';
        if (file_exists($jsonPath)) {
            $schemas = json_decode(file_get_contents($jsonPath), true) ?: [];
            if (isset($schemas['anamnese_crianca']) && !isset($schemas['anamnese_infantil'])) {
                $schemas['anamnese_infantil'] = $schemas['anamnese_crianca'];
            }
        } else {
            $schemas = [];
        }
    }
    return $schemas;
}

function slugifyRowKey(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $map = [
        'á'=>'a', 'à'=>'a', 'ã'=>'a', 'â'=>'a', 'ä'=>'a',
        'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e',
        'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i',
        'ó'=>'o', 'ò'=>'o', 'õ'=>'o', 'ô'=>'o', 'ö'=>'o',
        'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u',
        'ç'=>'c', 'ñ'=>'n'
    ];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/i', '_', $text);
    return trim(substr($text, 0, 45), '_');
}

/**
 * Renderiza uma seção completa com seus itens
 */
function renderFormSection(array $section, int $sectionIndex, array $existingData, string $prefix, array $patient): string {
    $title = htmlspecialchars($section['title'] ?? '');
    $desc = htmlspecialchars($section['description'] ?? '');
    $items = $section['items'] ?? [];

    $html = '<div class="form-section-container space-y-4" x-show="showAll || currentSection === ' . $sectionIndex . '" x-cloak>';
    
    // Cabeçalho da Seção
    $html .= '<div class="bg-gradient-to-r from-blue-50 to-indigo-50/40 p-4 sm:p-5 rounded-2xl border border-blue-100 mb-4">';
    $html .= '<div class="flex items-center gap-3">';
    $html .= '<span class="w-7 h-7 rounded-full bg-blue-600 text-white font-bold text-xs flex items-center justify-center shadow-xs flex-shrink-0">' . ($sectionIndex + 1) . '</span>';
    $html .= '<h3 class="text-sm sm:text-base font-bold text-blue-950 uppercase tracking-wide leading-tight">' . $title . '</h3>';
    $html .= '</div>';
    if (!empty($desc)) {
        $html .= '<p class="text-xs text-slate-600 mt-2 pl-10 leading-relaxed text-justify">' . nl2br($desc) . '</p>';
    }
    $html .= '</div>';

    // Itens da Seção
    $html .= '<div class="space-y-4">';
    foreach ($items as $item) {
        $html .= renderFormItem($item, $existingData, $prefix, $patient);
    }
    $html .= '</div>';

    $html .= '</div>';
    return $html;
}

/**
 * Renderiza um item individual do formulário
 */
function renderFormItem(array $item, array $existingData, string $prefix = 'a', array $patient = []): string {
    $type = $item['type'] ?? 'text';
    $id = $item['id'] ?? ('q' . ($item['index'] ?? ''));
    $label = htmlspecialchars($item['label'] ?? '');
    $help = htmlspecialchars($item['help'] ?? '');
    $required = !empty($item['required']);
    $options = $item['options'] ?? [];
    $hasOther = !empty($item['has_other']);

    $inputName = ($prefix === '') ? $id : "{$prefix}[{$id}]";
    $otherName = ($prefix === '') ? "{$id}_outro" : "{$prefix}[{$id}_outro]";

    // Obtenção do valor preexistente
    $val = $existingData[$id] ?? null;

    // Se não tiver valor na anamnese/cadastro, verifica dados prévios do paciente
    if ($val === null) {
        if ($id === 'full_name' || $id === 'nome') $val = $patient['full_name'] ?? null;
        elseif ($id === 'cpf_nf' || $id === 'cpf') $val = $patient['cpf_nf'] ?? null;
        elseif ($id === 'email_nf' || $id === 'email') $val = $patient['email_nf'] ?? null;
        elseif ($id === 'birth_date' || $id === 'data_nasc') $val = $patient['birth_date'] ?? null;
        elseif ($id === 'age' || $id === 'idade') $val = $patient['age'] ?? null;
        elseif ($id === 'phone_whatsapp') $val = $patient['phone_whatsapp'] ?? null;
        elseif ($id === 'responsible_name') $val = $patient['responsible_name'] ?? null;
        elseif ($id === 'address_full') $val = $patient['address_full'] ?? null;
        elseif ($id === 'escola') $val = $patient['school_name'] ?? null;
        elseif ($id === 'serie_escolaridade' || $id === 'escolaridade') $val = $patient['education_level'] ?? null;
    }

    $otherVal = $existingData["{$id}_outro"] ?? '';

    $reqBadge = $required ? '<span class="text-rose-500 font-bold ml-1" title="Campo Obrigatório">*</span>' : '<span class="text-slate-400 text-[11px] font-normal ml-1.5">(opcional)</span>';

    $html = '';

    // ITEM DE INFORMAÇÃO / SUBSEÇÃO
    if ($type === 'info') {
        $html .= '<div class="bg-amber-50/70 border border-amber-200/90 rounded-2xl p-5 my-4 text-xs text-amber-950 shadow-2xs">';
        if (!empty($label)) {
            $html .= '<h4 class="font-bold uppercase tracking-wider text-xs text-amber-900 mb-2 flex items-center gap-2">';
            $html .= '<span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span> ' . $label;
            $html .= '</h4>';
        }
        if (!empty($help)) {
            $html .= '<div class="text-slate-700 leading-relaxed whitespace-pre-line text-justify">' . $help . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    $html .= '<div class="form-item-wrapper bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-2xs space-y-2 hover:border-blue-300 transition-colors" data-item-id="' . htmlspecialchars($id) . '">';

    // Label do item
    $html .= '<label class="block font-semibold text-slate-800 text-xs sm:text-sm leading-snug">';
    $html .= $label . $reqBadge;
    $html .= '</label>';

    // Texto de apoio/ajuda
    if (!empty($help)) {
        $html .= '<p class="text-slate-500 text-xs leading-relaxed">' . $help . '</p>';
    }

    // CAMPOS DE TEXTO CURTO / PARÁGRAFO / NÚMERO / DATA
    if ($type === 'text') {
        $typeAttr = 'text';
        $placeholder = '';
        if (str_contains(mb_strtolower($id), 'email')) {
            $typeAttr = 'email';
            $placeholder = 'exemplo@email.com';
        } elseif (str_contains(mb_strtolower($id), 'whatsapp') || str_contains(mb_strtolower($id), 'telefone')) {
            $placeholder = '(00) 00000-0000';
        } elseif (str_contains(mb_strtolower($id), 'cpf')) {
            $placeholder = '000.000.000-00';
        } elseif (str_contains(mb_strtolower($id), 'cep')) {
            $placeholder = '00000-000';
        } elseif ($id === 'age' || $id === 'idade') {
            $typeAttr = 'number';
            $placeholder = 'Ex: 12';
        }

        $html .= '<input type="' . $typeAttr . '" name="' . htmlspecialchars($inputName) . '" value="' . htmlspecialchars((string)($val ?? '')) . '" ' . ($required ? 'required' : '') . ' class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition" placeholder="' . htmlspecialchars($placeholder) . '">';
    } elseif ($type === 'textarea') {
        $html .= '<textarea name="' . htmlspecialchars($inputName) . '" rows="3" ' . ($required ? 'required' : '') . ' class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition" placeholder="Descreva aqui com exemplos ou detalhes...">' . htmlspecialchars((string)($val ?? '')) . '</textarea>';
    } elseif ($type === 'date') {
        $html .= '<input type="date" name="' . htmlspecialchars($inputName) . '" value="' . htmlspecialchars((string)($val ?? '')) . '" ' . ($required ? 'required' : '') . ' class="w-full sm:w-64 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition">';
    } elseif ($type === 'radio') {
        $isOutroSelected = ($hasOther && !empty($otherVal)) || ($hasOther && !empty($val) && !in_array($val, $options));
        $html .= '<div class="space-y-2 pt-1" x-data="{ isOutro: ' . ($isOutroSelected ? 'true' : 'false') . ' }">';
        $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs sm:text-sm">';
        foreach ($options as $optIdx => $opt) {
            $isSel = ($val === $opt);
            $radioId = "{$id}_opt_{$optIdx}";
            $html .= '<label for="' . $radioId . '" class="flex items-start gap-2.5 p-3 rounded-xl border border-slate-200 bg-slate-50/70 cursor-pointer hover:bg-blue-50/50 hover:border-blue-200 transition">';
            $html .= '<input type="radio" id="' . $radioId . '" name="' . htmlspecialchars($inputName) . '" value="' . htmlspecialchars($opt) . '" ' . ($isSel ? 'checked' : '') . ' ' . ($required ? 'required' : '') . ' @change="isOutro = false" class="mt-0.5 text-blue-600 focus:ring-blue-500">';
            $html .= '<span class="text-slate-800 leading-snug">' . htmlspecialchars($opt) . '</span>';
            $html .= '</label>';
        }

        if ($hasOther) {
            $outroRadioId = "{$id}_opt_outro";
            $html .= '<label for="' . $outroRadioId . '" class="flex items-start gap-2.5 p-3 rounded-xl border border-slate-200 bg-slate-50/70 cursor-pointer hover:bg-blue-50/50 hover:border-blue-200 transition">';
            $html .= '<input type="radio" id="' . $outroRadioId . '" name="' . htmlspecialchars($inputName) . '" value="__outro__" ' . ($isOutroSelected ? 'checked' : '') . ' ' . ($required ? 'required' : '') . ' @change="isOutro = true" class="mt-0.5 text-blue-600 focus:ring-blue-500">';
            $html .= '<span class="text-slate-800 font-medium">Outro (especificar)</span>';
            $html .= '</label>';
        }
        $html .= '</div>';

        if ($hasOther) {
            $html .= '<div x-show="isOutro" x-cloak class="pt-2">';
            $html .= '<input type="text" name="' . htmlspecialchars($otherName) . '" value="' . htmlspecialchars((string)$otherVal) . '" placeholder="Especifique aqui..." class="w-full border border-blue-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm bg-blue-50/30 text-slate-800 outline-none focus:ring-2 focus:ring-blue-500">';
            $html .= '</div>';
        }
        $html .= '</div>';
    } elseif ($type === 'checkbox') {
        $selectedList = is_array($val) ? $val : (is_string($val) && !empty($val) ? [$val] : []);
        $hasOutroCheck = in_array('__outro__', $selectedList) || !empty($otherVal);
        $html .= '<div class="space-y-2 pt-1" x-data="{ isOutro: ' . ($hasOutroCheck ? 'true' : 'false') . ' }">';
        $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs sm:text-sm">';
        foreach ($options as $optIdx => $opt) {
            $isSel = in_array($opt, $selectedList);
            $checkId = "{$id}_chk_{$optIdx}";
            $html .= '<label for="' . $checkId . '" class="flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200 bg-slate-50/70 cursor-pointer hover:bg-blue-50/50 hover:border-blue-200 transition">';
            $html .= '<input type="checkbox" id="' . $checkId . '" name="' . htmlspecialchars($inputName) . '[]" value="' . htmlspecialchars($opt) . '" ' . ($isSel ? 'checked' : '') . ' class="mt-0.5 rounded text-blue-600 focus:ring-blue-500">';
            $html .= '<span class="text-slate-800 leading-snug">' . htmlspecialchars($opt) . '</span>';
            $html .= '</label>';
        }

        if ($hasOther) {
            $outroChkId = "{$id}_chk_outro";
            $html .= '<label for="' . $outroChkId . '" class="flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200 bg-slate-50/70 cursor-pointer hover:bg-blue-50/50 hover:border-blue-200 transition">';
            $html .= '<input type="checkbox" id="' . $outroChkId . '" name="' . htmlspecialchars($inputName) . '[]" value="__outro__" ' . ($hasOutroCheck ? 'checked' : '') . ' @change="isOutro = $el.checked" class="mt-0.5 rounded text-blue-600 focus:ring-blue-500">';
            $html .= '<span class="text-slate-800 font-medium">Outro (especificar)</span>';
            $html .= '</label>';
        }
        $html .= '</div>';

        if ($hasOther) {
            $html .= '<div x-show="isOutro" x-cloak class="pt-2">';
            $html .= '<input type="text" name="' . htmlspecialchars($otherName) . '" value="' . htmlspecialchars((string)$otherVal) . '" placeholder="Especifique aqui os outros detalhes..." class="w-full border border-blue-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm bg-blue-50/30 text-slate-800 outline-none focus:ring-2 focus:ring-blue-500">';
            $html .= '</div>';
        }
        $html .= '</div>';
    } elseif ($type === 'select') {
        $html .= '<select name="' . htmlspecialchars($inputName) . '" ' . ($required ? 'required' : '') . ' class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition">';
        $html .= '<option value="">Selecione uma opção...</option>';
        foreach ($options as $opt) {
            $isSel = ($val === $opt);
            $html .= '<option value="' . htmlspecialchars($opt) . '" ' . ($isSel ? 'selected' : '') . '>' . htmlspecialchars($opt) . '</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'grid') {
        $html .= renderGridItem($item, $existingData, $prefix);
    }

    $html .= '</div>';
    return $html;
}

/**
 * Renderiza Matriz Grid (Tabela responsiva no desktop, cards no mobile)
 */
function renderGridItem(array $item, array $existingData, string $prefix = 'a'): string {
    $id = $item['id'] ?? ('q' . ($item['index'] ?? ''));
    $rows = $item['grid_rows'] ?? [];
    $cols = $item['grid_cols'] ?? ['Sim', 'Não', 'Desconheço'];
    $gridVal = $existingData[$id] ?? [];

    $html = '<div class="pt-2 space-y-3">';

    // TABELA DESKTOP
    $html .= '<div class="hidden sm:block overflow-x-auto rounded-xl border border-slate-200">';
    $html .= '<table class="w-full text-xs text-left text-slate-700 divide-y divide-slate-200">';
    $html .= '<thead class="bg-slate-100/80 font-bold text-slate-700">';
    $html .= '<tr>';
    $html .= '<th scope="col" class="py-3 px-4 text-xs font-semibold uppercase tracking-wider">Critério / Condição</th>';
    foreach ($cols as $col) {
        $html .= '<th scope="col" class="py-3 px-3 text-center text-xs font-semibold whitespace-nowrap">' . htmlspecialchars($col) . '</th>';
    }
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody class="divide-y divide-slate-100 bg-white">';

    foreach ($rows as $rIdx => $rowTitle) {
        $rKey = slugifyRowKey($rowTitle);
        $currRowVal = $gridVal[$rKey] ?? null;
        $rowBg = ($rIdx % 2 === 0) ? 'bg-white' : 'bg-slate-50/50';

        $html .= '<tr class="' . $rowBg . ' hover:bg-blue-50/40 transition">';
        $html .= '<td class="py-2.5 px-4 font-medium text-slate-800 text-xs leading-snug">' . htmlspecialchars($rowTitle) . '</td>';

        foreach ($cols as $cIdx => $col) {
            $isSel = ($currRowVal === $col);
            $radioName = ($prefix === '') ? "{$id}[{$rKey}]" : "{$prefix}[{$id}][{$rKey}]";
            $radioId = "grid_{$id}_{$rKey}_{$cIdx}";

            $html .= '<td class="py-2 px-3 text-center whitespace-nowrap">';
            $html .= '<label for="' . $radioId . '" class="inline-flex items-center justify-center p-1.5 cursor-pointer rounded-lg hover:bg-blue-100/60">';
            $html .= '<input type="radio" id="' . $radioId . '" name="' . htmlspecialchars($radioName) . '" value="' . htmlspecialchars($col) . '" ' . ($isSel ? 'checked' : '') . ' class="text-blue-600 focus:ring-blue-500 cursor-pointer">';
            $html .= '</label>';
            $html .= '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';

    // CARDS MOBILE (< 640px)
    $html .= '<div class="block sm:hidden space-y-2">';
    foreach ($rows as $rIdx => $rowTitle) {
        $rKey = slugifyRowKey($rowTitle);
        $currRowVal = $gridVal[$rKey] ?? null;
        $radioName = ($prefix === '') ? "{$id}[{$rKey}]" : "{$prefix}[{$id}][{$rKey}]";

        $html .= '<div class="p-3 rounded-xl border border-slate-200 bg-slate-50/60 space-y-2">';
        $html .= '<p class="text-xs font-semibold text-slate-800 leading-snug">' . htmlspecialchars($rowTitle) . '</p>';
        $html .= '<div class="flex flex-wrap gap-1.5 text-xs">';
        foreach ($cols as $cIdx => $col) {
            $isSel = ($currRowVal === $col);
            $mRadioId = "m_grid_{$id}_{$rKey}_{$cIdx}";
            $html .= '<label for="' . $mRadioId . '" class="flex-1 min-w-[70px] flex items-center justify-center gap-1 py-1.5 px-2 rounded-lg border border-slate-200 bg-white cursor-pointer hover:bg-blue-50">';
            $html .= '<input type="radio" id="' . $mRadioId . '" name="' . htmlspecialchars($radioName) . '" value="' . htmlspecialchars($col) . '" ' . ($isSel ? 'checked' : '') . ' class="text-blue-600 focus:ring-blue-500">';
            $html .= '<span class="text-[11px] font-medium text-slate-700">' . htmlspecialchars($col) . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '</div>';
    return $html;
}

/**
 * Renderiza o visualizador completo de respostas da Anamnese (para o painel da psicóloga)
 */
function renderAnamnesisAnswersViewer(array $answers, string $model, array $schema): string {
    $renderedKeys = ['_meta'];
    $html = '<div class="space-y-4 text-xs">';

    $sections = $schema['sections'] ?? [];

    foreach ($sections as $sIdx => $section) {
        $secTitle = htmlspecialchars($section['title'] ?? '');
        $secDesc = htmlspecialchars($section['description'] ?? '');
        $items = $section['items'] ?? [];

        $hasAnswers = false;
        $itemsHtml = '';

        foreach ($items as $item) {
            $id = $item['id'] ?? '';
            $type = $item['type'] ?? 'text';
            $label = htmlspecialchars($item['label'] ?? '');
            
            if ($type === 'info') continue;
            
            // Pula se for campo _exemplo (será renderizado acoplado ao domínio principal)
            if (str_ends_with($id, '_exemplo')) continue;

            $val = $answers[$id] ?? null;
            if ($val === null || $val === '' || (is_array($val) && empty($val))) {
                continue;
            }

            $hasAnswers = true;
            $renderedKeys[] = $id;

            // Checa se há write-in outro
            $otherVal = $answers["{$id}_outro"] ?? null;
            if ($otherVal) {
                $renderedKeys[] = "{$id}_outro";
            }

            // Checa se há exemplo associado
            $exemploVal = $answers["{$id}_exemplo"] ?? null;
            if ($exemploVal) {
                $renderedKeys[] = "{$id}_exemplo";
            }

            $itemsHtml .= '<div class="p-3.5 rounded-xl bg-slate-50/70 border border-slate-200/80 space-y-2">';
            $itemsHtml .= '<div class="flex items-start justify-between gap-2">';
            $itemsHtml .= '<strong class="text-slate-800 text-xs font-semibold leading-snug">' . $label . '</strong>';
            $itemsHtml .= '</div>';

            if ($type === 'grid') {
                $rows = $item['grid_rows'] ?? [];
                $cols = $item['grid_cols'] ?? ['Sim', 'Não', 'Desconheço'];
                $gridVal = is_array($val) ? $val : [];

                $positiveCount = 0;
                foreach ($gridVal as $gv) {
                    if (in_array($gv, ['Sim', 'Frequentemente', 'Sempre', 'Pai', 'Mãe'])) {
                        $positiveCount++;
                    }
                }

                $itemsHtml .= '<div x-data="{ onlyPositive: false }" class="space-y-2 pt-1">';
                $itemsHtml .= '<div class="flex items-center justify-between text-[11px] text-slate-500 pb-1.5 border-b border-slate-200/70">';
                $itemsHtml .= '<span>' . count($gridVal) . ' de ' . count($rows) . ' critérios avaliados</span>';
                if ($positiveCount > 0) {
                    $itemsHtml .= '<div class="flex items-center gap-2">';
                    $itemsHtml .= '<span class="bg-amber-100 text-amber-900 border border-amber-300 font-bold px-2 py-0.5 rounded-full text-[10px]">' . $positiveCount . ' afirmativos (atenção)</span>';
                    $itemsHtml .= '<button type="button" @click="onlyPositive = !onlyPositive" class="text-royal-700 hover:underline font-semibold">';
                    $itemsHtml .= '<span x-text="onlyPositive ? \'Ver todos\' : \'Filtrar afirmativos\'"></span>';
                    $itemsHtml .= '</button>';
                    $itemsHtml .= '</div>';
                }
                $itemsHtml .= '</div>';

                $itemsHtml .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-1.5 pt-1">';
                foreach ($rows as $rTitle) {
                    $rKey = slugifyRowKey($rTitle);
                    $ans = $gridVal[$rKey] ?? null;
                    if (!$ans) continue;

                    $isPositive = in_array($ans, ['Sim', 'Frequentemente', 'Sempre', 'Pai', 'Mãe']);
                    $badgeClass = match ($ans) {
                        'Sim' => 'bg-amber-100 text-amber-900 border-amber-300 font-bold',
                        'Frequentemente', 'Sempre' => 'bg-rose-100 text-rose-900 border-rose-300 font-bold',
                        'Não' => 'bg-slate-100 text-slate-500 border-slate-200',
                        'Desconheço' => 'bg-slate-50 text-slate-400 border-slate-200',
                        default => 'bg-blue-50 text-royal-700 border-blue-200 font-medium'
                    };

                    $itemsHtml .= '<div x-show="!onlyPositive || ' . ($isPositive ? 'true' : 'false') . '" class="flex items-center justify-between gap-2 p-2 rounded-lg bg-white border border-slate-200/70 text-xs">';
                    $itemsHtml .= '<span class="text-slate-700 leading-tight font-medium text-[11px]">' . htmlspecialchars($rTitle) . '</span>';
                    $itemsHtml .= '<span class="px-2 py-0.5 rounded-md border text-[11px] ' . $badgeClass . ' flex-shrink-0">' . htmlspecialchars($ans) . ($isPositive ? ' ⚠️' : '') . '</span>';
                    $itemsHtml .= '</div>';
                }
                $itemsHtml .= '</div>';
                $itemsHtml .= '</div>';

            } elseif ($type === 'checkbox') {
                $itemsHtml .= '<div class="flex flex-wrap gap-1.5 pt-1">';
                if (is_array($val)) {
                    foreach ($val as $optVal) {
                        if ($optVal === '__outro__') continue;
                        $itemsHtml .= '<span class="bg-blue-50 text-royal-700 border border-blue-200 px-2.5 py-1 rounded-lg text-xs font-medium">' . htmlspecialchars((string)$optVal) . '</span>';
                    }
                } else {
                    $itemsHtml .= '<span class="bg-blue-50 text-royal-700 border border-blue-200 px-2.5 py-1 rounded-lg text-xs font-medium">' . htmlspecialchars((string)$val) . '</span>';
                }
                if ($otherVal) {
                    $itemsHtml .= '<span class="bg-purple-50 text-purple-800 border border-purple-200 px-2.5 py-1 rounded-lg text-xs font-semibold">Outro: ' . htmlspecialchars((string)$otherVal) . '</span>';
                }
                $itemsHtml .= '</div>';

            } elseif ($type === 'radio' || $type === 'select') {
                $itemsHtml .= '<div class="pt-1">';
                $isPositive = in_array($val, ['Frequentemente', 'Sempre', 'Muito alto', 'Alto', 'Sim']);
                $badgeClass = $isPositive ? 'bg-amber-50 text-amber-900 border-amber-200 font-bold' : 'bg-blue-50 text-royal-800 border-blue-200 font-medium';
                
                $itemsHtml .= '<span class="inline-block px-3 py-1 rounded-lg border text-xs ' . $badgeClass . '">' . htmlspecialchars((string)$val) . '</span>';
                if ($otherVal) {
                    $itemsHtml .= '<span class="inline-block ml-2 px-3 py-1 rounded-lg border border-purple-200 bg-purple-50 text-purple-800 text-xs font-semibold">Outro: ' . htmlspecialchars((string)$otherVal) . '</span>';
                }
                $itemsHtml .= '</div>';

            } else {
                $itemsHtml .= '<div class="pt-1 text-slate-800 text-xs font-medium whitespace-pre-line leading-relaxed">';
                $itemsHtml .= nl2br(htmlspecialchars((string)$val));
                $itemsHtml .= '</div>';
            }

            // Se tiver exemplo clínico preenchido (comum na anamnese da infância)
            if ($exemploVal) {
                $itemsHtml .= '<div class="mt-2.5 p-3 rounded-xl bg-blue-50/70 border border-blue-200/80 text-xs text-slate-700 space-y-1">';
                $itemsHtml .= '<div class="flex items-center gap-1.5 font-bold text-royal-800 text-[11px]">';
                $itemsHtml .= '<span>📝</span> Exemplos Práticos Citados no Dia a Dia:';
                $itemsHtml .= '</div>';
                $itemsHtml .= '<p class="whitespace-pre-line leading-relaxed text-slate-800 pl-4 border-l-2 border-royal-300">' . nl2br(htmlspecialchars((string)$exemploVal)) . '</p>';
                $itemsHtml .= '</div>';
            }

            $itemsHtml .= '</div>';
        }

        if (!$hasAnswers) {
            continue;
        }

        $html .= '<details open class="group bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">';
        $html .= '<summary class="flex items-center justify-between p-4 font-semibold text-slate-800 bg-slate-50/80 hover:bg-slate-100/70 cursor-pointer select-none transition">';
        $html .= '<div class="flex items-center gap-2.5">';
        $html .= '<span class="w-6 h-6 rounded-full bg-royal-700 text-white font-bold text-xs flex items-center justify-center flex-shrink-0">' . ($sIdx + 1) . '</span>';
        $html .= '<span class="text-sm font-bold text-slate-900">' . $secTitle . '</span>';
        $html .= '</div>';
        $html .= '<span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>';
        $html .= '</summary>';

        $html .= '<div class="p-4 space-y-3 border-t border-slate-100">';
        if (!empty($secDesc)) {
            $html .= '<p class="text-slate-500 text-xs leading-relaxed italic mb-3">' . $secDesc . '</p>';
        }
        $html .= $itemsHtml;
        $html .= '</div>';
        $html .= '</details>';
    }

    // Campos restantes (não mapeados no schema ou de versões legadas)
    $extraKeys = array_diff(array_keys($answers), $renderedKeys);
    if (!empty($extraKeys)) {
        $extraHtml = '';
        foreach ($extraKeys as $k) {
            $extraVal = $answers[$k];
            if ($extraVal === null || $extraVal === '' || (is_array($extraVal) && empty($extraVal))) continue;
            
            $lbl = ucwords(str_replace('_', ' ', $k));
            $extraHtml .= '<div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200/70">';
            $extraHtml .= '<strong class="text-slate-600 block text-[11px]">' . htmlspecialchars($lbl) . '</strong>';
            $extraHtml .= '<div class="mt-1 text-slate-800 text-xs font-medium">';
            if (is_array($extraVal)) {
                $extraHtml .= '<div class="flex flex-wrap gap-1">';
                foreach ($extraVal as $kItem => $itemVal) {
                    $extraHtml .= '<span class="bg-slate-200 text-slate-700 px-2 py-0.5 rounded text-[11px]">' . (is_string($kItem) ? htmlspecialchars($kItem) . ': ' : '') . htmlspecialchars((string)$itemVal) . '</span>';
                }
                $extraHtml .= '</div>';
            } else {
                $extraHtml .= nl2br(htmlspecialchars((string)$extraVal));
            }
            $extraHtml .= '</div>';
            $extraHtml .= '</div>';
        }

        if (!empty($extraHtml)) {
            $html .= '<details class="group bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">';
            $html .= '<summary class="flex items-center justify-between p-4 font-semibold text-slate-700 bg-slate-50 cursor-pointer select-none">';
            $html .= '<div class="flex items-center gap-2">';
            $html .= '<span class="text-xs font-bold text-slate-500">📎</span>';
            $html .= '<span class="text-xs font-bold uppercase tracking-wider text-slate-600">Outras Informações Registradas</span>';
            $html .= '</div>';
            $html .= '<span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>';
            $html .= '</summary>';
            $html .= '<div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-2.5 border-t border-slate-100">' . $extraHtml . '</div>';
            $html .= '</details>';
        }
    }

    $html .= '</div>';
    return $html;
}

/**
 * Renderiza o resumo visual das respostas do Formulário de Cadastro do Cliente
 */
function renderClientFormAnswersViewer(array $cadJson): string {
    if (empty($cadJson)) return '';

    $html = '<div class="bg-gradient-to-r from-blue-50/80 via-white to-indigo-50/50 rounded-2xl border border-blue-200/80 p-5 shadow-2xs space-y-4 mb-5">';
    $html .= '<div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-blue-100">';
    $html .= '<div class="flex items-center gap-2.5">';
    $html .= '<span class="w-7 h-7 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center">✓</span>';
    $html .= '<div>';
    $html .= '<h4 class="font-bold text-slate-900 text-sm">Formulário de Cadastro Preenchido Online</h4>';
    $html .= '<p class="text-[11px] text-slate-500">Enviado pelo cliente em: <strong class="text-slate-700">' . htmlspecialchars($cadJson['data_envio'] ?? 'Data registrada') . '</strong></p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<span class="text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full">Sincronizado</span>';
    $html .= '</div>';

    $html .= '<div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">';
    
    // Card 1: Avaliando
    $html .= '<div class="p-3.5 rounded-xl bg-white border border-slate-200/70 space-y-1">';
    $html .= '<span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Dados do Avaliando</span>';
    $html .= '<p class="font-semibold text-slate-800 text-sm">' . htmlspecialchars($cadJson['full_name'] ?? ($cadJson['paciente_nome'] ?? 'Não informado')) . '</p>';
    $html .= '<p class="text-slate-600 text-xs">CPF: ' . htmlspecialchars($cadJson['cpf_nf'] ?? 'Não informado') . '</p>';
    $html .= '<p class="text-slate-600 text-xs">Nascimento: ' . (!empty($cadJson['birth_date']) ? date('d/m/Y', strtotime($cadJson['birth_date'])) : 'Não informado') . ' (' . htmlspecialchars((string)($cadJson['age'] ?? '')) . ' anos)</p>';
    $html .= '<p class="text-slate-600 text-xs">WhatsApp: ' . htmlspecialchars($cadJson['phone_whatsapp'] ?? 'Não informado') . '</p>';
    $html .= '</div>';

    // Card 2: Responsável Financeiro
    $html .= '<div class="p-3.5 rounded-xl bg-white border border-slate-200/70 space-y-1">';
    $html .= '<span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Responsável Financeiro & Contratual</span>';
    $html .= '<p class="font-semibold text-slate-800 text-sm">' . htmlspecialchars($cadJson['responsible_name'] ?? ($cadJson['resp_financeiro_nome'] ?? 'O próprio paciente')) . '</p>';
    $html .= '<p class="text-slate-600 text-xs">Vínculo: ' . htmlspecialchars($cadJson['resp_vinculo'] ?? ($cadJson['resp_tipo'] ?? 'Não informado')) . '</p>';
    if (!empty($cadJson['resp_vinculo_outro'])) {
        $html .= '<p class="text-slate-600 text-xs italic">Específico: ' . htmlspecialchars($cadJson['resp_vinculo_outro']) . '</p>';
    }
    $html .= '<p class="text-slate-600 text-xs">CPF: ' . htmlspecialchars($cadJson['resp_cpf'] ?? ($cadJson['cpf_nf'] ?? 'Não informado')) . '</p>';
    $html .= '<p class="text-slate-600 text-xs">E-mail NF: ' . htmlspecialchars($cadJson['email_nf'] ?? 'Não informado') . '</p>';
    $html .= '</div>';

    // Card 3: Demanda e Origem
    $html .= '<div class="p-3.5 rounded-xl bg-white border border-slate-200/70 space-y-1">';
    $html .= '<span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Demanda & Indicação</span>';
    $html .= '<p class="font-semibold text-royal-800 text-xs">' . htmlspecialchars($cadJson['tipo_atendimento'] ?? 'Avaliação Neuropsicológica') . '</p>';
    $html .= '<p class="text-slate-600 text-xs">Como conheceu: <strong class="text-slate-700">' . htmlspecialchars($cadJson['como_conheceu'] ?? 'Não informado') . '</strong></p>';
    if (!empty($cadJson['indicacao_detalhe'])) {
        $html .= '<p class="text-slate-600 text-xs italic">Indicação: ' . htmlspecialchars($cadJson['indicacao_detalhe']) . '</p>';
    }
    if (!empty($cadJson['address_full'])) {
        $html .= '<p class="text-slate-500 text-[11px] pt-1 border-t border-slate-100">📍 ' . htmlspecialchars($cadJson['address_full']) . '</p>';
    }
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</div>';
    return $html;
}
