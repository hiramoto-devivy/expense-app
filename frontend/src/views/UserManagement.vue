<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../store/auth';
import { useRouter } from 'vue-router';
import { UserPlus, Shield, User } from 'lucide-vue-next';

const authStore = useAuthStore();
const router = useRouter();

const users = ref<any[]>([]);
const isLoading = ref(false);

const form = ref({
  username: '',
  password: '',
  role: 'user'
});

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
      form.value = { username: '', password: '', role: 'user' };
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

onMounted(() => {
  if (authStore.user?.role !== 'admin') {
    router.push('/');
  } else {
    fetchUsers();
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
            <label class="form-label">ユーザー名 *</label>
            <input v-model="form.username" type="text" class="form-input" required placeholder="例：yamada" />
          </div>
          
          <div class="form-group">
            <label class="form-label">パスワード *</label>
            <input v-model="form.password" type="password" class="form-input" required />
          </div>

          <div class="form-group">
            <label class="form-label">権限 *</label>
            <select v-model="form.role" class="form-select" required>
              <option value="user">一般社員</option>
              <option value="admin">管理者</option>
            </select>
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
              </tr>
            </thead>
            <tbody>
              <tr v-if="users.length === 0">
                <td colspan="3" class="empty-state">ユーザーが見つかりません。</td>
              </tr>
              <tr v-for="u in users" :key="u.id">
                <td>{{ u.id }}</td>
                <td>{{ u.username }}</td>
                <td>
                  <span class="role-badge" :class="u.role">
                    <Shield v-if="u.role === 'admin'" :size="14" style="margin-right: 4px;" />
                    <User v-else :size="14" style="margin-right: 4px;" />
                    {{ u.role === 'admin' ? '管理者' : '一般社員' }}
                  </span>
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
  background: rgba(139, 92, 246, 0.2);
  color: #c4b5fd;
  border: 1px solid rgba(139, 92, 246, 0.3);
}
.role-badge.user {
  background: rgba(16, 185, 129, 0.2);
  color: #6ee7b7;
  border: 1px solid rgba(16, 185, 129, 0.3);
}
</style>
