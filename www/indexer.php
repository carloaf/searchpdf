<?php
// indexer.php: Versão final com a coluna 'total_pages'

echo "--- Iniciando o Indexador de PDFs (v5 - com total_pages) ---\n";

// --- Configurações lidas a partir das variáveis de ambiente do Docker ---
$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'searchpdf_db';
$dbUser = getenv('DB_USER') ?: 'searchpdf_user';
$dbPass = getenv('DB_PASS') ?: 'user_password';

// Permite configurar o diretório de uploads via variável de ambiente.
// Fallback para caminhos compatíveis com execução fora do container (ex.: cron local).
$uploadsDir = getenv('UPLOADS_DIR');
if (!$uploadsDir) {
    $projectRoot = dirname(__FILE__);
    // Quando executado dentro do container o caminho tradicional é /var/www/html/public/uploads
    $defaultContainerPath = '/var/www/html/public/uploads';
    if (is_dir($defaultContainerPath)) {
        $uploadsDir = $defaultContainerPath;
    } else {
        $uploadsDir = realpath($projectRoot . '/public/uploads') ?: $projectRoot . '/public/uploads';
    }
}

// Permite indexar um único arquivo (usado após o upload): php indexer.php --file=/caminho/arquivo.pdf
$singleFile = null;
if (PHP_SAPI === 'cli' && isset($argv)) {
    foreach (array_slice($argv, 1) as $arg) {
        if (strpos($arg, '--file=') === 0) {
            $singleFile = substr($arg, strlen('--file='));
        }
    }
}

/**
 * Função recursiva para buscar todos os arquivos PDF em um diretório e suas subpastas.
 * @param string $dir O diretório inicial.
 * @return array Uma lista de caminhos de arquivo.
 */
