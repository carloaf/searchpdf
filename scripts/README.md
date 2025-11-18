# Download Automático de Boletins

Este diretório contém scripts JavaScript para automatizar o download de boletins PDF do sistema SISBOL.

## 📋 Scripts Disponíveis

### 1. `download_boletins.js` (Versão Simples)
Script básico que faz o download sequencial de todos os boletins da página.

### 2. `download_boletins_avancado.js` (Versão Avançada) ⭐
Script com recursos extras:
- Extração de informações dos boletins
- Barra de progresso no console
- Estatísticas detalhadas
- Listagem em tabela
- Instruções de movimentação dos arquivos

## 🚀 Como Usar

### Passo 1: Acessar a Página de Boletins
1. Abra o navegador e acesse: `http://10.166.66.246/band/baixar_boletim.php`
2. Faça login no sistema
3. Selecione:
   - **Tipo de Boletim**: BOLETIM INTERNO
   - **Ano**: 2024
   - **Mês**: Julho (ou o mês desejado)

### Passo 2: Abrir o Console do Navegador
- **Chrome/Edge**: Pressione `F12` ou `Ctrl+Shift+J` (Linux/Windows) / `Cmd+Opt+J` (Mac)
- **Firefox**: Pressione `F12` ou `Ctrl+Shift+K` (Linux/Windows) / `Cmd+Opt+K` (Mac)
- Vá para a aba **Console**

### Passo 3: Copiar e Executar o Script

#### Opção A: Script Avançado (Recomendado)
```javascript
// Copie todo o conteúdo do arquivo download_boletins_avancado.js
// Cole no console e pressione Enter
```

#### Opção B: Script Simples
```javascript
// Copie todo o conteúdo do arquivo download_boletins.js
// Cole no console e pressione Enter
```

### Passo 4: Aguardar os Downloads
- O script iniciará o download sequencial de todos os PDFs
- Os arquivos serão salvos na pasta **Downloads** do seu navegador
- Você verá o progresso no console

### Passo 5: Mover Arquivos para o Destino Final

Após o download, execute no terminal:

```bash
# Para Julho/2024
mv ~/Downloads/2024-07-*.pdf "/home/augusto/workspace/searchpdf/uploads/BI 2024/Julho/"

# Para outros meses (exemplo: Janeiro)
mv ~/Downloads/2024-01-*.pdf "/home/augusto/workspace/searchpdf/uploads/BI 2024/Janeiro/"
```

## 📁 Estrutura de Pastas

```
/home/augusto/workspace/searchpdf/uploads/BI 2024/
├── Janeiro/
├── Fevereiro/
├── Marco/
├── Abril/
├── Maio/
├── Junho/
├── Julho/
├── Agosto/
├── Setembro/
├── Outubro/
├── Novembro/
└── Dezembro/
```

## ⚙️ Configurações

No script avançado, você pode ajustar:

```javascript
const CONFIG = {
    delayBetweenDownloads: 2500, // Delay em milissegundos (2.5 segundos)
    showProgress: true,           // Exibir progresso
    baseUrl: '...'               // URL base (ajustado automaticamente)
};
```

## 🔧 Solução de Problemas

### O navegador bloqueia múltiplos downloads?
1. Quando aparecer a notificação do navegador, clique em **"Permitir"** ou **"Allow multiple downloads"**
2. Configure o navegador para sempre permitir downloads do site

### Os arquivos não estão sendo baixados?
1. Verifique se você está logado no sistema
2. Certifique-se de que está na página correta (baixar_boletim.php)
3. Verifique se há boletins listados na página

### Como baixar boletins de outros meses?
1. Na página do SISBOL, selecione o mês desejado
2. Execute o script novamente
3. Mova os arquivos para a pasta do mês correspondente

## 📝 Exemplo Completo

```bash
# 1. Criar estrutura de pastas (já foi feito)
mkdir -p "/home/augusto/workspace/searchpdf/uploads/BI 2024/Julho"

# 2. Após executar o script no navegador e baixar os arquivos:
mv ~/Downloads/2024-07-*.pdf "/home/augusto/workspace/searchpdf/uploads/BI 2024/Julho/"

# 3. Verificar arquivos baixados
ls -lh "/home/augusto/workspace/searchpdf/uploads/BI 2024/Julho/"

# 4. Contar arquivos
ls "/home/augusto/workspace/searchpdf/uploads/BI 2024/Julho/" | wc -l
```

## 🎯 Automatização para Todo o Ano

Para baixar todos os meses de 2024, repita o processo:

1. Selecione o mês na página
2. Execute o script no console
3. Mova os arquivos para a pasta do mês
4. Repita para o próximo mês

## 💡 Dicas

- **Delay entre downloads**: O script usa um delay de 2.5 segundos entre downloads para evitar sobrecarga no servidor
- **Verificação de downloads**: Use o console do navegador para acompanhar o progresso
- **Backup**: Considere fazer backup dos arquivos após o download completo
- **Indexação**: Após mover todos os arquivos, execute o indexador do SearchPDF para torná-los pesquisáveis

## 📊 Estatísticas Esperadas

Para julho/2024 (baseado no HTML fornecido):
- **Total de boletins**: 23 arquivos
- **Tamanho médio**: ~45 KB por arquivo
- **Tempo estimado**: ~60 segundos (com delay de 2.5s)

## 🔐 Segurança

- O script só funciona quando você está autenticado no sistema
- Não armazena nem transmite credenciais
- Executa apenas no contexto da página atual
- Não modifica dados no servidor

## 📞 Suporte

Se encontrar problemas:
1. Verifique o console do navegador para mensagens de erro
2. Certifique-se de estar usando um navegador moderno (Chrome, Firefox, Edge)
3. Confirme que o JavaScript está habilitado
4. Verifique a conexão com o servidor (10.166.66.246)
