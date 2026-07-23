<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Capacitor } from '@capacitor/core'
import { Device } from '@capacitor/device'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore(); const router = useRouter(); const identity = ref(''); const password = ref(''); const error = ref('')
async function submit() {
  error.value = ''
  try {
    const device = await Device.getId()
    await auth.signIn({ identity: identity.value, password: password.value, device_name: device.identifier, device_uuid: device.identifier, platform: Capacitor.getPlatform(), app_version: '0.1.0' })
    await router.push('/')
  } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Login gagal.' }
}
</script>

<template>
  <main class="auth-page"><div class="brand-mark">LP</div><h1>Selamat datang</h1><p>Masuk ke LintasPOS Mobile</p><form @submit.prevent="submit"><label>Username atau email<input v-model="identity" autocomplete="username" required /></label><label>Password<input v-model="password" type="password" autocomplete="current-password" required /></label><p v-if="error" class="error">{{ error }}</p><button class="primary" :disabled="auth.busy">{{ auth.busy ? 'Memproses…' : 'Masuk' }}</button></form></main>
</template>
