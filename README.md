# SearchPDF

Um sistema de busca em documentos PDF com recursos avançados de pesquisa, indexação e **upload seguro com autenticação**.

## Visão Geral

SearchPDF é uma aplicação web desenvolvida para permitir a busca rápida e eficiente em arquivos PDF. O sistema indexa os arquivos e permite pesquisas por conteúdo e por metadados, facilitando a recuperação de informações em grandes coleções de documentos.

## Características

- ✅ Indexação automática de documentos PDF
- ✅ Busca por conteúdo de texto completo
- ✅ Visualização da estrutura de arquivos em árvore
- ✅ Filtros de busca (case sensitive, palavra inteira)
- ✅ Estatísticas de uso e documentos
- ✅ Interface responsiva e moderna
- 🆕 **Sistema de autenticação de usuários**
- 🆕 **Upload protegido de PDFs com controle de permissões**
- 🆕 **Auditoria completa de uploads**
- 🆕 **Organização automática por ano/mês/categoria**

## Tecnologias Utilizadas

- PHP 7.4+
- MySQL/MariaDB
- Slim Framework 4
- Twig Template Engine
- Docker & Docker Compose
- Bootstrap & jQuery
- OCR para indexação de PDFs

## Requisitos

- Docker e Docker Compose
- Servidor Web (para produção)
- MySQL/MariaDB (para produção)

## Instalação com Docker

1. Clone este repositório:
```bash
git clone https://github.com/carloaf/searchpdf.git
cd searchpdf
```

2. Configure as variáveis de ambiente (opcional):
```bash
cp .env.example .env
# Edite o arquivo .env conforme necessário
```

3. Inicie os contêineres Docker:
```bash
docker-compose up -d
```

4. Acesse a aplicação:
http://localhost:8080 (ou a porta configurada)

## Desenvolvimento

Este projeto segue o fluxo de trabalho de Git baseado em branches:

- `main`: Branch principal que contém o código estável e pronto para produção
- `dev`: Branch de desenvolvimento onde novas funcionalidades são integradas antes de ir para produção

Para contribuir com o projeto:

1. Clone o repositório
2. Crie uma branch a partir de `dev` para sua funcionalidade: `git checkout -b feature/nova-funcionalidade`
3. Faça suas alterações e adicione commits: `git commit -am "Adiciona nova funcionalidade"`
4. Envie para o GitHub: `git push origin feature/nova-funcionalidade`
5. Crie um Pull Request para a branch `dev`

## Configuração

A configuração da aplicação pode ser encontrada em `www/config/settings.php`. 
Uma cópia de exemplo é fornecida como `www/config/settings-dist.php`.

## Estrutura de Diretórios


## 🔐 Sistema de Upload com Autenticação

**Novo!** Sistema completo de upload de PDFs com autenticação e controle de acesso.

### Características do Sistema de Upload

- ✅ **Autenticação de usuários** com senha criptografada (bcrypt)
- ✅ **Controle de permissões** por roles (admin, uploader, viewer)
- ✅ **Validação de arquivos** (tipo PDF, tamanho máximo 50MB)
- ✅ **Organização automática** em pastas por ano/mês/categoria
- ✅ **Auditoria completa** de todos os uploads
- ✅ **Interface intuitiva** com histórico de uploads
- ✅ **Preparado para indexação automática**
- 🆕 **Acesso público à busca** (não requer autenticação)
- 🆕 **Login via modal** na página principal (botão com ícone de chave)

### Acesso Rápido

```bash
# Deploy completo (criar tabelas + configurar permissões)
./scripts/deploy_upload_system.sh

# Acesso público (busca)
http://localhost:8080/

# Acesso admin/upload (clique no ícone de chave 🔑 na página principal)
```

### Credenciais Padrão

⚠️ **Alterar em produção!**

- **Admin**: `admin` / `admin123`
- **Uploader**: `uploader` / `uploader123`
- **Viewers**: Não precisam de autenticação (acesso público à busca)

### Documentação Completa

- 📚 [Sistema de Upload - Guia Completo](docs/UPLOAD_SYSTEM.md)
- 🚀 [Deploy Rápido](docs/QUICK_DEPLOY.md)

## Licença

Este projeto está licenciado sob a licença MIT - veja o arquivo LICENSE para detalhes.

## Autoria

Desenvolvido inicialmente por Augusto <carloafernandes@gmail.com>.

**Nota:** Este software foi desenvolvido para fins educacionais e pode ser adaptado para uso em ambientes de produção conforme necessário.


## Cron de indexação automática

Para manter a base de dados atualizada com os PDFs enviados para `www/public/uploads`, foi adicionada uma rotina automatizada de indexação.

### Scripts disponíveis

- `scripts/indexacao_automatica.sh`: shell script chamado pelo cron. Exporta as variáveis necessárias e executa `php www/indexer.php`, registrando logs em `logs/`.
- `scripts/crontab_indexador`: template com a configuração do cron (segunda a sexta-feira, das 08h às 18h, a cada 2 horas).
- `scripts/instalar_cron.sh`: instala/atualiza o cron usando o template acima.

### Instalação do cron

1. Garanta que o PHP e as dependências (`pdftotext`, `pdfinfo`, etc.) estão no PATH.
2. Torne o instalador executável e execute-o a partir da raiz do projeto:

```bash
chmod +x scripts/instalar_cron.sh
./scripts/instalar_cron.sh
```

O script criará (ou atualizará) a entrada do cron do usuário atual com a seguinte agenda:

```
0 8-18/2 * * 1-5 /home/augusto/workspace/searchpdf/scripts/indexacao_automatica.sh
```

### Logs

- `logs/cron_indexador.log`: mensagens de execução do shell script.
- `logs/indexador_output.log`: saída completa do `indexer.php` em cada execução.

### Personalizações

- Para alterar o diretório de uploads, defina a variável de ambiente `UPLOADS_DIR` antes de executar o script ou ajuste `indexer.php`.
- Se precisar apontar para um binário PHP específico, defina `SEARCHPDF_PHP_BIN` com o caminho completo.
- Quando o PHP rodar dentro de um container Docker, informe o nome do container via `SEARCHPDF_PHP_CONTAINER` (padrão `searchpdf_app`).
