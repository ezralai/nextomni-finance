<script setup lang="ts">
interface Props {
  variant?: 'metric-card' | 'account-card' | 'table-row' | 'chart-block' | 'text' | 'badge'
  count?: number
}

withDefaults(defineProps<Props>(), {
  variant: 'text',
  count: 1,
})
</script>

<template>
  <div class="w-full space-y-3">
    <!-- Metric Card Skeleton -->
    <template v-if="variant === 'metric-card'">
      <div
        v-for="i in count"
        :key="i"
        class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-900/60 animate-pulse space-y-3 shadow-sm"
      >
        <div class="flex items-center justify-between">
          <div class="h-3 w-24 bg-slate-200 dark:bg-slate-800 rounded"></div>
          <div class="w-8 h-8 bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
        </div>
        <div class="h-7 w-36 bg-slate-200 dark:bg-slate-800 rounded"></div>
        <div class="h-2.5 w-48 bg-slate-200 dark:bg-slate-800 rounded"></div>
      </div>
    </template>

    <!-- Account Card Skeleton -->
    <template v-else-if="variant === 'account-card'">
      <div
        v-for="i in count"
        :key="i"
        class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 animate-pulse space-y-4 shadow-sm"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-slate-200 dark:bg-slate-800 rounded-xl"></div>
            <div class="space-y-1.5">
              <div class="h-3.5 w-28 bg-slate-200 dark:bg-slate-800 rounded"></div>
              <div class="h-2.5 w-16 bg-slate-200 dark:bg-slate-800 rounded"></div>
            </div>
          </div>
          <div class="w-6 h-6 bg-slate-200 dark:bg-slate-800 rounded"></div>
        </div>
        <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-between items-baseline">
          <div class="h-3 w-16 bg-slate-200 dark:bg-slate-800 rounded"></div>
          <div class="h-5 w-28 bg-slate-200 dark:bg-slate-800 rounded"></div>
        </div>
      </div>
    </template>

    <!-- Table Row Skeleton -->
    <template v-else-if="variant === 'table-row'">
      <div
        v-for="i in count"
        :key="i"
        class="p-4 flex items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800/80 animate-pulse"
      >
        <div class="flex items-center space-x-3.5 min-w-0 flex-1">
          <div class="w-10 h-10 bg-slate-200 dark:bg-slate-800 rounded-xl flex-shrink-0"></div>
          <div class="space-y-2 flex-1">
            <div class="h-3.5 w-40 bg-slate-200 dark:bg-slate-800 rounded"></div>
            <div class="h-2.5 w-24 bg-slate-200 dark:bg-slate-800 rounded"></div>
          </div>
        </div>
        <div class="flex items-center space-x-4 flex-shrink-0">
          <div class="space-y-1.5 text-right">
            <div class="h-4 w-20 bg-slate-200 dark:bg-slate-800 rounded ml-auto"></div>
            <div class="h-2.5 w-12 bg-slate-200 dark:bg-slate-800 rounded ml-auto"></div>
          </div>
          <div class="w-6 h-6 bg-slate-200 dark:bg-slate-800 rounded"></div>
        </div>
      </div>
    </template>

    <!-- Chart Block Skeleton -->
    <template v-else-if="variant === 'chart-block'">
      <div
        v-for="i in count"
        :key="i"
        class="p-6 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 animate-pulse space-y-4 shadow-sm"
      >
        <div class="flex justify-between items-center">
          <div class="h-4 w-32 bg-slate-200 dark:bg-slate-800 rounded"></div>
          <div class="h-3 w-20 bg-slate-200 dark:bg-slate-800 rounded"></div>
        </div>
        <div class="h-48 w-full bg-slate-100 dark:bg-slate-800/50 rounded-xl flex items-end justify-between p-4 gap-2">
          <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t h-1/3"></div>
          <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t h-2/3"></div>
          <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t h-1/2"></div>
          <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t h-3/4"></div>
          <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t h-2/5"></div>
        </div>
      </div>
    </template>

    <!-- Generic Text / Line Skeleton -->
    <template v-else>
      <div
        v-for="i in count"
        :key="i"
        class="h-3 bg-slate-200 dark:bg-slate-800 rounded animate-pulse"
      ></div>
    </template>
  </div>
</template>
