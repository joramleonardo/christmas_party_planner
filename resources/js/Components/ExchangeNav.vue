<script setup>
import { ref, onMounted } from "vue";
import { Link } from "@inertiajs/vue3";
import { isSoundEnabled, toggleSound, playChime } from "@/Utils/soundFx";

const props = defineProps({
  projectSlug: { type: String, required: true },
  projectName: { type: String, default: "Event Planner" },
  currentPage: { type: String, default: "" },
});

const soundOn = ref(true);
const copied = ref(false);
const mobileMenuOpen = ref(false);

onMounted(() => {
  soundOn.value = isSoundEnabled();
});

const handleToggleSound = () => {
  soundOn.value = toggleSound();
};

const copyShareLink = async () => {
  const url = `${window.location.origin}/${props.projectSlug}/menu`;
  if (navigator.share) {
    try {
      await navigator.share({
        title: props.projectName,
        text: `Join our party and gift exchange for ${props.projectName}! 🎁`,
        url,
      });
      return;
    } catch (e) {
      // fallback to clipboard
    }
  }

  try {
    await navigator.clipboard.writeText(url);
    copied.value = true;
    playChime();
    setTimeout(() => {
      copied.value = false;
    }, 2500);
  } catch (err) {
    console.error("Clipboard copy failed", err);
  }
};

const navItems = [
  { key: "menu", label: "Dashboard", icon: "🏠", path: "" },
  { key: "secret-santa", label: "Secret Santa", icon: "🎅", path: "/exchange-gift-ideas/secret-santa" },
  { key: "awards", label: "Awards Night", icon: "🏆", path: "/awards" },
  { key: "food-planning", label: "Food & Ambagan", icon: "🍱", path: "/food-planning" },
  { key: "wishlist", label: "Wishlists", icon: "📝", path: "/wishlist" },
  { key: "small-gifts", label: "Small Gifts", icon: "🎁", path: "/exchange-gift-ideas/small-gifts" },
  { key: "something", label: "Something...", icon: "✨", path: "/exchange-gift-ideas/something" },
];
</script>

<template>
  <header class="sticky top-0 z-50 backdrop-blur-md bg-slate-950/80 border-b border-red-500/20 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-14 sm:h-16 gap-3">
        <!-- Brand / Project Name -->
        <div class="flex items-center gap-2 min-w-0">
          <Link
            :href="`/${projectSlug}/menu`"
            class="flex items-center gap-2 group min-w-0"
            title="Back to Dashboard"
          >
            <span class="inline-flex items-center justify-center h-8 w-8 rounded-xl bg-gradient-to-br from-red-600 to-amber-500 text-white font-bold text-base shadow-md group-hover:scale-105 transition shrink-0">
              🎄
            </span>
            <div class="flex flex-col min-w-0">
              <span class="text-xs sm:text-sm font-bold text-slate-100 group-hover:text-amber-200 truncate transition">
                {{ projectName }}
              </span>
              <span class="text-[10px] text-amber-300/80 font-medium">Party Planner</span>
            </div>
          </Link>
        </div>

        <!-- Desktop Navigation Items -->
        <nav class="hidden md:flex items-center gap-1 overflow-x-auto py-1">
          <Link
            v-for="item in navItems"
            :key="item.key"
            :href="`/${projectSlug}${item.path ? (item.path.startsWith('/') ? item.path : '/' + item.path) : '/menu'}`"
            :class="[
              'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition whitespace-nowrap',
              currentPage === item.key
                ? 'bg-gradient-to-r from-red-600/90 to-amber-600/90 text-white shadow-md border border-amber-400/40 ring-1 ring-amber-400/30'
                : 'text-slate-300 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>{{ item.icon }}</span>
            <span>{{ item.label }}</span>
          </Link>
        </nav>

        <!-- Action Buttons (Sound + Share + Mobile Toggle) -->
        <div class="flex items-center gap-2 shrink-0">
          <!-- Copy / Share Button -->
          <button
            type="button"
            @click="copyShareLink"
            class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/30 active:scale-95 transition"
            :title="copied ? 'Copied to clipboard!' : 'Share party link with your group'"
          >
            <span v-if="copied">✅ Copied!</span>
            <template v-else>
              <span>🔗</span>
              <span class="hidden sm:inline">Share Link</span>
            </template>
          </button>

          <!-- Audio FX Toggle -->
          <button
            type="button"
            @click="handleToggleSound"
            class="inline-flex items-center justify-center h-8 w-8 rounded-xl bg-slate-800/90 border border-slate-700 text-sm hover:bg-slate-700/80 transition"
            :title="soundOn ? 'Mute party sound effects' : 'Enable sound effects'"
          >
            <span v-if="soundOn">🔊</span>
            <span v-else>🔇</span>
          </button>

          <!-- Mobile Hamburger Toggle -->
          <button
            type="button"
            @click="mobileMenuOpen = !mobileMenuOpen"
            class="md:hidden inline-flex items-center justify-center h-8 w-8 rounded-xl bg-slate-800/90 border border-slate-700 text-slate-200 hover:bg-slate-700 text-sm"
            aria-label="Toggle menu"
          >
            <span v-if="mobileMenuOpen">✕</span>
            <span v-else>☰</span>
          </button>
        </div>
      </div>

      <!-- Mobile Navigation Drawer / Dropdown -->
      <div
        v-if="mobileMenuOpen"
        class="md:hidden py-3 border-t border-slate-800/80 grid grid-cols-2 gap-1.5"
      >
        <Link
          v-for="item in navItems"
          :key="'mobile-' + item.key"
          :href="`/${projectSlug}${item.path ? (item.path.startsWith('/') ? item.path : '/' + item.path) : '/menu'}`"
          @click="mobileMenuOpen = false"
          :class="[
            'flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold transition',
            currentPage === item.key
              ? 'bg-gradient-to-r from-red-600 to-amber-600 text-white font-bold shadow'
              : 'text-slate-300 bg-slate-900/60 hover:bg-slate-800'
          ]"
        >
          <span>{{ item.icon }}</span>
          <span class="truncate">{{ item.label }}</span>
        </Link>
      </div>
    </div>
  </header>
</template>

