<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useAuthStore } from '../store/auth';
import { Lock, Unlock, Calendar, CheckCircle2, AlertCircle } from 'lucide-vue-next';

const authStore = useAuthStore();
const currentDate = new Date();
const selectedMonth = ref(`${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`);

const users = ref<any[]>([]);
const closingStatus = ref<Record<number, boolean>>({});
const isLoading = ref(false);

const getHeaders = () => ({
  'Authorization': `Bearer ${authStore.token}`,
  'Content-Type': 'application/json'
});

const fetchUsers = async () => {
  const res = await fetch('/api/users.php', { headers: getHeaders() });
  if (res.ok) {
    const data = await res.json();
    users.value = data.users;
  }
};

const fetchClosingStatus = async () => {
  isLoading.value = true;
  try {
    const res = await fetch(`/api/closings.php?year_month=${selectedMonth.value}`, { headers: getHeaders() });
    if (res.ok) {
      const data = await res.json();
      // Map user_id to closed status
      const status: Record<number, boolean> = {};
      data.closings.forEach((c: any) => {
        status[c.user_id] = true;
      });
      closingStatus.value = status;
    }
  } catch (e) {
    console.error(e);
  } finally {
    isLoading.value = false;
  }
};

const toggleUserClosing = async (userId: number, currentStatus: boolean) => {
  const res = await fetch('/api/closings.php', {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({
      target_user_id: userId,
      year_month: selectedMonth.value,
      is_closed: !currentStatus
    })
  });
  
  if (res.ok) {
    await fetchClosingStatus();
  }
};

const bulkClose = async () => {
  if (!confirm(`${selectedMonth.value} の全ユーザーを一括で締めます。よろしいですか？`)) return;
  
  const res = await fetch('/api/closings.php', {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({
      target_user_id: 'all',
      year_month: selectedMonth.value,
      is_closed: true
    })
  });
  
  if (res.ok) {
    await fetchClosingStatus();
    alert('一括締めを完了しました');
  }
};

const bulkUnlock = async () => {
  if (!confirm(`${selectedMonth.value} の全ユーザーの締めを一括で解除します。よろしいですか？`)) return;
  
  const res = await fetch('/api/closings.php', {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({
      target_user_id: 'all',
      year_month: selectedMonth.value,
      is_closed: false
    })
  });
  
  if (res.ok) {
    await fetchClosingStatus();
    alert('一括解除を完了しました');
  }
};

onMounted(() => {
  fetchUsers();
  fetchClosingStatus();
});

watch(selectedMonth, fetchClosingStatus);
</script>

<template>
  <div class="closing-management fade-enter-active">
    <div class="header-actions">
      <h2>締め処理管理</h2>
      <div class="actions-right">
        <input type="month" v-model="selectedMonth" class="form-input month-picker" />
      </div>
    </div>

    <div class="bulk-actions glass glass-panel mb-6">
      <div class="bulk-info">
        <Calendar :size="20" />
        <div>
          <h3>一括操作 ({{ selectedMonth }})</h3>
          <p class="text-muted small">全ユーザーに対して一括で締め・解除を行います。</p>
        </div>
      </div>
      <div class="bulk-buttons">
        <button @click="bulkClose" class="btn btn-danger">
          <Lock :size="18" style="margin-right: 8px;" /> 一括締め
        </button>
        <button @click="bulkUnlock" class="btn btn-secondary">
          <Unlock :size="18" style="margin-right: 8px;" /> 一括解除
        </button>
      </div>
    </div>

    <div class="glass glass-panel">
      <div class="list-header">
        <h3>ユーザー別締め状態</h3>
        <span class="text-muted small">
          締まったユーザー: {{ Object.values(closingStatus).filter(v => v).length }} / {{ users.length }}
        </span>
      </div>

      <div class="table-responsive mt-4">
        <table class="closing-table">
          <thead>
            <tr>
              <th>ユーザー名</th>
              <th>権限</th>
              <th>状態</th>
              <th style="width: 120px;">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id" :class="{ 'closed-row': closingStatus[u.id] }">
              <td>{{ u.username }}</td>
              <td>
                <span class="role-badge" :class="u.role">{{ u.role === 'admin' ? '管理者' : '一般社員' }}</span>
              </td>
              <td>
                <span v-if="closingStatus[u.id]" class="status-badge closed">
                  <CheckCircle2 :size="14" /> 締め済み
                </span>
                <span v-else class="status-badge open">
                  <AlertCircle :size="14" /> 未締め
                </span>
              </td>
              <td>
                <button 
                  @click="toggleUserClosing(u.id, !!closingStatus[u.id])"
                  class="btn btn-sm w-100"
                  :class="closingStatus[u.id] ? 'btn-secondary' : 'btn-primary'"
                >
                  {{ closingStatus[u.id] ? '解除' : '締める' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bulk-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  background: rgba(99, 102, 241, 0.05);
}
@media (max-width: 640px) {
  .bulk-actions {
    flex-direction: column;
    gap: 16px;
    align-items: flex-start;
  }
}
.bulk-info {
  display: flex;
  align-items: center;
  gap: 16px;
}
.bulk-info h3 {
  margin: 0;
  font-size: 1.1rem;
}
.bulk-buttons {
  display: flex;
  gap: 12px;
}
.closing-table {
  width: 100%;
  border-collapse: collapse;
}
.closing-table th, .closing-table td {
  padding: 12px 16px;
  border-bottom: 1px solid rgba(0,0,0,0.05);
}
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 500;
}
.status-badge.closed {
  background: rgba(239, 68, 68, 0.1);
  color: var(--danger);
}
.status-badge.open {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}
.closed-row {
  background: rgba(0,0,0,0.02);
}
.mb-6 {
  margin-bottom: 24px;
}
.w-100 {
  width: 100%;
}
.btn-sm {
  padding: 6px 12px;
  font-size: 0.85rem;
}
.role-badge {
  font-size: 0.75rem;
  padding: 2px 6px;
  border-radius: 4px;
}
.role-badge.admin { background: #e0e7ff; color: #4338ca; }
.role-badge.user { background: #f3f4f6; color: #4b5563; }
</style>
