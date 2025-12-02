<?php

/**
 * Gera dinamicamente uma lista de arquivos pesquisáveis
 *
 * @author Augusto <carloafernandes@gmail.com>
 * @copyright Public domain, 11 D Sup
 * @license MIT
 * @version 2.0 (Modernizado para Slim 4 e com rota /iframe)
 */

use Slim\Factory\AppFactory;
use Slim\Psr7\Stream;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

// Carrega o autoload do Composer
require __DIR__ . '/../vendor/autoload.php';

// Carrega as configurações
if (!file_exists(__DIR__ . '/../config/settings.php')) {
    die('Nenhum arquivo config/settings.php foi encontrado.');
}
require __DIR__ . '/../config/settings.php';

// Cria a instância da aplicação
$app = AppFactory::create();

// Define o caminho base da aplicação
$app->setBasePath($settings['path_relative']);

// Adiciona o Middleware de Configurações (Settings) com a sintaxe correta do Slim 4
$app->add(Libs\SettingsMiddleware::class);

// Adiciona o Middleware do Twig (para renderizar as views)
$twig = Twig::create(__DIR__ . '/../src/view', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

// ===================================================================
// ROTAS (com a sintaxe correta do Slim 4)
// ===================================================================

// Rota principal (para acesso direto)
$app->get('/', \Controller\PanelController::class);

// Rota para a versão "limpa" da aplicação (para AJAX/iFrame)
$app->get('/iframe', function ($request, $response) {
    $view = Twig::fromRequest($request);
    $settings = $request->getAttribute('settings');

    // Chama o novo método para gerar o HTML da árvore de arquivos e a contagem
    $treeResult = \Model\FilesModel::buildFileTreeHtml(
        $settings['directory_files'], 
        $settings['allowed_extensions'],
        [
            'baseUrl' => $settings['url_base'] ?? '',
            'rootDir' => $settings['directory_files'],
            'downloadSecret' => $settings['download_secret'] ?? null,
            'downloadRoute' => $settings['download_route'] ?? 'download',
        ]
    );
    
    // Extrai o HTML do resultado
    $treeHtml = $treeResult['html'];

    // Renderiza o template 'iframe.twig', passando o HTML bruto
    return $view->render($response, 'iframe.twig', [
        'treeHtml' => $treeHtml,
        'url_base' => $settings['url_base'],
        'lang' => $settings['lang'],
        // Adicione outras variáveis que seu panel.twig (incluído no iframe.twig) possa precisar
        'panel_variables' => [] 
    ]);
});

// --- NOVA ROTA PARA ESTATÍSTICAS ---
$app->get('/stats', function ($request, $response) {
    $stats = ['total' => 0, 'week' => 0, 'last_file' => 'Nenhum', 'max_number' => 0];
    try {
        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbName = getenv('DB_NAME') ?: 'searchpdf_db';
        $dbUser = getenv('DB_USER') ?: 'searchpdf_user';
        $dbPass = getenv('DB_PASS') ?: 'user_password';
        $pdo = new \PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);

        $stats['total'] = $pdo->query("SELECT COUNT(*) FROM search_log")->fetchColumn();
        $stats['week'] = $pdo->query("SELECT COUNT(*) FROM search_log WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        
        // Verificar se a tabela tem algum arquivo
        $hasFiles = $pdo->query("SELECT COUNT(*) FROM pdf_index")->fetchColumn();
        
        if ($hasFiles > 0) {
            try {
                // Buscar o maior número de documento (padrão O_XXX) apenas do ano atual
                $maxNumber = 0;
                $maxNumberFile = '';
                $currentYear = date('Y');
                
                // Consulta arquivos do ano atual (tanto no diretório YYYY/ quanto em BI YYYY/)
                $filesQuery = $pdo->query("SELECT file_path FROM pdf_index WHERE (file_path LIKE '/var/www/html/public/uploads/$currentYear/%' OR file_path LIKE '/var/www/html/public/uploads/BI $currentYear/%')");
                
                while ($row = $filesQuery->fetch(\PDO::FETCH_ASSOC)) {
                    $filePath = $row['file_path'];
                    if (preg_match('/O_(\d{3})/', $filePath, $matches)) {
                        $number = (int)$matches[1];
                        if ($number > $maxNumber) {
                            $maxNumber = $number;
                            $maxNumberFile = $filePath;
                        }
                    }
                }
                
                $stats['max_number'] = $maxNumber;
                $stats['max_file'] = basename($maxNumberFile);
                
                // Extrair a data do nome do arquivo com o maior número
                if (!empty($maxNumberFile) && preg_match('/(\d{4}-\d{2}-\d{2})_O_/', $maxNumberFile, $dateMatches)) {
                    $stats['max_date'] = $dateMatches[1];
                } else {
                    $stats['max_date'] = date('Y-m-d');
                }
                
                // Buscar o arquivo com maior número do boletim do ano corrente
                $currentYear = date('Y');
                
                // Busca todos os arquivos do ano corrente e extrai o número do boletim
                $stmt = $pdo->prepare("SELECT file_path, last_indexed_at FROM pdf_index 
                                      WHERE file_path LIKE ? 
                                      ORDER BY file_path DESC");
                $stmt->execute(["%/$currentYear-%"]);
                $files = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                $lastFile = null;
                $maxNumber = -1;
                
                // Encontra o arquivo com o maior número de boletim
                foreach ($files as $file) {
                    if (preg_match('/_O_(\d+)_/', $file['file_path'], $matches)) {
                        $number = intval($matches[1]);
                        if ($number > $maxNumber) {
                            $maxNumber = $number;
                            $lastFile = $file;
                        }
                    }
                }
                
                // Se não encontrou do ano corrente, busca o mais recente de qualquer ano
                if (!$lastFile) {
                    $stmt = $pdo->query("SELECT file_path, last_indexed_at FROM pdf_index ORDER BY file_path DESC");
                    $files = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    foreach ($files as $file) {
                        if (preg_match('/_O_(\d+)_/', $file['file_path'], $matches)) {
                            $number = intval($matches[1]);
                            if ($number > $maxNumber) {
                                $maxNumber = $number;
                                $lastFile = $file;
                            }
                        }
                    }
                }
                
                if ($lastFile && isset($lastFile['file_path'])) {
                    $fileName = basename($lastFile['file_path']);
                    
                    // Formata o nome do arquivo: "2025-11-18_O_217_boletim" -> "BI 217, de 18 Nov 25"
                    if (preg_match('/(\d{4})-(\d{2})-(\d{2})_O_(\d+)/', $fileName, $matches)) {
                        $year = substr($matches[1], -2); // Pega últimos 2 dígitos do ano
                        $month = $matches[2];
                        $day = $matches[3];
                        $number = ltrim($matches[4], '0'); // Remove zeros à esquerda
                        
                        // Array de meses abreviados em português
                        $monthNames = [
                            '01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr',
                            '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
                            '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'
                        ];
                        
                        $monthName = $monthNames[$month] ?? $month;
                        $stats['last_file'] = "BI $number, de $day $monthName $year";
                    } else {
                        // Se não conseguir extrair, limita o tamanho do nome
                        $stats['last_file'] = strlen($fileName) > 50 
                            ? substr($fileName, 0, 47) . '...' 
                            : $fileName;
                    }
                    
                    // Adiciona informação de quando foi indexado
                    $stats['last_indexed_at'] = $lastFile['last_indexed_at'];
                } else {
                    $stats['last_file'] = 'Arquivo sem nome';
                }
            } catch (\Exception $e) {
                // Em caso de qualquer erro na consulta, mostre uma mensagem genérica
                error_log("Erro ao buscar último arquivo: " . $e->getMessage());
                $stats['last_file'] = 'Erro ao buscar arquivo';
            }
        } else {
            $stats['last_file'] = 'Nenhum arquivo indexado';
        }

    } catch (\PDOException $e) {
        error_log("Falha ao buscar estatísticas via AJAX: " . $e->getMessage());
    }
    
    $response->getBody()->write(json_encode($stats));
    return $response->withHeader('Content-Type', 'application/json');
});
// --- FIM DA NOVA ROTA ---

// --- NOVA ROTA PARA DADOS DO GRÁFICO ---
$app->get('/stats/daily', function ($request, $response) {
    $chartData = [
        'labels' => [],
        'data' => []
    ];
    try {
        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbName = getenv('DB_NAME') ?: 'searchpdf_db';
        $dbUser = getenv('DB_USER') ?: 'searchpdf_user';
        $dbPass = getenv('DB_PASS') ?: 'user_password';
        $pdo = new \PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);

        // Query que agrupa as buscas por dia nos últimos 7 dias
        $query = "
            SELECT 
                DATE(searched_at) as search_date, 
                COUNT(*) as count 
            FROM search_log 
            WHERE searched_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
            GROUP BY search_date 
            ORDER BY search_date ASC;
        ";
        $stmt = $pdo->query($query);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Preenche os últimos 7 dias, mesmo que não haja buscas
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $chartData['labels'][] = date('d/m', strtotime($date)); // Formato DD/MM
            
            $found = false;
            foreach ($results as $row) {
                if ($row['search_date'] == $date) {
                    $chartData['data'][] = (int)$row['count'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $chartData['data'][] = 0;
            }
        }

    } catch (\PDOException $e) {
        error_log("Falha ao buscar dados do gráfico: " . $e->getMessage());
    }
    
    $response->getBody()->write(json_encode($chartData));
    return $response->withHeader('Content-Type', 'application/json');
});

// Nova rota para os termos mais pesquisados
$app->get('/stats/terms', function ($request, $response) {
    $chartData = [
        'labels' => [],
        'data' => [],
        'colors' => []
    ];
    
    try {
        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbName = getenv('DB_NAME') ?: 'searchpdf_db';
        $dbUser = getenv('DB_USER') ?: 'searchpdf_user';
        $dbPass = getenv('DB_PASS') ?: 'user_password';
        $pdo = new \PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);

        // Lista de nomes comuns para filtrar
        $nomesList = [
            'JOSE', 'JOAO', 'ANTONIO', 'FRANCISCO', 'CARLOS', 'PAULO', 'PEDRO', 'LUCAS',
            'LUIZ', 'MARCOS', 'LUIS', 'GABRIEL', 'RAFAEL', 'DANIEL', 'MARCELO', 'BRUNO',
            'EDUARDO', 'FELIPE', 'RODRIGO', 'MANOEL', 'MARIA', 'ANA', 'FRANCISCA', 'ANTONIA',
            'ADRIANA', 'JULIANA', 'MARCIA', 'FERNANDA', 'PATRICIA', 'ALINE', 'SANDRA', 'CAMILA',
            'AMANDA', 'BRUNA', 'JESSICA', 'LETICIA', 'JULIA', 'LUCIANA', 'VANESSA', 'MARIANA',
            'SILVA', 'SANTOS', 'OLIVEIRA', 'SOUZA', 'LIMA', 'PEREIRA', 'FERREIRA', 'ALVES',
            'RODRIGUES', 'COSTA', 'GOMES', 'MARTINS', 'ARAUJO', 'MELO', 'BARBOSA', 'RIBEIRO',
            'ALMEIDA', 'CARVALHO', 'LOPES', 'SOARES', 'VIEIRA', 'FERNANDES', 'GONÇALVES', 'CASTRO',
            'CARDOSO', 'TEIXEIRA', 'VELOSO', 'GARCIA', 'PINTO', 'MORAES', 'MARQUES', 'DIAS',
            'AUGUSTO', 'ROBERTO', 'GUSTAVO', 'RICARDO', 'HENRIQUE', 'DIEGO', 'VITOR', 'ROGERIO',
            'GUILHERME', 'RENATO', 'FLAVIO', 'LEANDRO', 'FABIO', 'ANDRE', 'CLAUDIO', 'THIAGO',
            'ALEX', 'FERNANDO', 'RENAN', 'SAMUEL', 'JORGE', 'MAURICIO', 'CAIO', 'VINICIUS',
            'WILIAN', 'WASHINGTON', 'WELLINGTON', 'WESLEY'
        ];

        // Constrói uma cláusula WHERE complexa para excluir nomes em diferentes padrões
        $whereConditions = [];
        
        // 1. Excluir termos que são exatamente nomes da lista
        $placeholders1 = implode(',', array_fill(0, count($nomesList), '?'));
        $whereConditions[] = "UPPER(search_term) NOT IN ($placeholders1)";
        
        // 2. Excluir termos que começam com algum nome da lista seguido de espaço
        // (para pegar nomes compostos como "Carlos Augusto")
        $likeConditions = [];
        foreach ($nomesList as $nome) {
            $likeConditions[] = "UPPER(search_term) NOT LIKE ?";
        }
        $whereConditions[] = '(' . implode(' AND ', $likeConditions) . ')';
        
        // 3. Filtrar termos muito curtos
        $whereConditions[] = "LENGTH(search_term) > 2";
        
        // Monta a query final
        $query = "
            SELECT 
                search_term, 
                COUNT(*) as count 
            FROM search_log
            WHERE " . implode(' AND ', $whereConditions) . "
            GROUP BY search_term 
            ORDER BY count DESC 
            LIMIT 10;
        ";
        
        $stmt = $pdo->prepare($query);
        
        // Vincula os parâmetros para a primeira condição (IN)
        $paramIndex = 1;
        foreach($nomesList as $nome) {
            $stmt->bindValue($paramIndex++, strtoupper($nome));
        }
        
        // Vincula os parâmetros para a segunda condição (LIKE)
        foreach($nomesList as $nome) {
            $stmt->bindValue($paramIndex++, strtoupper($nome) . ' %');
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Cores para o gráfico (10 cores com variações de verde e cores complementares)
        $colors = [
            'rgba(46, 125, 50, 0.7)',    // Verde primário
            'rgba(67, 160, 71, 0.7)',    // Verde mais claro
            'rgba(102, 187, 106, 0.7)',  // Verde ainda mais claro
            'rgba(156, 204, 101, 0.7)',  // Verde amarelado
            'rgba(212, 225, 87, 0.7)',   // Amarelo esverdeado
            'rgba(255, 193, 7, 0.7)',    // Âmbar
            'rgba(255, 152, 0, 0.7)',    // Laranja
            'rgba(255, 87, 34, 0.7)',    // Laranja profundo
            'rgba(244, 67, 54, 0.7)',    // Vermelho
            'rgba(121, 85, 72, 0.7)'     // Marrom
        ];
        
        $i = 0;
        foreach ($results as $row) {
            // Limitar o tamanho do termo para exibição no gráfico
            $term = strlen($row['search_term']) > 15 
                ? substr($row['search_term'], 0, 15) . '...' 
                : $row['search_term'];
                
            $chartData['labels'][] = $term;
            $chartData['data'][] = (int)$row['count'];
            $chartData['colors'][] = $colors[$i % count($colors)];
            $i++;
        }
        
        // Se não houver resultados após filtrar, adicione uma mensagem
        if (empty($chartData['labels'])) {
            $chartData['labels'][] = 'Sem termos não-nominais';
            $chartData['data'][] = 1;
            $chartData['colors'][] = 'rgba(200, 200, 200, 0.7)';
        }

    } catch (\PDOException $e) {
        error_log("Falha ao buscar termos mais pesquisados: " . $e->getMessage());
        // Em caso de erro, adicione uma mensagem padrão para evitar gráfico vazio
        $chartData['labels'][] = 'Erro ao carregar';
        $chartData['data'][] = 1;
        $chartData['colors'][] = 'rgba(244, 67, 54, 0.7)';
    }
    
    $response->getBody()->write(json_encode($chartData));
    return $response->withHeader('Content-Type', 'application/json');
});
// --- FIM DA NOVA ROTA ---

// Nova rota para listar arquivos do ano atual
$app->get('/files/list/{ano}', function ($request, $response, $args) {
    $ano = $args['ano'] ?? date('Y');
    
    // Instancia o controller
    $controller = new \Controller\FilesController();
    
    // Usa o controller para listar os arquivos do ano
    $arquivos = $controller->listarArquivosDoAno($ano);
    
    // Retorna os arquivos em formato JSON
    $response->getBody()->write(json_encode($arquivos));
    return $response->withHeader('Content-Type', 'application/json');
});

// --- ROTAS PARA NOVAS ESTATÍSTICAS ---
$app->get('/stats/top-searches', [\Controller\StatsController::class, 'getTopSearchTerms']);
$app->get('/stats/document-distribution', [\Controller\StatsController::class, 'getDocumentDistribution']);

// Rota responsável por entregar arquivos via token sem revelar caminhos reais
$app->get('/download/{token}', function ($request, $response, $args) {
    $settings = $request->getAttribute('settings');
    $secret = $settings['download_secret'] ?? null;
    $baseDirectory = $settings['directory_files'] ?? null;

    if (empty($secret) || empty($baseDirectory)) {
        return $response->withStatus(404);
    }

    $token = $args['token'] ?? '';
    if ($token === '') {
        return $response->withStatus(404);
    }

    $relativePath = \Model\DownloadToken::decode($token, $secret);
    if ($relativePath === null) {
        return $response->withStatus(404);
    }

    $rootRealPath = realpath($baseDirectory);
    if ($rootRealPath === false) {
        return $response->withStatus(404);
    }

    $fullPath = $rootRealPath . DIRECTORY_SEPARATOR . $relativePath;
    $fileRealPath = realpath($fullPath);
    if ($fileRealPath === false) {
        return $response->withStatus(404);
    }

    $normalizedRoot = rtrim($rootRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($fileRealPath, $normalizedRoot)) {
        return $response->withStatus(403);
    }

    if (!is_file($fileRealPath) || !is_readable($fileRealPath)) {
        return $response->withStatus(404);
    }

    $handle = fopen($fileRealPath, 'rb');
    if ($handle === false) {
        return $response->withStatus(500);
    }

    $stream = new Stream($handle);
    $mimeType = mime_content_type($fileRealPath) ?: 'application/octet-stream';
    $filename = basename($fileRealPath);

    $response = $response
        ->withHeader('Content-Type', $mimeType)
        ->withHeader('Content-Disposition', 'attachment; filename="' . addslashes($filename) . '"');

    $size = filesize($fileRealPath);
    if ($size !== false) {
        $response = $response->withHeader('Content-Length', (string) $size);
    }

    return $response->withBody($stream);
});

// ===================================================================
// ROTAS DE AUTENTICAÇÃO
// ===================================================================
$app->get('/login', [\Controller\AuthController::class, 'showLogin']);
$app->post('/login', [\Controller\AuthController::class, 'processLogin']);
$app->get('/logout', [\Controller\AuthController::class, 'logout']);
$app->get('/auth/check', [\Controller\AuthController::class, 'checkAuth']);

// ===================================================================
// ROTAS PROTEGIDAS (exigem autenticação e permissão de upload)
// ===================================================================
$app->group('', function ($group) {
    // Página de upload
    $group->get('/upload', [\Controller\UploadController::class, 'showUploadForm']);
    
    // API de upload
    $group->post('/upload', [\Controller\UploadController::class, 'processUpload']);
    
    // API de deleção de upload
    $group->delete('/upload/{id}', [\Controller\UploadController::class, 'deleteUpload']);
    
})->add(new \Libs\RoleMiddleware(['admin', 'uploader']))
  ->add(\Libs\AuthMiddleware::class);

// Rotas AJAX para a funcionalidade de busca
$app->post('/searchFile', [\Controller\AjaxController::class, 'searchFile']);
$app->post('/selectedFiles', [\Controller\AjaxController::class, 'selectedFiles']);

// Outras rotas (mantidas do original, se ainda forem necessárias)
$app->any('/search', [\Controller\AjaxController::class, 'search']);
$app->get('/ocrmypdf', \Controller\OcrMyPdf::class);

// Executa a aplicação
$app->run();