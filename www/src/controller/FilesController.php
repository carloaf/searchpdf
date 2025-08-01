<?php

namespace Controller;

class FilesController {
    
    public function listarArquivosDoAno($ano) {
        $resultado = [];
        
        // Verifica se é um ano válido
        if (!is_numeric($ano) || $ano < 2000 || $ano > 2100) {
            return $resultado;
        }
        
        // Diretório base dos uploads
        $baseDir = realpath(__DIR__ . '/../../../uploads');
        
        // Caminho para o diretório do ano específico
        $anoDir = $baseDir . '/' . $ano;
        
        // Verifica se o diretório existe
        if (!is_dir($anoDir)) {
            return $resultado;
        }
        
        // Para depuração
        error_log("Verificando arquivos em: $anoDir");
        
        // Diretórios específicos para verificar
        $tiposDirs = ['BI', 'BA'];
        $mesesPT = [
            'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];
        $mesesAbrev = [
            'JAN', 'FEV', 'MAR', 'ABR', 'MAIO', 'JUN', 
            'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'
        ];
        
        // Percorre as pastas de tipos (BI, BA)
        foreach ($tiposDirs as $tipo) {
            $tipoDir = $anoDir . '/' . $tipo;
            if (!is_dir($tipoDir)) {
                continue;
            }
            
            error_log("Verificando pasta de tipo: $tipoDir");
            
            // Primeiro tenta os meses em português
            foreach ($mesesPT as $mes) {
                $mesDir = $tipoDir . '/' . $mes;
                if (!is_dir($mesDir)) {
                    continue;
                }
                
                error_log("Verificando pasta de mês: $mesDir");
                
                // Lista os arquivos no diretório do mês
                $files = scandir($mesDir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    
                    $path = $mesDir . '/' . $file;
                    
                    if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf') {
                        // Extrai informações do arquivo para depuração
                        $match = [];
                        if (preg_match('/O_(\d{3})/', $file, $match)) {
                            $numeroArquivo = $match[1];
                            error_log("Encontrado arquivo: $file com número: $numeroArquivo");
                        }
                        
                        // Adiciona apenas arquivos PDF
                        $resultado[] = $path;
                    }
                }
            }
            
            // Depois tenta os meses abreviados
            foreach ($mesesAbrev as $mes) {
                $mesDir = $tipoDir . '/' . $mes;
                if (!is_dir($mesDir)) {
                    continue;
                }
                
                error_log("Verificando pasta de mês abreviado: $mesDir");
                
                // Lista os arquivos no diretório do mês
                $files = scandir($mesDir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    
                    $path = $mesDir . '/' . $file;
                    
                    if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf') {
                        // Adiciona apenas arquivos PDF
                        $resultado[] = $path;
                    }
                }
            }
        }
        
        error_log("Total de arquivos encontrados: " . count($resultado));
        return $resultado;
    }
}
