<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { useExpenseStore } from '../store/expense';
import { useAuthStore } from '../store/auth';
import { Trash2, FileText, Image as ImageIcon, Lock, Unlock } from 'lucide-vue-next';

const expenseStore = useExpenseStore();
const authStore = useAuthStore();

const currentDate = new Date();
const currentYearMonth = ref(`${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`);

const isMonthClosed = computed(() => {
  return expenseStore.closedMonths.includes(currentYearMonth.value);
});

const toggleMonthStatus = async () => {
  if (!authStore.user || authStore.user.role !== 'admin') return;
  const closing = !isMonthClosed.value;
  const msg = closing ? 'この月を締めますか？（追加・編集・削除ができなくなります）' : 'この月の締めを解除しますか？';
  if (confirm(msg)) {
    const success = await expenseStore.toggleClosing(currentYearMonth.value, closing);
    if (!success) alert('状態の変更に失敗しました');
  }
};

const fetchList = async () => {
  await expenseStore.fetchExpenses(currentYearMonth.value);
};

onMounted(() => {
  fetchList();
});

watch(currentYearMonth, () => {
  fetchList();
});

const deleteExpense = async (id: number) => {
  if (confirm('この経費を削除してもよろしいですか？')) {
    await expenseStore.deleteExpense(id);
  }
};

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
};

const openReceipt = (filename: string) => {
  window.open(`/api/uploads/${filename}`, '_blank');
};
</script>

<template>
  <div class="expense-list fade-enter-active">
    <div v-if="isMonthClosed" class="closed-alert">
      <Lock :size="16" style="margin-right: 8px;" />
      この月は締め込み済みのため、経費の追加・編集・削除はできません。
    </div>

    <div class="header-actions">
      <h2>経費一覧</h2>
      <div class="actions-right">
        <button v-if="authStore.user?.role === 'admin'" @click="toggleMonthStatus" class="btn" :class="isMonthClosed ? 'btn-danger' : 'btn-primary'" style="margin-right: 12px;">
          <Lock v-if="!isMonthClosed" :size="18" style="margin-right: 6px;" />
          <Unlock v-else :size="18" style="margin-right: 6px;" />
          {{ isMonthClosed ? '🔓 締めを解除' : '🔒 月を締める' }}
        </button>
        <input type="month" v-model="currentYearMonth" class="form-input month-picker" />
      </div>
    </div>

    <div class="glass glass-panel table-container">
      <table class="expense-table">
        <thead>
          <tr>
            <th>日付</th>
            <th>カテゴリ</th>
            <th>メモ</th>
            <th>金額</th>
            <th>領収書</th>
            <th>アクション</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="expenseStore.expenses.length === 0">
            <td colspan="6" class="empty-state">今月の経費は見つかりませんでした。</td>
          </tr>
          <tr v-for="exp in expenseStore.expenses" :key="exp.id">
            <td>{{ exp.date }}</td>
            <td><span class="badge">{{ exp.category_name }}</span></td>
            <td>{{ exp.description || '-' }}</td>
            <td class="amount-cell">{{ formatCurrency(exp.amount) }}</td>
            <td>
              <button v-if="exp.receipt_file_path" @click="openReceipt(exp.receipt_file_path)" class="btn-icon" title="領収書を見る">
                <FileText :size="18" v-if="exp.receipt_file_path.endsWith('.pdf')" />
                <ImageIcon :size="18" v-else />
              </button>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <button v-if="!isMonthClosed" @click="deleteExpense(exp.id)" class="btn-icon danger" title="削除">
                <Trash2 :size="18" />
              </button>
              <span v-else class="text-muted"><Lock :size="14"/></span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.closed-alert {
  background: rgba(239, 68, 68, 0.1);
  color: var(--danger);
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  border: 1px solid rgba(239, 68, 68, 0.2);
}
.header-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}
.actions-right {
  display: flex;
  align-items: center;
}
.month-picker {
  width: auto;
}
.table-container {
  overflow-x: auto;
}
.expense-table {
  width: 100%;
  border-collapse: collapse;
}
.expense-table th {
  text-align: left;
  padding: 12px 16px;
  color: var(--text-muted);
  font-weight: 600;
  border-bottom: 2px solid rgba(0,0,0,0.05);
}
.expense-table td {
  padding: 16px;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  vertical-align: middle;
}
.expense-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.4);
}
.amount-cell {
  font-weight: 600;
}
.badge {
  background: rgba(99, 102, 241, 0.1);
  color: var(--primary-color);
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
}
.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  padding: 6px;
  border-radius: 4px;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.btn-icon:hover {
  background: rgba(0,0,0,0.05);
  color: var(--text-main);
}
.btn-icon.danger:hover {
  color: var(--danger);
  background: rgba(239, 68, 68, 0.1);
}
.empty-state {
  text-align: center;
  padding: 40px;
  color: var(--text-muted);
}
.text-muted {
  color: var(--text-muted);
}
</style>
