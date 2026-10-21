<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useAccountStore } from '~/stores/accounts'
import { useTransactionStore } from '~/stores/transactions'
import { useBudgetStore } from '~/stores/budgets'
import { useCurrencyStore, type ConversionResult } from '~/stores/currencies'
import {
  Wallet,
  ArrowUpRight,
  ArrowDownLeft,
  ArrowRightLeft,
  Activity,
  CheckCircle2,
  AlertCircle,
  Database,
  Server,
  Layers,
  Sparkles,
  RefreshCw,
  TrendingUp,
  User as UserIcon,
  LogIn,
  LogOut,
  X,
  Lock,
  Mail,
  UserCheck,
  ShieldCheck,
  Globe,
  Plus,
  CreditCard,
  Building,
  Smartphone,
  Coins,
  PiggyBank,
  Trash2,
  ExternalLink,
  Search,
  Filter,
  Calendar,
  Tag,
  ReceiptText,
  DollarSign,
  Target,
  BarChart3,
  Percent,
  ArrowLeftRight,
  Download,
  FileSpreadsheet,
  CalendarClock,
  Repeat,
  Check,
  Trophy,
  Flag,
  Upload,
  Sun,
  Moon,
  Menu,
  LayoutDashboard,
  HelpCircle,
  BookOpen,
  SlidersHorizontal,
} from 'lucide-vue-next'
import { useTheme } from '~/composables/useTheme'
import { useToast } from '~/composables/useToast'
import { useReportStore } from '~/stores/reports'
import { useRecurringStore } from '~/stores/recurring'
import { useGoalStore } from '~/stores/goals'
import { useImportStore } from '~/stores/imports'
import ToastContainer from '~/components/ui/ToastContainer.vue'
import SkeletonLoader from '~/components/ui/SkeletonLoader.vue'
import AmountInput from '~/components/ui/AmountInput.vue'
import ConfirmModal from '~/components/ui/ConfirmModal.vue'
import TransactionTable from '~/components/transactions/TransactionTable.vue'
import ToolsTab from '~/components/tools/ToolsTab.vue'
import InstitutionLogo from '~/components/ui/InstitutionLogo.vue'
import InfoTooltip from '~/components/ui/InfoTooltip.vue'
import OnboardingModal from '~/components/ui/OnboardingModal.vue'
import FinancialGlossaryModal from '~/components/ui/FinancialGlossaryModal.vue'
import GettingStartedChecklist from '~/components/ui/GettingStartedChecklist.vue'
import QuickStartHub from '~/components/ui/QuickStartHub.vue'
import MarketTickerTape from '~/components/markets/MarketTickerTape.vue'
import MarketsTab from '~/components/markets/MarketsTab.vue'
import AnalyticsTab from '~/components/analytics/AnalyticsTab.vue'
import AuthModal from '~/components/auth/AuthModal.vue'
import AddAccountModal from '~/components/workspace/modals/AddAccountModal.vue'
import TransactionModal from '~/components/workspace/modals/TransactionModal.vue'
import TransferModal from '~/components/workspace/modals/TransferModal.vue'
import { useMarketStore } from '~/stores/markets'

const config = useRuntimeConfig()
const apiBase = config.public.apiBase
const authStore = useAuthStore()
const accountStore = useAccountStore()
const transactionStore = useTransactionStore()
const budgetStore = useBudgetStore()
const currencyStore = useCurrencyStore()
const reportStore = useReportStore()
const recurringStore = useRecurringStore()
const goalStore = useGoalStore()
const importStore = useImportStore()
const marketStore = useMarketStore()
const toast = useToast()

interface HealthResponse {
  status: string
  app: string
  version: string
  environment: string
  timestamp: string
  database: {
    status: string
    driver: string
  }
}

const health = ref<HealthResponse | null>(null)
const isHealthLoading = ref(false)
const responseTimeMs = ref<number | null>(null)

// Theme & Navigation System
const { isDark, toggleTheme } = useTheme()
const activeTab = ref<'dashboard' | 'accounts' | 'transactions' | 'budgets' | 'analytics' | 'markets' | 'tools'>('dashboard')
const isMobileMenuOpen = ref(false)
const isOnboardingTourOpen = ref(false)
const isGlossaryOpen = ref(false)
const isBeginnerDashboardMode = ref(false)
const isChecklistForceVisible = ref(false)

const openOnboardingTour = () => {
  isOnboardingTourOpen.value = true
}

const openGlossary = () => {
  isGlossaryOpen.value = true
}

const toggleBeginnerDashboardMode = () => {
  isBeginnerDashboardMode.value = !isBeginnerDashboardMode.value
  if (typeof window !== 'undefined') {
    try {
      localStorage.setItem('nextomni_beginner_mode', String(isBeginnerDashboardMode.value))
    } catch {
      // Ignore localStorage restrictions
    }
  }
}

const handleOnboardingAction = (actionName: string) => {
  if (actionName === 'add-account') {
    openAddAccountModal()
  } else if (actionName === 'record-transaction') {
    openAddTransactionModal('expense')
  } else if (actionName === 'add-budget') {
    openAddBudgetModal()
  } else if (actionName === 'add-recurring') {
    openAddRecurringModal()
  }
}

// Global Confirmation Dialog State
const confirmState = ref({
  isOpen: false,
  title: 'Confirm Action',
  message: '',
  confirmText: 'Confirm',
  variant: 'danger' as 'danger' | 'warning' | 'info',
  isLoading: false,
  onConfirm: () => Promise.resolve() as Promise<void> | void,
})

const openConfirmDialog = (options: {
  title?: string
  message: string
  confirmText?: string
  variant?: 'danger' | 'warning' | 'info'
  onConfirm: () => Promise<void> | void
}) => {
  confirmState.value = {
    isOpen: true,
    title: options.title || 'Confirm Action',
    message: options.message,
    confirmText: options.confirmText || 'Confirm',
    variant: options.variant || 'danger',
    isLoading: false,
    onConfirm: options.onConfirm,
  }
}

const handleConfirmExecute = async () => {
  confirmState.value.isLoading = true
  try {
    await confirmState.value.onConfirm()
    confirmState.value.isOpen = false
  } finally {
    confirmState.value.isLoading = false
  }
}

const handleGlobalKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (confirmState.value.isOpen) {
      confirmState.value.isOpen = false
      return
    }
    if (showTransactionModal.value) showTransactionModal.value = false
    else if (showTransferModal.value) showTransferModal.value = false
    else if (showAddAccountModal.value) showAddAccountModal.value = false
    else if (showBudgetModal.value) showBudgetModal.value = false
    else if (showGoalModal.value) showGoalModal.value = false
    else if (showRecurringModal.value) showRecurringModal.value = false
    else if (showImportModal.value) showImportModal.value = false
    else if (showConverterModal.value) showConverterModal.value = false
    else if (showAuthModal.value) showAuthModal.value = false
    else if (isMobileMenuOpen.value) isMobileMenuOpen.value = false
  }
}

// Modal Visibility & Trigger State
const showAuthModal = ref(false)
const authMode = ref<'login' | 'register'>('login')
const isAuthSubmitting = ref(false)
const showAddAccountModal = ref(false)
const showTransactionModal = ref(false)
const showTransferModal = ref(false)
const defaultTransactionType = ref<'expense' | 'income'>('expense')

// Budget Modal State
const showBudgetModal = ref(false)
const budgetError = ref<string | null>(null)

const now = new Date()
const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
const lastDayOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)

const newBudgetForm = ref({
  category_id: '',
  amount: '',
  period: 'monthly',
  start_date: firstDayOfMonth,
  end_date: lastDayOfMonth,
  notes: '',
})

// FX Converter Modal State
const showConverterModal = ref(false)
const converterForm = ref({
  amount: 100,
  from: 'USD',
  to: 'MYR',
})
const conversionResult = ref<ConversionResult | null>(null)
const converterError = ref<string | null>(null)

// Recurring Transactions & Subscription Modal State
const showRecurringModal = ref(false)
const recurringError = ref<string | null>(null)
const newRecurringForm = ref({
  account_id: '',
  category_id: '',
  type: 'expense' as 'income' | 'expense',
  description: '',
  amount: '',
  frequency: 'monthly',
  start_date: new Date().toISOString().slice(0, 10),
  end_date: '',
  auto_post: true,
  notes: '',
})

// Financial Goals & Savings Milestones Modal State
const showGoalModal = ref(false)
const showContributeModal = ref(false)
const selectedGoalForContribution = ref<any>(null)
const contributionAmount = ref('')
const goalError = ref<string | null>(null)
const newGoalForm = ref({
  name: '',
  description: '',
  target_amount: '',
  current_amount: '',
  target_date: '',
  account_id: '',
  color: '#10b981',
})

// Bank Statement Import Modal State
const showImportModal = ref(false)
const importAccountId = ref('')
const selectedImportFile = ref<File | null>(null)
const importError = ref<string | null>(null)
const importSuccessMessage = ref<string | null>(null)

// Transaction Filter State
const filterType = ref<'all' | 'income' | 'expense' | 'transfer'>('all')
const filterAccountId = ref<string>('')
const filterSearch = ref<string>('')

// Currency Switcher
const isUpdatingCurrency = ref(false)

const currencySymbol = computed(() => {
  const cur = authStore.user?.base_currency || 'MYR'
  switch (cur) {
    case 'USD':
      return '$'
    case 'SGD':
      return 'S$'
    case 'EUR':
      return '€'
    case 'GBP':
      return '£'
    default:
      return 'RM'
  }
})

const checkHealth = async () => {
  isHealthLoading.value = true
  const startTime = performance.now()
  try {
    const data = await $fetch<HealthResponse>(`${apiBase}/health`)
    health.value = data
    responseTimeMs.value = Math.round(performance.now() - startTime)
  } catch {
    health.value = null
    responseTimeMs.value = null
  } finally {
    isHealthLoading.value = false
  }
}

const handleAuthSuccess = async () => {
  showAuthModal.value = false
  await refreshDashboard()
}

const handleDemoLogin = async () => {
  isAuthSubmitting.value = true
  try {
    await authStore.demoLogin()
    showAuthModal.value = false
    toast.success('Demo Session Active', 'Signed in as Demo User')
    await refreshDashboard()
  } catch (err: any) {
    toast.error('Demo Error', err?.message || 'Failed to authenticate demo session.')
  } finally {
    isAuthSubmitting.value = false
  }
}

const handleCurrencyChange = async (newCurrency: string) => {
  if (!authStore.isAuthenticated || isUpdatingCurrency.value) return
  isUpdatingCurrency.value = true
  try {
    await authStore.updateProfile({ base_currency: newCurrency })
    await refreshDashboard()
  } catch (err: any) {
    console.error('Failed to update base currency:', err)
  } finally {
    isUpdatingCurrency.value = false
  }
}

const openAddAccountModal = () => {
  showAddAccountModal.value = true
}

const handleAccountCreated = async () => {
  await refreshDashboard()
}

const handleDeleteAccount = (id: string, name: string) => {
  openConfirmDialog({
    title: 'Remove Financial Account',
    message: `Are you sure you want to remove account "${name}"? All associated transactions and balance records will be removed.`,
    confirmText: 'Remove Account',
    variant: 'danger',
    onConfirm: async () => {
      try {
        await accountStore.deleteAccount(id)
        await refreshDashboard()
        toast.info('Account Removed', `Account "${name}" has been removed.`)
      } catch (err: any) {
        toast.error('Account Removal Failed', err?.data?.message || 'Failed to delete account.')
      }
    },
  })
}

// Transaction & Transfer Modal Triggers
const openAddTransactionModal = (defaultType: 'expense' | 'income' = 'expense') => {
  defaultTransactionType.value = defaultType
  showTransactionModal.value = true
}

const openAddTransferModal = () => {
  showTransferModal.value = true
}

const handleDeleteTransaction = (id: string | number, desc: string) => {
  openConfirmDialog({
    title: 'Delete Transaction',
    message: `Delete transaction "${desc}"? The financial account balance will be restored and reversed.`,
    confirmText: 'Delete & Reverse',
    variant: 'danger',
    onConfirm: async () => {
      try {
        await transactionStore.deleteTransaction(String(id))
        await refreshDashboard()
        toast.info('Transaction Deleted', `Reversed balance adjustment for "${desc}"`)
      } catch (err: any) {
        toast.error('Deletion Failed', err.message || 'Failed to delete transaction.')
      }
    },
  })
}

