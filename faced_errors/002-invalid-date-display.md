# Bug: "Invalid Date" exibido na listagem de transações e outras views

**Data:** 2026-03-20
**Severidade:** Média — dados são exibidos incorretamente para o usuário, mas nenhuma funcionalidade é bloqueada
**Status:** Resolvido

---

## Sintomas reportados

- Datas exibidas como `"Invalid Date"` na listagem de transações (`TransactionHistoryView`)
- Potencialmente o mesmo problema em `InvestmentsView` e `GoalsView`, que utilizam a mesma função

---

## Análise da causa raiz

### A função `formatDate` assume formato de data sem timestamp

Em todos os três arquivos afetados, `formatDate` concatena `'T00:00:00'` diretamente ao valor recebido da API antes de passar para `new Date()`:

```js
// TransactionHistoryView.vue
function formatDate(dateStr) {
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
}

// InvestmentsView.vue
function formatDate(d) {
  if (!d) return ''
  return new Date(d + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

// GoalsView.vue
function formatDate(dateStr) {
  if (!dateStr) return null
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  })
}
```

### O que a API retorna

O backend Laravel serializa datas como timestamps ISO 8601 completos:

```
2026-03-15T00:00:00.000000Z
```

### Por que a concatenação quebra

Ao concatenar `'T00:00:00'` ao timestamp completo, o resultado é:

```
"2026-03-15T00:00:00.000000Z" + "T00:00:00"
= "2026-03-15T00:00:00.000000ZT00:00:00"
```

Essa string não é um formato de data válido reconhecido por nenhum parser JavaScript. `new Date()` retorna um objeto `Invalid Date`, e qualquer método chamado sobre ele (`toLocaleDateString`) retorna a string `"Invalid Date"`.

### Por que funcionou na criação mas quebrou na listagem

Durante a criação de uma transação no modal, o valor do campo `date` é um `<input type="date">` que produz o formato `YYYY-MM-DD` (ex.: `"2026-03-15"`). Nesse caso, `"2026-03-15" + "T00:00:00"` = `"2026-03-15T00:00:00"` — válido. O problema só aparece ao **ler dados da API**, que retorna o formato completo com microsegundos e timezone.

---

## Arquivos afetados

| Arquivo | Linha | Contexto |
|---------|-------|----------|
| `frontend/src/views/TransactionHistoryView.vue` | 98–101 | Datas das transações na listagem |
| `frontend/src/views/InvestmentsView.vue` | 80–83 | Datas das transações de investimentos |
| `frontend/src/views/GoalsView.vue` | 91–95 | Data-alvo (deadline) das metas |

---

## Solução aplicada

Truncar `dateStr` para os primeiros 10 caracteres (`YYYY-MM-DD`) antes de concatenar o sufixo de horário. Isso funciona corretamente tanto com strings curtas (`"2026-03-15"`) quanto com timestamps completos (`"2026-03-15T00:00:00.000000Z"`):

```js
// Antes
new Date(dateStr + 'T00:00:00')

// Depois
new Date(dateStr.slice(0, 10) + 'T00:00:00')
```

A chamada `.slice(0, 10)` extrai apenas `YYYY-MM-DD`, descartando qualquer sufixo de horário ou timezone já presente. O resultado é sempre um formato ISO válido: `"2026-03-15T00:00:00"`.

### Arquivos modificados

| Arquivo | Mudança |
|---------|---------|
| `frontend/src/views/TransactionHistoryView.vue` | `dateStr` → `dateStr.slice(0, 10)` |
| `frontend/src/views/InvestmentsView.vue` | `d` → `d.slice(0, 10)` |
| `frontend/src/views/GoalsView.vue` | `dateStr` → `dateStr.slice(0, 10)` |
