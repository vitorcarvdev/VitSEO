# VitSEO

Ferramenta leve de auditoria SEO. Ela analisa uma página pública e separa o que foi observado em:

- problemas comprovados
- recomendações
- riscos potenciais
- informações
- itens não verificáveis

Cada análise existe só durante a requisição. Não há login, banco nem histórico.

O projeto está em desenvolvimento.

## Recursos atuais

- SEO técnico
- performance da requisição HTTP
- mobile
- imagens
- GEO
- E-E-A-T
- Analytics
- subpáginas
- palavras-chave
- semântica
- Open Graph
- X/Twitter Cards
- JSON-LD / Schema
- geração do prompt “Ajustar com IA”

A ausência de um recurso opcional, como Open Graph, Twitter Cards ou JSON-LD, não é tratada como erro obrigatório. JSON-LD inválido pode ser um problema comprovado.

## Como executar localmente

Requisitos reais do projeto:

- PHP 8.1 ou superior, com `curl`, `dom`, `mbstring` e `openssl`
- nenhum framework e nenhuma dependência de Composer
- o arquivo `config/cacert.pem`, já incluído, para a validação TLS

Na pasta do projeto:

```bat
serve.bat
```

`serve.bat` usa `php` se ele estiver no PATH. Abra `http://127.0.0.1:8094/`.

O servidor embutido do PHP também pode ser iniciado assim:

```bash
php -d xdebug.mode=off -d display_errors=0 -S 127.0.0.1:8094 -t public
```

Testes:

```bash
php -d xdebug.mode=off tests/run.php
```