// Budget Modal Handlers
const openAddBudgetModal = () => {
  if (transactionStore.categories.length > 0 && !newBudgetForm.value.category_id) {
    const expenseCats = transactionStore.categories.filter(c => c.type === 'expense')
    newBudgetForm.value.category_id = expenseCats[0]?.id || ''
  }
  newBudgetForm.value.amount = ''
  newBudgetForm.value.notes = ''
  budgetError.value = null
  showBudgetModal.value = true
}

// 1-Click Starter Budget Presets for New Users
const popularBudgetPresets = [
  { icon: '🍔', name: 'Food & Dining', slug: 'food-dining', amount: 800 },
  { icon: '🛒', name: 'Groceries', slug: 'groceries-supermarket', amount: 500 },
  { icon: '🚗', name: 'Transportation', slug: 'transportation', amount: 350 },
  { icon: '💡', name: 'Utilities & Bills', slug: 'housing-utilities', amount: 450 },
  { icon: '🛍️', name: 'Lifestyle & Shopping', slug: 'lifestyle-shopping', amount: 300 },
]

const applyBudgetPreset = (preset: typeof popularBudgetPresets[0]) => {
  let cat = transactionStore.categories.find(c => c.slug === preset.slug)
  if (!cat) {
    for (const parent of transactionStore.categories) {
      const match = parent.children?.find(ch => ch.slug === preset.slug)
      if (match) {
        cat = parent
        break
      }
    }
  }
  if (!cat) {
    cat = transactionStore.categories.find(c => c.name.toLowerCase().includes(preset.name.toLowerCase().slice(0, 4)))
  }
  if (cat) {
    newBudgetForm.value.category_id = cat.id
  }
  newBudgetForm.value.amount = preset.amount
}

const handleCreateBudget = async () => {
  budgetError.value = null
  try {
    await budgetStore.createBudget({
      category_id: newBudgetForm.value.category_id,
      amount: newBudgetForm.value.amount,
      period: newBudgetForm.value.period,
      start_date: newBudgetForm.value.start_date,
      end_date: newBudgetForm.value.end_date,
      notes: newBudgetForm.value.notes || undefined,
    })
    showBudgetModal.value = false
    await refreshDashboard()
  } catch (err: any) {
    budgetError.value = err.message || 'Failed to create budget limit.'
  }
}

const handleDeleteBudget = (id: string, catName: string) => {
  openConfirmDialog({
    title: 'Remove Budget Limit',
    message: `Remove monthly spending limit for "${catName}"? This will not delete recorded transactions.`,
    confirmText: 'Remove Limit',
    variant: 'danger',
    onConfirm: async () => {
      try {
        await budgetStore.deleteBudget(id)
        await refreshDashboard()
        toast.info('Budget Removed', `Monthly limit for "${catName}" removed.`)
      } catch (err: any) {
        toast.error('Failed to Remove Budget', err.message || 'Operation failed.')
      }
    },
  })
}

// FX Converter Handlers
const openConverterModal = () => {
  converterForm.value.from = 'USD'
  converterForm.value.to = authStore.user?.base_currency || 'MYR'
  converterForm.value.amount = 100
  conversionResult.value = null
  converterError.value = null
  showConverterModal.value = true
  runConversion()
}

const runConversion = async () => {
  converterError.value = null
  try {
    conversionResult.value = await currencyStore.convert(
      converterForm.value.amount,
      converterForm.value.from,
      converterForm.value.to
    )
  } catch (err: any) {
    converterError.value = err.message || 'Conversion failed.'
  }
}

// Recurring Transactions Handlers
const openAddRecurringModal = () => {
  if (accountStore.accounts.length > 0 && !newRecurringForm.value.account_id) {
    newRecurringForm.value.account_id = accountStore.accounts[0]?.id || ''
  }
  if (transactionStore.categories.length > 0 && !newRecurringForm.value.category_id) {
    const expenseCats = transactionStore.categories.filter(c => c.type === 'expense')
    newRecurringForm.value.category_id = expenseCats[0]?.id || ''
  }
  newRecurringForm.value.description = ''
  newRecurringForm.value.amount = ''
  newRecurringForm.value.notes = ''
  recurringError.value = null
  showRecurringModal.value = true
}

const handleCreateRecurring = async () => {
  recurringError.value = null
  try {
    await recurringStore.createRecurring({
      account_id: newRecurringForm.value.account_id,
      category_id: newRecurringForm.value.category_id || undefined,
      type: newRecurringForm.value.type,
      description: newRecurringForm.value.description,
      amount: newRecurringForm.value.amount,
      frequency: newRecurringForm.value.frequency,
      start_date: newRecurringForm.value.start_date,
      end_date: newRecurringForm.value.end_date || undefined,
      auto_post: newRecurringForm.value.auto_post,
      notes: newRecurringForm.value.notes || undefined,
    })
    showRecurringModal.value = false
    await refreshDashboard()
  } catch (err: any) {
    recurringError.value = err.message || 'Failed to establish recurring transaction.'
  }
}

const handleDeleteRecurring = (id: string, desc: string) => {
  openConfirmDialog({
    title: 'Cancel Recurring Obligation',
    message: `Cancel recurring transaction "${desc}"? Future automated occurrences will no longer be generated.`,
    confirmText: 'Cancel Recurring',
    variant: 'danger',
    onConfirm: async () => {
      try {
        await recurringStore.deleteRecurring(id)
        await refreshDashboard()
        toast.info('Recurring Cancelled', `Cancelled schedule for "${desc}"`)
      } catch (err: any) {
        toast.error('Failed to Cancel Recurring', err.message || 'Operation failed.')
      }
    },
  })
}

const handleToggleRecurringActive = async (recurring: any) => {
  try {
    await recurringStore.updateRecurring(recurring.id, {
      is_active: !recurring.is_active,
    })
  } catch (err: any) {
    alert(err.message || 'Failed to toggle status.')
  }
}

// Financial Goals Handlers
const openAddGoalModal = () => {
  if (accountStore.accounts.length > 0 && !newGoalForm.value.account_id) {
    newGoalForm.value.account_id = accountStore.accounts[0]?.id || ''
  }
  newGoalForm.value.name = ''
  newGoalForm.value.description = ''
  newGoalForm.value.target_amount = ''
  newGoalForm.value.current_amount = ''
  newGoalForm.value.target_date = ''
  goalError.value = null
  showGoalModal.value = true
}

const handleCreateGoal = async () => {
  goalError.value = null
  try {
    await goalStore.createGoal({
      name: newGoalForm.value.name,
      description: newGoalForm.value.description || undefined,
      target_amount: newGoalForm.value.target_amount,
      current_amount: newGoalForm.value.current_amount || undefined,
      target_date: newGoalForm.value.target_date || undefined,
      account_id: newGoalForm.value.account_id || undefined,
      color: newGoalForm.value.color,
    })
    showGoalModal.value = false
    await refreshDashboard()
  } catch (err: any) {
    goalError.value = err.message || 'Failed to establish financial goal.'
  }
}

const handleDeleteGoal = (id: string, name: string) => {
  openConfirmDialog({
    title: 'Remove Savings Goal',
    message: `Remove savings goal "${name}"? Progress and target pacing history will be cleared.`,
    confirmText: 'Remove Goal',
    variant: 'danger',
    onConfirm: async () => {
      try {
        await goalStore.deleteGoal(id)
        await refreshDashboard()
        toast.info('Goal Removed', `Target "${name}" removed.`)
      } catch (err: any) {
        toast.error('Failed to Delete Goal', err.message || 'Operation failed.')
      }
    },
  })
}

const openContributeModal = (goalItem: any) => {
  selectedGoalForContribution.value = goalItem
  contributionAmount.value = ''
  goalError.value = null
  showContributeModal.value = true
}

const handleContribute = async () => {
  if (!selectedGoalForContribution.value) return
  goalError.value = null
  try {
    await goalStore.contribute(selectedGoalForContribution.value.goal.id, contributionAmount.value)
    showContributeModal.value = false
    await refreshDashboard()
  } catch (err: any) {
    goalError.value = err.message || 'Failed to record contribution.'
  }
}

// Statement Import Handlers
const openImportModal = () => {
  if (accountStore.accounts.length > 0 && !importAccountId.value) {
    importAccountId.value = accountStore.accounts[0]?.id || ''
  }
  selectedImportFile.value = null
  importError.value = null
  importSuccessMessage.value = null
  showImportModal.value = true
}

const handleFileSelect = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    selectedImportFile.value = target.files[0] || null
  }
}

const handleUploadStatement = async () => {
  if (!selectedImportFile.value || !importAccountId.value) return
  importError.value = null
  importSuccessMessage.value = null
  try {
    const res = await importStore.uploadStatement(importAccountId.value, selectedImportFile.value)
    importSuccessMessage.value = `Statement reconciled: ${res.imported_count} new transactions posted, ${res.duplicate_count} duplicates skipped.`
    toast.success('Statement Reconciled', `${res.imported_count} new transactions posted, ${res.duplicate_count} duplicates skipped.`)
    await refreshDashboard()
  } catch (err: any) {
    importError.value = err.message || 'Failed to reconcile bank statement.'
    toast.error('Reconciliation Failed', importError.value)
  }
}

const applyTransactionFilters = () => {
  transactionStore.fetchTransactions({
    type: filterType.value === 'all' ? undefined : filterType.value,
    account_id: filterAccountId.value || undefined,
    search: filterSearch.value.trim() || undefined,
  })
}

const handleExportCsv = async () => {
  try {
    await reportStore.downloadCsv({
      type: filterType.value === 'all' ? undefined : filterType.value,
      account_id: filterAccountId.value || undefined,
    })
    toast.success('Export Ready', 'Transactions CSV report downloaded.')
  } catch (err: any) {
    toast.error('Export Failed', err?.message || 'Failed to export CSV')
  }
}

const handleExportJson = async () => {
  try {
    await reportStore.downloadJson({
      type: filterType.value === 'all' ? undefined : filterType.value,
      account_id: filterAccountId.value || undefined,
    })
    toast.success('Export Ready', 'Transactions JSON report downloaded.')
  } catch (err: any) {
    toast.error('Export Failed', err?.message || 'Failed to export JSON')
  }
}

