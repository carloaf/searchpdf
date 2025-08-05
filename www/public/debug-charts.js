/**
 * debug-charts.js
 * Script auxiliar para diagnóstico de problemas com gráficos
 * (Versão modificada com logs desativados)
 */

// Função para diagnóstico da estrutura DOM
function logDomStructure() {
    // Logs desativados para reduzir ruído no console
    /*
    console.log('=== DOCUMENT READY FIRED ===');
    console.log('=== DOM STRUCTURE LOG ===');
    console.log('Body children count:', document.body.children.length);
    console.log('Chart placeholders found:');
    console.log('- #chart-placeholder exists:', !!document.getElementById('chart-placeholder'));
    console.log('- #document-distribution-chart exists:', !!document.getElementById('document-distribution-chart'));
    console.log('- #document-distribution-chart-container exists:', !!document.getElementById('document-distribution-chart-container'));
    */
}

// Função para testar se Chart.js está funcionando corretamente
function testChartJsBasic() {
    // Logs desativados para reduzir ruído no console
    /*
    console.log('=== TESTING CHART.JS FUNCTIONALITY ===');
    
    try {
        console.log('Chart.js version:', Chart.version);
        console.log('Verificação de Chart.js concluída');
    } catch (error) {
        console.error('Error checking Chart.js:', error);
    }
    */
}

// Função para testar chamadas de API
function testApiCalls() {
    // Logs desativados para reduzir ruído no console
    /*
    console.log('=== TESTING API CALLS ===');
    
    // Test document distribution API
    $.ajax({
        url: '/stats/document-distribution',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('Document distribution API success!');
            console.log('Has years array:', !!data.years);
            console.log('Has months array:', !!data.monthOrder);
            console.log('Has data object:', !!data.data);
        },
        error: function(xhr, status, error) {
            console.error('Document distribution API error:', error);
        }
    });
    */
}

// Executa quando o documento estiver pronto
$(document).ready(function() {
    // Funções de diagnóstico desativadas
    // logDomStructure();
    // testChartJsBasic();
    // testApiCalls();
    
    // Observadores de eventos para diagnóstico (desativados)
    /*
    // Para o gráfico de distribuição de documentos
    if (document.getElementById('document-distribution-chart')) {
        const docDistCanvas = document.getElementById('document-distribution-chart');
        const observer = new MutationObserver(function(mutations) {
            console.log('Document distribution chart DOM changed!');
        });
        observer.observe(docDistCanvas, { attributes: true, childList: true, subtree: true });
    }
    */
});