function getAllPdfs($dir) {
    $files = [];
    if (!is_dir($dir)) {
        return $files;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (strtolower($file->getExtension()) === 'pdf') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

/**
 * Verifica se um PDF precisa ser (re)indexado.
 * @param PDO $pdo
 * @param string $filePath
 * @param bool $force Ignora a checagem de data de modificação.
 * @return bool
 */
function pdfNeedsIndexing($pdo, $filePath, $force = false) {
    if ($force) {
        return true;
    }

    $stmtCheck = $pdo->prepare("SELECT last_indexed_at FROM pdf_index WHERE file_path = ?");
    $stmtCheck->execute([$filePath]);
    $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        return true;
    }

    $fileModTime = @filemtime($filePath);
    $indexedTime = strtotime($result['last_indexed_at']);

    return $fileModTime === false || $fileModTime > $indexedTime;
}

/**
 * Converte um caminho gravado no banco para o caminho real no sistema de arquivos.
 * O banco guarda caminhos absolutos do container (/var/www/html/public/uploads/...),
 * que podem diferir de $uploadsDir quando o indexador roda fora do container.
 * @param string $dbPath
 * @param string $uploadsDir
 * @return string|null
 */
function resolveDbPath($dbPath, $uploadsDir) {
    $uploadsDir = rtrim($uploadsDir, DIRECTORY_SEPARATOR);

    if (strpos($dbPath, $uploadsDir . DIRECTORY_SEPARATOR) === 0) {
        return $dbPath;
    }

    $marker = '/uploads/';
    $pos = strpos($dbPath, $marker);
    if ($pos === false) {
        return null;
    }

    return $uploadsDir . DIRECTORY_SEPARATOR . ltrim(substr($dbPath, $pos + strlen($marker)), '/');
}

/**
 * Remove do índice os registros cujos PDFs não existem mais no disco.
 * @param PDO $pdo
 * @param string $uploadsDir
 * @return int Quantidade de registros removidos.
 */
function removeOrphanedIndexEntries($pdo, $uploadsDir) {
    $stmt = $pdo->query("SELECT id, file_path FROM pdf_index");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deleteStmt = $pdo->prepare("DELETE FROM pdf_index WHERE id = ?");
    $removed = 0;

    foreach ($rows as $row) {
        $realPath = resolveDbPath($row['file_path'], $uploadsDir);
        if ($realPath === null || is_file($realPath)) {
            continue;
        }

        $deleteStmt->execute([$row['id']]);
        $removed++;
    }

    return $removed;
}

/**
 * Extrai o texto do PDF e grava/atualiza o registro em pdf_index.
 * @param PDO $pdo
 * @param string $filePath
 * @return array{success: bool, pages: int}
 */
function indexPdfFile($pdo, $filePath) {
    // Extrai o texto
    $command = "pdftotext -enc UTF-8 " . escapeshellarg($filePath) . " -";
    $text = shell_exec($command);

    // Extrai o número de páginas
    $totalPages = 0;
    $pdfInfoOutput = shell_exec("pdfinfo " . escapeshellarg($filePath));
    if (is_string($pdfInfoOutput) && preg_match('/Pages:\s*(\d+)/', $pdfInfoOutput, $matches)) {
        $totalPages = (int) $matches[1];
    }

    if ($text === null || empty(trim($text))) {
        return ['success' => false, 'pages' => 0];
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM pdf_index WHERE file_path = ?");
    $stmtCheck->execute([$filePath]);
    $existingId = $stmtCheck->fetchColumn();

    if ($existingId !== false) {
        $stmtUpdate = $pdo->prepare("UPDATE pdf_index SET content = ?, total_pages = ? WHERE file_path = ?");
        $stmtUpdate->execute([$text, $totalPages, $filePath]);
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO pdf_index (file_path, content, total_pages) VALUES (?, ?, ?)");
        $stmtInsert->execute([$filePath, $text, $totalPages]);
    }

    return ['success' => true, 'pages' => $totalPages];
}

// Conexão PDO, forçando o charset para utf8mb4
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Conexão com o banco de dados estabelecida com sucesso.\n";
} catch (PDOException $e) {
    echo "ERRO: Falha na conexão com o banco: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Cria a tabela com a estrutura correta, incluindo a coluna total_pages
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `pdf_index` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `file_path` TEXT NOT NULL,
          `content` LONGTEXT,
          `total_pages` INT(5) DEFAULT 0,
          `last_indexed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `file_path_unique` (`file_path`(255)),
          FULLTEXT KEY `content_fulltext` (`content`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "Tabela 'pdf_index' verificada/criada com a estrutura correta.\n";
} catch (PDOException $e) {
    echo "ERRO: Não foi possível criar/verificar a tabela 'pdf_index'.\n";
    echo "Detalhes: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Cria a tabela de log de buscas se não existir
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `search_log` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `search_term` VARCHAR(255) NOT NULL,
      `results_count` INT(11) NOT NULL,
      `searched_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
// --- FIM DA ADIÇÃO ---

echo "Tabelas 'pdf_index' e 'search_log' verificadas/criadas com sucesso.\n";

// Em modo de arquivo único valida o caminho e indexa apenas ele; caso contrário varre todo o diretório.
if ($singleFile !== null) {
    $realFile = realpath($singleFile);
    $realUploadsDir = realpath($uploadsDir);

    $isPdf = $realFile !== false
        && is_file($realFile)
        && strtolower(pathinfo($realFile, PATHINFO_EXTENSION)) === 'pdf';
    $insideUploads = $realUploadsDir !== false
        && strpos($realFile . DIRECTORY_SEPARATOR, rtrim($realUploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;

    if (!$isPdf || !$insideUploads) {
        echo "ERRO: Arquivo inválido para indexação: $singleFile\n";
        exit(1);
    }

    $files = [$realFile];
    echo "Modo de arquivo único: $realFile\n";
} else {
    $files = getAllPdfs($uploadsDir);
}

if (empty($files)) {
    echo "AVISO: Nenhum arquivo PDF encontrado em '$uploadsDir'.\n";
} else {
    echo "Encontrados " . count($files) . " arquivos PDF para processar.\n";
}

// Limpa registros de PDFs que foram removidos do disco (somente na varredura completa).
if ($singleFile === null && is_dir($uploadsDir)) {
    try {
        $orphansRemoved = removeOrphanedIndexEntries($pdo, $uploadsDir);
        if ($orphansRemoved > 0) {
            echo "Registros órfãos removidos: $orphansRemoved\n";
        }
    } catch (PDOException $e) {
        echo "AVISO: Não foi possível remover registros órfãos: " . $e->getMessage() . "\n";
    }
}

$indexedCount = 0;
$skippedCount = 0;
$failedCount = 0;

foreach ($files as $filePath) {
    try {
        if (!pdfNeedsIndexing($pdo, $filePath, $singleFile !== null)) {
            $skippedCount++;
            continue;
        }

        echo "Indexando: $filePath ... ";

        $result = indexPdfFile($pdo, $filePath);

        if (!$result['success']) {
            $failedCount++;
            echo "FALHA (não foi possível extrair conteúdo).\n";
            continue;
        }

        $indexedCount++;
        echo "OK ({$result['pages']} páginas).\n";
    } catch (PDOException $e) {
        $failedCount++;
        echo "FALHA (" . $e->getMessage() . ").\n";
    }
}

echo "\n--- Indexação Concluída ---\n";
echo "Arquivos indexados/atualizados: $indexedCount\n";
echo "Arquivos já atualizados (pulados): $skippedCount\n";
if ($failedCount > 0) {
    echo "Arquivos com falha: $failedCount\n";
}

?>