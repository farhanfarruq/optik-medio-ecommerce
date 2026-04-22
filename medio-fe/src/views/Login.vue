<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();

const form = ref({ email: '', password: '' });
const isLoading = ref(false);
const errorMessage = ref('');

const handleLogin = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    await authStore.login(form.value);
    router.push('/profile'); // Atau halaman yang dituju sebelumnya
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Login failed. Please try again.';
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <main class="flex-grow flex items-center justify-center py-20 px-6">
    <div class="w-full max-w-md bg-surface-container-low p-8 rounded-xl shadow-sm border border-outline-variant/15">
      <h1 class="font-headline text-3xl mb-2 text-center">Welcome Back</h1>
      <p class="text-on-surface-variant text-center mb-8 text-sm">Sign in to The Curated Lens.</p>
      
      <form @submit.prevent="handleLogin" class="flex flex-col gap-5">
        <div v-if="errorMessage" class="bg-error-container text-error p-3 rounded text-sm text-center">
          {{ errorMessage }}
        </div>
        
        <div>
          <label class="block font-label text-sm mb-1 text-on-surface-variant">Email</label>
          <input v-model="form.email" type="email" required class="w-full bg-surface-container-highest border-0 border-b-2 border-outline focus:border-secondary rounded-t-md p-3">
        </div>
        
        <div>
          <label class="block font-label text-sm mb-1 text-on-surface-variant">Password</label>
          <input v-model="form.password" type="password" required class="w-full bg-surface-container-highest border-0 border-b-2 border-outline focus:border-secondary rounded-t-md p-3">
        </div>
        
        <button type="submit" :disabled="isLoading" class="w-full bg-primary text-white py-4 rounded-md font-medium mt-4 hover:bg-primary-container disabled:opacity-50 transition-colors">
          {{ isLoading ? 'Authenticating...' : 'Sign In' }}
        </button>
      </form>
    </div>
  </main>
</template>