# Bug: Dashboard não reflete alterações na Reserva de Emergência

**Data:** 2026-03-29
**Severidade:** Média — dados financeiros exibidos incorretamente, mas sem perda de dados
**Status:** Resolvido

---

## Sintomas reportados

- Após configurar a meta da reserva de emergência ou registrar um depósito, o card de Reserva de Emergência no dashboard principal continuava exibindo os dados antigos (zerado ou desatualizado)
- A atualização só acontecia após aguardar até 5 minutos ou recarregar a página manualmente

---

## Análise da causa raiz

O bug tinha **duas causas independentes**, ambas necessárias para o problema se manifestar.

### Causa 1 — Backend: `updateGoal` não invalidava o cache do dashboard

`DashboardService::getSummary` armazena a resposta em cache por 300 segundos:

```php
return Cache::remember(self::cacheKey($user->id), 300, fn () => $this->buildSummary($user));
```

O método `EmergencyFundService::deposit` já chamava `Cache::forget` corretamente. Porém, `EmergencyFundService::updateGoal` — responsável por configurar a meta — **não invalidava o cache**, então o dashboard continuava servindo dados stale após a configuração inicial:

```php
// updateGoal — antes da correção
public function updateGoal(User $user, array $data): array
{
    $this->fundRepository->createOrUpdate($user->id, [...]);
    $this->activityLog->goalUpdated($user->id, $user->id);
    return $this->getStatus($user); // cache do dashboard nunca era limpo
}
```

### Causa 2 — Frontend: `EmergencyFundView` não notificava o dashboard após salvar

O `DashboardView` observa `ui.transactionVersion` para saber quando re-buscar dados:

```js
watch(() => ui.transactionVersion, () => dashboard.fetch())
```

Porém, as funções `saveGoal` e `submitDeposit` em `EmergencyFundView.vue` não incrementavam esse contador. `submitDeposit` chamava `ui.notifyTransactionCreated()`, que fecha o modal de transações como efeito colateral indesejado. `saveGoal` não notificava nada.

Resultado: mesmo com o cache do backend limpo, o `DashboardView` não sabia que precisava re-buscar — os dados só eram atualizados quando o componente desmontava e remontava (navegação completa).

---

## Arquivos afetados

| Arquivo | Problema |
|---------|----------|
| `backend/app/Services/EmergencyFundService.php` | `updateGoal` não chamava `Cache::forget` |
| `frontend/src/stores/ui.js` | Faltava função `notifyDataChanged` sem efeito colateral de modal |
| `frontend/src/views/EmergencyFundView.vue` | `saveGoal` sem notificação; `submitDeposit` usando função errada |

---

## Solução aplicada

### Backend — `EmergencyFundService::updateGoal`

Adicionada chamada a `Cache::forget` após persistir a meta:

```php
$this->activityLog->goalUpdated($user->id, $user->id);

Cache::forget(DashboardService::cacheKey($user->id)); // adicionado

return $this->getStatus($user);
```

### Frontend — `useUiStore`

Adicionada função `notifyDataChanged` que incrementa `transactionVersion` sem fechar o modal de transações, para uso em contextos onde não há modal envolvido:

```js
function notifyDataChanged() {
  transactionVersion.value++
}
```

### Frontend — `EmergencyFundView`

- `saveGoal`: adicionado `ui.notifyDataChanged()` após persistir a meta com sucesso
- `submitDeposit`: substituído `ui.notifyTransactionCreated()` (que fechava o modal de transações como efeito colateral) por `ui.notifyDataChanged()`

---

## Lição aprendida

Sempre que um service invalida cache em uma operação (ex.: `deposit`), verificar se **todas as outras operações do mesmo recurso** que alteram dados relevantes também invalidam o mesmo cache (ex.: `updateGoal`). Uma operação esquecida é suficiente para causar inconsistência.

No frontend, funções reutilizadas entre contextos diferentes devem ter responsabilidades claras e sem efeitos colaterais implícitos — `notifyTransactionCreated` fechava o modal de transações, comportamento correto apenas quando chamado a partir do modal.
