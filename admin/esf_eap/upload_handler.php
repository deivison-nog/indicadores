<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/auth/check_auth.php';

// Admin only — POST only
if ($currentUser['type'] !== 'admin') {
    header('Location: ' . BASE_URL . 'user/view.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'admin/esf_eap/mais_acesso.php');
    exit;
}

$adminId    = $currentUser['admin_id'];
$redirectTo = BASE_URL . 'admin/esf_eap/mais_acesso.php';

// ── Helper: flash + redirect ──
function flashAndRedirect(string $msg, string $type, string $url): never
{
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type']    = $type;
    header('Location: ' . $url);
    exit;
}

// ── 1. Validate competency ──
$competency = trim($_POST['competency'] ?? '');
if (!preg_match('/^\d{2}\/\d{4}$/', $competency)) {
    flashAndRedirect('Competência inválida. Use o formato MM/AAAA (ex: 01/2024).', 'danger', $redirectTo);
}

// ── 2. Validate uploaded file ──
if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o tamanho permitido pelo servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o tamanho permitido pelo formulário.',
        UPLOAD_ERR_PARTIAL    => 'O upload foi parcial. Tente novamente.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo.',
        UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão PHP.',
    ];
    $errCode = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errMsg  = $uploadErrors[$errCode] ?? 'Erro desconhecido no upload.';
    flashAndRedirect($errMsg, 'danger', $redirectTo);
}

$uploadedTmp  = $_FILES['csv_file']['tmp_name'];
$originalName = basename($_FILES['csv_file']['name']);

// Basic extension check
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    flashAndRedirect('O arquivo deve ter extensão .csv.', 'danger', $redirectTo);
}

// File size limit: 10 MB
if ($_FILES['csv_file']['size'] > 10 * 1024 * 1024) {
    flashAndRedirect('Arquivo muito grande. Limite: 10 MB.', 'danger', $redirectTo);
}

// ── 3. Ensure uploads directory exists ──
$uploadsDir = UPLOADS_PATH . '/esf_eap';
if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true)) {
    flashAndRedirect('Não foi possível criar o diretório de uploads. Verifique as permissões.', 'danger', $redirectTo);
}

// ── 4. Build destination filename ──
$competencySafe = str_replace('/', '-', $competency);   // e.g. 01-2024
$safeOriginal   = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', $originalName);
$destFilename   = $adminId . '_' . $competencySafe . '_' . $safeOriginal;
$destPath       = $uploadsDir . '/' . $destFilename;

// ── 5. Read & parse CSV ──
$content = file_get_contents($uploadedTmp);
if ($content === false) {
    flashAndRedirect('Não foi possível ler o arquivo enviado.', 'danger', $redirectTo);
}

// Strip UTF-8 BOM
$bom = "\xEF\xBB\xBF";
if (str_starts_with($content, $bom)) {
    $content = substr($content, 3);
}

// Normalise line endings
$content = str_replace(["\r\n", "\r"], "\n", $content);
$lines   = explode("\n", trim($content));

if (count($lines) < 2) {
    flashAndRedirect('O arquivo CSV está vazio ou sem dados.', 'danger', $redirectTo);
}

// ── 6. Detect separator ──
function detectSeparator(string $headerLine): string
{
    $semiCount  = substr_count($headerLine, ';');
    $commaCount = substr_count($headerLine, ',');
    return $semiCount >= $commaCount ? ';' : ',';
}

// ── 7. Find header row ──
// More-specific aliases MUST appear before less-specific ones that are
// substrings of them (e.g. 'consulta agendada programada' before 'consulta agendada').
$colAliases = [
    'equipe'                       => ['equipe', 'nome da equipe', 'nome equipe'],
    'categoria'                    => ['categoria profissional', 'categoria', 'profissional'],
    'atendimento_urgencia'         => ['atendimento de urgência', 'atendimento de urgencia', 'urgência', 'urgencia'],
    'consulta_agendada_programada' => ['consulta agendada programada', 'consulta agendada programada / cuidado continuado', 'cuidado continuado'],
    'consulta_agendada'            => ['consulta agendada'],
    'consulta_no_dia'              => ['consulta no dia'],
    'total'                        => ['totais', 'total'],
];

$headerRow = -1;
$headerMap = [];
// Detect separator from the actual header row (the one that contains 'equipe'
// and 'categoria') rather than from $lines[0] which may be a metadata row.
$separator = ';'; // fallback

