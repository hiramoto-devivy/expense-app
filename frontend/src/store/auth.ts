import { defineStore } from 'pinia';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('token'));
  const user = ref<any>(null);
  const router = useRouter();

  const isAuthenticated = () => !!token.value;

  const setToken = (newToken: string, userData: any) => {
    token.value = newToken;
    user.value = userData;
    localStorage.setItem('token', newToken);
  };

  const logout = () => {
    token.value = null;
    user.value = null;
    localStorage.removeItem('token');
    router.push('/login');
  };

  const verifyToken = async () => {
    if (!token.value) return false;
    try {
      const res = await fetch('/api/verify.php', {
        headers: { Authorization: `Bearer ${token.value}` }
      });
      if (res.ok) {
        const data = await res.json();
        user.value = data.user;
        return true;
      } else {
        logout();
        return false;
      }
    } catch (e) {
      console.error(e);
      return false;
    }
  };

  return { token, user, isAuthenticated, setToken, logout, verifyToken };
});
