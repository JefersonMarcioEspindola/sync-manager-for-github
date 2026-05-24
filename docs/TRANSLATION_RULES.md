# Diretrizes de Tradução (Translation Guidelines)

Este repositório suporta três idiomas principais para toda a interface do plugin WordPress:
- **Português (Brasil)** (`pt_BR`)
- **Inglês (EUA)** (`en_US`)
- **Espanhol** (`es_ES`)

Para manter a conformidade do projeto, siga rigorosamente as regras abaixo ao desenvolver novas funcionalidades ou alterar textos de interface:

## 1. Internacionalização no Código PHP
Toda string exibida na interface do usuário deve passar por funções de tradução do WordPress. Use sempre o text domain `sync-manager-for-github`.

Exemplos corretos:
- Para textos comuns: `__( 'Texto exemplo', 'sync-manager-for-github' )`
- Para impressão direta: `_e( 'Texto exemplo', 'sync-manager-for-github' )`
- Para atributos HTML: `esc_attr__( 'Texto exemplo', 'sync-manager-for-github' )`
- Para exibição em HTML: `esc_html_e( 'Texto exemplo', 'sync-manager-for-github' )`

**Aviso Importante:** Evite escapar aspas duplas com barra invertida (`\"`) dentro de strings PHP delimitadas por aspas simples (`'`), use aspas duplas puras no interior do texto (`'Texto com "aspas"'`) para garantir que os analisadores de strings e ferramentas de tradução extraiam as chaves corretamente.

## 2. Atualização dos Arquivos de Tradução
Toda vez que uma nova string for adicionada ou alterada no código, os catálogos de tradução (`.po` e `.mo`) localizados na pasta `sync-manager-for-github/languages/` devem ser atualizados.

> [!IMPORTANT]
> **REGRA OBRIGATÓRIA (CRITICAL RULE):**
> Todo e qualquer novo texto introduzido ou modificado na interface do usuário **DEVE** possuir sua respectiva versão nos 3 idiomas suportados: **Português (Brasil)**, **Inglês** e **Espanhol**. Não é permitido adicionar novas funcionalidades ou textos sem prover as 3 traduções.

Para manter os catálogos e compilar os binários de forma simplificada, siga este fluxo:
1. Abra o arquivo de script utilitário `update_translations.js` (localizado nos scripts de desenvolvimento/scratch).
2. Adicione a nova chave (em português) dentro da variável `translationsDb` mapeando suas traduções em inglês (`en_US`) e espanhol (`es_ES`):
   ```javascript
   "Sua nova string em português": {
     en_US: "Your new string in English",
     es_ES: "Su nueva cadena en español"
   }
   ```
3. Execute o script (`node update_translations.js`) para regenerar as estruturas e compilar os binários `.mo`.

## 3. Padrão de Idiomas
- **Origem (msgid):** O texto original inserido no código PHP deve ser em **Português (Brasil)**.
- **Tradução:** As traduções para Inglês e Espanhol devem ser precisas, mantendo qualquer placeholder formatado (`%s`, `%1$s`, etc.) idêntico ao original.

