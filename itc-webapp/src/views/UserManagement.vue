<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../store/auth';
import { useRouter } from 'vue-router';
import { UserPlus, Shield, User, Edit2, X, Save } from 'lucide-vue-next';

const authStore = useAuthStore();
const router = useRouter();

interface UserData {
  id: number;
  username: string;
  display_name: string;
  role: string;
  bank_code: string;
  branch_code: string;
  account_type: string;
  account_number: string;
  account_holder: string;
  account_type_name?: string;
  password?: string;
}

const users = ref<UserData[]>([]);
const accountTypes = ref<{ code_value: string, display_name: string }[]>([]);
const isLoading = ref(false);

const form = ref<UserData>({
  id: 0,
  username: '',
  display_name: '',
  password: '',
  role: 'user',
  bank_code: '',
  branch_code: '',
  account_type: '普通',
  account_number: '',
  account_holder: ''
});

const editingUser = ref<number | null>(null);
const editForm = ref<UserData>({ ...form.value });
const showEditModal = ref(false);

const startEdit = (user: UserData) => {
  editingUser.value = user.id;
  editForm.value = { 
    ...user,
    password: '' // Don't show password, leave empty to keep current
  };
  showEditModal.value = true;
};

const cancelEdit = () => {
  editingUser.value = null;
  showEditModal.value = false;
};

const getHeaders = () => {
  return {
    'Authorization': `Bearer ${authStore.token}`,
    'Content-Type': 'application/json'
  };
};

const fetchUsers = async () => {
  try {
    const res = await fetch('/api/users.php', { headers: getHeaders() });
    if (res.ok) {
      const data = await res.json();
      users.value = data.users;
    }
  } catch (e) {
    console.error('Failed to fetch users', e);
  }
};

const fetchCodes = async () => {
  try {
    const res = await fetch('/api/codes.php?group=account_type', { headers: getHeaders() });
    if (res.ok) {
      const data = await res.json();
      accountTypes.value = data.codes;
    }
  } catch (e) {
    console.error('Failed to fetch codes', e);
  }
};

const submit = async () => {
  if (!form.value.username || !form.value.password) {
    alert('必須項目をすべて入力してください');
    return;
  }
  
  isLoading.value = true;
  try {
    const res = await fetch('/api/users.php', {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify(form.value)
    });
    
    if (res.ok) {
      form.value = { 
        id: 0,
        username: '', 
        display_name: '',
        password: '', 
        role: 'user',
        bank_code: '',
        branch_code: '',
        account_type: '普通',
        account_number: '',
        account_holder: ''
      };
      alert('ユーザーを追加しました');
      await fetchUsers();
    } else {
      const data = await res.json();
      alert(data.error || 'ユーザーの追加に失敗しました');
    }
  } catch (e) {
    alert('通信エラーが発生しました');
  } finally {
    isLoading.value = false;
  }
};

const update = async () => {
  if (!editForm.value.username) return;
  
  isLoading.value = true;
  try {
    const res = await fetch(`/api/users.php?id=${editingUser.value}&_method=PUT`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify(editForm.value)
    });
    
    if (res.ok) {
      alert('ユーザー情報を更新しました');
      editingUser.value = null;
      showEditModal.value = false;
      await fetchUsers();
    } else {
      const data = await res.json();
      alert(data.error || '更新に失敗しました');
    }
  } catch (e) {
    alert('通信エラーが発生しました');
  } finally {
    isLoading.value = false;
  }
};

import { Trash2 } from 'lucide-vue-next';

const deleteUser = async (id: number) => {
  if (!confirm('このユーザーを削除してもよろしいですか？（関連する経費がある場合は削除できません）')) return;
  
  isLoading.value = true;
  try {
    const res = await fetch(`/api/users.php?id=${id}&_method=DELETE`, {
      method: 'POST',
      headers: getHeaders()
    });
    
    if (res.ok) {
      alert('ユーザーを削除しました');
      await fetchUsers();
    } else {
      const data = await res.json();
      alert(data.error || 'ユーザーの削除に失敗しました');
    }
  } catch (e) {
    alert('通信エラーが発生しました');
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  if (authStore.user?.role !== 'admin') {
    router.push('/');
  } else {
    fetchUsers();
    fetchCodes();
  }
});
</script>

