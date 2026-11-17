<?php

namespace Model;

/**
 * FilesModel class
 *
 * @author Augusto <carloafernandes@gmail.com>
 * @version 2.1 (Com ordenação e tradução de meses)
 * @license MIT
 */
class FilesModel 
{
    /**
     * Gera a estrutura de árvore de arquivos em HTML, com contagem e ordenação customizada.
     */
    public static function buildFileTreeHtml($dir, $allowed_extensions, array $options = [])
    {
        $dir = rtrim($dir, '/');

        $options['rootDir'] = $options['rootDir'] ?? $dir;
        $options['publicRoot'] = $options['publicRoot'] ?? rtrim(dirname($options['rootDir']), '/');

        if (!isset($options['publicPrefix'])) {
            $relativeRoot = str_replace($options['publicRoot'], '', $options['rootDir']);
            $options['publicPrefix'] = trim($relativeRoot, '/');
        }

        $rootDir = rtrim($options['rootDir'], '/');
        $publicRoot = rtrim($options['publicRoot'], '/');
        $publicPrefix = $options['publicPrefix'];
        $baseUrl = isset($options['baseUrl']) ? rtrim($options['baseUrl'], '/') : '';
        $downloadSecret = $options['downloadSecret'] ?? null;
        $downloadRoute = trim($options['downloadRoute'] ?? 'download', '/');

        $html = '<ul class="list-unstyled file-tree">';
        $items = is_dir($dir) ? scandir($dir) : [];
        
        $dirs = [];
        $files = [];
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $dirs[] = $item;
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (is_file($path) && in_array($ext, $allowed_extensions)) {
                    $files[] = $item;
                }
            }
        }
        
        // Dicionário para traduzir e ordenar os meses (chaves normalizadas: minúsculas e sem acento)
        $monthMap = [
            'jan' => [1, 'Janeiro'], 'janeiro' => [1, 'Janeiro'],
            'fev' => [2, 'Fevereiro'], 'fevereiro' => [2, 'Fevereiro'],
            'mar' => [3, 'Março'], 'marco' => [3, 'Março'],
            'abr' => [4, 'Abril'], 'abril' => [4, 'Abril'],
            'mai' => [5, 'Maio'], 'maio' => [5, 'Maio'],
            'jun' => [6, 'Junho'], 'junho' => [6, 'Junho'],
            'jul' => [7, 'Julho'], 'julho' => [7, 'Julho'],
            'ago' => [8, 'Agosto'], 'agosto' => [8, 'Agosto'],
            'set' => [9, 'Setembro'], 'setembro' => [9, 'Setembro'],
            'out' => [10, 'Outubro'], 'outubro' => [10, 'Outubro'],
            'nov' => [11, 'Novembro'], 'novembro' => [11, 'Novembro'],
            'dez' => [12, 'Dezembro'], 'dezembro' => [12, 'Dezembro']
        ];

        // Função de comparação customizada para ordenar as pastas
        usort($dirs, function($a, $b) use ($monthMap) {
            $aMonth = self::getMonthInfo($a, $monthMap);
            $bMonth = self::getMonthInfo($b, $monthMap);

            // Se ambos são meses, ordena por número do mês
            if ($aMonth !== null && $bMonth !== null) {
                return $aMonth[0] <=> $bMonth[0];
            }

            // Meses vêm antes dos demais diretórios (garante ordenação total e estável)
            if ($aMonth !== null) {
                return -1;
            }
            if ($bMonth !== null) {
                return 1;
            }
            
            // Extrai anos de strings como "BI 2025", "2025", etc.
            $aYear = null;
            $bYear = null;
            
            if (is_numeric($a)) {
                $aYear = (int)$a;
            } elseif (preg_match('/\b(\d{4})\b/', $a, $matches)) {
                $aYear = (int)$matches[1];
            }
            
            if (is_numeric($b)) {
                $bYear = (int)$b;
            } elseif (preg_match('/\b(\d{4})\b/', $b, $matches)) {
                $bYear = (int)$matches[1];
            }
            
            // Se ambos contêm anos, ordena em ordem decrescente (mais recente primeiro)
            if ($aYear !== null && $bYear !== null) {
                return ($bYear <=> $aYear) ?: strnatcasecmp($a, $b); // 2025, 2024, 2023...
            }
            
            // Ordenação alfabética para o resto
            return strnatcasecmp($a, $b);
        });

        // Ordena os arquivos alfabeticamente
        natcasesort($files);

        $totalFileCount = count($files);

        // Renderiza as pastas primeiro
        foreach ($dirs as $folder) {
            $path = "$dir/$folder";
            
            $subTreeResult = self::buildFileTreeHtml($path, $allowed_extensions, $options);
            $subTreeHtml = $subTreeResult['html'];
            $subTreeCount = $subTreeResult['count'];
            
            $totalFileCount += $subTreeCount;

            $badge = ($subTreeCount > 0) 
                ? '<span class="badge bg-secondary rounded-pill ms-auto">' . $subTreeCount . '</span>' 
                : '';

            // Traduz o nome da pasta se for um mês
            $folderName = htmlspecialchars($folder);
            $folderMonth = self::getMonthInfo($folder, $monthMap);
            if ($folderMonth !== null) {
                $folderName = htmlspecialchars($folderMonth[1]);
            }

            $html .= '<li>';
            $html .= '  <div class="folder-item d-flex align-items-center collapsed">';
            $html .= '    <i class="fas fa-chevron-right me-2 folder-toggle"></i>';
            $html .= '    <input type="checkbox" class="form-check-input me-2 folder-check">';
            $html .= '    <i class="fas fa-folder folder-icon"></i>';
            $html .= '    <span class="folder-name">' . $folderName . '</span>';
            $html .=      $badge;
            $html .= '  </div>';
            $html .= $subTreeHtml;
            $html .= '</li>';
        }

        // Renderiza os arquivos depois
        foreach ($files as $file) {
            $path = "$dir/$file";
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            $relativePath = $path;
            $rootPrefix = $rootDir . '/';
            if (str_starts_with($path, $rootPrefix)) {
                $relativePath = substr($path, strlen($rootPrefix));
            }
            $relativePath = ltrim($relativePath, '/');

            $publicSegments = [];
            if ($publicPrefix !== '') {
                $publicSegments[] = $publicPrefix;
            }
            if ($relativePath !== '') {
                $publicSegments[] = $relativePath;
            }
            $publicRelativePath = implode('/', $publicSegments);

            $downloadToken = null;
            if (!empty($downloadSecret) && $relativePath !== '') {
                $downloadToken = DownloadToken::encode($relativePath, $downloadSecret);
            }

            $linkHref = '#';
            $linkExtraAttrs = ' role="button" tabindex="0"';
            $downloadAttributes = '';

            if ($downloadToken) {
                $downloadAttributes = ' data-download-token="' . htmlspecialchars($downloadToken) . '" data-download-route="' . htmlspecialchars($downloadRoute) . '"';
            } else {
                $linkExtraAttrs = ' target="_blank" rel="noopener"';
                $linkHref = $baseUrl !== ''
                    ? $baseUrl . '/' . ltrim($publicRelativePath, '/')
                    : '/' . ltrim($publicRelativePath, '/');
            }

            $html .= '<li>';
            $html .= '  <div class="file-item d-flex align-items-center" data-ext="' . $ext . '"' . $downloadAttributes . '>';
            $html .= '    <input type="checkbox" class="form-check-input me-2 file-check" data-path="' . htmlspecialchars($path) . '">';
            $html .= '    <i class="fas fa-file-pdf file-icon"></i>';
            $html .= '    <a href="' . htmlspecialchars($linkHref) . '" class="file-name"' . $linkExtraAttrs . ' aria-label="Baixar ' . htmlspecialchars($file) . '">' . htmlspecialchars($file) . '</a>';
            $html .= '  </div>';
            $html .= '</li>';
        }

        $html .= '</ul>';

        return ['html' => $html, 'count' => $totalFileCount];
    }

    /**
     * Normaliza o nome de uma pasta de mês para comparação, ignorando acentos e diferenças de caixa.
     * Necessário porque nem todas as pastas usam a mesma grafia (ex.: "Março" e "Marco").
     */
    public static function normalizeMonthKey($name)
    {
        return strtr(mb_strtolower(trim($name), 'UTF-8'), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
    }

    /**
     * Identifica um diretório de mês do calendário.
     *
     * @return array{0: int, 1: string}|null [número do mês, nome em português] ou null se não for mês
     */
    private static function getMonthInfo($folderName, array $monthMap)
    {
        return $monthMap[self::normalizeMonthKey($folderName)] ?? null;
    }

    /**
     * Busca por um texto no índice do banco de dados e formata o resultado.
     */
    public static function searchPDFFromIndex($searchTerm, $selectedFiles, $total_words, $ignore_case, $exact_match, $settings)
    {
        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbName = getenv('DB_NAME') ?: 'searchpdf_db';
        $dbUser = getenv('DB_USER') ?: 'searchpdf_user';
        $dbPass = getenv('DB_PASS') ?: 'user_password';

        try {
            $pdo = new \PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            return json_encode(['error' => 'Erro de conexão com o banco de dados.']);
        }

        $absolutePaths = $selectedFiles;

        if (empty($absolutePaths)) {
            return json_encode([]);
        }

        $queryTerm = $searchTerm;
        if ($exact_match) { $queryTerm = '"' . $queryTerm . '"'; }

        $sql = "SELECT file_path, content, total_pages FROM pdf_index WHERE MATCH(content) AGAINST(? IN BOOLEAN MODE)";
        
        $placeholders = implode(',', array_fill(0, count($absolutePaths), '?'));
        $sql .= " AND file_path IN ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $params = array_merge([$queryTerm], $absolutePaths);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // --- INÍCIO DO CÓDIGO DE LOGGING ---
        try {
            $logStmt = $pdo->prepare("INSERT INTO search_log (search_term, results_count) VALUES (?, ?)");
            $logStmt->execute([$searchTerm, count($results)]);
        } catch (\PDOException $e) {
            // Se o logging falhar, não quebra a aplicação, apenas registra o erro no log do servidor
            error_log("Falha ao registrar a busca no log: " . $e->getMessage());
        }

        $formattedResults = [];
        $url_base_from_settings = $settings['url_base'];

        foreach ($results as $index => $row) {
            $filePath = $row['file_path'];
            $content = $row['content'];
            $totalPages = $row['total_pages'];
            $filename = basename($filePath);
            
            $relativePath = str_replace('/var/www/html/public/', '', $filePath);
            $fileUrl = rtrim($url_base_from_settings, '/') . '/' . $relativePath;

            $pos = $ignore_case ? stripos($content, $searchTerm) : strpos($content, $searchTerm);
            if ($pos !== false) {
                $start = max(0, $pos - 150);
                $snippet = substr($content, $start, 300);
                
                $pageNumberText = 'Pág. ?';
                if (preg_match('/(Pag|Pág|página)\s*(nº|\.)?\s*(\d+)/i', $snippet, $pageMatches)) {
                    $pageNumberText = 'Pág. ' . $pageMatches[3];
                }

                $snippet = '... ' . htmlspecialchars(trim(preg_replace('/\s+/', ' ', $snippet))) . ' ...';
                $pattern = '/' . preg_quote($searchTerm, '/') . '/i';
                $snippet = preg_replace_callback($pattern, function($matches) {
                    // Agora gera apenas a classe, o estilo será controlado pelo CSS
                    return '<span class="highlight">' . htmlspecialchars($matches[0]) . '</span>';
                }, $snippet);

                $formattedResults[] = [
                    'index' => $index + 1,
                    'filename' => '<span class="filename-center">' . $filename . " ($totalPages páginas)" . '</span>',
                    'path_file' => $fileUrl,
                    'page' => $pageNumberText,
                    'text' => $snippet
                ];
                usort($formattedResults, function($a, $b) {
                    return strnatcasecmp($b['filename'], $a['filename']);
                });
            }
        }
        return json_encode($formattedResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}