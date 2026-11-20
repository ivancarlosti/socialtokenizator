# Fix: clique na caixa de colagem não abre o seletor de arquivos

## Context

As telas de upload (`/admin/upload`) e edição (`/admin/images/{uuid}/edit`) possuem uma
"paste zone" (`#paste-zone`) com o texto "Clique aqui e pressione Ctrl+V para colar uma
imagem". O CSS define `cursor: pointer` e o texto convida ao clique, mas o handler de
clique apenas chamava `pasteZone.focus()` — nunca `fileInput.click()`. Resultado: colar
com Ctrl+V funcionava, mas clicar na caixa não abria o explorador de arquivos.

O código de clipboard está duplicado nas duas views (padrão atual do projeto: scripts
inline por view, sem `resources/js`).

## Goal

Fazer o clique (e o acionamento por teclado) na paste zone abrir o seletor nativo de
arquivos, sem quebrar o fluxo de colar (Ctrl+V), de drag-and-drop e do botão de limpar
prévia.

## Steps

1. `resources/views/admin/upload.blade.php`
   - No listener `click` de `#paste-zone`: manter `pasteZone.focus()` e adicionar
     `fileInput.click()`.
   - Adicionar listener `keydown` em `#paste-zone`: `Enter`/`Space` disparam
     `fileInput.click()` (acessibilidade, já que a zona tem `tabindex="0"`).
2. `resources/views/admin/edit.blade.php`
   - Aplicar exatamente as mesmas alterações (mesmo padrão de código).
3. Nenhuma mudança de CSS: `input.click()` ocorre dentro do gesto do usuário e as
   classes/estilos da paste zone permanecem iguais. Não é necessário `npm run build`.
4. Não alterar o handler de `document` `paste`, o `change` do `fileInput`, o
   drag-and-drop nem o botão `#paste-clear-btn` (que já usa `e.stopPropagation()`,
   portanto limpar a prévia não abre o explorador).

## Risks

- Baixo: `fileInput.click()` só é chamado a partir de um gesto do usuário, sem risco de
  bloqueio de popup.
- Com prévia visível, clicar na imagem também abre o seletor — comportamento
  considerado aceitável (clique = escolher/repor arquivo).
- Lógica duplicada em duas views: correções futuras precisam ser replicadas nos dois
  arquivos (padrão já existente no projeto).
- Colar continua funcionando globalmente porque o handler de `paste` está em `document`
  e só ignora `input`/`textarea`.
