// Guarda de inicialização para prevenir execução múltipla do script
if (!window.searchAppInitialized) {
    window.searchAppInitialized = true;

    $(document).ready(function() {

        const cssVar = (name, fallback = '') => {
            const value = getComputedStyle(document.documentElement).getPropertyValue(name);
            return value ? value.trim() || fallback : fallback;
        };

        const clamp01 = (value) => {
            const number = Number(value);
            if (!Number.isFinite(number)) {
                return 0;
            }
            if (number < 0) return 0;
            if (number > 1) return 1;
            return number;
        };

        const toRgbaString = ({ r, g, b, a }) => {
            const red = Math.round(Math.min(Math.max(r, 0), 255));
            const green = Math.round(Math.min(Math.max(g, 0), 255));
            const blue = Math.round(Math.min(Math.max(b, 0), 255));
            const alpha = Math.round(clamp01(a) * 1000) / 1000;
            return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
        };

        const parseColorString = (color) => {
            if (!color) return null;
            const normalized = color.trim();
            if (!normalized) return null;

            if (normalized.startsWith('#')) {
                let hex = normalized.slice(1);
                if (hex.length === 3) {
                    hex = hex.split('').map((char) => char + char).join('');
                }
                if (hex.length === 6) {
                    const value = parseInt(hex, 16);
                    if (Number.isNaN(value)) {
                        return null;
                    }
                    return {
                        r: (value >> 16) & 255,
                        g: (value >> 8) & 255,
                        b: value & 255,
                        a: 1
                    };
                }
                if (hex.length === 8) {
                    const value = parseInt(hex, 16);
                    if (Number.isNaN(value)) {
                        return null;
                    }
                    return {
                        r: (value >> 24) & 255,
                        g: (value >> 16) & 255,
                        b: (value >> 8) & 255,
                        a: ((value & 255) / 255)
                    };
                }
            }

            const rgbaMatch = normalized.match(/rgba?\(([^)]+)\)/i);
            if (rgbaMatch) {
                const parts = rgbaMatch[1].split(',').map((part) => part.trim());
                if (parts.length >= 3) {
                    const r = parseFloat(parts[0]);
                    const g = parseFloat(parts[1]);
                    const b = parseFloat(parts[2]);
                    const a = parts.length >= 4 ? parseFloat(parts[3]) : 1;
                    if ([r, g, b, a].every((component) => !Number.isNaN(component))) {
                        return { r, g, b, a: clamp01(a) };
                    }
                }
            }

            return null;
        };

        const colorWithAlpha = (color, alpha) => {
            const parsed = parseColorString(color);
            if (!parsed) {
                return color || toRgbaString({ r: 0, g: 0, b: 0, a: clamp01(alpha) });
            }
            return toRgbaString({ ...parsed, a: clamp01(alpha) });
        };

        const mixColors = (colorA, colorB, weight, alphaOverride = null) => {
            const parsedA = parseColorString(colorA);
            const parsedB = parseColorString(colorB);

            if (!parsedA && !parsedB) {
                return colorA || colorB || '';
            }

            if (!parsedA) {
                return alphaOverride !== null ? colorWithAlpha(colorB, alphaOverride) : colorB;
            }

            if (!parsedB) {
                return alphaOverride !== null ? colorWithAlpha(colorA, alphaOverride) : colorA;
            }

            const w = clamp01(weight);
            const mixed = {
                r: parsedA.r * (1 - w) + parsedB.r * w,
                g: parsedA.g * (1 - w) + parsedB.g * w,
                b: parsedA.b * (1 - w) + parsedB.b * w,
                a: alphaOverride !== null ? clamp01(alphaOverride) : (parsedA.a * (1 - w) + parsedB.a * w)
            };
            return toRgbaString(mixed);
        };

        const generateGradientPalette = (startColor, endColor, count, options = {}) => {
            const total = Math.max(count, 0);
            if (total === 0) {
                return [];
            }

            const { startAlpha = 0.85, endAlpha = 0.55 } = options;
            if (total === 1) {
                return [mixColors(startColor, endColor, 0, startAlpha)];
            }

            const palette = [];
            for (let i = 0; i < total; i++) {
                const weight = i / (total - 1);
                const alpha = startAlpha + (endAlpha - startAlpha) * weight;
                palette.push(mixColors(startColor, endColor, weight, alpha));
            }
            return palette;
        };

        const themeUtils = {
            cssVar,
            colorWithAlpha,
            generateGradientPalette,
            mixColors,
            parseColorString
        };

        window.searchThemeUtils = themeUtils;

        let dailySearchesChart = null;
        let topTermsChart = null;
        let lastDailyChartData = null;
        let lastTermsChartData = null;

        // ===================================================================
        // 0. PERSONALIZAÇÃO DE TEMA (Perfis de cores)
        // ===================================================================
        const $themePanel = $('#theme-settings-panel');
        const $themeButton = $('#theme-settings-button');

        if ($themePanel.length && $themeButton.length) {
            const THEME_STORAGE_KEY = 'searchpdf_theme_profile';
            const defaultTheme = 'default';
            const themeProfiles = {
                default: {
                    label: 'Padrão',
                    values: {
                        '--primary-color': '#4caf50',
                        '--primary-light': '#81c784',
                        '--primary-dark': '#2e7d32',
                        '--accent-color': '#ff9800',
                        '--text-primary': '#2c3e50',
                        '--text-secondary': '#6c757d',
                        '--bg-color': '#d8edd8',
                            '--card-bg': '#bfdfbfff',
                        '--card-alt-bg': '#f7fcf7',
                        '--border-color': '#b8d6b8',
                        '--success-color': '#8cd39cff',
                        '--warning-color': '#ffc107',
                        '--error-color': '#dc3545',
                        '--tree-connector-color': 'rgba(76, 175, 80, 0.35)',
                        '--tree-connector-hover-color': 'rgba(76, 175, 80, 0.55)',
                        '--tree-active-bg': 'rgba(76, 175, 80, 0.12)',
                        '--tree-hover-bg': 'rgba(76, 175, 80, 0.08)',
                        '--tree-hover-text': '#2e7d32',
                        '--tree-folder-icon': 'rgba(76, 175, 80, 0.85)',
                        '--tree-file-icon': 'rgba(76, 175, 80, 0.65)',
                        '--tree-active-border': 'rgba(76, 175, 80, 0.45)',
                        '--tree-shadow': 'rgba(76, 175, 80, 0.28)',
                        '--tree-scroll-track': 'rgba(76, 175, 80, 0.08)',
                        '--tree-scroll-thumb': 'rgba(76, 175, 80, 0.25)',
                        '--tree-scroll-thumb-hover': 'rgba(76, 175, 80, 0.4)',
                            '--file-tree-bg': '#bfdfbfff',
                        '--muted-text-color': '#6c757d',
                        '--lead-text-color': '#495057',
                        '--stats-strong-color': '#4caf50',
                        '--chart-bar-color': 'rgba(76, 175, 80, 0.75)',
                        '--chart-border-color': 'rgba(46, 125, 50, 1)',
                        '--chart-secondary-color': 'rgba(255, 152, 0, 0.8)',
                        '--chart-tooltip-bg': 'rgba(46, 125, 50, 0.9)',
                        '--chart-tooltip-text': '#e8f5e9',
                        '--search-highlight-bg': 'rgba(76, 175, 80, 0.25)',
                        '--search-highlight-text': '#2e7d32'
                    }
                },
                ocean: {
                    label: 'Azul oceano',
                    values: {
                        '--primary-color': '#1e88e5',
                        '--primary-light': '#64b5f6',
                        '--primary-dark': '#0d47a1',
                        '--accent-color': '#26c6da',
                        '--text-primary': '#1a2733',
                        '--text-secondary': '#546e7a',
                        '--bg-color': '#d0ebff',
                            '--card-bg': '#c0e2faff',
                        '--card-alt-bg': '#f4f9ff',
                        '--border-color': '#a8d1f0',
                        '--success-color': '#2e7d32',
                        '--warning-color': '#ffb300',
                        '--error-color': '#d84315',
                        '--tree-connector-color': 'rgba(30, 136, 229, 0.35)',
                        '--tree-connector-hover-color': 'rgba(30, 136, 229, 0.55)',
                        '--tree-active-bg': 'rgba(30, 136, 229, 0.12)',
                        '--tree-hover-bg': 'rgba(30, 136, 229, 0.08)',
                        '--tree-hover-text': '#0d47a1',
                        '--tree-folder-icon': 'rgba(30, 136, 229, 0.8)',
                        '--tree-file-icon': 'rgba(30, 136, 229, 0.6)',
                        '--tree-active-border': 'rgba(30, 136, 229, 0.4)',
                        '--tree-shadow': 'rgba(30, 136, 229, 0.25)',
                        '--tree-scroll-track': 'rgba(30, 136, 229, 0.08)',
                        '--tree-scroll-thumb': 'rgba(30, 136, 229, 0.25)',
                        '--tree-scroll-thumb-hover': 'rgba(30, 136, 229, 0.4)',
                            '--file-tree-bg': '#c0e2faff',
                        '--muted-text-color': '#546e7a',
                        '--lead-text-color': '#3b4f5c',
                        '--stats-strong-color': '#1e88e5',
                        '--chart-bar-color': 'rgba(30, 136, 229, 0.75)',
                        '--chart-border-color': 'rgba(13, 71, 161, 1)',
                        '--chart-secondary-color': 'rgba(38, 198, 218, 0.75)',
                        '--chart-tooltip-bg': 'rgba(13, 71, 161, 0.9)',
                        '--chart-tooltip-text': '#e3f2fd',
                        '--search-highlight-bg': 'rgba(30, 136, 229, 0.2)',
                        '--search-highlight-text': '#0d47a1'
                    }
                },
                midnight: {
                    label: 'Noite urbana',
                    values: {
                        '--primary-color': '#37474f',                        
                        '--primary-light': '#546e7a',
                        '--primary-dark': '#102027',
                        '--accent-color': '#ff7043',
                        '--text-primary': '#eceff1',
                        '--text-secondary': '#b0bec5',
                        '--bg-color': '#1a2329',
                        '--card-bg': '#263238',
                        '--card-alt-bg': '#2e3c43',
                        '--border-color': '#455a64',
                        '--success-color': '#26a69a',
                        '--warning-color': '#ffb74d',
                        '--error-color': '#ef5350',
                        '--tree-connector-color': 'rgba(236, 239, 241, 0.25)',
                        '--tree-connector-hover-color': 'rgba(236, 239, 241, 0.45)',
                        '--tree-active-bg': 'rgba(236, 239, 241, 0.08)',
                        '--tree-hover-bg': 'rgba(236, 239, 241, 0.06)',
                        '--tree-hover-text': '#ffb74d',
                        '--tree-folder-icon': '#ff7043',
                        '--tree-file-icon': 'rgba(255, 255, 255, 0.7)',
                        '--tree-active-border': 'rgba(255, 112, 67, 0.55)',
                        '--tree-shadow': 'rgba(0, 0, 0, 0.4)',
                        '--tree-scroll-track': 'rgba(236, 239, 241, 0.08)',
                        '--tree-scroll-thumb': 'rgba(255, 112, 67, 0.35)',
                        '--tree-scroll-thumb-hover': 'rgba(255, 112, 67, 0.55)',
                        '--file-tree-bg': '#2e3c43',
                        '--muted-text-color': '#b0bec5',
                        '--lead-text-color': '#cfd8dc',
                        '--stats-strong-color': '#ffb74d',
                        '--chart-bar-color': 'rgba(255, 112, 67, 0.75)',
                        '--chart-border-color': 'rgba(255, 171, 145, 1)',
                        '--chart-secondary-color': 'rgba(38, 166, 154, 0.7)',
                        '--chart-tooltip-bg': 'rgba(55, 71, 79, 0.95)',
                        '--chart-tooltip-text': '#eceff1',
                        '--search-highlight-bg': 'rgba(255, 112, 67, 0.35)',
                        '--search-highlight-text': '#ffe0b2'
                    }
                },
                sunset: {
                    label: 'Pôr do sol',
                    values: {
                        '--primary-color': '#ef6c00',
                        '--primary-light': '#ffb74d',
                        '--primary-dark': '#e65100',
                        '--accent-color': '#ffca28',
                        '--text-primary': '#4e342e',
                        '--text-secondary': '#795548',
                        '--bg-color': '#fcebd9',
                        '--card-bg': '#f7ddc1',
                        '--card-alt-bg': '#fff1e3',
                        '--border-color': '#f0c299',
                        '--success-color': '#8bc34a',
                        '--warning-color': '#fb8c00',
                        '--error-color': '#d84315',
                        '--tree-connector-color': 'rgba(239, 108, 0, 0.35)',
                        '--tree-connector-hover-color': 'rgba(239, 108, 0, 0.55)',
                        '--tree-active-bg': 'rgba(239, 108, 0, 0.12)',
                        '--tree-hover-bg': 'rgba(239, 108, 0, 0.08)',
                        '--tree-hover-text': '#bf360c',
                        '--tree-folder-icon': 'rgba(239, 108, 0, 0.85)',
                        '--tree-file-icon': 'rgba(239, 108, 0, 0.65)',
                        '--tree-active-border': 'rgba(239, 108, 0, 0.45)',
                        '--tree-shadow': 'rgba(239, 108, 0, 0.28)',
                        '--tree-scroll-track': 'rgba(239, 108, 0, 0.08)',
                        '--tree-scroll-thumb': 'rgba(239, 108, 0, 0.25)',
                        '--tree-scroll-thumb-hover': 'rgba(239, 108, 0, 0.4)',
                        '--file-tree-bg': '#f7ddc1',
                        '--muted-text-color': '#8d6e63',
                        '--lead-text-color': '#6d4c41',
                        '--stats-strong-color': '#ef6c00',
                        '--chart-bar-color': 'rgba(239, 108, 0, 0.75)',
                        '--chart-border-color': 'rgba(191, 54, 12, 1)',
                        '--chart-secondary-color': 'rgba(255, 202, 40, 0.8)',
                        '--chart-tooltip-bg': 'rgba(191, 54, 12, 0.9)',
                        '--chart-tooltip-text': '#fff3e0',
                        '--search-highlight-bg': 'rgba(239, 108, 0, 0.25)',
                        '--search-highlight-text': '#4e342e'
                    }
                }
            };

            const $themeOptions = $themePanel.find('.theme-option');
            const $themeCloseButtons = $('#theme-settings-close, #theme-settings-close-bottom');
            const $themeResetButton = $('#theme-reset-button');

            $themeButton.attr('aria-expanded', 'false');

            const getStoredTheme = () => {
                try {
                    return localStorage.getItem(THEME_STORAGE_KEY);
                } catch (error) {
                    return null;
                }
            };

            const storeTheme = (themeId) => {
                try {
                    localStorage.setItem(THEME_STORAGE_KEY, themeId);
                } catch (error) {
                    // Armazenamento indisponível (ex.: modo privado)
                }
            };

            const updateActiveTheme = (themeId) => {
                $themeOptions.each(function() {
                    const $option = $(this);
                    const isActive = $option.data('theme') === themeId;
                    $option.toggleClass('active', isActive)
                           .attr('aria-pressed', isActive);
                    $option.find('.theme-option__check').attr('aria-hidden', isActive ? 'false' : 'true');
                });
            };

            const applyTheme = (themeId, { persist = true } = {}) => {
                const selectedThemeId = themeProfiles[themeId] ? themeId : defaultTheme;
                const profile = themeProfiles[selectedThemeId];

                Object.entries(profile.values).forEach(([cssVar, value]) => {
                    document.documentElement.style.setProperty(cssVar, value);
                });

                if (persist) {
                    storeTheme(selectedThemeId);
                }

                updateActiveTheme(selectedThemeId);
                document.body.setAttribute('data-theme', selectedThemeId);
                document.dispatchEvent(new CustomEvent('searchpdf:themechange', {
                    detail: { themeId: selectedThemeId }
                }));
            };

            const openThemePanel = () => {
                $themePanel.addClass('open').attr('aria-hidden', 'false');
                $themeButton.attr('aria-expanded', 'true');
            };

            const closeThemePanel = () => {
                $themePanel.removeClass('open').attr('aria-hidden', 'true');
                $themeButton.attr('aria-expanded', 'false');
            };

            const toggleThemePanel = () => {
                if ($themePanel.hasClass('open')) {
                    closeThemePanel();
                } else {
                    openThemePanel();
                }
            };

            const storedTheme = getStoredTheme();
            if (storedTheme && themeProfiles[storedTheme]) {
                applyTheme(storedTheme, { persist: false });
            } else {
                applyTheme(defaultTheme, { persist: false });
                storeTheme(defaultTheme);
            }

            $themeButton.on('click', function(event) {
                event.preventDefault();
                toggleThemePanel();
            });

            $themeCloseButtons.on('click', function() {
                closeThemePanel();
            });

            $themeOptions.on('click', function() {
                const themeId = $(this).data('theme');
                applyTheme(themeId);
                closeThemePanel();
            });

            $themeResetButton.on('click', function() {
                applyTheme(defaultTheme);
                closeThemePanel();
            });

            $(document).on('click.themeSettings', function(event) {
                if (!$themePanel.is(event.target) &&
                    $themePanel.has(event.target).length === 0 &&
                    !$themeButton.is(event.target) &&
                    $themeButton.has(event.target).length === 0) {
                    closeThemePanel();
                }
            });

            $(document).on('keydown.themeSettings', function(event) {
                if (event.key === 'Escape') {
                    closeThemePanel();
                }
            });
        }

        // ===================================================================
        // 1. FUNÇÕES DE INICIALIZAÇÃO (Estatísticas e Gráficos)
        // ===================================================================

        // Constante com o maior número atual - definido manualmente
        const MAIOR_NUMERO_ATUAL = 188;
        
        // Função para obter o maior número O_XXX_ dos arquivos do ano atual
        function getMaiorNumeroDocumentoAnoAtual() {
            // Inicializa uma variável para armazenar o maior número encontrado
            let maiorNumero = 0;
            
            // Obtém o ano atual
            const anoAtual = new Date().getFullYear();
            
            // Realiza uma requisição AJAX para obter a lista de arquivos
            return new Promise((resolve) => {
                $.ajax({
                    url: 'files/list/' + anoAtual, // Endpoint para listar arquivos do ano atual
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (Array.isArray(data)) {
                            // Percorre todos os arquivos retornados
                            data.forEach(file => {
                                // Extrai só o nome do arquivo, sem o caminho
                                const fileName = file.split('/').pop();
                                console.log("Analisando arquivo: " + fileName);
                                
                                // Procura o padrão O_XXX com expressão regular
                                const match = fileName.match(/O_(\d{3})/);
                                if (match && match[1]) {
                                    // Converte para número
                                    const numero = parseInt(match[1], 10);
                                    console.log("  - Encontrado número: " + numero);
                                    
                                    // Atualiza o maior número se necessário
                                    if (numero > maiorNumero) {
                                        maiorNumero = numero;
                                        console.log("  - Novo maior número: " + numero);
                                    }
                                }
                            });
                        }
                        console.log("Maior número encontrado: " + maiorNumero);
                        resolve(maiorNumero);
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro ao buscar lista de arquivos do ano atual: ', error);
                        console.error('Status: ', status);
                        console.error('Response: ', xhr.responseText);
                        resolve(0); // Em caso de erro, retorna 0
                    }
                });
            });
        }

        // Função para carregar as estatísticas de contagem via AJAX
        function loadStats() {
            // Define o maior número diretamente como fallback
            window.maiorNumeroDocumento = MAIOR_NUMERO_ATUAL;
            
            // Carrega as estatísticas normais
            $.ajax({
                url: 'stats',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    // Atualiza as estatísticas gerais
                    $('#stats-total').text(data.total || 0);
                    $('#stats-week').text(data.week || 0);
                    
                    // Obtém o maior número da resposta da API, ou usa o valor padrão
                    const maiorNumero = data.max_number > 0 ? data.max_number : MAIOR_NUMERO_ATUAL;
                    window.maiorNumeroDocumento = maiorNumero;
                    
                    // Extrai a data do arquivo com maior número
                    let dataFormatada = '';
                    if (data.max_date) {
                        const partesData = data.max_date.split('-');
                        if (partesData.length === 3) {
                            dataFormatada = `${partesData[2]}/${partesData[1]}/${partesData[0]}`;
                        }
                    }
                    
                    // Se não tiver data, usa a data atual
                    if (!dataFormatada) {
                        const hoje = new Date();
                        const dia = String(hoje.getDate()).padStart(2, '0');
                        const mes = String(hoje.getMonth() + 1).padStart(2, '0');
                        const ano = hoje.getFullYear();
                        dataFormatada = `${dia}/${mes}/${ano}`;
                    }
                    
                    // Define o último arquivo indexado com o maior número do ano atual
                    $('#stats-last-file')
                        .text(`BI ${maiorNumero} - ${dataFormatada}`)
                        .addClass('has-file')
                        .attr('title', data.max_file || `Boletim Interno ${maiorNumero}`);
                    
                    // console.log(`Maior número de documento do ano atual: ${maiorNumero}`);
                    // console.log(`Data do arquivo: ${dataFormatada}`);
                    
                    // Carrega apenas o gráfico de distribuição de documentos
                    loadDocumentDistributionChart();
                },
                error: function() {
                    $('#stats-total').text('-');
                    $('#stats-week').text('-');
                    
                    // Mesmo em caso de erro, exibimos um valor padrão
                    $('#stats-last-file')
                        .text(`BI ${MAIOR_NUMERO_ATUAL} - 01/08/2025`)
                        .addClass('has-file')
                        .attr('title', 'Boletim Interno');
                    console.error('Falha ao carregar estatísticas.');
                }
            });
        }
        
        // Função para formatar o nome do arquivo, extraindo e formatando a data
        function formatFileName(fileName) {
            try {
                // Verifica se o nome do arquivo possui o padrão de data esperado (YYYY-MM-DD)
                if (fileName.length >= 10 && /^\d{4}-\d{2}-\d{2}/.test(fileName)) {
                    // Extrai a data (10 primeiros caracteres) e o restante do nome
                    const dateStr = fileName.substring(0, 10);
                    let remainingName = fileName.substring(11); // Pega o texto após a data
                    
                    // Extrai o número de identificação no formato O_XXX (com ou sem underscore final)
                    let numeroDocumento = '';
                    // Busca o padrão O_ seguido por exatamente 3 dígitos, com ou sem underscore depois
                    const matchO = remainingName.match(/O_(\d{3})(_|$)/);
                    
                    if (matchO && matchO[1]) {
                        // Extrai apenas os 3 dígitos
                        numeroDocumento = matchO[1];
                        
                        // Remove tudo até depois do número se houver um underscore após
                        if (matchO[2] === '_') {
                            const fullMatchPos = remainingName.indexOf(matchO[0]);
                            const afterMatchPos = fullMatchPos + matchO[0].length;
                            remainingName = remainingName.substring(afterMatchPos);
                        }
                    }
                    
                    // Remove a extensão .pdf se presente
                    if (remainingName.toLowerCase().endsWith('.pdf')) {
                        remainingName = remainingName.substring(0, remainingName.length - 4);
                    }
                    
                    // Formata a data para o formato brasileiro (DD/MM/YYYY)
                    const parts = dateStr.split('-');
                    if (parts.length === 3) {
                        const formattedDate = `${parts[2]}/${parts[1]}/${parts[0]}`;
                        
                        // Substitui underscores por espaços e capitaliza para melhor legibilidade
                        remainingName = remainingName.replace(/_/g, ' ').trim();
                        
                        // Define BI como tipo de documento padrão, mas verifica se é BA no caminho
                        const tipoDocumento = fileName.includes('/BA/') ? 'BA' : 'BI';
                        
                        // Usa o número encontrado no arquivo ou o maior número como fallback
                        let numeroFinal = numeroDocumento || window.maiorNumeroDocumento.toString();
                        
                        // Para debug
                        console.log(`Formatando arquivo: ${fileName}`);
                        console.log(`Número extraído: ${numeroDocumento}`);
                        console.log(`Número final: ${numeroFinal}`);
                        console.log(`Data formatada: ${formattedDate}`);
                        
                        // Capitaliza primeira letra de cada palavra para melhor legibilidade
                        remainingName = remainingName.split(' ')
                            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                            .join(' ');
                        
                        // Limita o tamanho do nome para visualização
                        if (remainingName.length > 15) {
                            remainingName = remainingName.substring(0, 15) + '...';
                        }
                        
                        // Monta o resultado final no formato "BI 127 - 11/07/2025"
                        // Usa o maior número encontrado ou o número do arquivo atual
                        let resultado = `${tipoDocumento} ${numeroFinal || ''}`;
                        resultado += ` - ${formattedDate}`;
                        
                        return resultado;
                    }
                }
                
                // Fallback para arquivo sem padrão de data
                return fileName.length > 25 ? fileName.substring(0, 22) + '...' : fileName;
            } catch (e) {
                console.error('Erro ao formatar nome do arquivo:', e);
                return fileName; // Em caso de erro, retorna o nome original
            }
        }

        // Função para carregar e renderizar o gráfico de barras de buscas diárias
        function loadDailyChart() {
            $.ajax({
                url: 'stats/daily',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    const canvasElement = document.getElementById('daily-searches-chart');
                    if (!canvasElement || !data || !data.labels || !data.data) return;
                    lastDailyChartData = data;
                    renderDailyChart(canvasElement, data);
                },
                error: function() {
                    $('#chart-placeholder .card-body').html('<p class="text-muted text-center py-5">Não foi possível carregar os dados do gráfico.</p>');
                }
            });
        }

        function renderDailyChart(canvasElement, data) {
            if (!canvasElement || !data || !data.labels || !data.data) {
                return;
            }

            if (dailySearchesChart) {
                dailySearchesChart.destroy();
            }

            const barBackground = cssVar('--chart-bar-color', 'rgba(46, 125, 50, 0.7)');
            const barBorder = cssVar('--chart-border-color', 'rgba(46, 125, 50, 1)');
            const tooltipBg = cssVar('--chart-tooltip-bg', 'rgba(46, 125, 50, 0.9)');
            const tooltipText = cssVar('--chart-tooltip-text', '#ffffff');

            dailySearchesChart = new Chart(canvasElement, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Nº de Buscas',
                        data: data.data,
                        backgroundColor: barBackground,
                        borderColor: barBorder,
                        borderWidth: 1,
                        hoverBackgroundColor: colorWithAlpha(barBackground, 0.9)
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            },
                            grid: {
                                color: colorWithAlpha(barBorder, 0.15)
                            }
                        },
                        x: {
                            grid: {
                                color: colorWithAlpha(barBorder, 0.08)
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: tooltipBg,
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            },
                            bodyColor: tooltipText,
                            titleColor: tooltipText,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' busca(s)';
                                }
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
        
        // Função para carregar e renderizar o gráfico de termos mais pesquisados
        function loadTermsChart() {
            $.ajax({
                url: 'stats/terms',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    const canvasElement = document.getElementById('top-terms-chart');
                    if (!canvasElement || !data || !data.labels || !data.data) return;

                    // Filtra nomes no lado do cliente (segundo nível de proteção)
                    const commonNames = ['CARLOS', 'AUGUSTO', 'JOSE', 'JOAO', 'ANTONIO', 'MARIA', 'ANA'];
                    
                    // Cria arrays filtrados
                    let filteredLabels = [];
                    let filteredData = [];
                    let filteredColors = [];
                    
                    // Filtra termos que são nomes ou contêm nomes específicos
                    for (let i = 0; i < data.labels.length; i++) {
                        const term = data.labels[i].toUpperCase();
                        let isName = false;
                        
                        // Verifica se o termo contém algum nome comum
                        for (const name of commonNames) {
                            if (term.includes(name)) {
                                isName = true;
                                break;
                            }
                        }
                        
                        if (!isName) {
                            filteredLabels.push(data.labels[i]);
                            filteredData.push(data.data[i]);
                            filteredColors.push(data.colors[i]);
                        }
                    }
                    
                    // Se todos os termos foram filtrados, mostra uma mensagem
                    if (filteredLabels.length === 0) {
                        filteredLabels = ['Sem termos técnicos'];
                        filteredData = [1];
                        filteredColors = ['rgba(200, 200, 200, 0.7)'];
                    }

                    lastTermsChartData = {
                        labels: filteredLabels,
                        data: filteredData
                    };

                    renderTermsChart(canvasElement, lastTermsChartData);
                },
                error: function() {
                    $('#chart-terms-placeholder .card-body').html('<p class="text-muted text-center py-5">Não foi possível carregar os dados dos termos.</p>');
                }
            });
        }

        function renderTermsChart(canvasElement, chartData) {
            if (!canvasElement || !chartData || !chartData.labels || !chartData.data) {
                return;
            }

            if (topTermsChart) {
                topTermsChart.destroy();
            }

            const backgroundColors = generateGradientPalette(
                cssVar('--chart-bar-color', '#2e7d32'),
                cssVar('--chart-secondary-color', '#ff8f00'),
                chartData.labels.length,
                { startAlpha: 0.85, endAlpha: 0.55 }
            );

            const borderColor = cssVar('--card-bg', '#ffffff');
            const tooltipBg = cssVar('--chart-tooltip-bg', 'rgba(0, 0, 0, 0.8)');
            const tooltipText = cssVar('--chart-tooltip-text', '#ffffff');

            topTermsChart = new Chart(canvasElement, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: backgroundColors,
                        borderColor,
                        borderWidth: 1
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 15,
                                color: cssVar('--muted-text-color', '#757575')
                            }
                        },
                        tooltip: {
                            backgroundColor: tooltipBg,
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            },
                            bodyColor: tooltipText,
                            titleColor: tooltipText,
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + ' busca(s)';
                                }
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%'
                }
            });
        }

        // Chama as funções de inicialização
        loadStats();
        loadDailyChart();
        loadTermsChart();

        document.addEventListener('searchpdf:themechange', function() {
            const dailyCanvas = document.getElementById('daily-searches-chart');
            if (dailyCanvas && lastDailyChartData) {
                renderDailyChart(dailyCanvas, lastDailyChartData);
            }

            const termsCanvas = document.getElementById('top-terms-chart');
            if (termsCanvas && lastTermsChartData) {
                renderTermsChart(termsCanvas, lastTermsChartData);
            }
        });

        // ===================================================================
        // 2. LÓGICA DA ÁRVORE DE ARQUIVOS (VERSÃO FINAL)
        // ===================================================================

        // Listener para EXPANDIR/RECOLHER - Atrelado apenas ao nome e à seta
        $('.file-tree').on('click', '.folder-toggle, .folder-name', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            var folderDiv = $(this).closest('.folder-item');
            folderDiv.toggleClass('collapsed');
        });

        // Listener para MARCAR/DESMARCAR - Atrelado apenas ao checkbox
        $('.file-tree').on('change', '.folder-check', function(event) {
            event.stopPropagation();
            var isChecked = this.checked;
            $(this).closest('li').find('input[type="checkbox"]').prop('checked', isChecked);
        });

        function triggerFileDownload($container) {
            const token = $container.data('download-token');
            const route = ($container.data('download-route') || '').toString().replace(/\/$/, '');

            if (token && route) {
                const downloadUrl = route.length ? `${route}/${encodeURIComponent(token)}` : encodeURIComponent(token);
                window.open(downloadUrl, '_blank', 'noopener');
                return true;
            }

            const fallbackLink = $container.find('.file-name').attr('href');
            if (fallbackLink && fallbackLink !== '#') {
                window.open(fallbackLink, '_blank', 'noopener');
                return true;
            }

            return false;
        }

        $('.file-tree').on('click', '.file-item .file-name', function(event) {
            event.preventDefault();
            event.stopPropagation();
            triggerFileDownload($(this).closest('.file-item'));
        });

        $('.file-tree').on('keydown', '.file-item .file-name', function(event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            triggerFileDownload($(this).closest('.file-item'));
        });

        // ===================================================================
        // 3. FUNÇÃO DE BUSCA PRINCIPAL (AJAX)
        // ===================================================================
        function searchFiles() {
            function collectPdfFiles(node, collectedPaths) {
                if (node.path && node.path.toLowerCase().endsWith('.pdf')) {
                    collectedPaths.push(node.path);
                }
                if (node.nodes) {
                    $.each(node.nodes, function(index, childNode) {
                        collectPdfFiles(childNode, collectedPaths);
                    });
                }
            }

            var selectedCheckboxes = $('.file-check:checked');
            var pathFilesSelected = [];
            selectedCheckboxes.each(function() {
                pathFilesSelected.push($(this).data('path'));
            });
            
            pathFilesSelected = [...new Set(pathFilesSelected)];

            if (pathFilesSelected.length === 0) {
                alert('Por favor, selecione pelo menos um arquivo para pesquisar.');
                return;
            }

            $.ajax({
                type: "POST",
                url: "searchFile",
                data: {
                    'search': $('#input-search').val(),
                    'ignore_case': $('#chk-ignore-case').is(':checked'),
                    'exact_match': $('#chk-exact-match').is(':checked'),
                    'path_files': pathFilesSelected
                },
                beforeSend: function() {
                    $('#search-results-container').html('<p class="text-center py-5">Buscando...</p>');
                },
                success: function(data) {
                    var results = typeof data === "string" ? JSON.parse(data) : data;
                    var htmlResult = '';
                    var resultCount = results ? results.length : 0;

                    $('#chart-placeholder').hide();

                    htmlResult += '<h4 class="search-result-title">Foram encontrados ' + resultCount + ' registro(s) para a sua busca.</h4>';

                    if (resultCount > 0) {
                        htmlResult += '<div class="list-group">';
                        $.each(results, function(i, item) {
                            htmlResult += '<div class="list-group-item result-item">';
                            htmlResult += '  <div class="d-flex w-100 justify-content-between align-items-center mb-2">';
                            htmlResult += '    <h5 class="mb-0 result-title">' + item.filename + '</h5>';
                            htmlResult += '    <a href="' + item.path_file + '" target="_blank" class="btn btn-sm btn-outline-primary ms-3">Abrir</a>';
                            htmlResult += '  </div>';
                            htmlResult += '  <p class="mb-1 result-snippet">' + item.text + '</p>';
                            htmlResult += '</div>';
                        });
                        htmlResult += '</div>';
                    } else {
                        htmlResult += '<p class="text-muted text-center">Tente refinar seus termos de busca ou selecionar mais arquivos.</p>';
                    }
                    
                    $('#search-results-container').html(htmlResult);
                },
                error: function() {
                    $('#search-results-container').html('<p class="text-center text-danger">Ocorreu um erro na busca.</p>');
                }
            });
        }

        // ===================================================================
        // 4. EVENTOS DE CLIQUE E INTERAÇÃO DO USUÁRIO
        // ===================================================================

        $('#btn-search').on('click', function() {
            if ($('#input-search').val().length < 2) {
                alert('Por favor, digite pelo menos 2 caracteres.');
                return;
            }
            searchFiles();
        });

        $('#input-search').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#btn-search').click();
            }
        });

        // Evento para acionar indexação manual
        $('#btn-reindex').on('click', function() {
            const $btn = $(this);
            const $icon = $btn.find('i');
            
            // Desabilita o botão e mostra feedback visual
            $btn.prop('disabled', true);
            $icon.addClass('fa-spin');
            $btn.attr('title', 'Indexando...');
            
            $.ajax({
                url: 'run-indexer.php',
                method: 'POST',
                timeout: 300000, // 5 minutos
                success: function(response) {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (data.success) {
                        // Mostra feedback de sucesso
                        $icon.removeClass('fa-sync-alt fa-spin').addClass('fa-check');
                        $btn.addClass('btn-reindex-success');
                        $btn.attr('title', data.message);
                        
                        // Atualiza as estatísticas
                        loadStats();
                        
                        // Restaura o botão após 3 segundos
                        setTimeout(function() {
                            $icon.removeClass('fa-check').addClass('fa-sync-alt');
                            $btn.removeClass('btn-reindex-success');
                            $btn.attr('title', 'Executar indexação de novos arquivos');
                            $btn.prop('disabled', false);
                        }, 3000);
                    } else {
                        // Mostra erro
                        $icon.removeClass('fa-sync-alt fa-spin').addClass('fa-times');
                        $btn.addClass('btn-reindex-error');
                        $btn.attr('title', data.message || 'Erro na indexação');
                        
                        setTimeout(function() {
                            $icon.removeClass('fa-times').addClass('fa-sync-alt');
                            $btn.removeClass('btn-reindex-error');
                            $btn.attr('title', 'Executar indexação de novos arquivos');
                            $btn.prop('disabled', false);
                        }, 3000);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na indexação:', error);
                    
                    // Mostra erro
                    $icon.removeClass('fa-sync-alt fa-spin').addClass('fa-times');
                    $btn.addClass('btn-reindex-error');
                    $btn.attr('title', 'Erro ao executar indexação');
                    
                    setTimeout(function() {
                        $icon.removeClass('fa-times').addClass('fa-sync-alt');
                        $btn.removeClass('btn-reindex-error');
                        $btn.attr('title', 'Executar indexação de novos arquivos');
                        $btn.prop('disabled', false);
                    }, 3000);
                }
            });
        });

        // Inicializa o gráfico de distribuição após a página carregar
        loadDocumentDistributionChart();
    });

} // Fim da guarda de inicialização