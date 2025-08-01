// Função para carregar o gráfico de termos mais pesquisados
function loadTopSearchesChart() {
    $.ajax({
        url: 'stats/top-searches',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            // Verifica se temos um container para o gráfico
            if (!$('#top-searches-chart-container').length) {
                // Se não existir, cria o elemento no DOM
                const chartHtml = `
                <div class="card mt-4 shadow-sm" id="top-searches-chart-container">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Termos Mais Pesquisados</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="top-searches-chart" height="250"></canvas>
                    </div>
                </div>`;
                
                $('#chart-placeholder').after(chartHtml);
            }
            
            const canvasElement = document.getElementById('top-searches-chart');
            if (!canvasElement || !data.labels || !data.counts) {
                console.error('Elementos necessários para o gráfico não encontrados');
                return;
            }
            
            // Usa o método do Chart.js para destruir um gráfico existente
            const existingChart = Chart.getChart(canvasElement);
            if (existingChart) {
                existingChart.destroy();
            }
            
            // Configura as cores do gráfico
            const backgroundColors = [
                'rgba(46, 125, 50, 0.8)',    // Verde principal
                'rgba(56, 142, 60, 0.8)',    // Verde mais claro
                'rgba(67, 160, 71, 0.8)',    // Verde ainda mais claro
                'rgba(76, 175, 80, 0.8)',    // Verde primário
                'rgba(102, 187, 106, 0.8)',  // Verde claro
                'rgba(129, 199, 132, 0.8)',  // Verde mais claro
                'rgba(165, 214, 167, 0.8)',  // Verde ainda mais claro
                'rgba(200, 230, 201, 0.8)',  // Verde muito claro
                'rgba(232, 245, 233, 0.8)',  // Verde quase branco
                'rgba(220, 237, 200, 0.8)'   // Verde amarelado
            ];
            
            // Inverte os arrays para que os valores mais altos apareçam primeiro no gráfico
            const reversedLabels = [...data.labels].reverse();
            const reversedCounts = [...data.counts].reverse();
            const reversedAvgResults = [...data.avgResults].reverse();
            
            // Cria o novo gráfico
            new Chart(canvasElement, {
                type: 'bar',
                data: {
                    labels: reversedLabels,
                    datasets: [{
                        label: 'Número de Buscas',
                        data: reversedCounts,
                        backgroundColor: backgroundColors,
                        borderColor: backgroundColors.map(color => color.replace('0.8', '1')),
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',  // Gráfico de barras horizontais
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Número de Buscas',
                                font: {
                                    weight: 'bold'
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    const index = context.dataIndex;
                                    return [
                                        `Buscas: ${context.parsed.x}`,
                                        `Média de resultados: ${reversedAvgResults[index]}`
                                    ];
                                }
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar o gráfico de termos mais pesquisados:', error);
            if (!$('#top-searches-chart-container').length) {
                const errorHtml = `
                <div class="card mt-4 shadow-sm" id="top-searches-chart-container">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Termos Mais Pesquisados</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted text-center py-4">Não foi possível carregar os dados do gráfico.</p>
                    </div>
                </div>`;
                
                $('#chart-placeholder').after(errorHtml);
            }
        }
    });
}

// Função para carregar o gráfico de distribuição de documentos
function loadDocumentDistributionChart() {
    $.ajax({
        url: 'stats/document-distribution',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            // Verifica se temos um container para o gráfico
            if (!$('#document-distribution-chart-container').length) {
                // Se não existir, cria o elemento no DOM
                const chartHtml = `
                <div class="card mt-4 shadow-sm" id="document-distribution-chart-container">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Distribuição de Documentos por Mês</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="document-distribution-chart" height="250"></canvas>
                    </div>
                </div>`;
                
                $('#top-searches-chart-container').after(chartHtml);
            }
            
            const canvasElement = document.getElementById('document-distribution-chart');
            if (!canvasElement || !data || data.length === 0) {
                console.error('Elementos necessários para o gráfico não encontrados');
                return;
            }
            
            // Usa o método do Chart.js para destruir um gráfico existente
            const existingChart = Chart.getChart(canvasElement);
            if (existingChart) {
                existingChart.destroy();
            }
            
            // Prepara os dados para o gráfico
            const labels = data.map(item => item.label);
            const counts = data.map(item => item.count);
            
            // Filtra para mostrar apenas os últimos 12 meses (se houver mais)
            let filteredLabels = labels;
            let filteredCounts = counts;
            if (labels.length > 12) {
                filteredLabels = labels.slice(-12);
                filteredCounts = counts.slice(-12);
            }
            
            // Cores diferentes para o ano atual vs anos anteriores
            const currentYear = new Date().getFullYear().toString();
            const backgroundColors = filteredLabels.map(label => {
                // Verifica se o label contém o ano atual
                return label.includes(currentYear) 
                    ? 'rgba(46, 125, 50, 0.7)'   // Verde para o ano atual
                    : 'rgba(120, 144, 156, 0.7)'; // Azul acinzentado para anos anteriores
            });
            
            // Cria o novo gráfico
            new Chart(canvasElement, {
                type: 'bar',
                data: {
                    labels: filteredLabels,
                    datasets: [{
                        label: 'Quantidade de Documentos',
                        data: filteredCounts,
                        backgroundColor: backgroundColors,
                        borderColor: backgroundColors.map(color => color.replace('0.7', '1')),
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 5,
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Quantidade de Documentos',
                                font: {
                                    weight: 'bold'
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Mês/Ano',
                                font: {
                                    weight: 'bold'
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            callbacks: {
                                label: function(context) {
                                    return `${context.parsed.y} documento(s)`;
                                }
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar o gráfico de distribuição de documentos:', error);
            if (!$('#document-distribution-chart-container').length) {
                const errorHtml = `
                <div class="card mt-4 shadow-sm" id="document-distribution-chart-container">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Distribuição de Documentos por Mês</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted text-center py-4">Não foi possível carregar os dados do gráfico.</p>
                    </div>
                </div>`;
                
                $('#top-searches-chart-container').after(errorHtml);
            }
        }
    });
}
