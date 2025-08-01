<?php
// indexer.php: Versão final com a coluna 'total_pages'

echo "--- Iniciando o Indexador de PDFs (v5 - com total_pages) ---\n";

// --- Configurações lidas a partir das variáveis de ambiente do Docker ---
$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'searchpdf_db';
$dbUser = getenv('DB_USER') ?: 'searchpdf_user';
$dbPass = getenv('DB_PASS') ?: 'user_password';
$uploadsDir = '/var/www/html/public/uploads';

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

$files = getAllPdfs($uploadsDir);
if (empty($files)) {
    echo "AVISO: Nenhum arquivo PDF encontrado em '$uploadsDir'.\n";
} else {
    echo "Encontrados " . count($files) . " arquivos PDF para processar.\n";
}

// Prepara as queries SQL para incluir total_pages
$stmtCheck = $pdo->prepare("SELECT last_indexed_at FROM pdf_index WHERE file_path = ?");
$stmtInsert = $pdo->prepare("INSERT INTO pdf_index (file_path, content, total_pages) VALUES (?, ?, ?)");
$stmtUpdate = $pdo->prepare("UPDATE pdf_index SET content = ?, total_pages = ? WHERE file_path = ?");

$indexedCount = 0;
$skippedCount = 0;

foreach ($files as $filePath) {
    $fileModTime = filemtime($filePath);
    $stmtCheck->execute([$filePath]);
    $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $needsIndexing = true;
    if ($result) {
        $indexedTime = strtotime($result['last_indexed_at']);
        if ($fileModTime <= $indexedTime) {
            $needsIndexing = false;
            $skippedCount++;
        }
    }

    if ($needsIndexing) {
        echo "Indexando: $filePath ... ";
        
        // Extrai o texto
        $command = "pdftotext -enc UTF-8 " . escapeshellarg($filePath) . " -";
        $text = shell_exec($command);
        
        // Extrai o número de páginas
        $totalPages = 0;
        $pdfInfoOutput = shell_exec("pdfinfo " . escapeshellarg($filePath));
        if (is_string($pdfInfoOutput) && preg_match('/Pages:\s*(\d+)/', $pdfInfoOutput, $matches)) {
            $totalPages = (int)$matches[1];
        }

        if ($text === null || empty(trim($text))) {
            echo "FALHA (não foi possível extrair conteúdo).\n";
            continue;
        }

        if ($result) {
            // A query UPDATE é (content = ?, total_pages = ?, file_path = ?), então a ordem é [texto, paginas, caminho]
            $stmtUpdate->execute([$text, $totalPages, $filePath]);
        } else {
            // A query INSERT é (file_path, content, total_pages), então a ordem é [caminho, texto, paginas]
            $stmtInsert->execute([$filePath, $text, $totalPages]);
        }
        $indexedCount++;
        echo "OK ($totalPages páginas).\n";
    }
}

echo "\n--- Indexação Concluída ---\n";
echo "Arquivos indexados/atualizados: $indexedCount\n";
echo "Arquivos já atualizados (pulados): $skippedCount\n";

?>