<?php
/**
 * Script wrapper para executar o indexador via requisição HTTP
 * Executa o indexer.php e retorna o status em JSON
 */

// Evita timeout durante a indexação
set_time_limit(300); // 5 minutos

// Inicia buffer de saída para capturar o output do indexer
ob_start();

// Caminho para o indexer
$indexerPath = dirname(__DIR__) . '/indexer.php';

if (!file_exists($indexerPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Indexer script not found',
        'output' => ''
    ]);
    exit;
}

try {
    // Executa o indexer
    include $indexerPath;
    
    // Captura a saída
    $output = ob_get_clean();
    
    // Extrai informações do output
    $indexedCount = 0;
    $skippedCount = 0;
    
    if (preg_match('/Arquivos indexados\/atualizados:\s*(\d+)/', $output, $matches)) {
        $indexedCount = (int)$matches[1];
    }
    
    if (preg_match('/Arquivos já atualizados \(pulados\):\s*(\d+)/', $output, $matches)) {
        $skippedCount = (int)$matches[1];
    }
    
    // Retorna resposta de sucesso
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Indexação concluída. {$indexedCount} arquivos indexados, {$skippedCount} já atualizados.",
        'indexed' => $indexedCount,
        'skipped' => $skippedCount,
        'output' => $output
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro durante a indexação: ' . $e->getMessage(),
        'output' => ''
    ]);
}
