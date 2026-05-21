=== GitHub Sync Manager ===
Contributors: JefersonMarcioEspindola
Tags: github, sync, updates, plugin manager, private plugins, release updates
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 0.0.7
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Permite ao administrador do site instalar e manter atualizados outros plugins WordPress hospedados em repositórios do GitHub, públicos ou privados.

== Description ==

O **GitHub Sync Manager** é um plugin desenvolvido para simplificar a distribuição, instalação e atualização de outros plugins hospedados em repositórios do GitHub (públicos ou privados), utilizando as **Releases** do repositório como a única fonte de verdade (*Source of Truth*).

O caso de uso central é a distribuição ágil de plugins sob medida ou em desenvolvimento ativo: o desenvolvedor cria e publica uma nova release no GitHub, e o WordPress automaticamente identifica e exibe a atualização disponível na tela padrão de Plugins, exatamente como ocorre com plugins hospedados no diretório oficial do WordPress.org.

== Installation ==

1. Faça o download da última release em ZIP do plugin.
2. No painel do seu WordPress, navegue até **Plugins > Adicionar Novo > Enviar Plugin** e faça o upload do arquivo ZIP.
3. Ative o plugin.
4. Acesse a nova página **GitHub Sync** no menu lateral do painel do WordPress.
5. Insira um Personal Access Token (PAT) do GitHub para conectar a sua conta.

== Frequently Asked Questions ==

= O plugin é compatível com repositórios privados? =
Sim! Ao usar um Personal Access Token com escopo de permissão adequado, o plugin consegue se conectar e baixar atualizações de repositórios privados com total segurança.

= Como é feita a comparação de versões? =
O sistema realiza uma comparação utilizando Semantic Versioning (SemVer) entre a tag da última release publicada no GitHub e a versão declarada no cabeçalho do arquivo principal do plugin instalado localmente.

= O meu token de acesso (PAT) está seguro? =
Absolutamente. O token é criptografado usando o algoritmo autenticado AES-256-GCM antes de ser salvo no banco de dados. A chave criptográfica é derivada de forma única com base em chaves privadas (`AUTH_KEY`/`SECURE_AUTH_KEY`) do seu arquivo `wp-config.php`, garantindo proteção de nível militar.

== Changelog ==

= 0.0.7 =
* Corrigido erro de verificação impedindo a abertura do modal de instalação para repositórios sem releases.
* Implementado fallback automático para versão 0.0.0 na branch padrão em caso de falha de detecção do plugin, permitindo a instalação e seleção manual de subpasta.

= 0.0.6 =
* Adicionado pop-up (modal) de verificação e instalação de plugins.
* Implementada a seleção manual de ramo/versão (ref) e de pasta base (subfolder) no repositório.
* Adicionado badge indicador de subpastas configuradas na listagem de plugins gerenciados.
* Adicionadas novas traduções em português, espanhol e inglês.

= 0.0.5 =
* Corrigido suporte a caminhos com espaços e caracteres especiais utilizando codificação rawurlencode compatível com a API do GitHub.
* Corrigido bug de ordenação instável com usort no PHP 8+.
* Aumentado o limite de subpastas varridas de 3 para 5 diretórios.
* Adicionado logs detalhados de erros de API na aba Histórico de Logs.

= 0.0.4 =
* Adicionado suporte a instalação por branch como fallback quando não há releases publicadas no GitHub.
* Adicionado indicador visual de branch nos cards de plugins gerenciados.
* Adicionado botão para copiar prompt estruturado de IA para auxiliar na criação de releases no GitHub.

= 0.0.3 =
* Filtragem automática de repositórios na tela de Adicionar Plugin, exibindo apenas projetos PHP ou que possuam termos relacionados a plugins WordPress.
* Adicionado banner informativo avisando sobre a filtragem.
* Adicionado badge indicando a linguagem principal dos repositórios.

= 0.0.2 =
* Adicionado cabeçalhos de licenciamento GPLv2 or later para conformidade com o repositório oficial WordPress.org.
* Criados arquivos oficiais readme.txt e LICENSE.
* Organizada a estrutura de repositório e pastas para publicação.

= 0.0.1 =
* Versão de lançamento inicial contendo recursos base de criptografia AES-256-GCM, gerenciador de APIs, verificador automático via WP-Cron e dashboard administrativo.
