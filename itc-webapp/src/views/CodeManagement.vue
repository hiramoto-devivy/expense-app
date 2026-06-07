<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../store/auth';
import { Plus, Trash2, Tag } from 'lucide-vue-next';

const authStore = useAuthStore();
const groups = ref<any[]>([]);
const selectedGroup = ref('account_type');
const codes = ref<any[]>([]);
const isLoading = ref(false);

const newCode = ref({
  code_group: 'account_type',
  code_value: '',
  display_name: ''
});

const getHeaders = () => ({
  'Authorization': `Bearer ${authStore.token}`,
  'Content-Type': 'application/json'
});

const fetchGroups = async () => {
  const res = await fetch('/api/codes.php', { headers: getHeaders() });
  if (res.ok) {
    const data = await res.json();
    groups.value = data.groups;
  }
};

const fetchCodes = async () => {
  if (!selectedGroup.value) return;
  isLoading.value = true;
  const res = await fetch(`/api/codes.php?group=${selectedGroup.value}`, { headers: getHeaders() });
  if (res.ok) {
    const data = await res.json();
    codes.value = data.codes;
  }
  isLoading.value = false;
};

const addCode = async () => {
  if (!newCode.value.code_value || !newCode.value.display_name) return;
  
  const res = await fetch('/api/codes.php', {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({
      ...newCode.value,
      code_group: selectedGroup.value || newCode.value.code_group
    })
  });
  
  if (res.ok) {
    newCode.value.code_value = '';
    newCode.value.display_name = '';
    await fetchCodes();
    await fetchGroups();
  } else {
    const data = await res.json();
    alert(data.error || '追加に失敗しました');
  }
};

const deleteCode = async (id: number) => {
  if (!confirm('この項目を削除しますか？')) return;
  
  const res = await fetch(`/api/codes.php?id=${id}`, {
    method: 'DELETE',
    headers: getHeaders()
  });
  
  if (res.ok) {
    await fetchCodes();
  }
};

onMounted(() => {
  fetchGroups();
  fetchCodes();
});
</script>

<template>
  <div class="code-management fade-enter-active">
    <div class="header-actions">
      <h2>コードマスタ管理</h2>
    </div>

    <div class="management-grid">
      <!-- Group Selection -->
      <div class="glass glass-panel side-panel">
        <h3>グループ選択</h3>
        <div class="group-list mt-4">
          <button 
            v-for="g in groups" 
            :key="g.code_group"
            @click="selectedGroup = g.code_group; fetchCodes()"
            class="group-item"
            :class="{ active: selectedGroup === g.code_group }"
          >
            <Tag :size="16" /> {{ g.code_group }}
          </button>
          
          <div class="new-group mt-4 pt-4 border-t">
            <label class="form-label">新規グループ名</label>
            <input v-model="newCode.code_group" type="text" class="form-input sm" placeholder="例: bank_name" />
            <button @click="selectedGroup = newCode.code_group; codes = []" class="btn btn-secondary btn-sm w-100 mt-2">
              グループ作成
            </button>
          </div>
        </div>
      </div>

      <!-- Code List -->
      <div class="glass glass-panel main-panel">
        <div class="list-header">
          <h3>「{{ selectedGroup }}」の項目一覧</h3>
        </div>

        <div class="table-responsive mt-4">
          <table class="code-table">
            <thead>
              <tr>
                <th>コード値</th>
                <th>表示名</th>
                <th style="width: 80px;">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in codes" :key="c.id">
                <td>{{ c.code_value }}</td>
                <td>{{ c.display_name }}</td>
                <td>
                  <button @click="deleteCode(c.id)" class="btn-icon danger">
                    <Trash2 :size="16" />
                  </button>
                </td>
              </tr>
              <tr class="add-row">
                <td>
                  <input v-model="newCode.code_value" type="text" class="form-input sm" placeholder="コード値 (1, 2...)" />
                </td>
                <td>
                  <input v-model="newCode.display_name" type="text" class="form-input sm" placeholder="表示名 (普通, 当座...)" />
                </td>
                <td>
                  <button @click="addCode" class="btn btn-primary btn-sm">
                    <Plus :size="16" />
                  </button>
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
.management-grid {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 24px;
}
@media (max-width: 768px) {
  .management-grid {
    grid-template-columns: 1fr;
  }
}

.group-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.group-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.5);
  border: 1px solid transparent;
  text-align: left;
  transition: all 0.2s;
}
.group-item:hover {
  background: white;
  border-color: var(--primary-color);
}
.group-item.active {
  background: var(--primary-color);
  color: white;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.code-table {
  width: 100%;
  border-collapse: collapse;
}
.code-table th, .code-table td {
  padding: 12px;
  border-bottom: 1px solid rgba(0,0,0,0.05);
}
.add-row {
  background: rgba(99, 102, 241, 0.05);
}
.border-t {
  border-top: 1px solid rgba(0,0,0,0.1);
}
.w-100 {
  width: 100%;
}
.btn-sm {
  padding: 6px 12px;
  font-size: 0.85rem;
}
</style>
