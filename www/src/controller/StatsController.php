<?php

namespace Controller;

/**
 * Controller para fornecer estatísticas avançadas
 *
 * @author Augusto <carloafernandes@gmail.com>
 * @version 1.0
 * @license MIT
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
            
            return $response->withJson($data);
            
        } catch (\PDOException $e) {
            return $response->withJson(['error' => 'Erro ao consultar banco de dados: ' . $e->getMessage()]);
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
            
            // Extrai ano e mês do caminho do arquivo
            $stmt = $pdo->prepare("
                SELECT 
                    SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -4), '/', 1) as year,
                    SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -3), '/', 1) as month,
                    COUNT(*) as document_count
                FROM pdf_index 
                GROUP BY year, month
                ORDER BY year DESC, FIELD(month, 
                    'JAN', 'Janeiro', 'FEV', 'Fevereiro', 'MAR', 'Marco', 
                    'ABR', 'Abril', 'MAIO', 'Maio', 'JUN', 'Junho', 
                    'JUL', 'Julho', 'AGO', 'Agosto', 'SET', 'Setembro',
                    'OUT', 'Outubro', 'NOV', 'Novembro', 'DEZ', 'Dezembro')
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Formata os dados para o gráfico
            $data = [];
            foreach ($results as $row) {
                // Normaliza os nomes dos meses
                $monthName = self::normalizeMonthName($row['month']);
                $label = $monthName . '/' . $row['year'];
                $data[] = [
                    'label' => $label,
                    'count' => (int)$row['document_count']
                ];
            }
            
            return $response->withJson($data);
            
        } catch (\PDOException $e) {
            return $response->withJson(['error' => 'Erro ao consultar banco de dados: ' . $e->getMessage()]);
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