<template>
  <div class="user-management fade-enter-active">
    <div class="header-actions">
      <h2>ユーザー管理</h2>
    </div>

    <div class="management-grid">
      <!-- Add User Form -->
      <div class="glass glass-panel form-container">
        <h3>新規ユーザー追加</h3>
        <form @submit.prevent="submit" class="mt-4">
          <div class="form-group">
            <label class="form-label">ユーザーID (英数字・ハイフン) *</label>
            <input v-model="form.username" type="text" class="form-input" required pattern="[a-zA-Z0-9\-]+" placeholder="例：yamada" title="半角英数字、ハイフンのみ" />
          </div>
          
          <div class="form-group">
            <label class="form-label">管理画面での表示名</label>
            <input v-model="form.display_name" type="text" class="form-input" placeholder="例：山田 太郎" />
          </div>

          <div class="form-group">
            <label class="form-label">パスワード (英数字) *</label>
            <input v-model="form.password" type="password" class="form-input" required pattern="[a-zA-Z0-9]+" title="半角英数字のみ" />
          </div>

          <div class="form-group">
            <label class="form-label">権限 *</label>
            <select v-model="form.role" class="form-select" required>
              <option value="user">一般社員</option>
              <option value="admin">管理者</option>
            </select>
          </div>

          <div class="bank-info-section mt-4">
            <h4>振込先情報</h4>
            <div class="form-grid-mini">
              <div class="form-group">
                <label class="form-label">銀行コード</label>
                <input v-model="form.bank_code" type="text" class="form-input" maxlength="4" placeholder="0001" />
              </div>
              <div class="form-group">
                <label class="form-label">支店コード</label>
                <input v-model="form.branch_code" type="text" class="form-input" maxlength="3" placeholder="001" />
              </div>
            </div>
            <div class="form-grid-mini">
              <div class="form-group">
                <label class="form-label">種目</label>
                <select v-model="form.account_type" class="form-select">
                  <option v-for="code in accountTypes" :key="code.code_value" :value="code.code_value">
                    {{ code.display_name }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">口座番号</label>
                <input v-model="form.account_number" type="text" class="form-input" maxlength="7" placeholder="1234567" />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">口座名義人（カナ）</label>
              <input v-model="form.account_holder" type="text" class="form-input" placeholder="ヤマダ タロウ" />
            </div>
          </div>

          <div class="form-actions mt-4">
            <button type="submit" class="btn btn-primary w-100" :disabled="isLoading">
              <UserPlus :size="18" style="margin-right: 8px;" />
              {{ isLoading ? '追加中...' : 'ユーザーを追加' }}
            </button>
          </div>
        </form>
      </div>

      <!-- User List -->
      <div class="glass glass-panel list-container">
        <h3>登録済みユーザー</h3>
        <div class="table-responsive mt-4">
          <table class="user-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>ユーザー名</th>
                <th>権限</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="users.length === 0">
                <td colspan="4" class="empty-state">ユーザーが見つかりません。</td>
              </tr>
              <tr v-for="u in users" :key="u.id">
                <td>{{ u.id }}</td>
                <td>
                  <div class="user-info-cell">
                    <div class="username">{{ u.display_name || u.username }} <small v-if="u.display_name" class="text-muted">({{ u.username }})</small></div>
                    <div v-if="u.bank_code" class="bank-preview text-muted">
                      {{ u.bank_code }}-{{ u.branch_code }} {{ u.account_type_name || u.account_type }} {{ u.account_number }}
                    </div>
                  </div>
                </td>
                <td>
                  <span class="role-badge" :class="u.role">
                    <Shield v-if="u.role === 'admin'" :size="14" style="margin-right: 4px;" />
                    <User v-else :size="14" style="margin-right: 4px;" />
                    {{ u.role === 'admin' ? '管理者' : '一般社員' }}
                  </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <button @click="startEdit(u)" class="btn-icon" title="編集">
                    <Edit2 :size="16" />
                  </button>
                  <button v-if="u.id !== authStore.user?.id" @click="deleteUser(u.id)" class="btn-icon danger" title="削除">
                    <Trash2 :size="16" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="cancelEdit">
      <div class="glass modal-content fade-enter-active">
        <div class="modal-header">
          <h3>ユーザー情報を編集</h3>
          <button @click="cancelEdit" class="btn-icon">
            <X :size="20" />
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">ユーザーID (英数字・ハイフン) *</label>
            <input v-model="editForm.username" type="text" class="form-input" required pattern="[a-zA-Z0-9\-]+" title="半角英数字、ハイフンのみ" />
          </div>
          
          <div class="form-group">
            <label class="form-label">管理画面での表示名</label>
            <input v-model="editForm.display_name" type="text" class="form-input" placeholder="例：山田 太郎" />
          </div>
          
          <div class="form-group">
            <label class="form-label">パスワード（変更する場合のみ入力）</label>
            <input v-model="editForm.password" type="password" class="form-input" placeholder="新しいパスワード" />
          </div>

          <div class="form-group">
            <label class="form-label">権限</label>
            <select v-model="editForm.role" class="form-select">
              <option value="user">一般社員</option>
              <option value="admin">管理者</option>
            </select>
          </div>

          <div class="bank-info-section mt-4">
            <h4>振込先情報</h4>
            <div class="form-grid-mini">
              <div class="form-group">
                <label class="form-label">銀行コード</label>
                <input v-model="editForm.bank_code" type="text" class="form-input" maxlength="4" />
              </div>
              <div class="form-group">
                <label class="form-label">支店コード</label>
                <input v-model="editForm.branch_code" type="text" class="form-input" maxlength="3" />
              </div>
            </div>
            <div class="form-grid-mini">
              <div class="form-group">
                <label class="form-label">種目</label>
                <select v-model="editForm.account_type" class="form-select">
                  <option v-for="code in accountTypes" :key="code.code_value" :value="code.code_value">
                    {{ code.display_name }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">口座番号</label>
                <input v-model="editForm.account_number" type="text" class="form-input" maxlength="7" />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">口座名義人（カナ）</label>
              <input v-model="editForm.account_holder" type="text" class="form-input" />
            </div>
          </div>
        </div>

        <div class="modal-footer mt-6">
          <button @click="cancelEdit" class="btn btn-secondary">キャンセル</button>
          <button @click="update" class="btn btn-primary" :disabled="isLoading">
            <Save :size="18" style="margin-right: 8px;" />
            {{ isLoading ? '保存中...' : '変更を保存' }}
          </button>
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
.header-actions h2 {
  color: var(--primary-color);
}
.management-grid {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 24px;
}
@media (max-width: 768px) {
  .management-grid {
    grid-template-columns: 1fr;
  }
}
.mt-4 {
  margin-top: 16px;
}
.w-100 {
  width: 100%;
}
.user-table {
  width: 100%;
  border-collapse: collapse;
}
.user-table th, .user-table td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.user-table th {
  color: var(--text-muted);
  font-weight: 500;
  font-size: 0.9rem;
}
.role-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}
.role-badge.admin {
  background: rgba(99, 102, 241, 0.15);
  color: #4f46e5;
  border: 1px solid rgba(99, 102, 241, 0.2);
}
.role-badge.user {
  background: rgba(16, 185, 129, 0.15);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.bank-info-section h4 {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin-bottom: 12px;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  padding-bottom: 4px;
}

.form-grid-mini {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.label-xs {
  font-size: 0.7rem;
  color: var(--text-muted);
  display: block;
  margin-bottom: 2px;
}

.form-input.sm, .form-select.sm {
  padding: 6px 10px;
  font-size: 0.85rem;
}

.form-input.xs, .form-select.xs {
  padding: 4px 8px;
  font-size: 0.75rem;
}

.bank-edit-grid {
  display: grid;
  grid-template-columns: 60px 60px 70px 1fr 1fr;
  gap: 4px;
  margin-top: 8px;
}

.user-info-cell .username {
  font-weight: 500;
}

.bank-preview {
  font-size: 0.75rem;
  margin-top: 2px;
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
  max-height: 90vh;
  overflow-y: auto;
  padding: 24px;
  background: var(--surface-color);
  animation: modal-enter 0.3s ease;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  padding-bottom: 12px;
}

.modal-header h3 {
  margin: 0;
  color: var(--primary-color);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.mt-6 {
  margin-top: 24px;
}

@keyframes modal-enter {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
