<?php

namespace Controller;

use Model\UserModel;
use Model\FilesModel;

/**
 * Controller para upload de arquivos PDF
 * 
 * @author Augusto <carloafernandes@gmail.com>
 */
class UploadController
{
    /**
     * Exibe página de upload
     */
    public static function showUploadForm($request, $response)
    {
        $view = \Slim\Views\Twig::fromRequest($request);
        $settings = $request->getAttribute('settings');
        
        // Busca histórico de uploads do usuário
        $userId = $request->getAttribute('user_id');
        $history = UserModel::getUploadHistory($userId, 20);
        
        return $view->render($response, 'upload.twig', [
            'username' => $request->getAttribute('username'),
            'role' => $request->getAttribute('user_role'),
            'history' => $history,
            'max_file_size' => self::getMaxFileSize(),
            'max_file_size_mb' => self::getMaxFileSize() / 1024 / 1024
        ]);
    }
    
    /**
     * Processa o upload de arquivos
     */
    public static function processUpload($request, $response)
    {
        $settings = $request->getAttribute('settings');
        $userId = $request->getAttribute('user_id');
        $uploadedFiles = $request->getUploadedFiles();
        
        // Verifica se há arquivos
        if (empty($uploadedFiles['pdf_file'])) {
            return self::jsonResponse($response, [
                'success' => false,
                'error' => 'Nenhum arquivo foi enviado.'
            ], 400);
        }
        
        $uploadedFile = $uploadedFiles['pdf_file'];
        
        // Verifica erros no upload
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return self::jsonResponse($response, [
                'success' => false,
                'error' => self::getUploadErrorMessage($uploadedFile->getError())
            ], 400);
        }
        
        // Obtém dados do POST
        $post = $request->getParsedBody();
        $year = $post['year'] ?? date('Y');
        $month = $post['month'] ?? date('F');
        $category = $post['category'] ?? 'BI'; // BI ou BA
        
        // Validações
        $validation = self::validateUpload($uploadedFile, $year, $month, $category);
        if (!$validation['valid']) {
            return self::jsonResponse($response, [
                'success' => false,
                'error' => $validation['error']
            ], 400);
        }
        
        // Define o diretório de destino
        $baseDir = $settings['directory_files'] ?? '/var/www/html/public/uploads';
        $targetDir = self::buildTargetDirectory($baseDir, $year, $month, $category);
        
        // Limpa cache de status de arquivos
        clearstatcache(true, $baseDir);
        
        // Verifica se o diretório base existe
        if (!is_dir($baseDir)) {
            error_log("Diretório base não existe: $baseDir");
            return self::jsonResponse($response, [
                'success' => false,
                'error' => 'Diretório base de uploads não encontrado.'
            ], 500);
        }
        
        // Verifica permissões de escrita de forma mais robusta
        $testFile = $baseDir . '/.write_test_' . uniqid();
        $canWrite = @file_put_contents($testFile, 'test');
        if ($canWrite === false) {
            error_log("Diretório base não é gravável: $baseDir (test write failed)");
            error_log("Permissões: " . sprintf('%o', fileperms($baseDir)));
            error_log("Dono: " . fileowner($baseDir) . ", Grupo: " . filegroup($baseDir));
            error_log("PHP UID: " . posix_geteuid() . ", GID: " . posix_getegid());
            return self::jsonResponse($response, [
                'success' => false,
                'error' => 'Diretório de upload não tem permissões de escrita.'
            ], 500);
        }
        @unlink($testFile);
        
