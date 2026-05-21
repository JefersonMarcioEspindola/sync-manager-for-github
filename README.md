# GitHub Sync Manager

O **GitHub Sync Manager** é um plugin WordPress open-source desenvolvido para simplificar a distribuição, instalação e atualização de outros plugins hospedados em repositórios do GitHub (públicos ou privados), utilizando as **Releases** do repositório como a única fonte de verdade (*Source of Truth*).

O caso de uso central é a distribuição ágil de plugins sob medida ou em desenvolvimento ativo: o desenvolvedor cria e publica uma nova release no GitHub, e o WordPress automaticamente identifica e exibe a atualização disponível na tela padrão de Plugins, exatamente como ocorre com plugins hospedados no diretório oficial do WordPress.org.

---

## 🚀 Principais Recursos

- **Integração Nativa de Atualizações**: Quando uma nova release é detectada, o plugin se acopla ao ecossistema nativo do WordPress. A atualização é executada pelo botão nativo "Atualizar agora" na tela principal de Plugins, preservando os ganchos (*hooks*) de atualização oficiais.
- **Conectividade Segura**: Autenticação simplificada por meio de Personal Access Tokens (PAT) do GitHub, com suporte total tanto a **Classic PATs** quanto a **Fine-Grained PATs**.
- **Resolução Canônica de Slugs**: Corrige automaticamente o nome da pasta do plugin baixada do GitHub (que costuma conter tags ou hashes, como `meu-plugin-1.0.0`) para o slug real definido no cabeçalho `Text Domain` ou nome do arquivo do plugin. Isso evita a desativação acidental do plugin durante atualizações.
- **Cópia de Segurança & Recuperação Automática**: Antes de substituir os arquivos de um plugin durante uma atualização, o sistema cria uma cópia de segurança direta da pasta existente. Se a atualização falhar por qualquer motivo (erros de escrita, arquivos corrompidos, queda de rede), a versão anterior é restaurada imediatamente.
- **Cache Inteligente de Releases**: As consultas às APIs do GitHub são salvas em transients do WordPress por **1 hora**, otimizando a performance e protegendo sua conta contra os limites de requisições do GitHub (Rate Limit).
- **Proteção de Diretórios Temporários**: As pastas locais de download (`gsm-temp`) e de backup (`gsm-backups`) criadas dentro de `wp-content/uploads/` são blindadas contra acessos públicos diretos via arquivos `.htaccess` (`Deny from all`) e silenciadores `index.php`.
- **Prevenção de Deadlocks**: O plugin impede a auto-gestão de seus próprios arquivos para evitar conflitos catastróficos durante o seu próprio fluxo de atualização.
- **Aba de Logs Completa**: Histórico em tempo real das últimas 100 ações executadas (instalações, atualizações, conexões, verificações periódicas e falhas de rede), salvas no banco de dados sem prejudicar o tempo de carregamento da área pública do site (`autoload = no`).

---

## 🛠️ Requisitos e Segurança do Token

1. **Chaves de Segurança do WordPress**: Para garantir a segurança dos tokens salvos, o plugin utiliza **criptografia autenticada AES-256-GCM** (uma das mais seguras da atualidade). A chave de criptografia é derivada de forma única com base nas constantes `AUTH_KEY` e `SECURE_AUTH_KEY` configuradas no arquivo `wp-config.php`.
   - Se estas chaves não estiverem configuradas ou mantiverem os valores padrão de fábrica do WordPress, o plugin recusará a conexão por motivos de segurança até que sejam alteradas para strings fortes.
2. **Exposição Zero**: O token nunca é exibido em texto limpo no HTML, campos de formulário, respostas de API ou histórico de logs. Na interface de configurações, o token salvo aparece sempre mascarado (exibindo apenas os últimos 4 caracteres, ex: `••••••••••••••••ABCD`).

---

## 📂 Estrutura de Pastas do Repositório

```text
├── github-sync-manager/         # Pasta principal contendo todo o código do plugin
│   ├── assets/
│   │   ├── css/admin.css        # Estilos customizados perfeitamente integrados ao WP-Admin
│   │   └── js/admin.js          # Lógica de abas, busca em tempo real e chamadas AJAX assíncronas
│   ├── includes/
│   │   ├── class-admin.php      # Telas administrativas e endpoints AJAX de controle
│   │   ├── class-encryption.php # Criptografia AES-256-GCM das credenciais
│   │   ├── class-github-api.php # Cliente de chamadas REST à API do GitHub
│   │   ├── class-manager.php    # Controle de banco de dados, diretórios e garbage collector
│   │   └── class-updater.php    # Acoplamento de atualizações nativas do WordPress
│   └── github-sync-manager.php  # Arquivo principal do plugin (iniciador, autoloader e WP-Cron)
│
└── releases/                    # Pasta de distribuição contendo os pacotes compactados
    └── github-sync-manager-v0.0.1.zip
```

---

## 💾 Instalação e Configuração rápida

1. Acesse a pasta `releases/` deste repositório e baixe o arquivo `.zip` da versão desejada.
2. No painel administrativo do seu WordPress, vá em **Plugins > Adicionar Novo > Enviar Plugin** e faça o upload do arquivo ZIP.
3. Ative o plugin.
4. Acesse o menu **GitHub Sync** que aparecerá no menu lateral do WordPress.
5. Crie um Personal Access Token (PAT) no seu GitHub (com escopo `repo` ou permissões equivalentes nos repositórios que deseja gerenciar) e cole na tela inicial para liberar o painel de controle.

---

## 🔒 Licença

Este projeto é um software livre e de código aberto licenciado sob a licença **MIT**. Sinta-se à vontade para utilizar, modificar e contribuir!
