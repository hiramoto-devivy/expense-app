<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useExpenseStore } from '../store/expense';
import { Trash2, FileText, Image as ImageIcon } from 'lucide-vue-next';

const expenseStore = useExpenseStore();

const currentDate = new Date();
const currentYearMonth = ref(`${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`);

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
  if (confirm('Are you sure you want to delete this expense?')) {
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
    <div class="header-actions">
      <h2>Expense List</h2>
      <input type="month" v-model="currentYearMonth" class="form-input month-picker" />
    </div>

    <div class="glass glass-panel table-container">
      <table class="expense-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Category</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Receipt</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="expenseStore.expenses.length === 0">
            <td colspan="6" class="empty-state">No expenses found for this month.</td>
          </tr>
          <tr v-for="exp in expenseStore.expenses" :key="exp.id">
            <td>{{ exp.date }}</td>
            <td><span class="badge">{{ exp.category_name }}</span></td>
            <td>{{ exp.description || '-' }}</td>
            <td class="amount-cell">{{ formatCurrency(exp.amount) }}</td>
            <td>
              <button v-if="exp.receipt_file_path" @click="openReceipt(exp.receipt_file_path)" class="btn-icon" title="View Receipt">
                <FileText :size="18" v-if="exp.receipt_file_path.endsWith('.pdf')" />
                <ImageIcon :size="18" v-else />
              </button>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <button @click="deleteExpense(exp.id)" class="btn-icon danger" title="Delete">
                <Trash2 :size="18" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.header-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
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
