<script setup lang="ts">
import { useAuthStore } from './store/auth';
import { LogOut, LayoutDashboard, PlusCircle, List } from 'lucide-vue-next';

const authStore = useAuthStore();
// router removed since it's unused

const logout = () => {
  authStore.logout();
};
</script>

<template>
  <div class="app-container">
    <header class="header" v-if="authStore.isAuthenticated()">
      <h1>Expense App</h1>
      <nav class="nav-links">
        <router-link to="/" class="nav-item">
          <LayoutDashboard :size="18" /> ダッシュボード
        </router-link>
        <router-link to="/expenses" class="nav-item">
          <List :size="18" /> 経費一覧
        </router-link>
        <router-link to="/add" class="nav-item">
          <PlusCircle :size="18" /> 経費追加
        </router-link>
        <button @click="logout" class="btn btn-danger nav-item">
          <LogOut :size="18" style="margin-right: 4px;" /> ログアウト
        </button>
      </nav>
    </header>
    
    <router-view v-slot="{ Component }">
      <transition name="fade" mode="out-in">
        <component :is="Component" />
      </transition>
    </router-view>
  </div>
</template>

<style scoped>
.nav-links {
  display: flex;
  gap: 15px;
  align-items: center;
}
.nav-item {
  display: flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  color: var(--text-main);
  font-weight: 500;
  transition: color 0.2s;
}
.nav-item:hover, .router-link-active {
  color: var(--primary-color);
}
</style>
