<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const menu = [
  { label: 'Dashboard', icon: '⌂', to: '/', permission: null },
  { label: 'Penjualan', icon: '＋', to: '/penjualan', permission: 'penjualan.view' },
  { label: 'Pembelian', icon: '↓', to: '/pembelian', permission: 'pembelian.view' },
  { label: 'Laporan', icon: '▤', to: '/laporan', permission: 'laporan.view' },
]

async function signOut() { await auth.signOut(); await router.push('/login') }
</script>

<template>
  <div class="mobile-shell">
    <header class="app-bar"><div><small>LintasPOS</small><h1>Dashboard</h1></div><button class="avatar" @click="signOut">{{ auth.user?.name?.slice(0, 1) || '?' }}</button></header>
    <main class="page-content"><slot /></main>
    <nav class="bottom-nav">
      <button v-for="item in menu.filter((entry) => !entry.permission || auth.can(entry.permission))" :key="item.to" :class="{ active: router.currentRoute.value.path === item.to }" @click="router.push(item.to)"><span>{{ item.icon }}</span><small>{{ item.label }}</small></button>
    </nav>
  </div>
</template>
