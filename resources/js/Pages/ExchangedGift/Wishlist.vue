<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import ExchangeNav from "@/Components/ExchangeNav.vue";
import { playChime, playCelebration, playPop } from "@/Utils/soundFx";

const props = defineProps({
  projects: { type: Array, required: true },
  currentProjectId: { type: Number, required: true },
  currentProjectSlug: { type: String, required: true },
  currentPage: { type: String, default: "wishlist" },
});

const LIST_TYPE = "classic";

// snow
const SNOWFLAKE_COUNT = 60;
const snowflakes = ref([]);

onMounted(() => {
  const flakes = [];
  for (let i = 0; i < SNOWFLAKE_COUNT; i++) {
    flakes.push({
      id: i,
      left: Math.random() * 100,
      size: 2 + Math.random() * 4,
      duration: 8 + Math.random() * 10,
      delay: Math.random() * -20,
      opacity: 0.25 + Math.random() * 0.7,
    });
  }
  snowflakes.value = flakes;

  // Auto-verify if code parameter is present in URL
  if (typeof window !== "undefined") {
    const urlParams = new URLSearchParams(window.location.search);
    const codeFromUrl = urlParams.get("code");
    if (codeFromUrl) {
      uniqueCode.value = codeFromUrl;
      verifyCode();
    }
  }
});

/* ----------------- Code + participant state ----------------- */
const uniqueCode = ref("");
const codeError = ref("");
const verifyingCode = ref(false);

const participant = ref(null); // from backend

const wishlistForm = ref({
  wishlist_1: "",
  wishlist_1_description: "",
  wishlist_2: "",
  wishlist_2_description: "",
  wishlist_3: "",
  wishlist_3_description: "",
});

const saveMessage = ref("");
const saveError = ref("");
const saving = ref(false);

const groupName = computed(() => {
  const group = props.projects.find((p) => p.id === props.currentProjectId);
  return group?.name || "Your Group";
});

// Helper to extract URLs from wishlist text/description
const extractUrl = (text) => {
  if (!text) return null;
  const match = text.match(/(https?:\/\/[^\s]+)/i);
  return match ? match[0] : null;
};

/* ----------------- Verify code ----------------- */
const verifyCode = async () => {
  codeError.value = "";
  saveMessage.value = "";
  saveError.value = "";
  participant.value = null;

  const code = uniqueCode.value.trim();
  if (!code) {
    codeError.value = "Please enter the unique code sent to your email.";
    return;
  }

  verifyingCode.value = true;
  try {
    const { data } = await axios.post("/exchange/wishlist/lookup-by-code", {
      project_id: props.currentProjectId,
      unique_code: code,
    });

    participant.value = data.participant;

    wishlistForm.value = {
      wishlist_1: data.participant.wishlist_1 || "",
      wishlist_1_description: data.participant.wishlist_1_description || "",
      wishlist_2: data.participant.wishlist_2 || "",
      wishlist_2_description: data.participant.wishlist_2_description || "",
      wishlist_3: data.participant.wishlist_3 || "",
      wishlist_3_description: data.participant.wishlist_3_description || "",
    };
    playChime();
  } catch (e) {
    console.error(e);
    codeError.value =
      e.response?.data?.message ||
      "Code not found for this group. Please check your email.";
  } finally {
    verifyingCode.value = false;
  }
};

const participantDisplayName = computed(() => {
  if (!participant.value) return "";
  return participant.value.codename || participant.value.name;
});

