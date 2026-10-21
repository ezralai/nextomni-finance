<template>
  <div
    v-if="modelValue"
    class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
  >
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
      <button
        @click="close"
        class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
      >
        <X class="w-5 h-5" />
      </button>

      <div class="space-y-1">
        <h3 class="text-lg font-bold text-white flex items-center space-x-2">
          <Upload class="w-5 h-5 text-emerald-400" />
          <span>Reconcile Bank Statement (CSV)</span>
        </h3>
        <p class="text-xs text-slate-400">
          Upload exported statement from Maybank, CIMB, or any financial CSV with automated deduplication
        </p>
      </div>

      <div v-if="error" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
        <span>{{ error }}</span>
      </div>

      <div v-if="successMessage" class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300 flex items-start space-x-2">
        <CheckCircle2 class="w-4 h-4 flex-shrink-0 mt-0.5 text-emerald-400" />
        <span>{{ successMessage }}</span>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Target Account</label>
          <select
            v-model="targetAccountId"
            required
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
          >
            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
              {{ acc.name }} ({{ acc.currency }}) - {{ acc.institution?.name || 'Manual' }}
            </option>
          </select>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Statement CSV File</label>
          <div class="border-2 border-dashed border-slate-800 hover:border-emerald-500/50 rounded-xl p-4 text-center transition bg-slate-950/50">
            <input
              id="statementModalFileInput"
              type="file"
              accept=".csv,.txt"
              required
              @change="onFileChange"
              class="hidden"
            />
            <label for="statementModalFileInput" class="cursor-pointer block space-y-2">
              <Upload class="w-6 h-6 text-slate-400 mx-auto" />
              <div class="text-xs text-slate-300 font-medium">
                {{ selectedFile ? selectedFile.name : 'Click to select CSV file' }}
              </div>
              <div class="text-[11px] text-slate-500">
                Supports Maybank, CIMB, Touch 'n Go, RHB, and standard canonical CSV formats
              </div>
              <!-- Supported Banks Interactive Logo Strip -->
              <div class="flex items-center justify-center space-x-2 pt-1.5">
                <InstitutionLogo code="MBB" size="xs" interactive />
                <InstitutionLogo code="CIMB" size="xs" interactive />
                <InstitutionLogo code="TNG" size="xs" interactive />
                <InstitutionLogo code="RHB" size="xs" interactive />
                <InstitutionLogo code="PBB" size="xs" interactive />
                <InstitutionLogo code="WISE" size="xs" interactive />
              </div>
            </label>
          </div>
        </div>

        <button
          type="submit"
          :disabled="isUploading || !selectedFile || !targetAccountId"
          class="w-full py-2.5 rounded-xl text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
        >
          <Upload class="w-4 h-4" />
          <span>{{ isUploading ? 'Reconciling Ledger...' : 'Parse & Reconcile Statement' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Upload, AlertCircle, CheckCircle2, X } from 'lucide-vue-next'
import InstitutionLogo from '~/components/ui/InstitutionLogo.vue'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    accounts: any[]
    isUploading?: boolean
    error?: string | null
    successMessage?: string | null
  }>(),
  {
    isUploading: false,
    error: null,
    successMessage: null,
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
  (e: 'submit', payload: { accountId: string; file: File }): void
}>()

const targetAccountId = ref('')
const selectedFile = ref<File | null>(null)

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      if (props.accounts.length > 0 && !targetAccountId.value) {
        targetAccountId.value = props.accounts[0]?.id || ''
      }
      selectedFile.value = null
    }
  }
)

const onFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    selectedFile.value = target.files[0] || null
  }
}

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

const handleSubmit = () => {
  if (!selectedFile.value || !targetAccountId.value) return
  emit('submit', {
    accountId: targetAccountId.value,
    file: selectedFile.value,
  })
}
</script>
