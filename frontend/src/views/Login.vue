<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore } from '../store/auth';
import { useRouter } from 'vue-router';
import { LogIn } from 'lucide-vue-next';

const username = ref('');
const password = ref('');
const error = ref('');
const isLoading = ref(false);

const authStore = useAuthStore();
const router = useRouter();

const login = async () => {
  error.value = '';
  isLoading.value = true;
  try {
    const res = await fetch('/api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: username.value, password: password.value })
    });
    
    if (res.ok) {
      const data = await res.json();
      authStore.setToken(data.token, data.user);
      router.push('/');
    } else {
      const data = await res.json();
      error.value = data.error || 'ログインに失敗しました';
    }
  } catch (e) {
    error.value = 'ネットワークエラー';
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div class="login-wrapper">
    <div class="glass glass-panel login-box">
      <div class="login-header">
        <h2>ログイン</h2>
        <p>経費を管理するためにサインインしてください</p>
      </div>
      
      <div v-if="error" class="error-alert">{{ error }}</div>
      
      <form @submit.prevent="login">
        <div class="form-group">
          <label class="form-label">ユーザー名</label>
          <input v-model="username" type="text" class="form-input" required />
        </div>
        
        <div class="form-group">
          <label class="form-label">パスワード</label>
          <input v-model="password" type="password" class="form-input" required />
        </div>
        
        <button type="submit" class="btn btn-primary w-100" :disabled="isLoading">
          <LogIn :size="18" style="margin-right: 8px;" />
          {{ isLoading ? 'サインイン中...' : 'サインイン' }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  height: calc(100vh - 100px);
}
.login-box {
  width: 100%;
  max-width: 400px;
}
.login-header {
  text-align: center;
  margin-bottom: 24px;
}
.login-header h2 {
  color: var(--primary-color);
  margin-bottom: 8px;
}
.login-header p {
  color: var(--text-muted);
}
.w-100 {
  width: 100%;
}
.error-alert {
  background: rgba(239, 68, 68, 0.1);
  color: var(--danger);
  padding: 10px;
  border-radius: 8px;
  margin-bottom: 16px;
  text-align: center;
  border: 1px solid rgba(239, 68, 68, 0.2);
}
</style>
