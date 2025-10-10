<?php

namespace Controller;

/**
 * Controller para fornecer estatísticas avançadas
 *
 * @author Augusto <carloafernandes@gmail.com>
 */
class StatsController
{
    /**
     * Retorna os termos mais pesquisados no sistema
     */
    public static function getTopSearchTerms($request, $response)
    {
        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbName = getenv('DB_NAME') ?: 'searchpdf_db';
        $dbUser = getenv('DB_USER') ?: 'searchpdf_user';
        $dbPass = getenv('DB_PASS') ?: 'user_password';

        try {
            $pdo = new \PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("
                SELECT search_term, COUNT(*) as search_count, 
                       AVG(results_count) as avg_results
                FROM search_log 
                WHERE LENGTH(search_term) > 2
                GROUP BY search_term 
                ORDER BY search_count DESC 
                LIMIT 10
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Formata os dados para consumo pelo gráfico
            $labels = [];
            $counts = [];
            $avgResults = [];
            
            foreach ($results as $row) {
                $labels[] = $row['search_term'];
                $counts[] = (int)$row['search_count'];
                $avgResults[] = round((float)$row['avg_results'], 1);
            }
            
            $data = [
                'labels' => $labels,
                'counts' => $counts,
                'avgResults' => $avgResults
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\PDOException $e) {
            // Log do erro
            error_log('Erro ao buscar top termos: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode(['error' => 'Erro ao consultar banco de dados: ' . $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Retorna distribuição de documentos por mês/ano
     */
    public static function getDocumentDistribution($request, $response)
    {
        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbName = getenv('DB_NAME') ?: 'searchpdf_db';
        $dbUser = getenv('DB_USER') ?: 'searchpdf_user';
        $dbPass = getenv('DB_PASS') ?: 'user_password';
        
        try {
            $pdo = new \PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Consulta SQL mais abrangente para capturar documentos únicos por nome de arquivo
            $stmt = $pdo->prepare("
                SELECT 
                    file_path,
                    SUBSTRING_INDEX(file_path, '/', -1) as filename,
                    COUNT(*) as document_count
                FROM pdf_index 
                GROUP BY SUBSTRING_INDEX(file_path, '/', -1)
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Organiza os dados por ano e mês para o gráfico
            $dataByYear = [];
            $years = [];
            $monthOrder = [
                'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
            ];
            
            foreach ($results as $row) {
                $filePath = $row['file_path'];
                $filename = $row['filename'];
                $count = (int)$row['document_count'];
                
                $year = null;
                $monthName = null;
                
                // Padrão 1: /uploads/BI YYYY/Mês/ (prioridade máxima)
                if (preg_match('#/BI\s+(\d{4})/([^/]+)/#', $filePath, $matches)) {
                    $year = $matches[1];
                    $monthName = self::normalizeMonthName($matches[2]);
                }
                // Padrão 2: /uploads/YYYY/Mês/
                elseif (preg_match('#/(\d{4})/([^/]+)/#', $filePath, $matches)) {
                    $year = $matches[1];
                    $monthName = self::normalizeMonthName($matches[2]);
                }
                // Padrão 3: /uploads/Mês/ (extrair ano do nome do arquivo)
                elseif (preg_match('#/uploads/([^/]+)/(\d{4})-\d{2}-\d{2}_#', $filePath, $matches)) {
                    $year = $matches[2];
                    $monthName = self::normalizeMonthName($matches[1]);
                }
                
                // Se conseguiu extrair ano e mês, adiciona aos dados (conta apenas 1 por arquivo único)
                if ($year && $monthName) {
                    if (!in_array($year, $years)) {
                        $years[] = $year;
                    }
                    
                    if (!isset($dataByYear[$year])) {
                        $dataByYear[$year] = [];
                    }
                    
                    if (!isset($dataByYear[$year][$monthName])) {
                        $dataByYear[$year][$monthName] = 0;
                    }
                    
                    $dataByYear[$year][$monthName] += 1; // Sempre conta 1 pois já agrupamos por filename
                }
            }
            
            // Ordena os anos cronologicamente
            sort($years);
            
            // Prepara a resposta com os dados organizados
            $response->getBody()->write(json_encode([
                'years' => $years,
                'monthOrder' => $monthOrder,
                'data' => $dataByYear
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\PDOException $e) {
            // Log do erro
            error_log('Erro ao buscar distribuição de documentos: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode(['error' => 'Erro ao consultar banco de dados: ' . $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Normaliza os nomes dos meses (converte as diferentes variações para um formato padrão)
     */
    private static function normalizeMonthName($month)
    {
        $month = strtolower($month);
        $monthMap = [
            'jan' => 'Janeiro',
            'janeiro' => 'Janeiro',
            'fev' => 'Fevereiro',
            'fevereiro' => 'Fevereiro',
            'mar' => 'Março',
            'marco' => 'Março',
            'abr' => 'Abril',
            'abril' => 'Abril',
            'mai' => 'Maio',
            'maio' => 'Maio',
            'jun' => 'Junho',
            'junho' => 'Junho',
            'jul' => 'Julho',
            'julho' => 'Julho',
            'ago' => 'Agosto',
            'agosto' => 'Agosto',
            'set' => 'Setembro',
            'setembro' => 'Setembro',
            'out' => 'Outubro',
            'outubro' => 'Outubro',
            'nov' => 'Novembro',
            'novembro' => 'Novembro',
            'dez' => 'Dezembro',
            'dezembro' => 'Dezembro'
        ];
        
        return isset($monthMap[$month]) ? $monthMap[$month] : ucfirst($month);
    }
}
