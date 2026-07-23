<script setup lang="ts">
import MobileLayout from '../layouts/MobileLayout.vue'
import { useAuthStore } from '../stores/auth'
const auth = useAuthStore()
</script>

<template><MobileLayout><section class="welcome-card"><p>Halo, {{ auth.user?.name }}</p><h2>Siap melayani hari ini?</h2></section><section class="summary-grid"><article><small>Penjualan hari ini</small><strong>—</strong></article><article><small>Kas hari ini</small><strong>—</strong></article><article><small>Stok menipis</small><strong>—</strong></article><article><small>Hutang supplier</small><strong>—</strong></article></section><section class="empty-state"><strong>Dashboard API siap dihubungkan</strong><p>Ringkasan operasional akan ditambahkan pada fase dashboard.</p></section></MobileLayout></template>