// e-SUS APS exports use a two-row header:
//   row A  ;;Tipo de Atendimento;Atendimento de Urgência;Consulta Agendada;…
//   row B  Equipe;Categoria Profissional;;
// We need to merge both rows to map all fields correctly.
// $prevLineIdx tracks the last non-empty, non-header line seen before the
// "Equipe / Categoria Profissional" row (row B above), which is row A.
$prevLineIdx = -1;

foreach ($lines as $lineIdx => $line) {
    if (trim($line) === '') {
        continue;
    }

    $lineLower = mb_strtolower($line);
    if (!str_contains($lineLower, 'equipe') || !str_contains($lineLower, 'categoria')) {
        $prevLineIdx = $lineIdx;
        continue;
    }

    // This line is the "equipe+categoria" header row — use it for separator detection.
    $separator = detectSeparator($line);
    $cols      = str_getcsv($line, $separator);
    $colsNorm  = array_map(fn($c) => mb_strtolower(trim($c)), $cols);

    $headerRow = $lineIdx;

    // If the previous non-empty, non-header row exists, merge its non-empty
    // cells into $colsNorm at the same column index.  This covers the common
    // e-SUS APS layout where numeric sub-column labels (row A) live on the row
    // immediately above the "Equipe / Categoria Profissional" row (row B).
    if ($prevLineIdx !== -1) {
        $prevCols     = str_getcsv($lines[$prevLineIdx], $separator);
        $prevColsNorm = array_map(fn($c) => mb_strtolower(trim($c)), $prevCols);
        foreach ($prevColsNorm as $idx => $prevCell) {
            if ($prevCell !== '' && ($colsNorm[$idx] ?? '') === '') {
                $colsNorm[$idx] = $prevCell;
            }
        }
    }

    // Map field → column index.
    // $usedCols prevents two fields from claiming the same column index, which
    // would happen when a shorter alias (e.g. 'consulta agendada') is a
    // substring of a longer column name ('consulta agendada programada').
    $usedCols = [];
    foreach ($colAliases as $field => $aliases) {
        foreach ($colsNorm as $idx => $colName) {
            if (isset($usedCols[$idx])) {
                continue;
            }
            foreach ($aliases as $alias) {
                if (str_contains($colName, $alias)) {
                    $headerMap[$field] = $idx;
                    $usedCols[$idx]    = true;
                    break 2;
                }
            }
        }
    }
    break;
}

if ($headerRow === -1) {
    flashAndRedirect(
        'Cabeçalho não encontrado no CSV. Certifique-se de exportar o relatório correto do e-SUS APS.',
        'danger',
        $redirectTo
    );
}

// Verify required columns (total/totais is optional — computed from row values if absent)
$requiredFields = ['equipe', 'categoria'];
foreach ($requiredFields as $f) {
    if (!isset($headerMap[$f])) {
        flashAndRedirect(
            "Coluna obrigatória \"{$f}\" não encontrada no CSV. Verifique o arquivo.",
            'danger',
            $redirectTo
        );
    }
}

// ── 8. Parse data rows ──
$parsedRows   = [];
$currentEquipe = '';
$skippedRows  = 0;