// Seed a quick 4-account starter portfolio with foreign multi-currency account
const seedSamplePortfolio = async () => {
  if (!authStore.isAuthenticated) return
  isAccountSubmitting.value = true
  try {
    const mbb = accountStore.institutions.find(i => i.code === 'MBB')
    const tng = accountStore.institutions.find(i => i.code === 'TNG')
    const cimb = accountStore.institutions.find(i => i.code === 'CIMB')

    let mbbAcc, tngAcc, cimbAcc, foreignAcc

    // 1. Maybank MYR (RM 14,250.00)
    if (mbb) {
      mbbAcc = await accountStore.createAccount({
        institution_id: mbb.id,
        name: 'Maybank Premier Savings',
        account_type: 'bank',
        currency: 'MYR',
        opening_balance: 14250.00,
        account_number_masked: '••••4198',
      })
    }
    // 2. TNG E-Wallet (RM 850.50)
    if (tng) {
      tngAcc = await accountStore.createAccount({
        institution_id: tng.id,
        name: 'Touch n Go eWallet',
        account_type: 'e_wallet',
        currency: 'MYR',
        opening_balance: 850.50,
      })
    }
    // 3. CIMB Visa (RM 1,240.20 debt)
    if (cimb) {
      cimbAcc = await accountStore.createAccount({
        institution_id: cimb.id,
        name: 'CIMB Visa Signature',
        account_type: 'credit_card',
        currency: 'MYR',
        opening_balance: 1240.20,
        account_number_masked: '••••8821',
      })
    }
    // 4. US Brokerage in USD ($1,500.00 USD)
    if (mbb) {
      foreignAcc = await accountStore.createAccount({
        institution_id: mbb.id,
        name: 'Interactive Brokers Global',
        account_type: 'investment',
        currency: 'USD',
        opening_balance: 1500.00,
        account_number_masked: '••••9902',
      })
    }

    // Seed Sample Transactions
    const salaryCat = transactionStore.categories.find(c => c.slug === 'income')?.children?.find(c => c.slug === 'salary-wages')
    const foodCat = transactionStore.categories.find(c => c.slug === 'food-dining')
    const groceriesCat = foodCat?.children?.find(c => c.slug === 'groceries-supermarket')
    const transportCat = transactionStore.categories.find(c => c.slug === 'transportation')
    const fuelCat = transportCat?.children?.find(c => c.slug === 'fuel-petrol')

    if (mbbAcc) {
      await transactionStore.createTransaction({
        account_id: mbbAcc.id,
        category_id: salaryCat?.id,
        type: 'income',
        amount: 8500.00,
        description: 'Monthly Salary Oct 2026',
      })
      await transactionStore.createTransaction({
        account_id: mbbAcc.id,
        category_id: groceriesCat?.id,
        type: 'expense',
        amount: 320.50,
        description: 'Jaya Grocer Weekly Shopping',
      })
    }

    if (cimbAcc && fuelCat) {
      await transactionStore.createTransaction({
        account_id: cimbAcc.id,
        category_id: fuelCat.id,
        type: 'expense',
        amount: 95.00,
        description: 'Petronas RON97 Fuel',
      })
    }

    if (mbbAcc && tngAcc) {
      await transactionStore.createTransfer({
        source_account_id: mbbAcc.id,
        destination_account_id: tngAcc.id,
        amount: 200.00,
        fee: 0,
        notes: 'Top-up TNG RFID for highway tolls',
      })
    }

    // Seed Sample Budgets
    if (foodCat) {
      await budgetStore.createBudget({
        category_id: foodCat.id,
        amount: 1200.00,
        period: 'monthly',
        start_date: firstDayOfMonth,
        end_date: lastDayOfMonth,
        notes: 'Monthly food and groceries cap',
      })
    }

    if (transportCat) {
      await budgetStore.createBudget({
        category_id: transportCat.id,
        amount: 400.00,
        period: 'monthly',
        start_date: firstDayOfMonth,
        end_date: lastDayOfMonth,
        notes: 'Monthly petrol and tolls cap',
      })
    }

    await refreshDashboard()
  } catch (err: any) {
    console.error('Error seeding portfolio:', err)
  } finally {
    isAccountSubmitting.value = false
  }
}

const refreshDashboard = async () => {
  if (authStore.isAuthenticated) {
    const baseCur = authStore.user?.base_currency || 'MYR'
    await Promise.all([
      accountStore.fetchAccounts(),
      transactionStore.fetchCategories(),
      budgetStore.fetchBudgets(),
      budgetStore.fetchCashFlow(),
      recurringStore.fetchRecurring(),
      goalStore.fetchGoals(),
      currencyStore.fetchRates(baseCur),
      applyTransactionFilters(),
    ])
  }
}

const getAccountIcon = (type: string) => {
  switch (type) {
    case 'bank':
      return Building
    case 'e_wallet':
      return Smartphone
    case 'credit_card':
      return CreditCard
    case 'cash':
      return Coins
    case 'investment':
      return PiggyBank
    default:
      return Wallet
  }
}