/* ----------------- Save wishlist ----------------- */
const saveWishlist = async () => {
  if (!participant.value) {
    saveError.value = "Please verify your code first.";
    return;
  }

  saveMessage.value = "";
  saveError.value = "";
  saving.value = true;

  try {
    const payload = {
      project_id: props.currentProjectId,
      unique_code: uniqueCode.value.trim(),
      ...wishlistForm.value,
    };

    const { data } = await axios.post("/exchange/wishlist/update-by-code", payload);
    saveMessage.value =
      data.message || "Your wishlist has been updated successfully.";
    playCelebration();
  } catch (e) {
    console.error(e);
    saveError.value =
      e.response?.data?.message ||
      "Unable to save your wishlist right now. Please try again.";
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <div
    class="relative min-h-screen bg-gradient-to-b from-red-900 via-slate-950 to-slate-900 text-slate-100 overflow-hidden"
  >
    <!-- Snow -->
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
      <div
        v-for="flake in snowflakes"
        :key="flake.id"
        class="snowflake"
        :style="{
          left: flake.left + '%',
          width: flake.size + 'px',
          height: flake.size + 'px',
          animationDuration: flake.duration + 's, ' + flake.duration / 2 + 's',
          animationDelay: flake.delay + 's, ' + flake.delay + 's',
          opacity: flake.opacity,
        }"
      />
    </div>

    <!-- Unified Navigation Bar -->
    <ExchangeNav
      :projectSlug="currentProjectSlug"
      :projectName="groupName"
      currentPage="wishlist"
    />

    <!-- Header -->
    <header
      class="relative z-10 bg-gradient-to-r from-red-700 via-red-600 to-rose-600/90 text-white shadow-lg border-b border-red-500/50"
    >
      <div
        class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between gap-4"
      >
        <div class="flex items-center gap-3">
          <div
            class="h-11 w-11 rounded-2xl bg-white/20 flex items-center justify-center text-2xl shadow-inner"
          >
            🎁
          </div>
          <div>
            <h1 class="text-2xl font-bold tracking-tight">
              Update Your Wishlist
            </h1>
            <p class="text-xs text-red-50/90">
              {{ groupName }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 text-xs">
          <Link
            :href="`/${currentProjectSlug}/menu`"
            class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-medium transition"
          >
            ← Back to Dashboard
          </Link>
        </div>
      </div>
    </header>

    <main class="relative z-10 max-w-5xl mx-auto px-4 py-6 space-y-6">
      <!-- Intro -->
      <section class="space-y-3">
        <h2 class="text-xl md:text-2xl font-semibold">
          Edit your gift wishes 🎄
        </h2>
        <p class="text-sm text-slate-200 max-w-2xl">
          Use the
          <span class="font-semibold text-amber-200">unique code</span> sent to
          your email when you joined the Exchange Gift. After verifying the
          code, you can update your wishlist items and descriptions so your
          Secret Santa has better ideas.
        </p>
      </section>

      <!-- Grid: Code + Form -->
      <section
        class="grid gap-5 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] items-start"
      >
        <!-- Left: Code verify -->
        <div
          class="bg-slate-900/80 border border-amber-500/40 rounded-3xl shadow-xl p-5 flex flex-col gap-4"
        >
          <h3 class="text-sm font-semibold flex items-center gap-2">
            <span
              class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-800 text-amber-300 text-sm"
            >
              🔑
            </span>
            Enter your unique code
          </h3>

          <p class="text-[12px] text-slate-300">
            You’ll find this code in the welcome email from the Exchange Gift
            system. It’s the same code used for awards and games.
          </p>

          <div class="space-y-2">
            <label class="text-[11px] text-slate-400">Unique Code</label>
            <input
              v-model="uniqueCode"
              type="text"
              placeholder="e.g. X9A7-3BQP"
              class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
                     focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                     placeholder:text-slate-500"
              @keydown.enter.prevent="verifyCode"
            />
          </div>

          <button
            type="button"
            class="px-3 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-300 text-slate-900 text-xs font-semibold
                   hover:from-emerald-400 hover:to-emerald-200 active:scale-[0.98] transition
                   disabled:opacity-40 disabled:cursor-not-allowed"
            @click="verifyCode"
            :disabled="verifyingCode"
          >
            <span v-if="!verifyingCode">Verify Code</span>
            <span v-else>Checking…</span>
          </button>

          <p
            v-if="codeError"
            class="text-[11px] bg-red-500/20 text-red-200 border border-red-400/60 rounded-xl px-3 py-2"
          >
            ⚠️ {{ codeError }}
          </p>

          <div
            v-if="participant"
            class="mt-2 text-[11px] bg-slate-800/80 border border-emerald-500/40 rounded-2xl px-3 py-2 text-emerald-100"
          >
            <div class="font-semibold mb-1">
              Code verified for:
              <span class="text-amber-200">
                {{ participantDisplayName }}
              </span>
            </div>
            <p>
              You can now edit and save your wishlist on the right side. You can
              update this again later using the same code.
            </p>
          </div>
        </div>

        <!-- Right: Wishlist form -->
        <div
          class="bg-slate-900/85 border border-slate-700 rounded-3xl shadow-xl p-5 space-y-4"
        >
          <div class="flex items-center justify-between gap-2">
            <h3 class="text-lg font-semibold flex items-center gap-2">
              <span
                class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-400 text-slate-900 text-sm"
              >
                🎀
              </span>
              Your Wishlist
            </h3>

            <span
              v-if="participant"
              class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] bg-emerald-500/15 border border-emerald-400/60 text-emerald-200"
            >
              ✅ Code verified
            </span>
          </div>

          <p class="text-[12px] text-slate-300">
            Add up to three wishlist items. You can also put a
            <span class="font-semibold text-amber-200">link</span> (e.g. Shopee,
            Lazada) in the description so your Secret Santa can see the exact
            item.
          </p>

          <div
            v-if="!participant"
            class="text-[11px] text-slate-400 italic bg-slate-800/70 border border-dashed border-slate-600 rounded-2xl px-3 py-3"
          >
            Verify your unique code on the left to start editing your wishlist.
          </div>

          <form
            v-else
            class="space-y-4"
            @submit.prevent="saveWishlist"
          >
            <!-- Item 1 -->
            <div class="space-y-2">
              <div class="flex items-center justify-between gap-2">
                <label class="text-[11px] text-slate-300 font-semibold">
                  1. Wishlist Item
                </label>
                <span class="text-[10px] text-slate-500">
                  Optional – but recommended
                </span>
              </div>
              <input
                v-model="wishlistForm.wishlist_1"
                type="text"
                placeholder="e.g. Cute mug, wireless mouse, scented candle"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
                       focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                       placeholder:text-slate-500"
              />
              <textarea
                v-model="wishlistForm.wishlist_1_description"
                rows="2"
                placeholder="Add notes or a link (Shopee/Lazada URL)…"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-700 bg-slate-900/80 text-xs text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                       placeholder:text-slate-500"
              />
              <div v-if="extractUrl(wishlistForm.wishlist_1) || extractUrl(wishlistForm.wishlist_1_description)" class="pt-1">
                <a
                  :href="extractUrl(wishlistForm.wishlist_1) || extractUrl(wishlistForm.wishlist_1_description)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-400/15 border border-amber-400/40 text-amber-200 text-[11px] font-medium hover:bg-amber-400/25 transition"
                >
                  <span>🔗</span>
                  <span>Test Shopping Link</span>
                  <span class="text-[9px] opacity-70">↗</span>
                </a>
              </div>
            </div>

            <!-- Item 2 -->
            <div class="space-y-2">
              <label class="text-[11px] text-slate-300 font-semibold">
                2. Wishlist Item
              </label>
              <input
                v-model="wishlistForm.wishlist_2"
                type="text"
                placeholder="Optional second idea…"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
                       focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                       placeholder:text-slate-500"
              />
              <textarea
                v-model="wishlistForm.wishlist_2_description"
                rows="2"
                placeholder="Notes or link for this item…"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-700 bg-slate-900/80 text-xs text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                       placeholder:text-slate-500"
              />
              <div v-if="extractUrl(wishlistForm.wishlist_2) || extractUrl(wishlistForm.wishlist_2_description)" class="pt-1">
                <a
                  :href="extractUrl(wishlistForm.wishlist_2) || extractUrl(wishlistForm.wishlist_2_description)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-400/15 border border-amber-400/40 text-amber-200 text-[11px] font-medium hover:bg-amber-400/25 transition"
                >
                  <span>🔗</span>
                  <span>Test Shopping Link</span>
                  <span class="text-[9px] opacity-70">↗</span>
                </a>
              </div>
            </div>

            <!-- Item 3 -->
            <div class="space-y-2">
              <label class="text-[11px] text-slate-300 font-semibold">
                3. Wishlist Item
              </label>
              <input
                v-model="wishlistForm.wishlist_3"
                type="text"
                placeholder="Optional third idea…"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
                       focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                       placeholder:text-slate-500"
              />
              <textarea
                v-model="wishlistForm.wishlist_3_description"
                rows="2"
                placeholder="Notes or link for this item…"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-700 bg-slate-900/80 text-xs text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                       placeholder:text-slate-500"
              />
              <div v-if="extractUrl(wishlistForm.wishlist_3) || extractUrl(wishlistForm.wishlist_3_description)" class="pt-1">
                <a
                  :href="extractUrl(wishlistForm.wishlist_3) || extractUrl(wishlistForm.wishlist_3_description)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-400/15 border border-amber-400/40 text-amber-200 text-[11px] font-medium hover:bg-amber-400/25 transition"
                >
                  <span>🔗</span>
                  <span>Test Shopping Link</span>
                  <span class="text-[9px] opacity-70">↗</span>
                </a>
              </div>
            </div>

            <div class="pt-2 flex flex-col gap-2">
              <button
                type="submit"
                class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl
                       bg-gradient-to-r from-amber-500 via-yellow-400 to-emerald-300
                       text-slate-900 text-sm font-semibold shadow-md
                       hover:from-amber-400 hover:via-yellow-300 hover:to-emerald-200
                       active:scale-[0.98] transition
                       disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="saving"
              >
                <span v-if="saving">Saving wishlist…</span>
                <span v-else>Save Wishlist</span>
              </button>

              <p
                v-if="saveMessage"
                class="text-[11px] bg-emerald-500/15 text-emerald-100 border border-emerald-400/60 rounded-xl px-3 py-2"
              >
                {{ saveMessage }}
              </p>

              <p
                v-if="saveError"
                class="text-[11px] bg-red-500/20 text-red-200 border border-red-400/60 rounded-xl px-3 py-2"
              >
                ⚠️ {{ saveError }}
              </p>
            </div>
          </form>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.snowflake {
  position: absolute;
  top: -10%;
  background-color: rgba(255, 255, 255, 0.95);
  border-radius: 999px;
  box-shadow: 0 0 6px rgba(255, 255, 255, 0.8);
  animation-name: snow-fall, snow-sway;
  animation-timing-function: linear, ease-in-out;
  animation-iteration-count: infinite, infinite;
}

@keyframes snow-fall {
  0% {
    transform: translate3d(0, 0, 0);
  }
  100% {
    transform: translate3d(0, 120vh, 0);
  }
}

@keyframes snow-sway {
  0% {
    margin-left: -10px;
  }
  50% {
    margin-left: 10px;
  }
  100% {
    margin-left: -10px;
  }
}
</style>
