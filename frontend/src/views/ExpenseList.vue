<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { useExpenseStore } from '../store/expense';
import { useAuthStore } from '../store/auth';
import { useRouter } from 'vue-router';
import { Trash2, Edit2, Lock, Unlock, Users, MessageSquare, X, FileText } from 'lucide-vue-next';

const expenseStore = useExpenseStore();
const authStore = useAuthStore();
const router = useRouter();

const selectedMemo = ref<string | null>(null);
const showMemoModal = ref(false);

const openMemo = (memo: string) => {
  selectedMemo.value = memo;
  showMemoModal.value = true;
};

const closeMemo = () => {
  showMemoModal.value = false;
};

const currentDate = new Date();
const currentYearMonth = ref(`${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`);

const isMonthClosed = computed(() => {
  return expenseStore.closedMonths.includes(currentYearMonth.value);
});

const usersList = ref<any[]>([]);

const fetchList = async () => {
  await expenseStore.fetchExpenses(currentYearMonth.value);
};

onMounted(async () => {
  if (authStore.user?.role === 'admin') {
    expenseStore.targetUserId = authStore.user.id;
    try {
      const res = await fetch('/api/users.php', {
        headers: { 'Authorization': `Bearer ${authStore.token}` }
      });
      if (res.ok) {
        const data = await res.json();
        usersList.value = data.users;
      }
    } catch (e) {
      console.error(e);
    }
  }
  fetchList();
});

watch([currentYearMonth, () => expenseStore.targetUserId], () => {
  fetchList();
});

const toggleMonthStatus = async (bulk = false) => {
  if (!authStore.user || authStore.user.role !== 'admin') return;
  
  if (bulk) {
    const msg = '【一括締め】\nこの月の全ユーザーの経費を締めますか？\n（全員の追加・編集・削除ができなくなります）';
    if (confirm(msg)) {
      const success = await expenseStore.toggleClosing(currentYearMonth.value, true, 'all');
      if (success) {
        alert('全ユーザーの経費を一括で締めました');
        fetchList();
      } else {
        alert('状態の変更に失敗しました');
      }
    }
    return;
  }

  const closing = !isMonthClosed.value;
  const msg = closing ? '現在表示中のユーザーのこの月を締めますか？（追加・編集・削除ができなくなります）' : '現在表示中のユーザーのこの月の締めを解除しますか？';
  if (confirm(msg)) {
    const success = await expenseStore.toggleClosing(currentYearMonth.value, closing);
    if (!success) alert('状態の変更に失敗しました');
  }
};

const deleteExpense = async (id: number) => {
  if (confirm('この経費を削除してもよろしいですか？')) {
    await expenseStore.deleteExpense(id);
  }
};

const editExpense = (id: number) => {
  router.push(`/edit/${id}`);
};

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
};

const openReceipt = (filename: string) => {
  window.open(`/api/uploads/${filename}`, '_blank');
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return '';
  const parts = dateStr.split('-');
  if (parts.length === 3) {
    return `${parseInt(parts[2], 10)}日`;
  }
  return dateStr;
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
        <select v-if="authStore.user?.role === 'admin'" v-model="expenseStore.targetUserId" class="form-select user-picker" style="margin-right: 12px; width: auto;">
          <option :value="authStore.user.id">自分の経費</option>
          <option v-for="u in usersList.filter(u => u.id !== authStore.user?.id)" :key="u.id" :value="u.id">
            {{ u.username }} さんの経費
          </option>
        </select>

        <div v-if="authStore.user?.role === 'admin'" class="admin-buttons" style="display: flex; gap: 8px; margin-right: 12px;">
          <button @click="toggleMonthStatus(true)" class="btn btn-danger" title="全ユーザーのこの月を一括で締める">
            <Users :size="18" style="margin-right: 6px;" /> 一括締め
          </button>
          <button @click="toggleMonthStatus(false)" class="btn" :class="isMonthClosed ? 'btn-danger' : 'btn-primary'">
            <Lock v-if="!isMonthClosed" :size="18" style="margin-right: 6px;" />
            <Unlock v-else :size="18" style="margin-right: 6px;" />
            {{ isMonthClosed ? '解除' : '個人締め' }}
          </button>
        </div>
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
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="expenseStore.expenses.length === 0">
            <td colspan="6" class="empty-state">今月の経費は見つかりませんでした。</td>
          </tr>
          <tr v-for="exp in expenseStore.expenses" :key="exp.id">
            <td class="date-cell">{{ formatDate(exp.date) }}</td>
            <td><span class="badge">{{ exp.category_name }}</span></td>
            <td>
              <button v-if="exp.description" @click="openMemo(exp.description)" class="btn-icon text-primary" title="メモを見る">
                <MessageSquare :size="18" />
              </button>
              <span v-else class="text-muted">ー</span>
            </td>
            <td class="amount-cell">{{ formatCurrency(exp.amount) }}</td>
            <td>
              <button v-if="exp.receipt_file_path" @click="openReceipt(exp.receipt_file_path)" class="btn-icon text-primary" title="領収書を見る">
                <FileText :size="18" />
              </button>
              <span v-else class="text-muted">ー</span>
            </td>
            <td>
              <div class="actions-wrapper">
                <button v-if="!isMonthClosed" @click="editExpense(exp.id)" class="btn-icon" title="編集">
                  <Edit2 :size="18" />
                </button>
                <button v-if="!isMonthClosed" @click="deleteExpense(exp.id)" class="btn-icon danger" title="削除">
                  <Trash2 :size="18" />
                </button>
                <span v-if="isMonthClosed" class="text-muted"><Lock :size="14"/></span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Memo Modal -->
    <transition name="fade">
      <div v-if="showMemoModal" class="modal-overlay" @click.self="closeMemo">
        <div class="glass modal-content">
          <div class="modal-header">
            <h3>メモ内容</h3>
            <button @click="closeMemo" class="btn-icon">
              <X :size="20" />
            </button>
          </div>
          <div class="modal-body">
            <p>{{ selectedMemo }}</p>
          </div>
          <div class="modal-footer">
            <button @click="closeMemo" class="btn btn-primary">閉じる</button>
          </div>
        </div>
      </div>
    </transition>
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
@media (max-width: 768px) {
  .header-actions {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  .actions-right {
    width: 100%;
    flex-direction: column;
    gap: 8px;
    align-items: stretch;
  }
  .admin-buttons {
    width: 100%;
    margin-right: 0 !important;
  }
  .admin-buttons button {
    flex: 1;
    justify-content: center;
  }
  .user-picker {
    margin-right: 0 !important;
    width: 100% !important;
  }
  .closed-alert {
    font-size: 0.9rem;
  }
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
.actions-wrapper {
  display: flex;
  gap: 4px;
}
.text-primary {
  color: var(--primary-color) !important;
  font-weight: bold;
}
.empty-state {
  text-align: center;
  padding: 40px;
  color: var(--text-muted);
}
.text-muted {
  color: var(--text-muted);
}
.date-cell {
  white-space: nowrap;
  font-weight: 500;
}
@media (max-width: 768px) {
  .expense-table th, .expense-table td {
    padding: 10px 8px;
    font-size: 0.9rem;
  }
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  width: 90%;
  max-width: 500px;
  padding: 24px;
  background: var(--surface-color);
  animation: modal-enter 0.3s ease;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  padding-bottom: 12px;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
}

.modal-body {
  padding: 12px 0 24px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-all;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
}

@keyframes modal-enter {
  from {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>
