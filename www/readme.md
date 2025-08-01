
# Instruções Gerais

## Instalação

* Copie o código para o diretório de publicação do Apache (Ex: /var/www)
* O diretório *default* de publicação deve ser /public
* O diretório */public/files* deve existir e conter os diretórios/arquivos (treeview)

## PDF
Instalar ***pdftotxt*** para converter pdf em texto:
https://www.xpdfreader.com/download.html

## OCR
Instalar ***ocrMyPdf*** para gerar um PDF pesquisável a partir de um PDF regular:
https://www.xpdfreader.com/download.html


## Plugin TreeView
A aplicação utiliza o plugir TreeView para renderizar a árvore de diretórios e arquivos.

https://github.com/jonmiles/bootstrap-treeview
https://jonmiles.github.io/bootstrap-treeview/ 


## PHP

### php.ini

Abra o php.ini e altere as seguintes diretivas:

`max_input_vars=10000`

`upload_max_filesize = 100M`

`post_max_size = 100M`

## Apache

### habilitar módulo *rewrite*

Execute no terminal:
`sudo a2enmod rewrite`

Altere a diretiva de None para All:
`AllowOverride All`

```
Ex:
<Directory /var/www/>
	Options Indexes FollowSymLinks
	AllowOverride All
	Require all granted
</Directory>
```

Restart o serviço:

`sudo service apache2 restart`









 
