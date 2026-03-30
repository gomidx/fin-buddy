# Bug: Painel de notificações cortado no mobile

**Data:** 2026-03-29
**Severidade:** Baixa — funcionalidade acessível, mas layout quebrado no mobile
**Status:** Solução aplicada, não resolvida — requer investigação adicional

---

## Sintoma reportado

No mobile, ao clicar no ícone de notificações no header, o painel abre parcialmente fora da tela (cortado à esquerda), impedindo a visualização do conteúdo.

---

## Análise da causa raiz

O painel de notificações é posicionado com `position: absolute` relativo ao botão do sino (`.notification-wrapper`). Sua posição horizontal é controlada por um inline style aplicado via prop `align`:

```js
...(props.align === 'left' ? { left: 0 } : { right: 0 })
```

No header mobile (`AppHeader.vue`), o componente é usado sem props — portanto com `align="right"` (padrão), o que aplica `right: 0`. Isso alinha a borda direita do painel com a borda direita do botão.

O problema é que o painel tem largura fixa de `300px` (no breakpoint mobile). Dependendo da posição do botão na tela, o painel transborda o viewport à esquerda, sendo cortado pelo overflow da página.

Como o painel é filho de um elemento com `position: relative` dentro do header, ele não tem referência ao viewport — qualquer overflow simplesmente sai da área visível sem scroll.

---

## Solução aplicada

No breakpoint `≤ 768px`, o painel foi alterado para `position: fixed`, desvinculando-o do elemento pai e ancorandoo diretamente ao viewport:

```css
@media (max-width: 768px) {
  .notification-panel {
    position: fixed;
    top: 68px;          /* logo abaixo do header (60px) */
    left: 12px !important;
    right: 12px !important;
    width: auto;
    max-height: 70vh;
  }
}
```

Os `!important` são necessários para sobrescrever os inline styles dinâmicos aplicados pelo prop `align`, que têm prioridade sobre regras CSS normais.

---

## Por que não foi resolvido completamente

A solução foi aplicada mas o bug persiste. Hipóteses a investigar:

1. **Os `!important` não estão sobrescrevendo o inline style** — inline styles têm especificidade máxima em CSS, mas `!important` em uma regra de folha de estilo deve superá-los. Isso pode variar por engine/browser.

2. **`position: fixed` está sendo contido por um ancestral com `transform`, `filter` ou `will-change`** — qualquer elemento ancestral com essas propriedades cria um novo containing block, fazendo `position: fixed` se comportar como `position: absolute`. Verificar se algum elemento pai no header ou layout aplica transform.

3. **O `top: 68px` pode estar incorreto** — se o header tiver altura diferente ou se houver padding/margin acima, o painel pode não aparecer no lugar esperado.

---

## Próximos passos sugeridos

1. Inspecionar no DevTools mobile o elemento `.notification-panel` e confirmar se `left/right` e `position` estão sendo aplicados corretamente
2. Verificar se existe algum ancestral com `transform` ou `will-change` que contenha o `position: fixed`
3. Como alternativa mais robusta, mover o painel para fora do DOM usando `<Teleport to="body">`, eliminando qualquer dependência de containing block
