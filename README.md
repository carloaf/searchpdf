# SearchPDF

Um sistema de busca em documentos PDF com recursos avançados de pesquisa e indexação.

## Visão Geral

SearchPDF é uma aplicação web desenvolvida para permitir a busca rápida e eficiente em arquivos PDF. O sistema indexa os arquivos e permite pesquisas por conteúdo e por metadados, facilitando a recuperação de informações em grandes coleções de documentos.

## Características

- Indexação automática de documentos PDF
- Busca por conteúdo de texto completo
- Visualização da estrutura de arquivos em árvore
- Filtros de busca (case sensitive, palavra inteira)
- Estatísticas de uso e documentos
- Interface responsiva e moderna

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

- `/www` - Código-fonte da aplicação
- `/www/src` - Arquivos do modelo MVC
- `/www/public` - Arquivos públicos acessíveis via web
- `/www/config` - Arquivos de configuração
- `/uploads` - Diretório para upload de arquivos
- `/mysql` - Dados persistentes do MySQL

## Licença

Este projeto está licenciado sob a licença MIT - veja o arquivo LICENSE para detalhes.

## Autoria

Desenvolvido inicialmente por Taylor Lopes e aprimorado por Augusto.

---

**Nota:** Este software foi desenvolvido para fins educacionais e pode ser adaptado para uso em ambientes de produção conforme necessário.
