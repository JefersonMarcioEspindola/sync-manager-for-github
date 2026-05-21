=== GitHub Sync Manager ===
Contributors: JefersonMarcioEspindola
Tags: github, sync, updates, plugin manager, private plugins, release updates
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 0.0.2
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

= 0.0.2 =
* Adicionado cabeçalhos de licenciamento GPLv2 or later para conformidade com o repositório oficial WordPress.org.
* Criados arquivos oficiais readme.txt e LICENSE.
* Organizada a estrutura de repositório e pastas para publicação.

= 0.0.1 =
* Versão de lançamento inicial contendo recursos base de criptografia AES-256-GCM, gerenciador de APIs, verificador automático via WP-Cron e dashboard administrativo.
