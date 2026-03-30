# Bug: Loop infinito ao tentar fazer login

**Data:** 2026-03-20
**Severidade:** Crítica — impede o uso do sistema
**Status:** Resolvido

---

## Sintomas reportados

- Após clicar em "Entrar" na tela de login, nenhum redirecionamento para o dashboard ocorria
- Nenhuma mensagem de erro era exibida
- A tela entrava em um loop infinito de atualização (reload contínuo do browser)
- O estado era irrecuperável sem fechar a aba

---

## Análise da causa raiz

O problema envolveu três arquivos interagindo de forma problemática em cadeia.

### 1. `TransactionModal` montado incondicionalmente em `App.vue`

```vue
<!-- frontend/src/App.vue -->
<template>
  <RouterView />
  <TransactionModal />  <!-- sempre montado, inclusive na tela de auth -->
</template>
```

O `TransactionModal` foi posicionado **fora do `RouterView`**, o que faz com que ele seja montado assim que o app carrega — independentemente de o usuário estar autenticado ou não. Isso inclui a tela de login.

### 2. `onMounted` do modal disparava chamada para endpoint autenticado

```js
// frontend/src/components/transactions/TransactionModal.vue
onMounted(fetchCategories)

async function fetchCategories() {
  try {
    const res = await api.get('/categories')  // endpoint protegido por auth
    categories.value = res.data
  } catch { /* silencioso */ }
}
```

Ao montar na tela de login, `fetchCategories` era chamado imediatamente. Como o usuário não estava autenticado, a requisição a `/categories` era enviada **sem token** e retornava `401 Unauthorized`.

O `catch { }` silencioso parecia proteger contra o erro — mas o interceptor do Axios agia **antes** do catch, conforme descrito abaixo.

### 3. Interceptor de 401 em `api.js` causava hard reload

```js
// frontend/src/services/api.js
err => {
  if (err.response?.status === 401) {
    localStorage.removeItem('fin_buddy_token')
    localStorage.removeItem('fin_buddy_user')
    window.location.href = '/'   // hard reload, bypassa o Vue Router
  }
  return Promise.reject(err)
}
```

O interceptor capturava o 401 de `/categories`, limpava o localStorage e chamava `window.location.href = '/'`. Isso provocava um **reload completo da página** — o `catch { }` do `fetchCategories` nunca chegava a executar porque a página já estava sendo destruída e recarregada.

### A cadeia que formava o loop

```
App carrega em `/`
  └─ TransactionModal monta
       └─ fetchCategories() → GET /categories (sem token)
            └─ 401 → interceptor → window.location.href = '/'
                 └─ Página recarrega em `/`
                      └─ TransactionModal monta novamente
                           └─ fetchCategories() → GET /categories (sem token)
                                └─ 401 → ... → loop infinito
```

### Por que o usuário conseguiu digitar antes do loop iniciar?

A requisição HTTP para `/categories` levava alguns milissegundos (latência de rede). Nesse intervalo, o usuário digitou as credenciais e clicou em "Entrar". O login foi enviado e **chegou a concluir com sucesso** (token foi definido no store), mas a resposta 401 do `/categories` — que estava em voo paralelo desde o carregamento da página — chegou logo em seguida, acionou o interceptor e desfez o estado antes que o `router.push('/dashboard')` pudesse ser processado.

### Por que nenhum erro foi exibido?

O `handleError` do formulário de login nunca foi chamado porque o login em si **não lançou exceção**. A falha veio do `/categories`, cuja exceção foi capturada e "silenciada" — mas o reload já havia sido disparado pelo interceptor antes do `catch` ser atingido.

---

## Problemas identificados

| # | Problema | Arquivo |
|---|----------|---------|
| 1 | `TransactionModal` montado incondicionalmente, inclusive na tela de auth | `App.vue` |
| 2 | `onMounted` do modal chama endpoint autenticado sem verificar autenticação | `TransactionModal.vue` |
| 3 | Interceptor de 401 usa `window.location.href` (hard reload) ao invés do Vue Router, criando o loop | `api.js` |

---

## Solução aplicada

### Correção 1 — `App.vue`: renderização condicional do modal

O `TransactionModal` passou a ser renderizado **apenas quando o usuário está autenticado**, usando `v-if` vinculado ao `isAuthenticated` do auth store.

```vue
<!-- Antes -->
<RouterView />
<TransactionModal />

<!-- Depois -->
<RouterView />
<TransactionModal v-if="auth.isAuthenticated" />
```

Isso elimina a causa raiz: o modal não monta na tela de auth e, portanto, `fetchCategories` nunca é chamado sem token.

### Correção 2 — `api.js`: substituição de `window.location.href` pelo Vue Router

O hard reload via `window.location.href` foi substituído por uma navegação via Vue Router (`router.replace`). Isso:
- Evita o reload completo da página (que destruía e recriava todos os componentes, reiniciando o ciclo)
- Mantém o estado do Pinia (o store é limpo explicitamente via `auth.clearAuth()`)
- Garante que os guards de rota do Vue Router sejam executados corretamente

```js
// Antes
localStorage.removeItem('fin_buddy_token')
localStorage.removeItem('fin_buddy_user')
window.location.href = '/'

// Depois
auth.clearAuth()
router.replace({ name: 'auth' })
```

### Arquivos modificados

| Arquivo | Mudança |
|---------|---------|
| `frontend/src/App.vue` | `v-if="auth.isAuthenticated"` no `TransactionModal` |
| `frontend/src/services/api.js` | Substituição de `window.location.href` por `router.replace` + `auth.clearAuth()` |
