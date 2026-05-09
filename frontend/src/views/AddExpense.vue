<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useExpenseStore } from '../store/expense';
import { useRouter } from 'vue-router';
import { Save, UploadCloud } from 'lucide-vue-next';

const expenseStore = useExpenseStore();
const router = useRouter();

const form = ref({
  date: new Date().toISOString().split('T')[0],
  category_id: '',
  amount: '',
  description: '',
  receipt_base64: '',
  receipt_name: ''
});

const isSubmitting = ref(false);

onMounted(() => {
  if (expenseStore.categories.length === 0) {
    expenseStore.fetchCategories();
  }
});

const handleFileUpload = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    form.value.receipt_base64 = e.target?.result as string;
    form.value.receipt_name = file.name;
  };
  reader.readAsDataURL(file);
};

const submit = async () => {
  if (!form.value.category_id || !form.value.amount || !form.value.date) {
    alert('必須項目をすべて入力してください');
    return;
  }
  
  isSubmitting.value = true;
  
  const d = new Date(form.value.date);
  const yearMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  
  const payload = {
    ...form.value,
    year_month: yearMonth,
    amount: parseInt(form.value.amount, 10)
  };
  
  const success = await expenseStore.addExpense(payload);
  
  isSubmitting.value = false;
  if (success) {
    router.push('/expenses');
  } else {
    alert('経費の追加に失敗しました');
  }
};
</script>

<template>
  <div class="add-expense fade-enter-active">
    <div class="header-actions">
      <h2>新しい経費の追加</h2>
    </div>

    <div class="glass glass-panel form-container">
      <form @submit.prevent="submit">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">日付 *</label>
            <input v-model="form.date" type="date" class="form-input" required />
          </div>
          
          <div class="form-group">
            <label class="form-label">カテゴリ *</label>
            <select v-model="form.category_id" class="form-select" required>
              <option value="" disabled>カテゴリを選択</option>
              <option v-for="cat in expenseStore.categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label">金額 (円) *</label>
          <input v-model="form.amount" type="number" min="1" class="form-input" required placeholder="例：1500" />
        </div>
        
        <div class="form-group">
          <label class="form-label">メモ</label>
          <input v-model="form.description" type="text" class="form-input" placeholder="例：クライアントとの昼食" />
        </div>

        <div class="form-group">
          <label class="form-label">領収書（画像/PDF）</label>
          <div class="file-upload-wrapper">
            <input type="file" id="file" @change="handleFileUpload" accept="image/*,application/pdf" class="file-input" />
            <label for="file" class="file-label">
              <UploadCloud :size="24" class="upload-icon" />
              <span v-if="form.receipt_name">{{ form.receipt_name }}</span>
              <span v-else>クリックしてアップロード、またはドラッグ＆ドロップ</span>
            </label>
          </div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-danger" @click="router.push('/expenses')">キャンセル</button>
          <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
            <Save :size="18" style="margin-right: 8px;" />
            {{ isSubmitting ? '保存中...' : '経費を保存' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.header-actions {
  margin-bottom: 24px;
}
.form-container {
  max-width: 600px;
  margin: 0 auto;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.file-input {
  display: none;
}
.file-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px;
  border: 2px dashed rgba(99, 102, 241, 0.4);
  border-radius: 8px;
  background: rgba(255,255,255,0.5);
  cursor: pointer;
  transition: all 0.2s;
  color: var(--text-muted);
}
.file-label:hover {
  background: rgba(99, 102, 241, 0.05);
  border-color: var(--primary-color);
  color: var(--primary-color);
}
.upload-icon {
  margin-bottom: 8px;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 32px;
}
</style>
