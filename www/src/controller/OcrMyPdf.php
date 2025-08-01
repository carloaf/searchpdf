<?php

namespace Controller;

use Slim\Views\Twig;
use Model\FilesModel;

/**
 * OcrMyPdf class
 * 
 * Obtém OCR de um PDF
 *
 * @author Augusto <carloafernandes@gmail.com>
 * @version 1.0
 * @license MIT
 */
class OcrMyPdf
{
    /**
     * Cria uma lista de arquivos a partir de um diretório
     */
    public static function listFilesFromDir($dir, $tree) 
    {
        foreach(scandir($dir) as $file) {             
            
            if ('.' === $file || '..' === $file) continue;

            if (is_dir($dir.'/'.$file)) {                    
                $tree = self::listFilesFromDir($dir.'/'.$file, $tree);
            }
            else {                
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array($ext, ['pdf'])) {                
                    
                    $tree[] = $dir.'/'.$file;
                }   
            }
        }
        return $tree;        
    }    

    /**
     * 
     */
    public function __invoke($request, $response)
    {        
        $settings = $request->getAttribute('settings');
        $dir = $settings['directory_files'];
        $tree = self::listFilesFromDir($dir, []);
        foreach($tree as $file) {            
            $text_file = shell_exec('pdftotext "'. $file . '" -');
            if (substr($text_file,0,1) == '') {
                shell_exec('ocrmypdf "' . $file . '" "' . $file .'"');
            }
        }
        return $response->withStatus(200);
    }
    
}