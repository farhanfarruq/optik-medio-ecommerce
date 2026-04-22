<template>
  <footer class="bg-surface-container-low border-t border-outline-variant/10 py-12 px-6">
    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8">
      <div class="col-span-1 md:col-span-2">
        <h2 class="font-headline text-2xl text-primary mb-4">Optik Medio</h2>
        <p class="text-on-surface-variant max-w-sm">Curating the finest lenses for your vision. Quality and style blended for the modern era.</p>
      </div>
      <div>
        <h3 class="font-headline text-lg mb-4">Shop</h3>
        <ul class="space-y-2 text-on-surface-variant text-sm">
          <li><router-link to="/products" class="hover:text-primary transition-colors">Frames</router-link></li>
          <li><router-link to="/products" class="hover:text-primary transition-colors">Lenses</router-link></li>
          <li><router-link to="/products" class="hover:text-primary transition-colors">Sunglasses</router-link></li>
        </ul>
      </div>
      <div>
        <h3 class="font-headline text-lg mb-4">Support</h3>
        <ul class="space-y-2 text-on-surface-variant text-sm">
          <li><router-link to="/profile" class="hover:text-primary transition-colors">My Account</router-link></li>
          <li><a href="#" class="hover:text-primary transition-colors">Shipping Policy</a></li>
          <li><a href="#" class="hover:text-primary transition-colors">Contact Us</a></li>
        </ul>
      </div>
    </div>
    <div class="max-w-7xl mx-auto mt-12 pt-8 border-t border-outline-variant/10 flex justify-between items-center text-xs text-on-surface-variant">
      <p>&copy; 2024 Optik Medio. All rights reserved.</p>
      <div class="flex gap-4">
        <a href="#">Instagram</a>
        <a href="#">Twitter</a>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
</script>
