<script setup lang="ts">
import { onMounted, ref, computed, watch } from 'vue';
import { useExpenseStore } from '../store/expense';
import { useAuthStore } from '../store/auth';

const expenseStore = useExpenseStore();
const authStore = useAuthStore();

const currentDate = new Date();
const currentYearMonth = ref(`${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`);

const fetchSummary = async () => {
  await expenseStore.fetchExpenses(currentYearMonth.value);
};

onMounted(() => {
  fetchSummary();
  if (expenseStore.categories.length === 0) {
    expenseStore.fetchCategories();
  }
});

watch(currentYearMonth, () => {
  fetchSummary();
});

const totalAmount = computed(() => {
  return expenseStore.expenses.reduce((sum, exp) => sum + exp.amount, 0);
});

const categorySummary = computed(() => {
  const summary: Record<string, number> = {};
  expenseStore.expenses.forEach(exp => {
    const catName = exp.category_name || 'Unknown';
    if (!summary[catName]) summary[catName] = 0;
    summary[catName] += exp.amount;
  });
  return Object.entries(summary).sort((a, b) => b[1] - a[1]);
});

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
};
</script>

<template>
  <div class="dashboard fade-enter-active">
    <div class="welcome-box glass glass-panel">
      <h2>Hello, {{ authStore.user?.username }}! 👋</h2>
      <p>Here is your expense summary for this month.</p>
    </div>

    <div class="controls">
      <input type="month" v-model="currentYearMonth" class="form-input month-picker" />
    </div>

    <div class="summary-cards">
      <div class="glass glass-panel card total-card">
        <h3>Total Expenses</h3>
        <div class="amount">{{ formatCurrency(totalAmount) }}</div>
      </div>
    </div>

    <div class="glass glass-panel category-breakdown">
      <h3>Category Breakdown</h3>
      <div v-if="categorySummary.length === 0" class="empty-state">
        No expenses found for this month.
      </div>
      <div v-else class="bar-chart">
        <div v-for="[category, amount] in categorySummary" :key="category" class="bar-row">
          <div class="bar-label">{{ category }}</div>
          <div class="bar-track">
            <div class="bar-fill" :style="{ width: `${(amount / totalAmount) * 100}%` }"></div>
          </div>
          <div class="bar-value">{{ formatCurrency(amount) }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.welcome-box {
  margin-bottom: 24px;
}
.welcome-box h2 {
  color: var(--primary-color);
  margin-bottom: 8px;
}
.controls {
  margin-bottom: 24px;
  display: flex;
  justify-content: flex-end;
}
.month-picker {
  width: auto;
}
.summary-cards {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
  margin-bottom: 24px;
}
.total-card {
  text-align: center;
  background: linear-gradient(135deg, var(--primary-color) 0%, #a855f7 100%);
  color: white;
  border: none;
}
.total-card h3 {
  font-weight: 500;
  margin-bottom: 8px;
  opacity: 0.9;
}
.amount {
  font-size: 2.5rem;
  font-weight: 700;
}
.category-breakdown h3 {
  margin-bottom: 20px;
}
.empty-state {
  text-align: center;
  color: var(--text-muted);
  padding: 20px;
}
.bar-row {
  display: flex;
  align-items: center;
  margin-bottom: 16px;
  gap: 16px;
}
.bar-label {
  width: 120px;
  font-weight: 500;
}
.bar-track {
  flex: 1;
  height: 12px;
  background: rgba(0,0,0,0.05);
  border-radius: 6px;
  overflow: hidden;
}
.bar-fill {
  height: 100%;
  background: var(--primary-color);
  border-radius: 6px;
  transition: width 0.5s ease;
}
.bar-value {
  width: 120px;
  text-align: right;
  font-weight: 600;
}
</style>