for ($i = $headerRow + 1; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    if ($line === '') {
        continue;
    }

    $cols = str_getcsv($line, $separator);

    // Pad columns to avoid undefined offset
    while (count($cols) <= max($headerMap)) {
        $cols[] = '';
    }

    // Team name — may be empty if continuation row
    $equipeVal = trim($cols[$headerMap['equipe']] ?? '');
    if ($equipeVal !== '') {
        $currentEquipe = $equipeVal;
    }

    if ($currentEquipe === '') {
        $skippedRows++;
        continue;
    }

    // Categoria — must be Médico or Enfermeiro
    $categoriaRaw = trim($cols[$headerMap['categoria']] ?? '');
    $categoriaKey = mb_strtolower($categoriaRaw, 'UTF-8');

    // Normalise accented chars for comparison (portable replacement map)
    $accentMap = [
        'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ẽ' => 'e',
        'á' => 'a', 'â' => 'a', 'à' => 'a', 'ã' => 'a',
        'í' => 'i', 'î' => 'i', 'ì' => 'i',
        'ó' => 'o', 'ô' => 'o', 'ò' => 'o', 'õ' => 'o',
        'ú' => 'u', 'û' => 'u', 'ù' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];
    $categoriaNorm = strtr($categoriaKey, $accentMap);
    if (!str_contains($categoriaNorm, 'medico') && !str_contains($categoriaNorm, 'enfermeiro')) {
        // Could be a summary row or unsupported category
        $skippedRows++;
        continue;
    }

    $getInt = function (string $field) use ($cols, $headerMap): int {
        if (!isset($headerMap[$field])) {
            return 0;
        }
        $v = preg_replace('/[^0-9]/', '', $cols[$headerMap[$field]] ?? '');
        return $v !== '' ? (int) $v : 0;
    };

    $parsedRows[] = [
        'equipe'                       => $currentEquipe,
        'categoria'                    => $categoriaRaw,
        'atendimento_urgencia'         => $getInt('atendimento_urgencia'),
        'consulta_agendada'            => $getInt('consulta_agendada'),
        'consulta_agendada_programada' => $getInt('consulta_agendada_programada'),
        'consulta_no_dia'              => $getInt('consulta_no_dia'),
        'total'                        => isset($headerMap['total'])
            ? $getInt('total')
            : ($getInt('atendimento_urgencia') + $getInt('consulta_agendada') + $getInt('consulta_agendada_programada') + $getInt('consulta_no_dia')),
    ];
}

if (empty($parsedRows)) {
    flashAndRedirect(
        'Nenhum dado válido (Médico/Enfermeiro) encontrado no CSV. Verifique o arquivo.',
        'danger',
        $redirectTo
    );
}

// ── 9. Check for existing import ──
try {
    $pdo = Database::getInstance();

    $stmt = $pdo->prepare(
        'SELECT id FROM competency_imports
         WHERE admin_id = ? AND indicator_key = ? AND competency = ?
         LIMIT 1'
    );
    $stmt->execute([$adminId, 'esf_eap_mais_acesso', $competency]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        // Warn user unless they already confirmed overwrite
        $confirmOverwrite = trim($_POST['confirm_overwrite'] ?? '');
        if ($confirmOverwrite !== '1') {
            $_SESSION['flash_message'] = 'Já existe uma importação para a competência ' . htmlspecialchars($competency) . '. Para substituir os dados, envie o arquivo novamente e confirme abaixo.';
            $_SESSION['flash_type']    = 'warning';
            $_SESSION['overwrite_competency'] = $competency;
            header('Location: ' . $redirectTo);
            exit;
        }

        // Overwrite confirmed: delete existing data then re-import
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('DELETE FROM mais_acesso_data WHERE import_id = ?');
        $stmt->execute([$existing]);

        $stmt = $pdo->prepare('DELETE FROM competency_imports WHERE id = ?');
        $stmt->execute([$existing]);

        $pdo->commit();
    }

    // ── 10. Move file to uploads ──
    if (!move_uploaded_file($uploadedTmp, $destPath)) {
        flashAndRedirect('Falha ao salvar o arquivo no servidor.', 'danger', $redirectTo);
    }

    // ── 11. Insert import record + rows ──
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO competency_imports (admin_id, indicator_key, competency, filename, filepath)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$adminId, 'esf_eap_mais_acesso', $competency, $originalName, $destPath]);
    $importId = (int) $pdo->lastInsertId();

    $insertRow = $pdo->prepare(
        'INSERT INTO mais_acesso_data
            (import_id, admin_id, competency, equipe, categoria,
             atendimento_urgencia, consulta_agendada, consulta_agendada_programada,
             consulta_no_dia, total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($parsedRows as $row) {
        $insertRow->execute([
            $importId,
            $adminId,
            $competency,
            $row['equipe'],
            $row['categoria'],
            $row['atendimento_urgencia'],
            $row['consulta_agendada'],
            $row['consulta_agendada_programada'],
            $row['consulta_no_dia'],
            $row['total'],
        ]);
    }

    $pdo->commit();

    $rowCount = count($parsedRows);
    flashAndRedirect(
        "Importação concluída com sucesso! {$rowCount} registros importados para a competência {$competency}.",
        'success',
        $redirectTo . '?competency=' . urlencode($competency)
    );

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Remove uploaded file if DB failed
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    flashAndRedirect('Erro ao salvar no banco de dados: ' . $e->getMessage(), 'danger', $redirectTo);
}
