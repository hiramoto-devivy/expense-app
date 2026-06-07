<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useExpenseStore } from '../store/expense';
import { useRoute, useRouter } from 'vue-router';
import { Save, UploadCloud, AlertCircle, Plus, Trash2 } from 'lucide-vue-next';

const expenseStore = useExpenseStore();
const route = useRoute();
const router = useRouter();

const isEditing = ref(false);
const editId = ref<number | null>(null);
const existingReceiptPath = ref<string | null>(null);

interface ExpenseItem {
  date: string;
  category_id: string;
  amount: string;
  description: string;
}

const items = ref<ExpenseItem[]>([
  {
    date: new Date().toISOString().split('T')[0],
    category_id: '',
    amount: '',
    description: ''
  }
]);

const receipt = ref({
  base64: '',
  name: ''
});

const isSubmitting = ref(false);

const hasClosedMonth = computed(() => {
  return items.value.some(item => item.date && expenseStore.closedMonths.includes(item.date.slice(0, 7)));
});

onMounted(async () => {
  if (expenseStore.categories.length === 0) {
    await expenseStore.fetchCategories();
  }
  
  if (route.params.id) {
    isEditing.value = true;
    editId.value = parseInt(route.params.id as string, 10);
    
    const exp = expenseStore.expenses.find(e => e.id === editId.value);
    if (exp) {
      items.value[0].date = exp.date;
      items.value[0].category_id = exp.category_id;
      items.value[0].amount = exp.amount;
      items.value[0].description = exp.description || '';
      existingReceiptPath.value = exp.receipt_file_path;
    } else {
      alert('指定された経費が見つかりません');
      router.push('/expenses');
    }
  }
});

const handleFileUpload = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file) processFile(file);
};

const handleDrop = (event: DragEvent) => {
  const file = event.dataTransfer?.files?.[0];
  if (file) processFile(file);
};

const processFile = (file: File) => {
  const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
  if (!validTypes.includes(file.type)) {
    alert('画像またはPDFファイルを選択してください');
    return;
  }

  const reader = new FileReader();
  reader.onload = (e) => {
    receipt.value.base64 = e.target?.result as string;
    receipt.value.name = file.name;
  };
  reader.readAsDataURL(file);
};

const addItem = () => {
  const lastItem = items.value[items.value.length - 1];
  items.value.push({
    date: lastItem.date,
    category_id: '',
    amount: '',
    description: ''
  });
};

const removeItem = (index: number) => {
  items.value.splice(index, 1);
};

const submit = async () => {
  for (const item of items.value) {
    if (!item.category_id || !item.amount || !item.date) {
      alert('必須項目をすべて入力してください');
      return;
    }
  }
  
  if (hasClosedMonth.value) {
    alert('締め込み済みの月が含まれているため、保存できません。');
    return;
  }
  
  isSubmitting.value = true;
  
  let success = false;
  if (isEditing.value && editId.value) {
    const item = items.value[0];
    const payload = {
      ...item,
      year_month: item.date.slice(0, 7),
      amount: parseInt(item.amount, 10),
      receipt_base64: receipt.value.base64,
      receipt_name: receipt.value.name
    };
    success = await expenseStore.updateExpense(editId.value, payload);
  } else {
    const payload = {
      expenses: items.value.map(item => ({
        ...item,
        year_month: item.date.slice(0, 7),
        amount: parseInt(item.amount, 10)
      })),
      receipt_base64: receipt.value.base64,
      receipt_name: receipt.value.name
    };
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

    <div v-if="hasClosedMonth" class="closed-alert">
      <AlertCircle :size="16" style="margin-right: 8px;" />
      ※締め込み済みの月が含まれているため、保存できません。
    </div>

    <div class="glass glass-panel form-container">
      <form @submit.prevent="submit">
        
        <div class="expense-items-wrapper">
          <div v-for="(item, index) in items" :key="index" class="expense-item">
            <div class="item-header" v-if="!isEditing && items.length > 1">
              <h4>明細 {{ index + 1 }}</h4>
              <button type="button" class="btn-icon danger" @click="removeItem(index)" title="この明細を削除">
                <Trash2 :size="16" />
              </button>
            </div>
            
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">日付 *</label>
                <input v-model="item.date" type="date" class="form-input" required />
              </div>
              
              <div class="form-group">
                <label class="form-label">カテゴリ *</label>
                <select v-model="item.category_id" class="form-select" required>
                  <option value="" disabled>カテゴリを選択</option>
                  <option v-for="cat in expenseStore.categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">金額 (円) *</label>
              <input v-model="item.amount" type="number" min="1" class="form-input" required placeholder="例：1500" />
            </div>
            
            <div class="form-group">
              <label class="form-label">メモ</label>
              <input v-model="item.description" type="text" class="form-input" placeholder="例：クライアントとの昼食" />
            </div>
          </div>
        </div>

        <div v-if="!isEditing" class="add-item-action">
          <button type="button" class="btn btn-secondary w-100 dashed-btn" @click="addItem">
            <Plus :size="16" style="margin-right: 8px;" />
            同じ領収書でもう一件（明細）を追加する
          </button>
        </div>

        <div class="form-group mt-6">
          <label class="form-label">領収書（画像/PDF） - {{ !isEditing && items.length > 1 ? '全ての明細に共通' : '任意' }}</label>
          <div class="file-upload-wrapper">
            <input type="file" id="file" @change="handleFileUpload" accept="image/*,application/pdf" class="file-input" />
            <label for="file" class="file-label" :class="{ 'has-existing': existingReceiptPath && !receipt.name }"
                   @dragover.prevent @dragleave.prevent @drop.prevent="handleDrop">
              <UploadCloud :size="24" class="upload-icon" />
              <div v-if="receipt.name" class="file-info">
                <strong>選択中:</strong> {{ receipt.name }}
              </div>
              <div v-else-if="existingReceiptPath" class="file-info">
                <strong>登録済み:</strong> {{ existingReceiptPath.split('/').pop() }}
                <div class="hint">クリックまたはドラッグ＆ドロップでファイルを変更</div>
              </div>
              <span v-else>クリックしてアップロード、またはドラッグ＆ドロップ</span>
            </label>
          </div>
        </div>

        <div class="form-actions mt-6">
          <button type="button" class="btn btn-danger" @click="router.push('/expenses')">キャンセル</button>
          <button type="submit" class="btn btn-primary" :disabled="isSubmitting || hasClosedMonth">
            <Save :size="18" style="margin-right: 8px;" />
            {{ isSubmitting ? '保存中...' : (isEditing ? '変更を保存' : '経費を保存') }}
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
.expense-item {
  padding: 16px;
  background: rgba(255, 255, 255, 0.3);
  border-radius: 8px;
  margin-bottom: 16px;
  border: 1px solid rgba(0,0,0,0.05);
}
.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  padding-bottom: 8px;
}
.item-header h4 {
  margin: 0;
  font-size: 0.95rem;
  color: var(--text-muted);
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
.file-label:hover, .file-label:focus {
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
}
.mt-6 {
  margin-top: 24px;
}
.w-100 {
  width: 100%;
}
.dashed-btn {
  border: 1px dashed var(--primary-color);
  background: transparent;
  color: var(--primary-color);
}
.dashed-btn:hover {
  background: rgba(99, 102, 241, 0.05);
}
.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  padding: 4px;
  border-radius: 4px;
  transition: all 0.2s;
}
.btn-icon.danger:hover {
  color: var(--danger);
  background: rgba(239, 68, 68, 0.1);
}
</style>
