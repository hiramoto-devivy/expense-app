<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useAuthStore } from '../store/auth';
import { Download, Calculator } from 'lucide-vue-next';

const authStore = useAuthStore();
const currentDate = new Date();
const selectedMonth = ref(`${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`);

const globalConfig = ref({
  transfer_code: '1',
  transfer_date: new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).toISOString().split('T')[0] // Default to end of current month
});

const userSummaries = ref<any[]>([]);
const isLoading = ref(false);
const isExporting = ref(false);

const fetchSummary = async () => {
  isLoading.value = true;
  try {
    const res = await fetch(`/api/expense_summary.php?year_month=${selectedMonth.value}`, {
      headers: { 'Authorization': `Bearer ${authStore.token}` }
    });
    if (res.ok) {
      const data = await res.json();
      userSummaries.value = data.summary.map((u: any) => ({
        ...u,
        transfer_date: globalConfig.value.transfer_date,
        transfer_code: globalConfig.value.transfer_code,
        // If amount is 0, we might want to exclude them, but let's keep them for now
        export: u.total_amount > 0
      }));
    }
  } catch (e) {
    console.error(e);
  } finally {
    isLoading.value = false;
  }
};

onMounted(fetchSummary);
watch(selectedMonth, fetchSummary);

// Apply global settings to all users
const applyGlobalSettings = () => {
  userSummaries.value.forEach(u => {
    u.transfer_date = globalConfig.value.transfer_date;
    u.transfer_code = globalConfig.value.transfer_code;
  });
};

const exportCsv = async () => {
  const exportData = userSummaries.value
    .filter(u => u.export)
    .map(u => ({
      transfer_code: u.transfer_code,
      transfer_date: u.transfer_date.replace(/-/g, ''), // Format as YYYYMMDD
      bank_code: u.bank_code,
      branch_code: u.branch_code,
      account_type: u.account_type,
      account_number: u.account_number,
      account_holder: u.account_holder,
      amount: u.total_amount
    }));

  if (exportData.length === 0) {
    alert('出力対象のデータがありません');
    return;
  }

  isExporting.value = true;
  try {
    const res = await fetch('/api/export_transfer_csv.php', {
      method: 'POST',
      headers: { 
        'Authorization': `Bearer ${authStore.token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ data: exportData })
    });
    
    if (res.ok) {
      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      // Use selectedMonth (YYYY-MM) as filename (YYYYMM.csv)
      const fileName = selectedMonth.value.replace('-', '') + '.csv';
      a.download = fileName;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } else {
      const errorData = await res.json().catch(() => ({}));
      alert('CSVの出力に失敗しました: ' + (errorData.error || res.statusText));
    }
  } catch (e) {
    console.error(e);
    alert('通信エラーが発生しました');
  } finally {
    isExporting.value = false;
  }
};

</script>

<template>
  <div class="export-settings fade-enter-active">
    <div class="header-actions">
      <h2>振込用CSV出力設定</h2>
      <div class="actions-right">
        <input type="month" v-model="selectedMonth" class="form-input month-picker" />
      </div>
    </div>

    <div class="management-grid">
      <!-- Global Config -->
      <div class="glass glass-panel config-panel">
        <h3>一括設定</h3>
        <div class="mt-4">
          <div class="form-group">
            <label class="form-label">振込コード</label>
            <input v-model="globalConfig.transfer_code" type="text" class="form-input" placeholder="1" />
          </div>
          <div class="form-group">
            <label class="form-label">振込日</label>
            <input v-model="globalConfig.transfer_date" type="date" class="form-input" />
          </div>
          <button @click="applyGlobalSettings" class="btn btn-secondary w-100 mt-2">
            <Calculator :size="18" style="margin-right: 8px;" /> 全員に適用
          </button>
        </div>
        
        <div class="export-actions mt-6">
          <button @click="exportCsv" class="btn btn-primary w-100 btn-lg" :disabled="isExporting">
            <Download :size="20" style="margin-right: 8px;" />
            {{ isExporting ? '出力中...' : 'CSVを出力する' }}
          </button>
        </div>
      </div>

      <!-- User List and Individual Adjustments -->
      <div class="glass glass-panel list-container">
        <div class="list-header">
          <h3>振込対象一覧 ({{ selectedMonth }})</h3>
          <span class="text-muted">{{ userSummaries.filter(u => u.export).length }} 名を選択中</span>
        </div>
        
        <div class="table-responsive mt-4">
          <table class="transfer-table">
            <thead>
              <tr>
                <th><input type="checkbox" @change="(e) => userSummaries.forEach(u => u.export = (e.target as HTMLInputElement).checked)" :checked="userSummaries.length > 0 && userSummaries.every(u => u.export)" /></th>
                <th>ユーザー名</th>
                <th>振込金額</th>
                <th>振込日</th>
                <th>口座情報</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="isLoading">
                <td colspan="5" class="empty-state">読み込み中...</td>
              </tr>
              <tr v-else-if="userSummaries.length === 0">
                <td colspan="5" class="empty-state">ユーザーが見つかりません。</td>
              </tr>
              <tr v-for="u in userSummaries" :key="u.id" :class="{ 'inactive-row': !u.export }">
                <td><input type="checkbox" v-model="u.export" /></td>
                <td>
                  <div class="user-name-cell">
                    {{ u.username }}
                    <div v-if="!u.bank_code" class="warning-text">※口座情報未登録</div>
                  </div>
                </td>
                <td>
                  <input v-model.number="u.total_amount" type="number" class="form-input sm amount-input" />
                </td>
                <td>
                  <input v-model="u.transfer_date" type="date" class="form-input sm date-input" />
                </td>
                <td>
                  <div v-if="u.bank_code" class="bank-info-preview">
                    {{ u.bank_code }}-{{ u.branch_code }} {{ u.account_type_name || u.account_type }} {{ u.account_number }}
                  </div>
                  <div v-else class="text-muted small">ー</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
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
.management-grid {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 24px;
}
@media (max-width: 1024px) {
  .management-grid {
    grid-template-columns: 1fr;
  }
}
.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.transfer-table {
  width: 100%;
  border-collapse: collapse;
}
.transfer-table th, .transfer-table td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid rgba(0,0,0,0.05);
}
.transfer-table th {
  color: var(--text-muted);
  font-size: 0.85rem;
}
.amount-input, .date-input {
  width: 120px;
  background: #ffffff;
  border: 1px solid rgba(0,0,0,0.2);
}
.date-input {
  width: 150px;
}
.amount-input:focus, .date-input:focus {
  background: #ffffff;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
.bank-info-preview {
  font-size: 0.8rem;
  color: var(--text-muted);
}
.inactive-row {
  opacity: 0.5;
}
.warning-text {
  color: var(--danger);
  font-size: 0.7rem;
  margin-top: 2px;
}
.mt-6 {
  margin-top: 24px;
}
.w-100 {
  width: 100%;
}
.btn-lg {
  padding: 16px;
  font-size: 1.1rem;
}
</style>
