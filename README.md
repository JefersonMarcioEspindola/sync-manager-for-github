# CodeSync Manager for GitHub

[🇺🇸 Read in English](#english) | [🇧🇷 Leia em Português](#portugues)

---

## English

**CodeSync Manager for GitHub** is an open-source WordPress plugin that turns any public or private GitHub repository into a managed source for WordPress **plugins and themes**, using **Releases** or **Branches** as the source of truth.

The main use case is fast delivery of bespoke or actively-developed packages: when you publish a release (or push to a branch) on GitHub, WordPress detects it instantly — either via the native update flow or in real time through the optional GitHub webhook — and surfaces it in the standard Plugins / Themes screen, identical to packages from WordPress.org.

---

### 🚀 Key Features

- **Plugin & Theme Support**: Manage both plugins and themes from GitHub repositories in the same dashboard.
- **Real-Time Webhook Sync**: Configure a GitHub webhook (push / release events) and the plugin auto-syncs whenever you push to the repo — no waiting for cron. Each managed package has its own webhook view with live ping status, recent activity log, and a one-click disconnect.
- **CodeSync Checker (Security & Quality Validation)**: Before installing any package, a four-step pipeline runs in-modal:
  1. Download + integrity hash
  2. Static scan for risky code (`eval`, `shell_exec`, `proc_open`, `ALLOW_UNFILTERED_UPLOADS`, unprepared SQL, XSS via superglobals, etc.)
  3. Package structure validation (plugin/theme headers, RequiresPHP, RequiresWP, Text Domain)
  4. File-size and dev-folder check (`.git`, `node_modules`, oversized assets), plus deprecated-function scan
- **Backup & Automatic Rollback**: A backup snapshot is taken before every update. If the install fails, the previous version is restored automatically. Manual rollback is available from the package card.
- **Verification Modal with Advanced Options**: A centered modal with `backdrop-filter` blur runs the checker, lets you pick the source (release or branch) and the base subfolder before installing.
- **Manual Branch and Subfolder Selection**: Install from a nested subfolder (e.g. `includes/`, `my-plugin/`) or from any branch. The unpacker isolates the subfolder contents, discards the rest of the ZIP, and renames the folder to the canonical slug.
- **Native WordPress Updates**: Updates plug into the core WordPress updater — the native "Update Now" button on the Plugins / Themes screen handles download and unzip via the standard upgrader.
- **First-Install vs Sync Detection**: The post-install modal distinguishes between a fresh install (guides the user to activate, with a one-click activate URL) and a sync over an already-installed package.
- **Branch Fallback**: If a repo has no published releases, the plugin lists and installs straight from branch references.
- **Automatic Repository Filtering**: Non-PHP / non-WordPress repos are hidden from the Add Package modal.
- **Secure Credentials**: GitHub PATs are stored with **AES-256-GCM authenticated encryption**, masked everywhere (`••••••••••••ABCD`), never echoed to HTML.
- **Smart Caching**: GitHub API responses cached in WordPress transients (1 hour) to respect rate limits.
- **Activity Log**: Last 100 events (install, update, sync, webhook ping, rollback, errors) stored with `autoload = no`. The per-repo webhook view shows the 20 most recent with a "older logs not displayed" footer.

---

### 🛠️ Requirements & Security

1. **AES-256-GCM Encryption**: Credentials are encrypted before saving. The encryption key is derived from `AUTH_KEY` and `SECURE_AUTH_KEY` in your `wp-config.php`.
2. **Zero Plaintext Leak**: Tokens are masked (e.g., `••••••••••••••••ABCD`) and never displayed in HTML, form inputs, or error logs.
3. **Compatibility**: Requires PHP 7.4+ (compatible with PHP 8+) and WordPress 5.8+.

---

### 📂 Directory Structure

```text
├── codesync-manager-for-github/          # Core plugin directory
│   ├── assets/
│   │   ├── css/admin.css             # Styles and modal CSS
│   │   └── js/admin.js               # Dashboard logic and AJAX handlers
│   ├── includes/
│   │   ├── class-admin-ui.php        # Admin menu, asset enqueue, HTML rendering
│   │   ├── class-admin-ajax.php      # All AJAX endpoints (connect, install, update, rollback, webhook)
│   │   ├── class-checker.php         # CodeSync Checker — pre-install security & quality scan
│   │   ├── class-webhook.php         # Webhook REST endpoint + auto-sync handler
│   │   ├── class-updater.php         # Native WordPress update hooks, install/rollback engine
│   │   ├── class-manager.php         # Database options, secure directories, activity log
│   │   ├── class-encryption.php      # AES-256-GCM credentials encryption
│   │   └── class-github-api.php      # GitHub REST API client (Trees API, repo/release/branch)
│   ├── languages/                    # Translation catalogs (PT, EN, ES)
│   ├── codesync-manager-for-github.php   # Main entrypoint
│   └── readme.txt                    # WordPress.org plugin readme
│
├── docs/                             # AI context and documentation
│   ├── TRANSLATION_RULES.md          # Translation guidelines
│   └── claude.md                     # AI user preferences (custom context)
│
├── LICENSE                           # Official GPLv2 license
├── README.md                         # Bilingual documentation
└── .gitignore                        # Git ignore patterns
```

---

### 💾 Installation & Setup

1. Download the latest ZIP from the **Releases** tab on GitHub.
2. In WordPress admin: **Plugins → Add New → Upload Plugin**, select the ZIP, install and activate.
3. Open the new **Sync Manager** menu item.
4. Paste a GitHub Personal Access Token (PAT) with `repo` scope to connect.
5. (Optional) For each managed package, open the **Webhook** modal to copy the payload URL + secret and configure the webhook on GitHub for instant auto-sync.

---

<a name="portugues"></a>
## Português

O **CodeSync Manager for GitHub** é um plugin WordPress open-source que transforma qualquer repositório GitHub (público ou privado) em uma fonte gerenciada de **plugins e temas** para WordPress, usando **Releases** ou **Branches** como fonte de verdade.

O caso de uso central é a distribuição ágil de pacotes sob medida ou em desenvolvimento ativo: você publica uma release (ou faz push em um branch) no GitHub e o WordPress detecta instantaneamente — seja pelo fluxo nativo de atualização, seja em tempo real via webhook opcional do GitHub — exibindo o pacote na tela padrão de Plugins / Temas, igual aos pacotes do WordPress.org.

---

### 🚀 Principais Recursos

- **Suporte a Plugins e Temas**: Gerencie plugins e temas vindos do GitHub no mesmo painel.
- **Sincronização em Tempo Real via Webhook**: Configure um webhook do GitHub (eventos push / release) e o plugin sincroniza sozinho a cada push — sem esperar o cron. Cada pacote tem sua própria tela de webhook com status de ping ao vivo, log de atividade recente e botão de desconectar em um clique.
- **CodeSync Checker (Validação de Segurança e Qualidade)**: Antes de instalar qualquer pacote, um pipeline de 4 etapas roda dentro do modal:
  1. Download + hash de integridade
  2. Varredura estática por código de risco (`eval`, `shell_exec`, `proc_open`, `ALLOW_UNFILTERED_UPLOADS`, SQL não preparado, XSS via superglobais, etc.)
  3. Validação da estrutura do pacote (headers de plugin/tema, RequiresPHP, RequiresWP, Text Domain)
  4. Verificação de tamanho de arquivos e pastas de dev (`.git`, `node_modules`, assets gigantes) + scan de funções deprecadas
- **Backup e Rollback Automático**: Snapshot é tirado antes de cada atualização. Se falhar, a versão anterior volta sozinha. Rollback manual também disponível no card do pacote.
- **Modal de Verificação com Opções Avançadas**: Modal centralizado com `backdrop-filter`, roda o checker e permite escolher a origem (release ou branch) e a subpasta base antes da instalação.
- **Seleção Manual de Branch e Subpasta**: Instale de uma subpasta aninhada (ex.: `includes/`, `my-plugin/`) ou de qualquer branch. O extrator isola o conteúdo da subpasta, descarta o resto do ZIP e renomeia para o slug canônico.
- **Integração Nativa com WordPress**: As atualizações passam pelo updater do WordPress — o botão "Atualizar agora" da tela de Plugins / Temas faz download e descompactação via upgrader padrão.
- **Detecção Primeira Instalação vs Sincronização**: O modal pós-instalação diferencia uma instalação nova (guia o usuário para ativar, com URL de ativação em 1 clique) de uma sincronização sobre pacote já instalado.
- **Fallback por Branch**: Se o repo não tem releases, lista e instala direto a partir das branches.
- **Filtragem Automática de Repositórios**: Repos sem estrutura PHP / WordPress não aparecem no modal de adicionar pacote.
- **Credenciais Seguras**: PATs do GitHub armazenados com **criptografia AES-256-GCM autenticada**, mascarados em todos os lugares (`••••••••••••ABCD`), nunca expostos em HTML.
- **Cache Inteligente**: Respostas da API do GitHub em transients do WordPress (1 hora) para respeitar rate limits.
- **Log de Atividade**: Últimos 100 eventos (instalação, update, sync, ping de webhook, rollback, erros) salvos com `autoload = no`. A tela do webhook por repo mostra os 20 mais recentes com aviso "logs mais antigos não exibidos".

---

### 🛠️ Requisitos e Segurança

1. **Criptografia AES-256-GCM**: O token do GitHub é criptografado antes de ser salvo no banco. A chave é derivada das constantes `AUTH_KEY` e `SECURE_AUTH_KEY` do `wp-config.php`.
2. **Exposição Zero**: O token nunca aparece em texto claro no HTML, em campos de formulário ou em logs de erro.
3. **Versões Suportadas**: PHP 7.4+ (compatível com PHP 8+) e WordPress 5.8+.

---

### 📂 Estrutura de Pastas do Repositório

```text
├── codesync-manager-for-github/          # Código principal do plugin
│   ├── assets/
│   │   ├── css/admin.css             # Estilos customizados e modais
│   │   └── js/admin.js               # Lógica do dashboard e chamadas AJAX
│   ├── includes/
│   │   ├── class-admin-ui.php        # Menu admin, enqueue de assets, renderização HTML
│   │   ├── class-admin-ajax.php      # Todos os endpoints AJAX (conectar, instalar, atualizar, rollback, webhook)
│   │   ├── class-checker.php         # CodeSync Checker — varredura pré-instalação
│   │   ├── class-webhook.php         # Endpoint REST do webhook + auto-sync
│   │   ├── class-updater.php         # Hooks de update do WordPress, motor de install/rollback
│   │   ├── class-manager.php         # Opções de banco, diretórios seguros, log de atividade
│   │   ├── class-encryption.php      # Criptografia AES-256-GCM
│   │   └── class-github-api.php      # Cliente REST do GitHub (Trees API, repo/release/branch)
│   ├── languages/                    # Catálogos de tradução (PT, EN, ES)
│   ├── codesync-manager-for-github.php   # Arquivo principal
│   └── readme.txt                    # Readme para o WordPress.org
│
├── docs/                             # Contexto de IA e documentação
│   ├── TRANSLATION_RULES.md          # Diretrizes de tradução
│   └── claude.md                     # Preferências do usuário (contexto de IA)
│
├── LICENSE                           # Licença oficial GPLv2
├── README.md                         # Documentação bilíngue
└── .gitignore                        # Regras de ignore do Git
```

---

### 💾 Instalação e Configuração

1. Baixe o ZIP mais recente na aba **Releases** no GitHub.
2. No painel do WordPress: **Plugins → Adicionar Novo → Enviar Plugin**, selecione o ZIP, instale e ative.
3. Acesse o novo menu **Sync Manager**.
4. Cole um Personal Access Token (PAT) do GitHub com escopo `repo` para conectar.
5. (Opcional) Para cada pacote gerenciado, abra a tela **Webhook** para copiar a Payload URL + secret e configure o webhook no GitHub para sincronização instantânea.

---

## 🔒 License

This project is open-source software licensed under the **GNU General Public License v2 (or later)**. Feel free to use, modify, and contribute!