        // Cria o diretório de destino se não existir
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                error_log("Falha ao criar diretório: $targetDir");
                return self::jsonResponse($response, [
                    'success' => false,
                    'error' => 'Erro ao criar diretório de destino.'
                ], 500);
            }
        }
        
        // Verifica se o diretório de destino é gravável
        if (!is_writable($targetDir)) {
            error_log("Diretório de destino não é gravável: $targetDir");
            return self::jsonResponse($response, [
                'success' => false,
                'error' => 'Diretório de destino não tem permissões de escrita.'
            ], 500);
        }
        
        // Sanitiza o nome do arquivo
        $originalFilename = $uploadedFile->getClientFilename();
        $sanitizedFilename = self::sanitizeFilename($originalFilename);
        $targetPath = $targetDir . '/' . $sanitizedFilename;
        
        // Verifica se o arquivo já existe
        if (file_exists($targetPath)) {
            return self::jsonResponse($response, [
                'success' => false,
                'error' => "O arquivo '$sanitizedFilename' já existe neste diretório."
            ], 409);
        }
        
        try {
            // Move o arquivo
            $uploadedFile->moveTo($targetPath);
            
            // Registra o log
            $logData = [
                'user_id' => $userId,
                'filename' => $sanitizedFilename,
                'original_filename' => $originalFilename,
                'file_path' => $targetPath,
                'file_size' => filesize($targetPath),
                'year' => $year,
                'month' => $month,
                'status' => 'success',
                'ip_address' => self::getClientIp($request)
            ];
            
            UserModel::logUpload($logData);
            
            // Trigger de indexação (opcional - implementar depois)
            self::triggerIndexation($targetPath);
            
            return self::jsonResponse($response, [
                'success' => true,
                'message' => 'Arquivo enviado com sucesso!',
                'file' => [
                    'filename' => $sanitizedFilename,
                    'size' => filesize($targetPath),
                    'path' => $targetPath,
                    'year' => $year,
                    'month' => $month,
                    'category' => $category
                ]
            ], 200);
            
        } catch (\Exception $e) {
            error_log("Erro no upload: " . $e->getMessage());
            
            // Registra falha no log
            UserModel::logUpload([
                'user_id' => $userId,
                'filename' => $sanitizedFilename,
                'original_filename' => $originalFilename,
                'file_path' => $targetPath,
                'file_size' => $uploadedFile->getSize(),
                'year' => $year,
                'month' => $month,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'ip_address' => self::getClientIp($request)
            ]);
            
            return self::jsonResponse($response, [
                'success' => false,
                'error' => 'Erro ao salvar o arquivo.'
            ], 500);
        }
    }
    
    /**
     * Valida o arquivo enviado
     */
    private static function validateUpload($uploadedFile, $year, $month, $category)
    {
        // Valida tipo de arquivo
        $allowedMimeTypes = ['application/pdf'];
        $clientMediaType = $uploadedFile->getClientMediaType();
        
        if (!in_array($clientMediaType, $allowedMimeTypes)) {
            return [
                'valid' => false,
                'error' => 'Apenas arquivos PDF são permitidos.'
            ];
        }
        
        // Valida tamanho
        $maxSize = self::getMaxFileSize();
        if ($uploadedFile->getSize() > $maxSize) {
            $maxSizeMb = $maxSize / 1024 / 1024;
            return [
                'valid' => false,
                'error' => "O arquivo excede o tamanho máximo de {$maxSizeMb}MB."
            ];
        }
        
        // Valida ano
        if (!is_numeric($year) || $year < 2000 || $year > 2100) {
            return [
                'valid' => false,
                'error' => 'Ano inválido.'
            ];
        }
        
        // Valida mês
        $validMonths = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];
        
        if (!in_array($month, $validMonths)) {
            return [
                'valid' => false,
                'error' => 'Mês inválido.'
            ];
        }
        
        // Valida categoria
        if (!in_array($category, ['BI', 'BA'])) {
            return [
                'valid' => false,
                'error' => 'Categoria inválida.'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Constrói o caminho do diretório de destino
     */
    private static function buildTargetDirectory($baseDir, $year, $month, $category)
    {
        // Padrão: /uploads/BI YYYY/Mês/
        return rtrim($baseDir, '/') . '/' . $category . ' ' . $year . '/' . $month;
    }
    
    /**
     * Sanitiza o nome do arquivo
     */
    private static function sanitizeFilename($filename)
    {
        // Remove caracteres perigosos
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove pontos múltiplos (exceto o último)
        $parts = explode('.', $filename);
        $extension = array_pop($parts);
        $basename = implode('_', $parts);
        
        return $basename . '.' . $extension;
    }
    
    /**
     * Retorna o tamanho máximo de upload permitido
     */
    private static function getMaxFileSize()
    {
        // 50MB por padrão
        return 50 * 1024 * 1024;
    }
    
    /**
     * Retorna mensagem de erro de upload
     */
    private static function getUploadErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'O arquivo excede o tamanho máximo permitido.';
            case UPLOAD_ERR_PARTIAL:
                return 'O arquivo foi enviado parcialmente.';
            case UPLOAD_ERR_NO_FILE:
                return 'Nenhum arquivo foi enviado.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Diretório temporário não encontrado.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Falha ao escrever o arquivo no disco.';
            case UPLOAD_ERR_EXTENSION:
                return 'Uma extensão PHP bloqueou o upload.';
            default:
                return 'Erro desconhecido no upload.';
        }
    }
    
    /**
     * Obtém o IP do cliente
     */
    private static function getClientIp($request)
    {
        $serverParams = $request->getServerParams();
        
        if (!empty($serverParams['HTTP_CLIENT_IP'])) {
            return $serverParams['HTTP_CLIENT_IP'];
        }
        
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            return $serverParams['HTTP_X_FORWARDED_FOR'];
        }
        
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Helper para resposta JSON
     */
    private static function jsonResponse($response, $data, $status = 200)
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
    
    /**
     * Dispara a indexação do arquivo (placeholder)
     * TODO: Implementar integração com o indexador existente
     */
    private static function triggerIndexation($filePath)
    {
        // Aqui você pode chamar o indexer.php ou adicionar à fila de indexação
        // Por exemplo:
        // exec("php /var/www/html/indexer.php --file=" . escapeshellarg($filePath) . " > /dev/null 2>&1 &");
        
        error_log("Arquivo pronto para indexação: $filePath");
    }
}