const formatDate = (isoString: string) => {
  try {
    const d = new Date(isoString)
    return d.toLocaleDateString('en-MY', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return isoString
  }
}

onMounted(async () => {
  // Process incoming Google OAuth callback tokens and errors
  if (typeof window !== 'undefined') {
    const urlParams = new URLSearchParams(window.location.search)
    const ticket = urlParams.get('ticket')
    const authToken = urlParams.get('auth_token')
    const authErr = urlParams.get('auth_error')

    if (ticket) {
      window.history.replaceState({}, document.title, window.location.pathname)
      try {
        await authStore.exchangeOAuthTicket(ticket)
        showAuthModal.value = false
      } catch {
        authError.value = 'Failed to exchange authentication ticket. Please try again.'
        showAuthModal.value = true
      }
    } else if (authToken) {
      window.history.replaceState({}, document.title, window.location.pathname)
      await authStore.handleOAuthToken(authToken)
      showAuthModal.value = false
    } else if (authErr) {
      window.history.replaceState({}, document.title, window.location.pathname)
      showAuthModal.value = true
      if (authErr === 'access_denied' || authErr === 'google_denied') {
        authError.value = 'Google sign-in was cancelled or access was denied.'
      } else {
        authError.value = 'Failed to authenticate with Google. Please try again.'
      }
    }
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleGlobalKeydown)
  }

  await Promise.all([
    checkHealth(),
    accountStore.fetchInstitutions(),
    currencyStore.fetchRates('MYR'),
    authStore.fetchMe(),
    marketStore.fetchIndices(),
  ])

  if (authStore.isAuthenticated) {
    await refreshDashboard()
    if (typeof window !== 'undefined') {
      try {
        const completed = localStorage.getItem('nextomni_onboarding_completed')
        if (!completed) {
          isOnboardingTourOpen.value = true
        }
        const savedBeginnerMode = localStorage.getItem('nextomni_beginner_mode')
        if (savedBeginnerMode === 'true') {
          isBeginnerDashboardMode.value = true
        }
      } catch {
        // Ignore localStorage restrictions
      }
    }
  }
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleGlobalKeydown)
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col selection:bg-emerald-500 selection:text-white transition-colors duration-200">
    <!-- Top Navigation Bar -->
    <header class="border-b border-slate-200 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/60 backdrop-blur-xl sticky top-0 z-40 transition-colors duration-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <NuxtLink to="/" class="flex items-center space-x-3 group">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition-transform">
            <Wallet class="w-5 h-5 text-slate-950 font-bold" />
          </div>
          <div>
            <div class="flex items-center space-x-2">
              <span class="font-extrabold text-lg tracking-tight bg-gradient-to-r from-slate-950 via-slate-800 to-slate-600 dark:from-white dark:via-slate-100 dark:to-slate-400 bg-clip-text text-transparent">NextOmni Finance</span>
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Workspace</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">Multi-Currency Architecture</p>
          </div>
        </NuxtLink>

        <div class="flex items-center space-x-2 sm:space-x-3">
          <!-- Backend Connectivity Badge -->
          <div
            class="hidden lg:flex items-center space-x-2 px-3 py-1.5 rounded-lg border text-xs font-medium transition-all"
            :class="health?.status === 'ok' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-300'"
          >
            <span class="relative flex h-2 w-2">
              <span
                v-if="health?.status === 'ok'"
                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"
              ></span>
              <span
                class="relative inline-flex rounded-full h-2 w-2"
                :class="health?.status === 'ok' ? 'bg-emerald-500' : 'bg-amber-500'"
              ></span>
            </span>
            <span>{{ health?.status === 'ok' ? 'API Online' : (isHealthLoading ? 'Connecting...' : 'API Offline') }}</span>
            <span v-if="responseTimeMs !== null" class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">({{ responseTimeMs }}ms)</span>
          </div>

          <!-- FX Converter Quick Button -->
          <button
            @click="openConverterModal"
            class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/80 dark:hover:bg-slate-700/80 text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition border border-slate-200 dark:border-slate-700/60 text-xs font-semibold flex items-center space-x-1.5 cursor-pointer"
            title="Real-time Foreign Exchange Converter"
          >
            <ArrowLeftRight class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">FX Rates</span>
          </button>

          <!-- Health Refresh Button -->
          <button
            @click="checkHealth"
            :disabled="isHealthLoading"
            class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/80 dark:hover:bg-slate-700/80 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition border border-slate-200 dark:border-slate-700/60 disabled:opacity-50 cursor-pointer"
            title="Refresh System Health"
            aria-label="Refresh System Health"
          >
            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isHealthLoading }" />
          </button>

          <!-- Dark/Light Theme Toggle -->
          <button
            @click="toggleTheme"
            class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/80 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition border border-slate-200 dark:border-slate-700/60 cursor-pointer"
            :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
            aria-label="Toggle Theme"
          >
            <Sun v-if="isDark" class="w-4 h-4 text-amber-400 transition-transform duration-200 hover:rotate-45" />
            <Moon v-else class="w-4 h-4 text-indigo-500 transition-transform duration-200 hover:-rotate-12" />
          </button>

          <!-- Interactive Guide & Onboarding Tour Trigger -->
          <button
            @click="openOnboardingTour"
            class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 transition flex items-center space-x-1.5 text-xs font-semibold cursor-pointer"
            title="Interactive Beginner Guide & Tutorial Tour"
            aria-label="Interactive Beginner Guide & Tutorial Tour"
          >
            <HelpCircle class="w-3.5 h-3.5 text-emerald-500" />
            <span class="hidden sm:inline">Help & Tour</span>
          </button>

          <!-- Financial Terms Glossary Trigger -->
          <button
            @click="openGlossary"
            class="px-2.5 py-1.5 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-500/30 transition flex items-center space-x-1.5 text-xs font-semibold cursor-pointer"
            title="Search Financial Terms & Concepts in Plain English"
            aria-label="Financial Terms Glossary"
          >
            <BookOpen class="w-3.5 h-3.5 text-blue-500" />
            <span class="hidden sm:inline">Glossary</span>
          </button>

          <!-- User Authentication Section -->
          <div v-if="authStore.isAuthenticated" class="flex items-center space-x-2 pl-2 border-l border-slate-200 dark:border-slate-800">
            <div class="flex items-center space-x-2 px-2.5 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
              <img
                v-if="authStore.user?.avatar"
                :src="authStore.user.avatar"
                :alt="authStore.user.name"
                class="w-6 h-6 rounded-full object-cover border border-emerald-500/30"
              />
              <div
                v-else
                class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs font-bold font-mono"
              >
                {{ authStore.user?.name?.charAt(0).toUpperCase() || 'U' }}
              </div>
              <div class="text-left hidden md:block">
                <div class="text-xs font-semibold text-slate-900 dark:text-white leading-none">{{ authStore.user?.name }}</div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ authStore.user?.email }}</div>
              </div>
              <select
                :value="authStore.user?.base_currency"
                @change="handleCurrencyChange(($event.target as HTMLSelectElement).value)"
                :disabled="isUpdatingCurrency"
                class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-emerald-600 dark:text-emerald-400 text-xs rounded px-1.5 py-0.5 font-mono cursor-pointer focus:outline-none focus:border-emerald-500"
                title="Change Base Ledger Currency"
              >
                <option value="MYR">MYR</option>
                <option value="SGD">SGD</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
              </select>
            </div>

            <button
              @click="authStore.logout"
              class="p-2 rounded-lg bg-slate-100 hover:bg-rose-100 dark:bg-slate-800/80 dark:hover:bg-red-500/20 text-slate-600 hover:text-rose-600 dark:text-slate-400 dark:hover:text-red-400 border border-slate-200 dark:border-slate-700/60 transition cursor-pointer"
              title="Sign Out"
              aria-label="Sign Out"
            >
              <LogOut class="w-4 h-4" />
            </button>

            <!-- Mobile Menu Toggle Button -->
            <button
              @click="isMobileMenuOpen = !isMobileMenuOpen"
              class="md:hidden p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/80 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700/60 cursor-pointer"
              aria-label="Toggle navigation menu"
            >
              <Menu class="w-4 h-4" />
            </button>
          </div>

          <div v-else class="flex items-center space-x-2 pl-2 border-l border-slate-200 dark:border-slate-800">
            <button
              @click="authMode = 'login'; showAuthModal = true"
              class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60 transition cursor-pointer"
            >
              Sign In
            </button>
            <button
              @click="authMode = 'register'; showAuthModal = true"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition shadow-md shadow-emerald-500/20 cursor-pointer"
            >
              Get Started
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Workspace Navigation Tabs (Visible when authenticated) -->
    <nav v-if="authStore.isAuthenticated" class="border-b border-slate-200 dark:border-slate-800/80 bg-white/90 dark:bg-slate-900/60 backdrop-blur-md sticky top-16 z-30 transition-colors duration-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center space-x-1 sm:space-x-2 overflow-x-auto py-2.5 no-scrollbar">
          <button
            @click="activeTab = 'dashboard'"
            class="flex items-center space-x-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition whitespace-nowrap cursor-pointer"
            :class="activeTab === 'dashboard' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
          >
            <LayoutDashboard class="w-4 h-4" />
            <span>Overview</span>
          </button>

          <button
            @click="activeTab = 'accounts'"
            class="flex items-center space-x-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition whitespace-nowrap cursor-pointer"
            :class="activeTab === 'accounts' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
          >
            <Wallet class="w-4 h-4" />
            <span>Accounts</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono">{{ accountStore.accounts.length }}</span>
          </button>

          <button
            @click="activeTab = 'transactions'"
            class="flex items-center space-x-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition whitespace-nowrap cursor-pointer"
            :class="activeTab === 'transactions' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
          >
            <ReceiptText class="w-4 h-4" />
            <span>Transactions</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono">{{ transactionStore.meta.total }}</span>
          </button>

          <button
            @click="activeTab = 'budgets'"
            class="flex items-center space-x-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition whitespace-nowrap cursor-pointer"
            :class="activeTab === 'budgets' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
          >
            <Target class="w-4 h-4" />
            <span>Budgets & Goals</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono">{{ budgetStore.budgets.length + goalStore.goals.length }}</span>
          </button>

          <button
            @click="activeTab = 'analytics'"
            class="flex items-center space-x-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition whitespace-nowrap cursor-pointer"
            :class="activeTab === 'analytics' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
          >
            <BarChart3 class="w-4 h-4" />
            <span>Analytics</span>
          </button>

          <button
            @click="activeTab = 'markets'"
            class="flex items-center space-x-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition whitespace-nowrap cursor-pointer"
            :class="activeTab === 'markets' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
          >
            <TrendingUp class="w-4 h-4" />
            <span>Markets</span>
            <span class="flex h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          </button>

          <button
            @click="activeTab = 'tools'"
            class="flex items-center space-x-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition whitespace-nowrap cursor-pointer"
            :class="activeTab === 'tools' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
          >
            <Layers class="w-4 h-4" />
            <span>FX & Tools</span>
          </button>
        </div>
      </div>
    </nav>

    <!-- Real-time Market Ticker Tape -->
    <MarketTickerTape
      @select-index="(sym) => { activeTab = 'markets'; marketStore.selectQuote(sym) }"
      @view-markets="activeTab = 'markets'"
    />

    <!-- Mobile Slide-Over Navigation Drawer -->
    <div
      v-if="isMobileMenuOpen && authStore.isAuthenticated"
      class="md:hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex flex-col justify-end"
    >
      <div class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 rounded-t-3xl p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
          <span class="font-bold text-sm text-slate-900 dark:text-white">Workspace Navigation</span>
          <button
            @click="isMobileMenuOpen = false"
            class="p-1.5 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="grid grid-cols-2 gap-2.5">
          <button
            @click="activeTab = 'dashboard'; isMobileMenuOpen = false"
            class="p-3 rounded-xl flex items-center space-x-2.5 text-xs font-semibold border transition cursor-pointer"
            :class="activeTab === 'dashboard' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
          >
            <LayoutDashboard class="w-4 h-4" />
            <span>Overview</span>
          </button>

          <button
            @click="activeTab = 'accounts'; isMobileMenuOpen = false"
            class="p-3 rounded-xl flex items-center space-x-2.5 text-xs font-semibold border transition cursor-pointer"
            :class="activeTab === 'accounts' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
          >
            <Wallet class="w-4 h-4" />
            <span>Accounts</span>
          </button>

          <button
            @click="activeTab = 'transactions'; isMobileMenuOpen = false"
            class="p-3 rounded-xl flex items-center space-x-2.5 text-xs font-semibold border transition cursor-pointer"
            :class="activeTab === 'transactions' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
          >
            <ReceiptText class="w-4 h-4" />
            <span>Transactions</span>
          </button>

          <button
            @click="activeTab = 'budgets'; isMobileMenuOpen = false"
            class="p-3 rounded-xl flex items-center space-x-2.5 text-xs font-semibold border transition cursor-pointer"
            :class="activeTab === 'budgets' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
          >
            <Target class="w-4 h-4" />
            <span>Budgets & Goals</span>
          </button>

          <button
            @click="activeTab = 'analytics'; isMobileMenuOpen = false"
            class="p-3 rounded-xl flex items-center space-x-2.5 text-xs font-semibold border transition cursor-pointer"
            :class="activeTab === 'analytics' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
          >
            <BarChart3 class="w-4 h-4" />
            <span>Analytics</span>
          </button>

          <button
            @click="activeTab = 'markets'; isMobileMenuOpen = false"
            class="p-3 rounded-xl flex items-center space-x-2.5 text-xs font-semibold border transition cursor-pointer"
            :class="activeTab === 'markets' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
          >
            <TrendingUp class="w-4 h-4" />
            <span>Markets</span>
          </button>

          <button
            @click="activeTab = 'tools'; isMobileMenuOpen = false"
            class="p-3 rounded-xl flex items-center space-x-2.5 text-xs font-semibold border transition cursor-pointer"
            :class="activeTab === 'tools' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
          >
            <Layers class="w-4 h-4" />
            <span>FX & Tools</span>
          </button>

          <button
            @click="openOnboardingTour(); isMobileMenuOpen = false"
            class="p-3 rounded-xl flex items-center space-x-2.5 text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 transition cursor-pointer"
          >
            <HelpCircle class="w-4 h-4 text-emerald-500" />
            <span>Beginner Guide & Tour</span>
          </button>

          <button
            @click="openGlossary(); isMobileMenuOpen = false"
            class="p-3 rounded-xl flex items-center space-x-2.5 text-xs font-semibold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/30 transition cursor-pointer"
          >
            <BookOpen class="w-4 h-4 text-blue-500" />
            <span>Financial Terms Glossary</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      <!-- Getting Started Onboarding Progress Checklist -->
      <GettingStartedChecklist
        v-if="authStore.isAuthenticated && (activeTab === 'dashboard' || activeTab === 'accounts')"
        :account-count="accountStore.accounts.length"
        :transaction-count="transactionStore.transactions.length"
        :budget-count="budgetStore.budgets.length"
        :recurring-count="recurringStore.recurringTransactions.length"
        :force-visible="isChecklistForceVisible"
        @navigate="(tab) => activeTab = tab as any"
        @action="handleOnboardingAction"
        @open-tour="openOnboardingTour"
        @dismiss="isChecklistForceVisible = false"
      />

      <!-- Authenticated User Banner with Quick Financial Actions -->
      <section v-if="authStore.isAuthenticated" class="p-6 rounded-2xl bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-900/60 border border-emerald-500/30 shadow-xl backdrop-blur-md flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400">
            <UserCheck class="w-6 h-6" />
          </div>
          <div>
            <div class="flex items-center space-x-2">
              <h2 class="text-xl font-bold text-white">{{ authStore.user?.name }}'s Financial Hub</h2>
              <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Multi-Currency FX Engine</span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              Base Ledger: <span class="text-emerald-400 font-mono font-bold">{{ authStore.user?.base_currency }}</span> &bull;
              Accounts: <span class="text-slate-200 font-mono">{{ accountStore.accounts.length }}</span> &bull;
              Budgets: <span class="text-slate-200 font-mono">{{ budgetStore.budgets.length }}</span> &bull;
              Transactions: <span class="text-slate-200 font-mono">{{ transactionStore.meta.total }}</span>
            </p>
          </div>
        </div>

        <!-- Quick Financial Actions & View Mode Toggle -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- View Mode Toggle (Beginner / Simple vs Detailed) -->
          <button
            type="button"
            @click="toggleBeginnerDashboardMode"
            class="px-3 py-2 rounded-xl text-xs font-semibold border transition flex items-center space-x-1.5 cursor-pointer"
            :class="isBeginnerDashboardMode ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800/80 text-slate-300 border-slate-700 hover:text-white'"
            :title="isBeginnerDashboardMode ? 'Switch to Detailed Ledger Mode' : 'Switch to Simplified Beginner Mode'"
          >
            <SlidersHorizontal class="w-3.5 h-3.5" />
            <span>{{ isBeginnerDashboardMode ? 'Beginner View' : 'Detailed View' }}</span>
          </button>

          <button
            @click="openAddTransactionModal('expense')"
            class="px-3 py-2 rounded-xl text-xs font-bold bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/40 transition flex items-center space-x-1.5 cursor-pointer"
          >
            <ArrowUpRight class="w-4 h-4 text-rose-400" />
            <span>Record Expense</span>
          </button>

          <button
            @click="openAddTransactionModal('income')"
            class="px-3 py-2 rounded-xl text-xs font-bold bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 transition flex items-center space-x-1.5 cursor-pointer"
          >
            <ArrowDownLeft class="w-4 h-4 text-emerald-400" />
            <span>Record Income</span>
          </button>

          <button
            @click="openAddTransferModal"
            class="px-3 py-2 rounded-xl text-xs font-bold bg-sky-500/20 hover:bg-sky-500/30 text-sky-300 border border-sky-500/40 transition flex items-center space-x-1.5 cursor-pointer"
          >
            <ArrowRightLeft class="w-4 h-4 text-sky-400" />
            <span>Transfer Funds</span>
          </button>

          <button
            @click="openAddBudgetModal"
            class="px-3 py-2 rounded-xl text-xs font-bold bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-300 border border-indigo-500/40 transition flex items-center space-x-1.5"
          >
            <Target class="w-4 h-4 text-indigo-400" />
            <span>Set Budget</span>
          </button>

          <button
            @click="openAddAccountModal"
            class="px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-1.5 shadow-lg shadow-emerald-500/20"
          >
            <Plus class="w-4 h-4" />
            <span>New Account</span>
          </button>
        </div>
      </section>

      <!-- Guest Welcome Banner -->
      <section v-else class="relative overflow-hidden rounded-2xl bg-gradient-to-b from-slate-900/90 to-slate-900/40 border border-slate-800 p-8 shadow-2xl backdrop-blur-md">
        <div class="absolute -right-16 -top-16 w-80 h-80 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-80 h-80 rounded-full bg-teal-500/10 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-3xl space-y-4">
          <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-slate-800/80 border border-slate-700 text-xs font-semibold text-emerald-400">
            <Sparkles class="w-3.5 h-3.5" />
            <span>Phase 6 — Multi-Currency FX Engine & Real-Time Portfolio Consolidation</span>
          </div>

          <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
            NextOmni Finance — Enterprise Multi-Currency Financial Architecture
          </h1>

          <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
            Engineered with <span class="text-emerald-400 font-medium">Laravel 11 (PHP 8.3)</span>,
            <span class="text-emerald-400 font-medium">PostgreSQL 16 UUIDv7</span>, and
            <span class="text-emerald-400 font-medium">Nuxt 3</span>. Features strict <code class="text-emerald-400 font-mono">NUMERIC(15, 6)</code>
            FX rates, automated cross-currency triangulation, and consolidated multi-currency net worth tracking across ASEAN & global assets.
          </p>

          <div class="flex flex-wrap items-center gap-3 pt-2">
            <button
              @click="handleDemoLogin"
              :disabled="isAuthSubmitting"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-2 shadow-lg shadow-emerald-500/20"
            >
              <LogIn class="w-4 h-4" />
              <span>Launch Live Demo Session</span>
            </button>
            <button
              @click="authMode = 'register'; showAuthModal = true"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
            >
              Create Account
            </button>
          </div>
        </div>
      </section>

      <!-- First-Time User Quick-Start Command Center (Shown on Dashboard when 0 accounts exist) -->
      <QuickStartHub
        v-if="authStore.isAuthenticated && accountStore.accounts.length === 0 && activeTab === 'dashboard'"
        :user-name="authStore.user?.name"
        :base-currency="authStore.user?.base_currency"
        :is-submitting="isAccountSubmitting"
        @add-account="openAddAccountModal"
        @seed-demo="seedSamplePortfolio"
        @import-csv="openImportModal"
        @open-tour="openOnboardingTour"
      />

      <!-- Beginner Mode Notification & Quick Concept Guidance -->
      <div
        v-if="authStore.isAuthenticated && activeTab === 'dashboard' && isBeginnerDashboardMode"
        class="p-4 rounded-2xl bg-gradient-to-r from-emerald-500/10 via-teal-500/5 to-slate-900/40 border border-emerald-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
      >
        <div class="flex items-center space-x-3">
          <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/30">
            <Sparkles class="w-4 h-4" />
          </div>
          <div>
            <span class="font-bold text-emerald-400">Beginner Focus Mode Active:</span>
            <span class="text-slate-300 ml-1">Prioritizing your Net Worth, Cash Inflow vs. Outflow, and monthly budget limits. Technical market tickers are collapsed.</span>
          </div>
        </div>
        <div class="flex items-center space-x-2 shrink-0">
          <button
            type="button"
            @click="openGlossary"
            class="px-2.5 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 font-semibold transition flex items-center space-x-1 border border-emerald-500/30 cursor-pointer"
          >
            <BookOpen class="w-3.5 h-3.5" />
            <span>Financial Terms</span>
          </button>
          <button
            type="button"
            @click="toggleBeginnerDashboardMode"
            class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold transition cursor-pointer"
          >
            Switch to Detailed View
          </button>
        </div>
      </div>

      <!-- Live Financial Metrics Grid (Bound to Real API Aggregations with Multi-Currency Consolidation) -->
      <section v-if="!authStore.isAuthenticated || activeTab === 'dashboard' || activeTab === 'accounts'" class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center space-x-2">
            <TrendingUp class="w-5 h-5 text-emerald-500 dark:text-emerald-400" />
            <span>Consolidated Portfolio Net Worth & Liquidity (In {{ authStore.user?.base_currency || 'MYR' }})</span>
          </h2>
          <div class="flex items-center space-x-1.5 text-xs text-slate-500 dark:text-slate-400 font-medium">
            <span>Multi-currency consolidated</span>
            <InfoTooltip
              title="Net Worth"
              text="The total value of everything you own (bank deposits, cash, investments) minus what you owe (credit card debt, loans). Foreign currencies are converted to your base currency."
              position="bottom"
            />
          </div>
        </div>

        <div v-if="accountStore.isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <SkeletonLoader variant="metric-card" :count="4" />
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Total Net Worth Card -->
          <div class="p-5 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition shadow-sm dark:shadow-none space-y-2">
            <div class="flex items-center justify-between">
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Net Worth</div>
              <InfoTooltip
                title="Consolidated Net Worth"
                text="Calculated as (Bank Deposits + Investments + Cash) minus Credit Card balances and Liabilities."
                position="top"
              />
            </div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white font-mono">
              {{ accountStore.summary ? accountStore.summary.net_worth_formatted : `${currencySymbol} 0.00` }}
            </div>
            <div class="flex items-center text-xs text-emerald-600 dark:text-emerald-400 font-medium space-x-1">
              <ArrowUpRight class="w-3.5 h-3.5" />
              <span>Consolidated Assets &minus; Liabilities</span>
            </div>
          </div>

          <!-- Liquid Cash & Bank Deposits -->
          <div class="p-5 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition shadow-sm dark:shadow-none space-y-2">
            <div class="flex items-center justify-between">
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Bank Deposits</div>
              <InfoTooltip
                title="Liquid Cash & Banks"
                text="Checking, savings, and e-wallet balances immediately available for spending or transfers."
                position="top"
              />
            </div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white font-mono">
              {{ currencySymbol }} {{ accountStore.summary ? accountStore.summary.breakdown.bank : '0.00' }}
            </div>
            <div class="flex items-center text-xs text-slate-500 dark:text-slate-400 font-medium space-x-1">
              <Building class="w-3.5 h-3.5 text-blue-500 dark:text-blue-400" />
              <span>Checking & Savings</span>
            </div>
          </div>

          <!-- Investments -->
          <div class="p-5 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition shadow-sm dark:shadow-none space-y-2">
            <div class="flex items-center justify-between">
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Global Investments & Brokerage</div>
              <InfoTooltip
                title="Investments & Assets"
                text="Foreign equity, ETF portfolios, and brokerage accounts converted to your base currency."
                position="top"
              />
            </div>
            <div class="text-2xl font-extrabold text-sky-600 dark:text-sky-400 font-mono">
              {{ currencySymbol }} {{ accountStore.summary ? accountStore.summary.breakdown.investment : '0.00' }}
            </div>
            <div class="flex items-center text-xs text-sky-600/80 dark:text-sky-400/80 space-x-1">
              <PiggyBank class="w-3.5 h-3.5" />
              <span>Foreign FX Consolidated</span>
            </div>
          </div>

          <!-- Liabilities (Credit Cards & Loans) -->
          <div class="p-5 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition shadow-sm dark:shadow-none space-y-2">
            <div class="flex items-center justify-between">
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Credit Card Debt</div>
              <InfoTooltip
                title="Liabilities & Debt"
                text="Outstanding credit balances that reduce your overall net worth."
                position="top"
              />
            </div>
            <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 font-mono">
              {{ currencySymbol }} {{ accountStore.summary ? accountStore.summary.breakdown.credit_card : '0.00' }}
            </div>
            <div class="flex items-center text-xs text-amber-600/80 dark:text-amber-400/80 space-x-1">
              <CreditCard class="w-3.5 h-3.5" />
              <span>Deducted from Net Worth</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Cash Flow & Monthly Analytics (Dashboard Overview) -->
      <section v-if="authStore.isAuthenticated && activeTab === 'dashboard' && budgetStore.cashFlow" class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-none space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <h3 class="text-base font-bold text-white flex items-center space-x-2">
              <BarChart3 class="w-5 h-5 text-indigo-400" />
              <span>Monthly Cash Flow & Savings Rate</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Period: {{ budgetStore.cashFlow.period.start_date }} to {{ budgetStore.cashFlow.period.end_date }}</p>
          </div>

          <div class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-mono font-bold">
            <Percent class="w-4 h-4" />
            <span>Savings Rate: {{ budgetStore.cashFlow.summary.savings_rate }}%</span>
            <InfoTooltip
              title="Savings Rate"
              text="Percentage of your income saved this month: ((Income - Expenses) / Income) × 100. Target 20%+ for healthy wealth accumulation."
              position="left"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-xs text-slate-400 font-medium">Total Inflows (Income)</span>
              <InfoTooltip
                title="Total Income"
                text="All salaries, investment dividends, and incoming transfers recorded for this calendar period."
                position="top"
              />
            </div>
            <div class="text-xl font-bold font-mono text-emerald-400">
              {{ budgetStore.cashFlow.summary.total_income_formatted }}
            </div>
          </div>

          <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-xs text-slate-400 font-medium">Total Outflows (Expenses)</span>
              <InfoTooltip
                title="Total Expenses"
                text="All living costs, bills, and discretionary spending deducted from your accounts."
                position="top"
              />
            </div>
            <div class="text-xl font-bold font-mono text-rose-400">
              {{ budgetStore.cashFlow.summary.total_expense_formatted }}
            </div>
          </div>

          <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-xs text-slate-400 font-medium">Net Monthly Savings</span>
              <InfoTooltip
                title="Net Cash Flow"
                text="Surplus money remaining after all expenses: Inflows minus Outflows."
                position="top"
              />
            </div>
            <div
              class="text-xl font-bold font-mono"
              :class="Number(budgetStore.cashFlow.summary.net_cash_flow) >= 0 ? 'text-emerald-400' : 'text-rose-400'"
            >
              {{ budgetStore.cashFlow.summary.net_cash_flow_formatted }}
            </div>
          </div>
        </div>

        <!-- Category Spending Distribution -->
        <div v-if="budgetStore.cashFlow.categories.length > 0" class="space-y-3 pt-2">
          <span class="text-xs font-bold text-slate-300 uppercase tracking-wider block">Spending Distribution by Category</span>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <div
              v-for="cat in budgetStore.cashFlow.categories"
              :key="cat.name"
              class="p-3 rounded-lg bg-slate-950/40 border border-slate-800 flex items-center justify-between"
            >
              <div class="overflow-hidden pr-2">
                <div class="text-xs font-semibold text-white truncate">{{ cat.name }}</div>
                <div class="text-[11px] font-mono text-slate-400">{{ cat.amount_formatted }}</div>
              </div>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-mono font-bold"
                :style="{ backgroundColor: `${cat.color}20`, color: cat.color || '#38bdf8' }"
              >
                {{ cat.percentage }}%
              </span>
            </div>
          </div>
        </div>
      </section>

      <!-- Dedicated Financial Intelligence & Health Analytics Hub -->
      <section v-if="authStore.isAuthenticated && activeTab === 'analytics'" class="space-y-6">
        <AnalyticsTab
          :currency-symbol="currencySymbol"
          @record-expense="openAddTransactionModal('expense')"
          @record-income="openAddTransactionModal('income')"
          @open-import="openImportModal"
        />
      </section>

      <!-- Real-Time Market Overview & Watchlist Widget (Dashboard Overview - Collapsed in Beginner Mode) -->
      <section v-if="authStore.isAuthenticated && activeTab === 'dashboard' && !isBeginnerDashboardMode" class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-none space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div class="flex items-center space-x-2">
            <Activity class="w-5 h-5 text-emerald-500" />
            <h3 class="text-base font-bold text-slate-900 dark:text-white">
              Live Stock Markets & Personal Watchlist
            </h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold border border-emerald-500/20">
              🇲🇾 KLSE &bull; 🇺🇸 NYSE/NASDAQ
            </span>
          </div>

          <button
            @click="activeTab = 'markets'"
            class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline flex items-center space-x-1 cursor-pointer"
          >
            <span>Open Market Terminal</span>
            <ArrowUpRight class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Major Index Cards Snapshot -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div
            v-for="idx in marketStore.indices"
            :key="idx.symbol"
            @click="activeTab = 'markets'; marketStore.selectQuote(idx.symbol)"
            class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 hover:border-emerald-500/40 transition cursor-pointer space-y-1 group"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-emerald-500 transition">
                {{ idx.name }}
              </span>
              <span
                class="w-1.5 h-1.5 rounded-full"
                :class="idx.market_status?.is_open ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"
              />
            </div>
            <div class="text-sm font-black font-mono text-slate-900 dark:text-white tabular-nums">
              {{ idx.price?.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </div>
            <div
              class="text-[11px] font-mono font-bold flex items-center space-x-0.5"
              :class="idx.is_positive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
            >
              <span>{{ idx.is_positive ? '+' : '' }}{{ idx.change_percent?.toFixed(2) }}%</span>
            </div>
          </div>
        </div>

        <!-- Quick Watchlist Strip -->
        <div v-if="marketStore.watchlist.length > 0" class="pt-2 flex flex-wrap items-center gap-2">
          <span class="text-xs font-medium text-slate-400">Quick Watchlist:</span>
          <button
            v-for="sym in marketStore.watchlist"
            :key="sym"
            @click="activeTab = 'markets'; marketStore.selectQuote(sym)"
            class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-xs font-mono font-bold text-slate-700 dark:text-slate-300 hover:bg-emerald-500/10 hover:text-emerald-500 transition cursor-pointer flex items-center space-x-1"
          >
            <span>{{ sym }}</span>
            <ArrowUpRight class="w-3 h-3 text-slate-400" />
          </button>
        </div>
      </section>

      <!-- Budgets & Spending Progress -->
      <section v-if="authStore.isAuthenticated && (activeTab === 'dashboard' || activeTab === 'budgets')" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <Target class="w-5 h-5 text-indigo-400" />
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Monthly Budgets & Category Spending Limits ({{ budgetStore.budgets.length }})</h2>
          </div>
          <button
            @click="openAddBudgetModal"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 transition flex items-center space-x-1"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Set Category Budget</span>
          </button>
        </div>

        <!-- Budget Cards Grid -->
        <div v-if="budgetStore.budgets.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="item in budgetStore.budgets"
            :key="item.budget.id"
            class="p-5 rounded-xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 transition relative group space-y-4"
          >
            <div class="flex items-start justify-between">
              <div>
                <div class="flex items-center space-x-2">
                  <h3 class="font-bold text-sm text-white">{{ item.category?.name || 'Category' }}</h3>
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase"
                    :class="{
                      'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20': item.status === 'healthy',
                      'bg-amber-500/10 text-amber-400 border border-amber-500/20': item.status === 'warning',
                      'bg-rose-500/10 text-rose-400 border border-rose-500/20': item.status === 'exceeded',
                    }"
                  >
                    {{ item.status === 'exceeded' ? 'Over Budget' : `${item.percent_used}%` }}
                  </span>
                </div>
                <p class="text-[11px] text-slate-400 font-mono mt-0.5">
                  {{ item.budget.start_date }} &rarr; {{ item.budget.end_date }}
                </p>
              </div>

              <button
                @click="handleDeleteBudget(item.budget.id, item.category?.name || 'budget')"
                class="p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-slate-800 opacity-0 group-hover:opacity-100 transition"
                title="Remove Budget Limit"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>

            <!-- Visual Progress Meter -->
            <div class="space-y-1.5">
              <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :style="{ width: `${Math.min(item.percent_used, 100)}%` }"
                  :class="{
                    'bg-emerald-500': item.status === 'healthy',
                    'bg-amber-500': item.status === 'warning',
                    'bg-rose-500': item.status === 'exceeded',
                  }"
                ></div>
              </div>

              <div class="flex items-center justify-between text-xs font-mono">
                <span class="text-slate-400">Spent: <strong class="text-white">{{ item.spent_formatted }}</strong></span>
                <span class="text-slate-400">Cap: <strong class="text-slate-200">{{ item.amount_formatted }}</strong></span>
              </div>
            </div>

            <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs">
              <span class="text-slate-400">Remaining</span>
              <span
                class="font-mono font-bold"
                :class="item.is_over_budget ? 'text-rose-400' : 'text-emerald-400'"
              >
                {{ item.remaining_formatted }}
              </span>
            </div>
          </div>
        </div>

        <div v-else class="p-8 rounded-2xl bg-slate-900/40 border border-dashed border-slate-800 text-center space-y-3">
          <div class="w-12 h-12 rounded-2xl bg-slate-800/80 mx-auto flex items-center justify-center text-slate-400">
            <Target class="w-6 h-6" />
          </div>
          <div class="space-y-1">
            <h3 class="text-base font-bold text-white">No Category Budgets Configured Yet</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
              Set monthly spending targets for Food & Dining, Transportation, or Utilities to monitor burn rate with visual warning meters.
            </p>
          </div>
          <div class="pt-1">
            <button
              @click="openAddBudgetModal"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-500 hover:bg-indigo-400 text-slate-950 transition inline-flex items-center space-x-1.5"
            >
              <Plus class="w-4 h-4" />
              <span>Set Your First Budget</span>
            </button>
          </div>
        </div>
      </section>

      <!-- Recurring Bills, Subscriptions & Scheduled Posting -->
      <section v-if="authStore.isAuthenticated && (activeTab === 'dashboard' || activeTab === 'budgets')" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <CalendarClock class="w-5 h-5 text-sky-400" />
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Recurring Bills & Subscriptions ({{ recurringStore.recurringTransactions.length }})</h2>
          </div>
          <button
            @click="openAddRecurringModal"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/30 transition flex items-center space-x-1"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Schedule Bill / Subscription</span>
          </button>
        </div>

        <!-- Recurring Grid -->
        <div v-if="recurringStore.recurringTransactions.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="rec in recurringStore.recurringTransactions"
            :key="rec.id"
            class="p-5 rounded-xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 transition relative group space-y-4"
          >
            <div class="flex items-start justify-between">
              <div class="space-y-1">
                <div class="flex items-center space-x-2">
                  <h3 class="font-bold text-sm text-white truncate max-w-[170px]">{{ rec.description }}</h3>
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase"
                    :class="rec.type === 'income' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'"
                  >
                    {{ rec.frequency }}
                  </span>
                </div>
                <p class="text-xs text-slate-400 flex items-center space-x-1">
                  <span>{{ rec.account?.name || 'Account' }}</span>
                  <span v-if="rec.category">&bull; {{ rec.category.name }}</span>
                </p>
              </div>

              <div class="flex items-center space-x-1">
                <button
                  @click="handleToggleRecurringActive(rec)"
                  :title="rec.is_active ? 'Pause subscription' : 'Activate subscription'"
                  class="px-2 py-1 rounded text-[10px] font-mono uppercase font-bold transition"
                  :class="rec.is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400 border border-slate-700'"
                >
                  {{ rec.is_active ? 'Active' : 'Paused' }}
                </button>
                <button
                  @click="handleDeleteRecurring(rec.id, rec.description)"
                  class="text-slate-600 hover:text-rose-400 p-1 opacity-0 group-hover:opacity-100 transition"
                  title="Cancel & Delete Schedule"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Amount & Recurrence Rate -->
            <div class="flex items-baseline justify-between pt-1">
              <div>
                <span class="text-2xl font-extrabold font-mono tracking-tight text-white">{{ rec.amount_formatted }}</span>
                <span class="text-xs text-slate-400 ml-1.5 font-medium">/ {{ rec.frequency }}</span>
              </div>
              <span
                v-if="rec.auto_post"
                class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-sky-500/10 text-sky-400 border border-sky-500/20 flex items-center space-x-1"
              >
                <Repeat class="w-3 h-3" />
                <span>Auto-Post</span>
              </span>
            </div>

            <!-- Timeline Status -->
            <div class="p-2.5 rounded-lg bg-slate-950/60 border border-slate-800/80 flex items-center justify-between text-[11px]">
              <span class="text-slate-400">Next Due Date:</span>
              <span class="font-mono font-bold text-sky-400">{{ rec.next_due_date }}</span>
            </div>
          </div>
        </div>

        <div v-else class="p-8 rounded-2xl bg-slate-900/40 border border-dashed border-slate-800 text-center space-y-3">
          <div class="w-12 h-12 rounded-2xl bg-slate-800/80 mx-auto flex items-center justify-center text-slate-400">
            <CalendarClock class="w-6 h-6 text-sky-400" />
          </div>
          <div class="space-y-1">
            <h3 class="text-base font-bold text-white">No Recurring Bills or Subscriptions Scheduled</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
              Automate tracking for home internet (Unifi), Netflix, cloud servers (AWS), insurance, or salary with automatic ledger postings.
            </p>
          </div>
          <div class="pt-1">
            <button
              @click="openAddRecurringModal"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-sky-500 hover:bg-sky-400 text-slate-950 transition inline-flex items-center space-x-1.5"
            >
              <Plus class="w-4 h-4" />
              <span>Schedule Your First Bill</span>
            </button>
          </div>
        </div>
      </section>

      <!-- Financial Goals & Savings Milestones -->
      <section v-if="authStore.isAuthenticated && (activeTab === 'dashboard' || activeTab === 'budgets')" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <Trophy class="w-5 h-5 text-amber-400" />
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Financial Goals & Savings Targets ({{ goalStore.goals.length }})</h2>
            <span
              v-if="goalStore.summary && goalStore.goals.length > 0"
              class="hidden sm:inline-flex px-2 py-0.5 rounded-full text-[11px] font-mono bg-amber-500/10 text-amber-400 border border-amber-500/20"
            >
              Saved {{ goalStore.summary.total_saved_formatted }} of {{ goalStore.summary.total_target_formatted }} ({{ goalStore.summary.overall_percent }}%)
            </span>
          </div>
          <button
            @click="openAddGoalModal"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 transition flex items-center space-x-1"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Create Goal</span>
          </button>
        </div>

        <!-- Goals Cards Grid -->
        <div v-if="goalStore.goals.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="item in goalStore.goals"
            :key="item.goal.id"
            class="p-5 rounded-xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 transition relative group space-y-4"
          >
            <div class="flex items-start justify-between">
              <div class="space-y-1">
                <div class="flex items-center space-x-2">
                  <div
                    class="w-3 h-3 rounded-full flex-shrink-0"
                    :style="{ backgroundColor: item.goal.color || '#10b981' }"
                  />
                  <h3 class="font-bold text-sm text-white truncate max-w-[170px]">{{ item.goal.name }}</h3>
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase"
                    :class="item.is_achieved ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'"
                  >
                    {{ item.is_achieved ? 'Achieved!' : `${item.percent_achieved}%` }}
                  </span>
                </div>
                <p v-if="item.goal.description" class="text-xs text-slate-400 truncate max-w-[220px]">
                  {{ item.goal.description }}
                </p>
              </div>

              <div class="flex items-center space-x-1">
                <button
                  @click="openContributeModal(item)"
                  class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 transition flex items-center space-x-1"
                  title="Add savings funds toward target"
                >
                  <Plus class="w-3 h-3" />
                  <span>Deposit</span>
                </button>
                <button
                  @click="handleDeleteGoal(item.goal.id, item.goal.name)"
                  class="text-slate-600 hover:text-rose-400 p-1 opacity-0 group-hover:opacity-100 transition"
                  title="Delete Goal"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Visual Progress Bar -->
            <div class="space-y-1.5">
              <div class="flex justify-between text-xs">
                <span class="text-slate-400 font-mono">
                  Saved: <strong class="text-white">{{ item.current_amount_formatted }}</strong>
                </span>
                <span class="text-slate-400 font-mono">
                  Target: <strong class="text-slate-300">{{ item.target_amount_formatted }}</strong>
                </span>
              </div>
              <div class="h-2 w-full bg-slate-950 rounded-full overflow-hidden p-0.5 border border-slate-800">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :style="{
                    width: `${item.percent_achieved}%`,
                    backgroundColor: item.goal.color || '#10b981',
                  }"
                />
              </div>
            </div>

            <!-- Pacing Metrics & Deadline -->
            <div class="grid grid-cols-2 gap-2 text-[11px] pt-1 border-t border-slate-800/80">
              <div class="p-2 rounded-lg bg-slate-950/60 border border-slate-800/60">
                <span class="text-slate-400 block text-[10px]">Monthly Required</span>
                <span class="font-mono font-bold" :class="item.is_achieved ? 'text-emerald-400' : 'text-amber-400'">
                  {{ item.is_achieved ? 'Target Met!' : item.monthly_required_formatted }}
                </span>
              </div>
              <div class="p-2 rounded-lg bg-slate-950/60 border border-slate-800/60">
                <span class="text-slate-400 block text-[10px]">Target Date</span>
                <span class="font-mono font-bold text-slate-300">
                  {{ item.goal.target_date || 'Ongoing' }}
                  <span v-if="item.days_remaining !== null && item.days_remaining > 0" class="text-[10px] text-slate-500">
                    ({{ item.days_remaining }}d)
                  </span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="p-8 rounded-2xl bg-slate-900/40 border border-dashed border-slate-800 text-center space-y-3">
          <div class="w-12 h-12 rounded-2xl bg-slate-800/80 mx-auto flex items-center justify-center text-slate-400">
            <Trophy class="w-6 h-6 text-amber-400" />
          </div>
          <div class="space-y-1">
            <h3 class="text-base font-bold text-white">No Financial Goals Created Yet</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
              Set milestones for Emergency Funds, Property Down Payments, or Vacation Reserves with automated monthly pacing calculations.
            </p>
          </div>
          <div class="pt-1">
            <button
              @click="openAddGoalModal"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition inline-flex items-center space-x-1.5"
            >
              <Plus class="w-4 h-4" />
              <span>Create Your First Goal</span>
            </button>
          </div>
        </div>
      </section>

      <!-- User's Financial Accounts Grid (With Multi-Currency FX Tags) -->
      <section v-if="authStore.isAuthenticated && (activeTab === 'dashboard' || activeTab === 'accounts')" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <Wallet class="w-5 h-5 text-emerald-400" />
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Your Financial Accounts ({{ accountStore.accounts.length }})</h2>
          </div>
          <button
            @click="openAddAccountModal"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 transition flex items-center space-x-1"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Add Account</span>
          </button>
        </div>

        <!-- Account Cards -->
        <div v-if="accountStore.isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <SkeletonLoader variant="account-card" :count="3" />
        </div>
        <div v-else-if="accountStore.accounts.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="acc in accountStore.accounts"
            :key="acc.id"
            class="p-5 rounded-xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 transition relative group space-y-4"
          >
            <div class="flex items-start justify-between">
              <div class="flex items-center space-x-3.5">
                <InstitutionLogo
                  :code="acc.institution?.code || acc.account_type"
                  :name="acc.institution?.name || acc.name"
                  :logo-url="acc.institution?.logo_url"
                  size="md"
                  interactive
                />
                <div>
                  <h3 class="font-bold text-sm text-white">{{ acc.name }}</h3>
                  <p class="text-xs text-slate-400 flex items-center space-x-1.5 mt-0.5">
                    <span>{{ acc.institution?.name || 'Manual Account' }}</span>
                    <span v-if="acc.account_number_masked" class="font-mono text-[11px] text-slate-500">({{ acc.account_number_masked }})</span>
                  </p>
                </div>
              </div>

              <!-- Delete button -->
              <button
                @click="handleDeleteAccount(acc.id, acc.name)"
                class="p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-slate-800 opacity-0 group-hover:opacity-100 transition"
                title="Remove Account"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>

            <div class="pt-2 border-t border-slate-800/80 flex items-end justify-between">
              <div>
                <span class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold block">Balance</span>
                <span class="text-xl font-extrabold font-mono" :class="acc.account_type === 'credit_card' ? 'text-amber-400' : 'text-white'">
                  {{ acc.current_balance_formatted }}
                </span>
                <!-- FX tag if account is in foreign currency -->
                <span
                  v-if="acc.currency !== authStore.user?.base_currency"
                  class="block text-[11px] text-sky-400 font-mono mt-0.5"
                >
                  &asymp; Multi-Currency FX Consolidated
                </span>
              </div>
              <div class="flex flex-col items-end space-y-1">
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-medium uppercase bg-slate-800 text-slate-400 border border-slate-700/60">
                  {{ acc.account_type }}
                </span>
                <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                  {{ acc.currency }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State for Accounts -->
        <div v-else class="p-8 rounded-2xl bg-slate-900/40 border border-dashed border-slate-800 text-center space-y-4">
          <div class="w-12 h-12 rounded-2xl bg-slate-800/80 mx-auto flex items-center justify-center text-slate-400">
            <Wallet class="w-6 h-6" />
          </div>
          <div class="space-y-1">
            <h3 class="text-base font-bold text-white">No Financial Accounts Configured Yet</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
              Add your Malaysian bank accounts (Maybank, CIMB), global investments (USD), or e-wallets (TNG) to track your consolidated net worth.
            </p>
          </div>
          <div class="flex items-center justify-center space-x-3 pt-2">
            <button
              @click="openAddAccountModal"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-2"
            >
              <Plus class="w-4 h-4" />
              <span>Add Your First Account</span>
            </button>
            <button
              @click="seedSamplePortfolio"
              :disabled="isAccountSubmitting"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
            >
              Seed Sample Portfolio & Multi-Currency Assets
            </button>
          </div>
        </div>
      </section>

      <!-- Transaction Ledger & Feed -->
      <section v-if="authStore.isAuthenticated && (activeTab === 'dashboard' || activeTab === 'transactions')" class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center space-x-2">
            <ReceiptText class="w-5 h-5 text-emerald-400" />
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Transaction Ledger & Activity Feed ({{ transactionStore.meta.total }})</h2>
          </div>

          <!-- Filter & Search Controls -->
          <div class="flex flex-wrap items-center gap-2">
            <!-- Search Input -->
            <div class="relative">
              <Search class="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-2.5 pointer-events-none" />
              <input
                v-model="filterSearch"
                @input="applyTransactionFilters"
                type="text"
                placeholder="Search description..."
                class="pl-8 pr-3 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 w-44"
              />
            </div>

            <!-- Account Filter Dropdown -->
            <select
              v-model="filterAccountId"
              @change="applyTransactionFilters"
              class="bg-slate-900 border border-slate-800 text-xs text-slate-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-emerald-500 cursor-pointer"
            >
              <option value="">All Accounts</option>
              <option v-for="acc in accountStore.accounts" :key="acc.id" :value="acc.id">
                {{ acc.name }} ({{ acc.currency }})
              </option>
            </select>

            <!-- Type Filter Segmented Control -->
            <div class="flex rounded-lg bg-slate-900 p-0.5 border border-slate-800">
              <button
                @click="filterType = 'all'; applyTransactionFilters()"
                class="px-2.5 py-1 text-xs rounded-md transition"
                :class="filterType === 'all' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-400 hover:text-white'"
              >
                All
              </button>
              <button
                @click="filterType = 'income'; applyTransactionFilters()"
                class="px-2.5 py-1 text-xs rounded-md transition"
                :class="filterType === 'income' ? 'bg-emerald-500/20 text-emerald-400 font-semibold' : 'text-slate-400 hover:text-white'"
              >
                Income
              </button>
              <button
                @click="filterType = 'expense'; applyTransactionFilters()"
                class="px-2.5 py-1 text-xs rounded-md transition"
                :class="filterType === 'expense' ? 'bg-rose-500/20 text-rose-400 font-semibold' : 'text-slate-400 hover:text-white'"
              >
                Expense
              </button>
              <button
                @click="filterType = 'transfer'; applyTransactionFilters()"
                class="px-2.5 py-1 text-xs rounded-md transition"
                :class="filterType === 'transfer' ? 'bg-sky-500/20 text-sky-400 font-semibold' : 'text-slate-400 hover:text-white'"
              >
                Transfer
              </button>
            </div>

            <!-- Statement Export & Import Actions -->
            <div class="flex items-center space-x-1.5 pl-1 border-l border-slate-800">
              <button
                @click="openImportModal"
                title="Upload & reconcile bank statement CSV"
                class="px-2.5 py-1 text-xs rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 transition flex items-center space-x-1.5 cursor-pointer"
              >
                <Upload class="w-3.5 h-3.5" />
                <span>Import CSV</span>
              </button>
              <button
                @click="handleExportCsv"
                :disabled="reportStore.isExporting"
                title="Download RFC 4180 CSV Financial Statement (Formula-Safe)"
                class="px-2.5 py-1 text-xs rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition flex items-center space-x-1.5 disabled:opacity-50 cursor-pointer"
              >
                <FileSpreadsheet class="w-3.5 h-3.5 text-emerald-400" />
                <span>{{ reportStore.isExporting ? 'Exporting...' : 'Export CSV' }}</span>
              </button>
              <button
                @click="handleExportJson"
                :disabled="reportStore.isExporting"
                title="Download Structured Open Financial JSON Statement"
                class="px-2.5 py-1 text-xs rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition flex items-center space-x-1.5 disabled:opacity-50 cursor-pointer"
              >
                <Download class="w-3.5 h-3.5 text-sky-400" />
                <span>JSON</span>
              </button>
            </div>
          </div>
        </div>

        <!-- High-Density Transaction Ledger Table -->
        <TransactionTable
          :transactions="transactionStore.transactions"
          :is-loading="transactionStore.isLoading"
          :can-create="accountStore.accounts.length > 0"
          @delete="handleDeleteTransaction"
          @record="openAddTransactionModal"
        />
      </section>

      <!-- Real-Time Stock Market Intelligence Hub (Malaysia Bursa & US Markets) -->
      <section v-if="activeTab === 'markets'" class="space-y-6">
        <MarketsTab />
      </section>

      <!-- Tools & Financial Utilities Hub -->
      <section v-if="activeTab === 'tools'" class="space-y-6">
        <ToolsTab
          :can-import="accountStore.accounts.length > 0"
          :institutions="accountStore.institutions"
          @open-fx="openConverterModal"
          @open-import="openImportModal"
          @export-csv="handleExportCsv"
          @export-json="handleExportJson"
        />
      </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-900 py-6 text-center text-xs text-slate-400 bg-slate-950">
      <p>NextOmni Finance &copy; 2026 &mdash; Built for Portfolio Demonstration & Technical Rigor. Non-custodial personal finance platform.</p>
    </footer>

    <!-- Modular Authentication Modal -->
    <AuthModal
      :is-open="showAuthModal"
      :initial-mode="authMode"
      @close="showAuthModal = false"
      @success="handleAuthSuccess"
    />

    <!-- Modular Add Account Modal -->
    <AddAccountModal
      :is-open="showAddAccountModal"
      @close="showAddAccountModal = false"
      @created="handleAccountCreated"
    />

    <!-- Modular Transaction Modal -->
    <TransactionModal
      :is-open="showTransactionModal"
      :default-type="defaultTransactionType"
      @close="showTransactionModal = false"
      @created="refreshDashboard"
      @open-add-account="openAddAccountModal"
    />

    <!-- Modular Transfer Modal -->
    <TransferModal
      :is-open="showTransferModal"
      @close="showTransferModal = false"
      @created="refreshDashboard"
      @open-add-account="openAddAccountModal"
    />

    <!-- Set Budget Modal -->
    <div
      v-if="showBudgetModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
        <button
          @click="showBudgetModal = false"
          class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
        >
          <X class="w-5 h-5" />
        </button>

        <div class="space-y-1">
          <h3 class="text-lg font-bold text-white flex items-center space-x-2">
            <Target class="w-5 h-5 text-indigo-400" />
            <span>Set Category Spending Target</span>
          </h3>
          <p class="text-xs text-slate-400">Establish budget caps with threshold progress tracking</p>
        </div>

        <div v-if="budgetError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
          <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
          <span>{{ budgetError }}</span>
        </div>

        <form @submit.prevent="handleCreateBudget" class="space-y-4">
          <!-- 1-Click Budget Starter Presets for New Users -->
          <div class="space-y-1.5 p-3 rounded-xl bg-slate-950/60 border border-slate-800">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-indigo-400 flex items-center space-x-1">
                <Sparkles class="w-3.5 h-3.5" />
                <span>1-Click Starter Presets</span>
              </span>
              <span class="text-[10px] text-slate-400">Pre-fills category & limit</span>
            </div>
            <div class="flex flex-wrap gap-1.5 pt-1">
              <button
                v-for="preset in popularBudgetPresets"
                :key="preset.name"
                type="button"
                @click="applyBudgetPreset(preset)"
                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-900 hover:bg-indigo-500/10 text-slate-300 hover:text-indigo-400 border border-slate-800 hover:border-indigo-500/40 transition flex items-center space-x-1.5 cursor-pointer"
              >
                <span>{{ preset.icon }}</span>
                <span>{{ preset.name }}</span>
                <span class="text-[10px] font-mono text-indigo-300 font-bold">({{ currencySymbol }} {{ preset.amount }})</span>
              </button>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Expense Category</label>
            <select
              v-model="newBudgetForm.category_id"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
            >
              <option
                v-for="cat in transactionStore.categories.filter(c => c.type === 'expense')"
                :key="cat.id"
                :value="cat.id"
              >
                {{ cat.name }} (Parent & Subcategories)
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Spending Cap Limit</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
              <input
                v-model="newBudgetForm.amount"
                type="number"
                step="0.01"
                min="0.01"
                required
                placeholder="1000.00"
                class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Start Date</label>
              <input
                v-model="newBudgetForm.start_date"
                type="date"
                required
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
              />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">End Date</label>
              <input
                v-model="newBudgetForm.end_date"
                type="date"
                required
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Optional Notes</label>
            <input
              v-model="newBudgetForm.notes"
              type="text"
              placeholder="e.g. Monthly groceries and food cap"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <button
            type="submit"
            :disabled="budgetStore.isSubmitting"
            class="w-full py-2.5 rounded-lg text-xs font-bold bg-indigo-500 hover:bg-indigo-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50"
          >
            <RefreshCw v-if="budgetStore.isSubmitting" class="w-4 h-4 animate-spin" />
            <span>{{ budgetStore.isSubmitting ? 'Establishing Limit...' : 'Confirm & Set Budget Cap' }}</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Real-Time Foreign Exchange (FX) Converter Modal -->
    <div
      v-if="showConverterModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
        <button
          @click="showConverterModal = false"
          class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
        >
          <X class="w-5 h-5" />
        </button>

        <div class="space-y-1">
          <h3 class="text-lg font-bold text-white flex items-center space-x-2">
            <ArrowLeftRight class="w-5 h-5 text-sky-400" />
            <span>Foreign Exchange (FX) Rate Engine</span>
          </h3>
          <p class="text-xs text-slate-400">Strict 6-decimal arbitrary precision currency conversion</p>
        </div>

        <div v-if="converterError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
          <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
          <span>{{ converterError }}</span>
        </div>

        <form @submit.prevent="runConversion" class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Amount to Convert</label>
            <input
              v-model.number="converterForm.amount"
              @input="runConversion"
              type="number"
              step="0.01"
              min="0.01"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono focus:outline-none focus:border-sky-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">From</label>
              <select
                v-model="converterForm.from"
                @change="runConversion"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
              >
                <option value="USD">USD ($)</option>
                <option value="MYR">MYR (RM)</option>
                <option value="SGD">SGD (S$)</option>
                <option value="EUR">EUR (€)</option>
                <option value="GBP">GBP (£)</option>
              </select>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">To</label>
              <select
                v-model="converterForm.to"
                @change="runConversion"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
              >
                <option value="MYR">MYR (RM)</option>
                <option value="SGD">SGD (S$)</option>
                <option value="USD">USD ($)</option>
                <option value="EUR">EUR (€)</option>
                <option value="GBP">GBP (£)</option>
              </select>
            </div>
          </div>
        </form>

        <!-- Live Conversion Calculation Result Card -->
        <div v-if="conversionResult" class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 space-y-2">
          <div class="text-xs text-slate-400 flex items-center justify-between">
            <span>Exchange Rate (6 Decimals)</span>
            <span class="font-mono text-sky-400 font-bold">1 {{ conversionResult.from_currency }} = {{ conversionResult.rate }} {{ conversionResult.to_currency }}</span>
          </div>
          <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between">
            <span class="text-xs text-slate-400">Converted Value</span>
            <span class="text-xl font-extrabold font-mono text-white">
              {{ conversionResult.converted_formatted }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Set Recurring Bill / Subscription Modal -->
    <div
      v-if="showRecurringModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
        <button
          @click="showRecurringModal = false"
          class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
        >
          <X class="w-5 h-5" />
        </button>

        <div class="space-y-1">
          <h3 class="text-lg font-bold text-white flex items-center space-x-2">
            <CalendarClock class="w-5 h-5 text-sky-400" />
            <span>Schedule Recurring Bill / Subscription</span>
          </h3>
          <p class="text-xs text-slate-400">Automate recurring expenses or salary inflows with cadence intervals</p>
        </div>

        <div v-if="recurringError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
          <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
          <span>{{ recurringError }}</span>
        </div>

        <form @submit.prevent="handleCreateRecurring" class="space-y-4">
          <!-- Type Toggle -->
          <div class="flex rounded-lg bg-slate-950 p-1 border border-slate-800">
            <button
              type="button"
              @click="newRecurringForm.type = 'expense'"
              class="flex-1 py-1.5 text-xs font-semibold rounded-md transition"
              :class="newRecurringForm.type === 'expense' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'text-slate-400 hover:text-white'"
            >
              Expense / Bill
            </button>
            <button
              type="button"
              @click="newRecurringForm.type = 'income'"
              class="flex-1 py-1.5 text-xs font-semibold rounded-md transition"
              :class="newRecurringForm.type === 'income' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'text-slate-400 hover:text-white'"
            >
              Income / Retainer
            </button>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Description / Merchant</label>
            <input
              v-model="newRecurringForm.description"
              type="text"
              required
              placeholder="e.g. Unifi 300Mbps, Netflix 4K, AWS Cloud"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-sky-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Linked Account</label>
              <select
                v-model="newRecurringForm.account_id"
                required
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
              >
                <option v-for="acc in accountStore.accounts" :key="acc.id" :value="acc.id">
                  {{ acc.name }} ({{ acc.currency }})
                </option>
              </select>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Category</label>
              <select
                v-model="newRecurringForm.category_id"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
              >
                <option value="">(Uncategorized)</option>
                <option
                  v-for="cat in transactionStore.categories"
                  :key="cat.id"
                  :value="cat.id"
                >
                  {{ cat.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Amount</label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
                <input
                  v-model="newRecurringForm.amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  required
                  placeholder="149.00"
                  class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-sky-500"
                />
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Cadence / Frequency</label>
              <select
                v-model="newRecurringForm.frequency"
                required
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
              >
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="biweekly">Bi-weekly (14 days)</option>
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly (3 months)</option>
                <option value="yearly">Yearly</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Start Date</label>
              <input
                v-model="newRecurringForm.start_date"
                type="date"
                required
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
              />
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">End Date (Optional)</label>
              <input
                v-model="newRecurringForm.end_date"
                type="date"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
              />
            </div>
          </div>

          <div class="flex items-center space-x-2 pt-1">
            <input
              id="autoPostCheckbox"
              v-model="newRecurringForm.auto_post"
              type="checkbox"
              class="rounded bg-slate-950 border-slate-800 text-sky-500 focus:ring-0 focus:ring-offset-0 cursor-pointer"
            />
            <label for="autoPostCheckbox" class="text-xs text-slate-300 cursor-pointer">
              Automatically post to ledger on recurrence date
            </label>
          </div>

          <button
            type="submit"
            :disabled="recurringStore.isSubmitting"
            class="w-full py-2.5 rounded-xl text-sm font-bold bg-sky-500 hover:bg-sky-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
          >
            <CalendarClock class="w-4 h-4" />
            <span>{{ recurringStore.isSubmitting ? 'Establishing Schedule...' : 'Confirm & Schedule' }}</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Create Financial Goal Modal -->
    <div
      v-if="showGoalModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
        <button
          @click="showGoalModal = false"
          class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
        >
          <X class="w-5 h-5" />
        </button>

        <div class="space-y-1">
          <h3 class="text-lg font-bold text-white flex items-center space-x-2">
            <Trophy class="w-5 h-5 text-amber-400" />
            <span>Create Financial Savings Goal</span>
          </h3>
          <p class="text-xs text-slate-400">Track target accumulation with automated monthly savings pace calculations</p>
        </div>

        <div v-if="goalError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
          <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
          <span>{{ goalError }}</span>
        </div>

        <form @submit.prevent="handleCreateGoal" class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Goal Name</label>
            <input
              v-model="newGoalForm.name"
              type="text"
              required
              placeholder="e.g. Emergency Fund (6 Months), House Down Payment"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Target Amount</label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
                <input
                  v-model="newGoalForm.target_amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  required
                  placeholder="30000.00"
                  class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-amber-500"
                />
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Initial Saved</label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
                <input
                  v-model="newGoalForm.current_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="5000.00"
                  class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-amber-500"
                />
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Target Deadline</label>
              <input
                v-model="newGoalForm.target_date"
                type="date"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Accent Color</label>
              <select
                v-model="newGoalForm.color"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              >
                <option value="#10b981">Emerald Green</option>
                <option value="#0ea5e9">Sky Blue</option>
                <option value="#f59e0b">Amber Gold</option>
                <option value="#8b5cf6">Purple</option>
                <option value="#ec4899">Pink</option>
              </select>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Dedicated Account (Optional)</label>
            <select
              v-model="newGoalForm.account_id"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
            >
              <option value="">(Unlinked / Portfolio-wide)</option>
              <option v-for="acc in accountStore.accounts" :key="acc.id" :value="acc.id">
                {{ acc.name }} ({{ acc.currency }})
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Notes / Strategy</label>
            <textarea
              v-model="newGoalForm.description"
              rows="2"
              placeholder="e.g. Save 20% of monthly salary into fixed deposit"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
            ></textarea>
          </div>

          <button
            type="submit"
            :disabled="goalStore.isSubmitting"
            class="w-full py-2.5 rounded-xl text-sm font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
          >
            <Trophy class="w-4 h-4" />
            <span>{{ goalStore.isSubmitting ? 'Establishing Goal...' : 'Establish Goal' }}</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Contribute to Goal Modal -->
    <div
      v-if="showContributeModal && selectedGoalForContribution"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-sm p-6 shadow-2xl relative space-y-5">
        <button
          @click="showContributeModal = false"
          class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
        >
          <X class="w-5 h-5" />
        </button>

        <div class="space-y-1">
          <h3 class="text-base font-bold text-white flex items-center space-x-2">
            <Flag class="w-4 h-4 text-emerald-400" />
            <span>Deposit Savings Funds</span>
          </h3>
          <p class="text-xs text-slate-400">
            Contributing to <strong class="text-white">{{ selectedGoalForContribution.goal.name }}</strong>
          </p>
        </div>

        <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1 text-xs">
          <div class="flex justify-between text-slate-400">
            <span>Current Saved:</span>
            <span class="font-mono text-white font-bold">{{ selectedGoalForContribution.current_amount_formatted }}</span>
          </div>
          <div class="flex justify-between text-slate-400">
            <span>Target Goal:</span>
            <span class="font-mono text-slate-300">{{ selectedGoalForContribution.target_amount_formatted }}</span>
          </div>
          <div class="flex justify-between text-slate-400 pt-1 border-t border-slate-800">
            <span>Remaining Target:</span>
            <span class="font-mono text-emerald-400 font-bold">{{ selectedGoalForContribution.remaining_amount_formatted }}</span>
          </div>
        </div>

        <div v-if="goalError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
          <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
          <span>{{ goalError }}</span>
        </div>

        <form @submit.prevent="handleContribute" class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Deposit Amount</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
              <input
                v-model="contributionAmount"
                type="number"
                step="0.01"
                min="0.01"
                required
                placeholder="500.00"
                class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>
          </div>

          <button
            type="submit"
            :disabled="goalStore.isSubmitting"
            class="w-full py-2.5 rounded-xl text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
          >
            <Check class="w-4 h-4" />
            <span>{{ goalStore.isSubmitting ? 'Recording Deposit...' : 'Confirm Deposit' }}</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Bank Statement Import & Reconciliation Modal -->
    <div
      v-if="showImportModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
        <button
          @click="showImportModal = false"
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

        <div v-if="importError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
          <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
          <span>{{ importError }}</span>
        </div>

        <div v-if="importSuccessMessage" class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300 flex items-start space-x-2">
          <CheckCircle2 class="w-4 h-4 flex-shrink-0 mt-0.5 text-emerald-400" />
          <span>{{ importSuccessMessage }}</span>
        </div>

        <form @submit.prevent="handleUploadStatement" class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Target Account</label>
            <select
              v-model="importAccountId"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
            >
              <option v-for="acc in accountStore.accounts" :key="acc.id" :value="acc.id">
                {{ acc.name }} ({{ acc.currency }}) - {{ acc.institution?.name || 'Manual' }}
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Statement CSV File</label>
            <div class="border-2 border-dashed border-slate-800 hover:border-emerald-500/50 rounded-xl p-4 text-center transition bg-slate-950/50">
              <input
                id="statementFileInput"
                type="file"
                accept=".csv,.txt"
                required
                @change="handleFileSelect"
                class="hidden"
              />
              <label for="statementFileInput" class="cursor-pointer block space-y-2">
                <Upload class="w-6 h-6 text-slate-400 mx-auto" />
                <div class="text-xs text-slate-300 font-medium">
                  {{ selectedImportFile ? selectedImportFile.name : 'Click to select CSV file' }}
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
            :disabled="importStore.isUploading || !selectedImportFile"
            class="w-full py-2.5 rounded-xl text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
          >
            <Upload class="w-4 h-4" />
            <span>{{ importStore.isUploading ? 'Reconciling Ledger...' : 'Parse & Reconcile Statement' }}</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Financial Terms & Concepts Glossary Modal -->
    <FinancialGlossaryModal
      :is-open="isGlossaryOpen"
      @close="isGlossaryOpen = false"
    />

    <!-- Interactive Onboarding & Beginner Tutorial Modal -->
    <OnboardingModal
      :is-open="isOnboardingTourOpen"
      @close="isOnboardingTourOpen = false"
      @action="handleOnboardingAction"
      @navigate="(tab) => activeTab = tab as any"
      @open-glossary="openGlossary"
    />

    <!-- High-Trust Confirmation Dialog -->
    <ConfirmModal
      :is-open="confirmState.isOpen"
      :title="confirmState.title"
      :message="confirmState.message"
      :confirm-text="confirmState.confirmText"
      :variant="confirmState.variant"
      :is-loading="confirmState.isLoading"
      @confirm="handleConfirmExecute"
      @close="confirmState.isOpen = false"
    />

    <!-- Global Toast Notification Center -->
    <ToastContainer />
  </div>
</template>
