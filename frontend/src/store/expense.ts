import { defineStore } from 'pinia';
import { ref } from 'vue';
import { useAuthStore } from './auth';

export const useExpenseStore = defineStore('expense', () => {
  const expenses = ref<any[]>([]);
  const categories = ref<any[]>([]);
  const authStore = useAuthStore();

  const getHeaders = () => ({
    'Authorization': `Bearer ${authStore.token}`,
    'Content-Type': 'application/json'
  });

  const fetchCategories = async () => {
    try {
      const res = await fetch('/api/categories.php', { headers: getHeaders() });
      if (res.ok) {
        const data = await res.json();
        categories.value = data.categories;
      } else if (res.status === 401) authStore.logout();
    } catch (e) {
      console.error(e);
    }
  };

  const fetchExpenses = async (yearMonth?: string) => {
    try {
      let url = '/api/expenses.php';
      if (yearMonth) url += `?year_month=${yearMonth}`;
      const res = await fetch(url, { headers: getHeaders() });
      if (res.ok) {
        const data = await res.json();
        expenses.value = data.expenses;
      } else if (res.status === 401) authStore.logout();
    } catch (e) {
      console.error(e);
    }
  };

  const addExpense = async (expenseData: any) => {
    try {
      const res = await fetch('/api/expenses.php', {
        method: 'POST',
        headers: getHeaders(),
        body: JSON.stringify(expenseData)
      });
      if (res.ok) {
        await fetchExpenses(expenseData.year_month);
        return true;
      } else if (res.status === 401) authStore.logout();
      return false;
    } catch (e) {
      console.error(e);
      return false;
    }
  };

  const deleteExpense = async (id: number) => {
    try {
      const res = await fetch(`/api/expenses.php?id=${id}`, {
        method: 'DELETE',
        headers: getHeaders()
      });
      if (res.ok) {
        expenses.value = expenses.value.filter(e => e.id !== id);
        return true;
      } else if (res.status === 401) authStore.logout();
      return false;
    } catch (e) {
      console.error(e);
      return false;
    }
  };

  return { expenses, categories, fetchCategories, fetchExpenses, addExpense, deleteExpense };
});
