// Script AVANÇADO para download automático de boletins PDF
// Execute no console do navegador (F12) quando estiver na página de boletins
// Os arquivos serão baixados para a pasta padrão de Downloads do navegador

(function() {
    'use strict';
    
    console.clear();
    console.log('%c📋 DOWNLOAD AUTOMÁTICO DE BOLETINS - JULHO 2024', 'color: #2e7d32; font-size: 16px; font-weight: bold;');
    console.log('═══════════════════════════════════════════════════════════════');
    
    // Configurações
    const CONFIG = {
        delayBetweenDownloads: 2500, // milissegundos entre cada download
        showProgress: true,
        baseUrl: window.location.origin + window.location.pathname.replace('baixar_boletim.php', '')
    };
    
    // Classe para gerenciar downloads
    class BoletimDownloader {
        constructor() {
            this.downloadLinks = [];
            this.completed = 0;
            this.total = 0;
        }
        
        // Extrai informações dos boletins da tabela
        extractBoletins() {
            const rows = document.querySelectorAll('table.lista tr[id]');
            console.log(`\n🔍 Analisando página...`);
            
            rows.forEach(row => {
                const orderCell = row.querySelector('td:first-child');
                const nameCell = row.querySelector('td:nth-child(2)');
                const sizeCell = row.querySelector('td:nth-child(3)');
                const actionCell = row.querySelector('td:nth-child(4)');
                
                if (orderCell && nameCell && actionCell) {
                    const downloadLink = actionCell.querySelector('a[href*="down.php"]');
                    
                    if (downloadLink) {
                        this.downloadLinks.push({
                            order: orderCell.textContent.trim(),
                            filename: nameCell.textContent.trim(),
                            size: sizeCell ? sizeCell.textContent.trim() : 'N/A',
                            url: downloadLink.href
                        });
                    }
                }
            });
            
            this.total = this.downloadLinks.length;
            
            if (this.total === 0) {
                console.error('❌ Nenhum boletim encontrado na página!');
                return false;
            }
            
            console.log(`✅ Encontrados ${this.total} boletins para download\n`);
            this.showBoletinsList();
            return true;
        }
        
        // Exibe lista de boletins encontrados
        showBoletinsList() {
            console.log('%c📄 LISTA DE BOLETINS:', 'color: #1e88e5; font-weight: bold;');
            console.table(this.downloadLinks.map(b => ({
                'Ordem': b.order,
                'Arquivo': b.filename,
                'Tamanho': b.size
            })));
            console.log('');
        }
        
        // Faz o download de um arquivo
        downloadFile(boletim, index) {
            return new Promise((resolve) => {
                const progress = `[${index + 1}/${this.total}]`;
                const percentage = Math.round(((index + 1) / this.total) * 100);
                
                console.log(`%c⬇️  ${progress} ${percentage}% - ${boletim.filename}`, 'color: #ff9800;');
                
                // Cria elemento <a> temporário para forçar download
                const link = document.createElement('a');
                link.href = boletim.url;
                link.download = boletim.filename;
                link.style.display = 'none';
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                this.completed++;
                
                setTimeout(() => {
                    resolve();
                }, CONFIG.delayBetweenDownloads);
            });
        }
        
        // Inicia o processo de download
        async startDownloads() {
            if (!this.extractBoletins()) {
                return;
            }
            
            console.log('%c🚀 INICIANDO DOWNLOADS...', 'color: #4caf50; font-size: 14px; font-weight: bold;');
            console.log(`⏱️  Delay entre downloads: ${CONFIG.delayBetweenDownloads}ms\n`);
            
            const startTime = Date.now();
            
            // Download sequencial com delay
            for (let i = 0; i < this.downloadLinks.length; i++) {
                await this.downloadFile(this.downloadLinks[i], i);
            }
            
            const endTime = Date.now();
            const duration = ((endTime - startTime) / 1000).toFixed(1);
            
            console.log('\n═══════════════════════════════════════════════════════════════');
            console.log(`%c✅ CONCLUÍDO!`, 'color: #4caf50; font-size: 16px; font-weight: bold;');
            console.log(`📊 Total: ${this.completed}/${this.total} arquivos`);
            console.log(`⏱️  Tempo total: ${duration}s`);
            console.log('\n📁 Os arquivos foram baixados para a pasta de Downloads do navegador');
            console.log('💡 Para mover os arquivos, execute no terminal:');
            console.log('%cmv ~/Downloads/2024-07-*.pdf "/home/augusto/workspace/searchpdf/uploads/BI 2024/Julho/"', 
                       'background: #263238; color: #aed581; padding: 5px; border-radius: 3px;');
        }
    }
    
    // Executa o downloader
    const downloader = new BoletimDownloader();
    downloader.startDownloads();
    
})();
