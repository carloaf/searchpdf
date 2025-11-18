// Script para download automático de boletins PDF
// Execute este script no console do navegador quando estiver logado na página de boletins

(async function downloadBoletins() {
    console.log('🚀 Iniciando download dos boletins...');
    
    // Seleciona todos os links de download (ícone de salvar)
    const downloadLinks = Array.from(document.querySelectorAll('a[href^="down.php?filename=boletim/"]'));
    
    if (downloadLinks.length === 0) {
        console.error('❌ Nenhum link de download encontrado!');
        return;
    }
    
    console.log(`📊 Total de boletins encontrados: ${downloadLinks.length}`);
    
    // Função para fazer download com delay
    async function downloadWithDelay(link, index, total) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const filename = link.href.match(/filename=boletim\/(.+\.pdf)/)[1];
                console.log(`⬇️  [${index + 1}/${total}] Baixando: ${filename}`);
                
                // Cria um elemento <a> temporário e simula o clique
                const tempLink = document.createElement('a');
                tempLink.href = link.href;
                tempLink.download = filename;
                tempLink.style.display = 'none';
                document.body.appendChild(tempLink);
                tempLink.click();
                document.body.removeChild(tempLink);
                
                resolve();
            }, index * 2000); // Delay de 2 segundos entre downloads
        });
    }
    
    // Executa os downloads sequencialmente
    const total = downloadLinks.length;
    for (let i = 0; i < total; i++) {
        await downloadWithDelay(downloadLinks[i], i, total);
    }
    
    console.log('✅ Processo de download iniciado! Verifique a pasta de Downloads do navegador.');
    console.log('📁 Após o download, mova os arquivos para: /home/augusto/workspace/searchpdf/uploads/BI 2024/Julho');
    
})();
