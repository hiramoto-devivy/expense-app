<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useExpenseStore } from '../store/expense';
import { useRoute, useRouter } from 'vue-router';
import { Save, UploadCloud, AlertCircle } from 'lucide-vue-next';

const expenseStore = useExpenseStore();
const route = useRoute();
const router = useRouter();

const isEditing = ref(false);
const editId = ref<number | null>(null);
const existingReceiptPath = ref<string | null>(null);

const form = ref({
  date: new Date().toISOString().split('T')[0],
  category_id: '',
  amount: '',
  description: '',
  receipt_base64: '',
  receipt_name: ''
});

const isSubmitting = ref(false);

onMounted(async () => {
  if (expenseStore.categories.length === 0) {
    await expenseStore.fetchCategories();
  }
  
  if (route.params.id) {
    isEditing.value = true;
    editId.value = parseInt(route.params.id as string, 10);
    
    // Find the expense to edit. (Assume it's in the current list, or we could fetch it)
    const exp = expenseStore.expenses.find(e => e.id === editId.value);
    if (exp) {
      form.value.date = exp.date;
      form.value.category_id = exp.category_id;
      form.value.amount = exp.amount;
      form.value.description = exp.description || '';
      existingReceiptPath.value = exp.receipt_file_path;
      // We don't populate receipt_base64, it requires a new upload to overwrite
    } else {
      alert('指定された経費が見つかりません');
      router.push('/expenses');
    }
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
  
  const yearMonth = form.value.date.slice(0, 7);
  if (expenseStore.closedMonths.includes(yearMonth)) {
    alert('この月は締め込み済みのため、経費を追加できません。');
    return;
  }
  
  isSubmitting.value = true;
  
  const payload = {
    ...form.value,
    year_month: yearMonth,
    amount: parseInt(form.value.amount as string, 10)
  };
  
  let success = false;
  if (isEditing.value && editId.value) {
    success = await expenseStore.updateExpense(editId.value, payload);
  } else {
    success = await expenseStore.addExpense(payload);
  }
  
  isSubmitting.value = false;
  if (success) {
    router.push('/expenses');
  } else {
    alert(isEditing.value ? '経費の更新に失敗しました' : '経費の追加に失敗しました');
  }
};
</script>

<template>
  <div class="add-expense fade-enter-active">
    <div class="header-actions">
      <h2>{{ isEditing ? '経費の編集' : '新しい経費の追加' }}</h2>
    </div>

    <div v-if="form.date && expenseStore.closedMonths.includes(form.date.slice(0, 7))" class="closed-alert">
      <AlertCircle :size="16" style="margin-right: 8px;" />
      ※選択された月は締め込み済みのため、保存できません。
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
            <label for="file" class="file-label" :class="{ 'has-existing': existingReceiptPath && !form.receipt_name }">
              <UploadCloud :size="24" class="upload-icon" />
              <div v-if="form.receipt_name" class="file-info">
                <strong>選択中:</strong> {{ form.receipt_name }}
              </div>
              <div v-else-if="existingReceiptPath" class="file-info">
                <strong>登録済み:</strong> {{ existingReceiptPath.split('/').pop() }}
                <div class="hint">クリックしてファイルを変更</div>
              </div>
              <span v-else>クリックしてアップロード、またはドラッグ＆ドロップ</span>
            </label>
          </div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-danger" @click="router.push('/expenses')">キャンセル</button>
          <button type="submit" class="btn btn-primary" :disabled="isSubmitting || !!(form.date && expenseStore.closedMonths.includes(form.date.slice(0, 7)))">
            <Save :size="18" style="margin-right: 8px;" />
            {{ isSubmitting ? '保存中...' : '経費を保存' }}
          </button>
        </div>
      </form>
    </div>
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
.form-container {
  max-width: 600px;
  margin: 0 auto;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
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
.file-info {
  text-align: center;
}
.hint {
  font-size: 0.8rem;
  margin-top: 4px;
  opacity: 0.8;
}
.has-existing {
  border-style: solid;
  background: rgba(99, 102, 241, 0.05);
  color: var(--primary-color);
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 32px;
}
</style>
