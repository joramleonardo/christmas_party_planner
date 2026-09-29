<!-- GroupServices.vue -->
<script setup>
import { ref, onMounted, computed, watch, nextTick } from "vue";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import ExchangeNav from "@/Components/ExchangeNav.vue";
import { playCelebration, playChime } from "@/Utils/soundFx";

const props = defineProps({
  project: { type: Object, required: true },
});

const LIST_TYPE = "classic";

/* -------------------- Participants state -------------------- */
const participants = ref([]);
const budgetStats = ref({});
const loadingParticipants = ref(false);
const savingParticipant = ref(false);

const newName = ref("");
const newEmail = ref("");
const newCodename = ref("");
const newBudgetChoice = ref(null);
const emailError = ref("");

// ✅ Up to 3 wishlist items
const newWishlist1 = ref("");
const newWishlist2 = ref("");
const newWishlist3 = ref("");
const newWishlist1Description = ref("");
const newWishlist2Description = ref("");
const newWishlist3Description = ref("");

/* (kept; optional feature you started) */
const myEmail = ref("");
const myCodename = ref("");
const codenameMessage = ref("");
const codenameError = ref("");
const savingCodename = ref(false);

/* -------------------- Remove modal (NEW) -------------------- */
const showRemoveModal = ref(false);
const removeTargetId = ref(null);
const santaCode = ref("");
const verifyingSantaCode = ref(false);
const removeError = ref("");
const removing = ref(false);
const santaCodeInputRef = ref(null);

const openRemoveModal = async (participantId) => {
  removeTargetId.value = participantId;
  santaCode.value = "";
  removeError.value = "";
  showRemoveModal.value = true;

  await nextTick();
  santaCodeInputRef.value?.focus?.();
};

const closeRemoveModal = () => {
  showRemoveModal.value = false;
  removeTargetId.value = null;
  santaCode.value = "";
  removeError.value = "";
  verifyingSantaCode.value = false;
  removing.value = false;
};

const verifySantaSecretCode = async () => {
  removeError.value = "";
  const code = santaCode.value.trim();
  if (!code) {
    removeError.value = "Please enter the Santa Secret Code.";
    return false;
  }

  verifyingSantaCode.value = true;
  try {
    const { data } = await axios.post("/exchange/verify-santa-code", {
      project_id: props.project.id,
      code,
    });

    if (!data?.valid) {
      removeError.value = "Invalid Santa Secret Code.";
      return false;
    }

    return true;
  } catch (e) {
    removeError.value =
      e.response?.data?.message || "Unable to verify code right now.";
    return false;
  } finally {
    verifyingSantaCode.value = false;
  }
};

const confirmRemoveParticipant = async () => {
  if (!removeTargetId.value) return;

  const ok = await verifySantaSecretCode();
  if (!ok) return;

  removing.value = true;
  try {
    await axios.delete(`/exchange/participants/${removeTargetId.value}`);
    await fetchParticipants();
    closeRemoveModal();
  } catch (e) {
    removeError.value =
      e.response?.data?.message || "Failed to remove participant.";
  } finally {
    removing.value = false;
  }
};

/* Close modal on ESC */
const onKeydown = (e) => {
  if (e.key === "Escape" && showRemoveModal.value) closeRemoveModal();
};

watch(showRemoveModal, (open) => {
  if (open) window.addEventListener("keydown", onKeydown);
  else window.removeEventListener("keydown", onKeydown);
});

/* -------------------- Budget options -------------------- */
const budgetOptions = [
  { value: 100, label: "₱100" },
  { value: 200, label: "₱200" },
  { value: 300, label: "₱300" },
  { value: 400, label: "₱400" },
  { value: 500, label: "₱500" },
  { value: 600, label: "₱600" },
  { value: 700, label: "₱700" },
  { value: 800, label: "₱800" },
  { value: 900, label: "₱900" },
  { value: 1000, label: "₱1000+" },
];

const participantCount = computed(() => participants.value.length);

const emailExists = computed(() => {
  const email = newEmail.value.trim().toLowerCase();
  if (!email) return false;
  return participants.value.some(
    (p) => (p.email || "").toLowerCase() === email
  );
});

/* -------------------- Budget computed helpers -------------------- */
const totalVotes = computed(() =>
  Object.values(budgetStats.value).reduce(
    (sum, v) => sum + (Number(v) || 0),
    0
  )
);

const budgetResults = computed(() =>
  budgetOptions.map((opt) => {
    const votes = Number(budgetStats.value?.[opt.value]) || 0;
    const percent =
      totalVotes.value > 0 ? Math.round((votes / totalVotes.value) * 100) : 0;
    return { ...opt, votes, percent };
  })
);

const topChoice = computed(() => {
  if (!budgetResults.value.length || totalVotes.value === 0) return null;
  const maxVotes = Math.max(...budgetResults.value.map((r) => r.votes));
  if (maxVotes <= 0) return null;

  const leaders = budgetResults.value.filter((r) => r.votes === maxVotes);
  return { maxVotes, choices: leaders };
});

const distributionMessage = computed(() => {
  const n = participantCount.value;
  if (!n) return "";
  if (n < 2) {
    return "You need at least 2 participants to generate fair assignments in Small Gifts Exchange (and to properly use the tools).";
  }
  return `With ${n} participant${n === 1 ? "" : "s"}, this master list will be reused across all group tools.`;
});

/* -------------------- Fetch + mutations -------------------- */
const fetchParticipants = async () => {
  loadingParticipants.value = true;
  emailError.value = "";

  try {
    const { data } = await axios.get("/exchange/data", {
      params: { project_id: props.project.id, list_type: LIST_TYPE },
    });

    participants.value = data.participants || [];
    budgetStats.value = data.budget_stats || {};
  } catch (e) {
    console.error("Error loading participants:", e);
  } finally {
    loadingParticipants.value = false;
  }
};

const addParticipant = async () => {
  const name = newName.value.trim();
  const email = newEmail.value.trim();
  const codename = newCodename.value.trim();
  const budgetVote = newBudgetChoice.value;

  const wishlist_1 = newWishlist1.value.trim();
  const wishlist_2 = newWishlist2.value.trim();
  const wishlist_3 = newWishlist3.value.trim();

    const wishlist_1_description = newWishlist1Description.value.trim();
    const wishlist_2_description = newWishlist2Description.value.trim();
    const wishlist_3_description = newWishlist3Description.value.trim();

  emailError.value = "";
  if (!name || !email || !budgetVote || !codename || emailExists.value) return;
  savingParticipant.value = true;

  try {
    await axios.post("/exchange/participants", {
        project_id: props.project.id,
        name,
        email,
        codename,
        list_type: LIST_TYPE,
        budget_vote: budgetVote,
        wishlist_1,
        wishlist_2,
        wishlist_3,
        wishlist_1_description,
        wishlist_2_description,
        wishlist_3_description,
    });

    newName.value = "";
    newEmail.value = "";
    newCodename.value = "";
    newBudgetChoice.value = null;

    // reset wishlists
    newWishlist1.value = "";
    newWishlist2.value = "";
    newWishlist3.value = "";

    newWishlist1Description.value = "";
    newWishlist2Description.value = "";
    newWishlist3Description.value = "";

    await fetchParticipants();
  } catch (e) {
    if (e.response?.status === 422) {
      const errors = e.response?.data?.errors || {};
      if (errors.email?.[0]) emailError.value = errors.email[0];
      else if (errors.codename?.[0]) emailError.value = errors.codename[0];
      else if (errors.name?.[0]) emailError.value = errors.name[0];
      else emailError.value = "Validation error. Please check your inputs.";
    } else {
      console.error("Error adding participant:", e);
    }
  }finally {
    savingParticipant.value = false; // ✅ done saving
  }
};

/* removed confirm() version – now uses modal */
const deleteParticipant = (id) => openRemoveModal(id);

/* -------------------- Services -------------------- */
const services = [
  {
    key: "secret-santa",
    icon: "🎅",
    title: "Exchange Gift Idea",
    tagline: "“Secret Santa”",
    description:
      "Classic one-to-one Secret Santa style assignments. Keep identities hidden until the big reveal.",
    available: true,
  },
  {
    key: "awards",
    icon: "🏆",
    title: "Awards",
    tagline: "Fun party awards & voting",
    description:
      "Use your unique code to vote for special awards (e.g. Best Dressed, Life of the Party, Silent Worker).",
    available: true,
  },
  {
    key: "food-planning",
    icon: "🍽️",
    title: "Food Planning",
    tagline: "Menu, budget & collection",
    description:
      "List food items and prices, see the total, and compute how much to collect per participant (with boss extra option).",
    available: true,
  },
  {
    key: "wishlist",
    icon: "📝",
    title: "Wishlists Hub",
    tagline: "Gift wishes & shopping links",
    description:
      "Check or update your 3 gift wishes anytime using your personal code so your Secret Santa knows what you want.",
    available: true,
  },
  {
    key: "others",
    icon: "⭐",
    title: "Other Activities",
    tagline: "Program & extras",
    description:
      "Plan your program, raffles, awards, or any extra activities for your celebration.",
    available: false,
  },
];

/* -------------------- Snowflakes -------------------- */
const SNOWFLAKE_COUNT = 80;
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

  fetchParticipants();
});

/* (kept; optional) */
const saveMyCodename = async () => {
  codenameMessage.value = "";
  codenameError.value = "";

  const email = myEmail.value.trim();
  const codename = myCodename.value.trim();
  if (!email || !codename) return;

  savingCodename.value = true;
  try {
    const { data } = await axios.post("/exchange/codenames/set", {
      project_id: props.project.id,
      list_type: LIST_TYPE,
      email,
      codename,
    });

    codenameMessage.value = data.message || "Codename saved!";
    myCodename.value = "";
    await fetchParticipants();
  } catch (e) {
    codenameError.value =
      e.response?.data?.message || "Unable to save codename right now.";
  } finally {
    savingCodename.value = false;
  }
};
</script>

<template>
  <div
    class="relative min-h-screen bg-gradient-to-b from-red-900 via-slate-950 to-slate-900 text-slate-50 overflow-hidden"
  >
    <!-- Snow background -->
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
      <div
        v-for="flake in snowflakes"
        :key="flake.id"
        class="snowflake"
        :style="{
          left: flake.left + '%',
          width: flake.size + 'px',
          height: flake.size + 'px',
          animationDuration:
            flake.duration + 's, ' + flake.duration / 2 + 's',
          animationDelay: flake.delay + 's, ' + flake.delay + 's',
          opacity: flake.opacity,
        }"
      />
    </div>

    <!-- ✅ Remove modal -->
    <div
      v-if="showRemoveModal"
      class="fixed inset-0 z-[60] flex items-center justify-center px-4"
      aria-modal="true"
      role="dialog"
    >
      <!-- backdrop -->
      <div
        class="absolute inset-0 bg-black/70 backdrop-blur-sm"
        @click="closeRemoveModal"
      ></div>

      <!-- panel -->
      <div
        class="relative w-full max-w-md rounded-3xl bg-slate-900 border border-red-400/40 shadow-2xl p-5"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="text-base font-semibold text-slate-50">
              Confirm remove participant
            </h3>
            <p class="text-[11px] text-slate-300 mt-1">
              Please enter the
              <span class="text-amber-200 font-semibold"
                >Santa Secret Code</span
              >
              to remove.
            </p>
          </div>

          <button
            type="button"
            class="h-8 w-8 rounded-xl border border-slate-700 bg-slate-800/80 text-slate-200 hover:bg-slate-800"
            @click="closeRemoveModal"
            :disabled="verifyingSantaCode || removing"
            aria-label="Close"
          >
            ✕
          </button>
        </div>

        <div class="mt-4 space-y-2">
          <label class="text-[11px] text-slate-300">Santa Secret Code</label>
          <input
            ref="santaCodeInputRef"
            v-model="santaCode"
            type="text"
            placeholder="XXXX-XXXX"
            class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
              focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
              placeholder:text-slate-500"
            @keydown.enter.prevent="confirmRemoveParticipant"
          />

          <p
            v-if="removeError"
            class="text-[11px] bg-red-500/20 text-red-200 border border-red-400/60 rounded-xl px-3 py-2"
          >
            ⚠️ {{ removeError }}
          </p>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-200 text-xs font-semibold hover:bg-slate-700/60"
              @click="closeRemoveModal"
              :disabled="verifyingSantaCode || removing"
            >
              Cancel
            </button>

            <button
              type="button"
              class="px-3 py-2 rounded-xl bg-gradient-to-r from-red-500 to-amber-300 text-slate-900 text-xs font-semibold
                hover:from-red-400 hover:to-yellow-200 active:scale-[0.98] transition
                disabled:opacity-50 disabled:cursor-not-allowed"
              @click="confirmRemoveParticipant"
              :disabled="!santaCode.trim() || verifyingSantaCode || removing"
            >
              <span v-if="verifyingSantaCode">Verifying...</span>
              <span v-else-if="removing">Removing...</span>
              <span v-else>Confirm Remove</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Unified Navigation Bar -->
    <ExchangeNav
      :projectSlug="project.slug"
      :projectName="project.name"
      currentPage="menu"
    />

    <main class="relative z-10">
      <div class="max-w-6xl mx-auto px-4 py-10 md:py-14 space-y-8">
        <!-- Header / Hero -->
        <section class="space-y-4">
          <div
            class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-800/60 border border-red-400/60 text-[11px] uppercase tracking-[0.18em] text-red-100"
          >
            <span>🎄</span>
            <span>Dashboard</span>
            <span>🎄</span>
          </div>

          <div
            class="flex flex-col md:flex-row md:items-end md:justify-between gap-4"
          >
            <div class="space-y-2">
              <h1 class="text-3xl md:text-4xl font-extrabold leading-tight">
                Welcome to:
                <span class="block text-amber-300">
                  {{ project.name }}
                </span>
              </h1>
              <p class="max-w-2xl text-sm md:text-base text-slate-200">
                Manage your Christmas Party here. Use the tools below—
                Exchange Gift Ideas, Food Planning, Awards and more party
                planning features in one place.
              </p>
            </div>

            <div class="text-right text-[11px] text-slate-300 space-y-1">
              <p class="font-semibold text-slate-100">
                Group Code:
                <span
                  v-if="project.unique_code"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-900 border border-emerald-400/60 text-emerald-200 font-mono text-[11px]"
                >
                  🎟️ {{ project.unique_code }}
                </span>
                <span v-else class="italic text-slate-400"
                  >(code not available)</span
                >
              </p>
              <p v-if="project.organizer_name">
                Organizer:
                <span class="font-medium text-slate-100">{{
                  project.organizer_name
                }}</span>
              </p>
              <p v-if="project.organizer_email">
                Contact:
                <span class="font-mono text-slate-200">{{
                  project.organizer_email
                }}</span>
              </p>
            </div>
          </div>
        </section>

        <!-- Participants + Budget (shared) -->
        <section class="grid gap-5 md:grid-cols-2">
          <!-- Participants -->
          <div
            class="bg-slate-900/80 backdrop-blur rounded-3xl shadow-xl border border-red-500/40 p-5"
          >
            <div class="flex items-center justify-between mb-3">
              <div>
                <h2 class="font-semibold text-lg flex items-center gap-2">
                  <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600/90 text-white text-sm"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      fill="currentColor"
                      class="bi bi-person-plus-fill"
                      viewBox="0 0 16 16"
                    >
                      <path
                        d="M1 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"
                      />
                      <path
                        fill-rule="evenodd"
                        d="M13.5 5a.5.5 0 0 1 .5.5V7h1.5a.5.5 0 0 1 0 1H14v1.5a.5.5 0 0 1-1 0V8h-1.5a.5.5 0 0 1 0-1H13V5.5a.5.5 0 0 1 .5-.5"
                      />
                    </svg>
                  </span>

                  Join the
                  <span class="block text-amber-300">
                    {{ project.name }}
                  </span>
                </h2>
                <p class="text-xs text-slate-300 mt-0.5">
                  Please input your name and email here.
                </p>
              </div>

              <div class="flex flex-col items-end">
                <span
                  class="text-[10px] text-slate-400 uppercase tracking-[0.18em]"
                  >Total</span
                >
                <span
                  class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-200 border border-emerald-500/40"
                >
                  {{ participantCount }}
                </span>
              </div>
            </div>

            <!-- Add participant -->
            <form
              @submit.prevent="addParticipant"
              class="flex flex-col gap-2 mb-3"
            >
              <div class="flex flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                  <input
                    v-model="newName"
                    type="text"
                    placeholder="Name (e.g. Maria Claus)"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
                      focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                      placeholder:text-slate-500 pr-8"
                  />
                  <span
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-500 text-xs"
                  >
                    #{{ participantCount + 1 }}
                  </span>
                </div>

                <div class="flex-1">
                  <input
                    v-model="newEmail"
                    type="email"
                    placeholder="Email (use to send lists/reminders)"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
                      focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                      placeholder:text-slate-500"
                  />
                </div>
              </div>

              <!-- Codename -->
              <div class="flex-1">
                <input
                  v-model="newCodename"
                  type="text"
                  placeholder="Codename (e.g. Frosty Falcon)"
                  required
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
                    focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                    placeholder:text-slate-500"
                />
                <p class="text-[10px] text-slate-400 mt-1">
                  Note: This will be used as your Secret Santa identity and for
                  games/other activities.
                </p>
              </div>

              <!-- Budget choice -->
              <div class="flex flex-col gap-1 mt-1">
                <span class="text-[11px] text-slate-300"
                  >Suggested budget per gift (PHP)</span
                >
                <div class="flex flex-wrap gap-2">
                  <label
                    v-for="opt in budgetOptions"
                    :key="opt.value"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-[11px] cursor-pointer
                      bg-slate-900/70 border-slate-600 hover:border-emerald-400/70 hover:bg-slate-800/80 transition"
                  >
                    <input
                      type="radio"
                      class="h-3.5 w-3.5 text-emerald-400 border-slate-500"
                      v-model="newBudgetChoice"
                      :value="opt.value"
                    />
                    <span class="text-slate-100">{{ opt.label }}</span>
                  </label>
                </div>
              </div>

              <!-- Wishlists (up to 3, optional) -->
<!-- Wishlists (up to 3, optional) -->
<div class="mt-2 space-y-3">
  <label class="text-[11px] text-slate-300">
    Wishlist
    <span class="text-slate-500">(up to 3 items with descriptions, optional)</span>
  </label>

  <!-- Wishlist #1 -->
  <div class="space-y-1.5">
    <input
      v-model="newWishlist1"
      type="text"
      placeholder="Wishlist #1 (e.g. Tumbler, plant, book, Shopee link...)"
      class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
        focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
        placeholder:text-slate-500"
    />
    <textarea
      v-model="newWishlist1Description"
      rows="2"
      placeholder="Description for Wishlist #1 (size, color, link, flavor, etc.)"
      class="w-full px-3 py-2 rounded-xl border border-slate-600 bg-slate-800/80 text-xs text-slate-50
        focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
        placeholder:text-slate-500"
    ></textarea>
  </div>

  <!-- Wishlist #2 -->
  <div class="space-y-1.5">
    <input
      v-model="newWishlist2"
      type="text"
      placeholder="Wishlist #2 (optional)"
      class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
        focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
        placeholder:text-slate-500"
    />
    <textarea
      v-model="newWishlist2Description"
      rows="2"
      placeholder="Description for Wishlist #2 (optional)"
      class="w-full px-3 py-2 rounded-xl border border-slate-600 bg-slate-800/80 text-xs text-slate-50
        focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
        placeholder:text-slate-500"
    ></textarea>
  </div>

  <!-- Wishlist #3 -->
  <div class="space-y-1.5">
    <input
      v-model="newWishlist3"
      type="text"
      placeholder="Wishlist #3 (optional)"
      class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
        focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
        placeholder:text-slate-500"
    />
    <textarea
      v-model="newWishlist3Description"
      rows="2"
      placeholder="Description for Wishlist #3 (optional)"
      class="w-full px-3 py-2 rounded-xl border border-slate-600 bg-slate-800/80 text-xs text-slate-50
        focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
        placeholder:text-slate-500"
    ></textarea>
  </div>

  <p class="text-[10px] text-slate-400 mt-1">
    Your Secret Santa (and gift planners) can use this as a detailed guide.
  </p>
</div>


              <div class="flex flex-wrap items-center gap-2 mt-1">
<button
  type="submit"
  class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-400 text-slate-900 text-sm font-semibold
         hover:from-emerald-400 hover:to-emerald-300 active:scale-[0.98] transition
         disabled:opacity-40 disabled:cursor-not-allowed"
  :disabled="
    savingParticipant ||
    !newName ||
    !newEmail ||
    !newBudgetChoice ||
    !newCodename ||
    emailExists ||
    loadingParticipants
  "
>
  <span v-if="savingParticipant">Saving...</span>
  <span v-else>Join Now</span>
</button>


                <span
                  v-if="emailExists"
                  class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] bg-red-500/20 text-red-200 border border-red-400/60"
                >
                  ⚠️ This email is already in this list.
                </span>

                <span
                  v-if="emailError && !emailExists"
                  class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] bg-red-500/20 text-red-200 border border-red-400/60"
                >
                  ⚠️ {{ emailError }}
                </span>
              </div>
            </form>

            <!-- Hint -->
            <p
              v-if="participantCount"
              class="text-[11px] rounded-xl px-3 py-2 mb-3 flex items-start gap-2 border"
              :class="
                participantCount >= 2
                  ? 'bg-emerald-500/10 text-emerald-100 border-emerald-500/40'
                  : 'bg-amber-500/20 text-amber-100 border-amber-400/40'
              "
            >
              <span class="mt-[1px]">
                {{ participantCount >= 2 ? "✅" : "⚠️" }}
              </span>
              <span>{{ distributionMessage }}</span>
            </p>

            <!-- List -->
            <div
              v-if="!participants.length"
              class="text-xs text-slate-300 italic py-6 text-center bg-slate-800/70 rounded-2xl border border-dashed border-slate-600"
            >
              No participants yet. Start adding names to see the list here.
            </div>

            <ul
              v-else
              class="space-y-1.5 max-h-64 overflow-y-auto pr-1 custom-scroll"
            >
              <li
                v-for="(p, index) in participants"
                :key="p.id"
                class="flex items-center justify-between text-sm bg-slate-800/80 rounded-2xl px-3 py-2 border border-slate-600/70"
              >
                <div class="flex items-center gap-2">
                  <span
                    class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-slate-900 text-[11px] text-slate-200 border border-slate-500"
                  >
                    {{ index + 1 }}
                  </span>

                  <div class="flex flex-col">
                    <span
                      class="font-medium text-slate-50 truncate max-w-[10rem] sm:max-w-xs"
                    >
                      {{ p.name }}
                    </span>
                  </div>
                </div>

                <button
                  type="button"
                  class="text-[11px] text-red-200 hover:text-red-100 px-2 py-1 rounded-full hover:bg-red-900/40 transition"
                  @click="deleteParticipant(p.id)"
                >
                  Remove
                </button>
              </li>
            </ul>
          </div>

          <!-- Budget Poll (view-only) -->
          <div
            class="bg-slate-900/85 backdrop-blur rounded-3xl shadow-xl border border-emerald-500/40 p-5 space-y-4"
          >
            <div
              class="flex flex-col md:flex-row md:items-start md:justify-between gap-4"
            >
              <div class="space-y-1 flex-1">
                <h2 class="font-semibold text-lg flex items-center gap-2">
                  <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-400 text-slate-900 text-sm"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      fill="currentColor"
                      class="bi bi-cash-coin"
                      viewBox="0 0 16 16"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M11 15a4 4 0 1 0 0-8 4 4 0 0 0 0 8m5-4a5 5 0 1 1-10 0 5 5 0 0 1 10 0"
                      />
                      <path
                        d="M9.438 11.944c.047.596.518 1.06 1.363 1.116v.44h.375v-.443c.875-.061 1.386-.529 1.386-1.207 0-.618-.39-.936-1.09-1.1l-.296-.07v-1.2c.376.043.614.248.671.532h.658c-.047-.575-.54-1.024-1.329-1.073V8.5h-.375v.45c-.747.073-1.255.522-1.255 1.158 0 .562.378.92 1.007 1.066l.248.061v1.272c-.384-.058-.639-.27-.696-.563h-.668zm1.36-1.354c-.369-.085-.569-.26-.569-.522 0-.294.216-.514.572-.578v1.1zm.432.746c.449.104.655.272.655.569 0 .339-.257.571-.709.614v-1.195z"
                      />
                      <path
                        d="M1 0a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h4.083q.088-.517.258-1H3a2 2 0 0 0-2-2V3a2 2 0 0 0 2-2h10a2 2 0 0 0 2 2v3.528c.38.34.717.728 1 1.154V1a1 1 0 0 0-1-1z"
                      />
                      <path
                        d="M9.998 5.083 10 5a2 2 0 1 0-3.132 1.65 6 6 0 0 1 3.13-1.567"
                      />
                    </svg>
                  </span>
                  Budget Poll (PHP)
                </h2>
              </div>

              <div
                class="px-3 py-2 rounded-2xl bg-slate-800/80 border border-slate-600 text-xs text-slate-100 flex items-center gap-2"
              >
                <span
                  class="text-[11px] text-slate-400 uppercase tracking-[0.18em]"
                  >Total Votes</span
                >
                <span
                  class="px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/50 text-emerald-200 font-semibold"
                >
                  {{ totalVotes }}
                </span>
              </div>
            </div>

            <div
              v-if="topChoice"
              class="mt-1 p-3 rounded-2xl bg-emerald-500/10 border border-emerald-400/60 text-[11px] text-emerald-100"
            >
              <div class="font-semibold text-xs mb-1">
                Current leading budget
              </div>

              <div v-if="topChoice.choices.length === 1">
                <span class="font-semibold">{{
                  topChoice.choices[0].label
                }}</span>
                ({{ topChoice.maxVotes }} vote<span
                  v-if="topChoice.maxVotes !== 1"
                  >s</span
                >)
              </div>
              <div v-else>
                Tie between
                <span class="font-semibold">
                  {{ topChoice.choices.map((c) => c.label).join(", ") }}
                </span>
                ({{ topChoice.maxVotes }} vote<span
                  v-if="topChoice.maxVotes !== 1"
                  >s</span
                >
                each)
              </div>
            </div>

            <div class="mt-3 space-y-2">
              <p class="text-[11px] text-slate-300">Detailed results:</p>

              <div
                v-for="res in budgetResults"
                :key="res.value"
                class="space-y-1"
              >
                <div
                  class="flex items-center justify-between text-[11px] text-slate-300"
                >
                  <span>{{ res.label }}</span>
                  <span>
                    {{ res.votes }} vote<span v-if="res.votes !== 1">s</span> ·
                    {{ res.percent }}%
                  </span>
                </div>
                <div class="h-2 rounded-full bg-slate-800 overflow-hidden">
                  <div
                    class="h-full rounded-full bg-emerald-400"
                    :style="{ width: (res.percent || 0) + '%' }"
                  ></div>
                </div>
              </div>

              <p class="text-[10px] text-slate-400 mt-2 italic">
                Use this as a guide; actual gift prices may still vary.
              </p>
            </div>
          </div>
        </section>

        <!-- Services grid -->
        <section class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="svc in services"
            :key="svc.key"
            class="bg-slate-900/85 border border-slate-700/80 rounded-3xl shadow-xl p-5 flex flex-col justify-between backdrop-blur"
          >
            <div class="space-y-3">
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                  <div
                    class="h-10 w-10 rounded-2xl bg-slate-800 flex items-center justify-center text-2xl shadow-inner"
                  >
                    {{ svc.icon }}
                  </div>
                  <div>
                    <h2 class="text-sm font-semibold text-slate-50">
                      {{ svc.title }}
                    </h2>
                    <p class="text-xs text-amber-200">{{ svc.tagline }}</p>
                  </div>
                </div>

                <span
                  v-if="!svc.available"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] bg-slate-800 border border-slate-600 text-slate-300"
                >
                  Coming soon
                </span>
              </div>

              <p class="text-xs text-slate-300 leading-relaxed">
                {{ svc.description }}
              </p>
            </div>

            <div class="mt-4">
                <Link
                v-if="svc.key === 'small'"
                :href="`/${project.slug}/exchange-gift-ideas/small-gifts`"
                class="inline-flex items-center justify-center w-full px-3 py-2 rounded-xl bg-gradient-to-r from-red-500 via-red-400 to-amber-300
                    text-slate-900 text-xs font-semibold shadow-md hover:from-red-400 hover:via-amber-300 hover:to-yellow-200
                    active:scale-[0.98] transition"
                >
                Open Small Gifts Exchange
                </Link>

                <Link
                v-else-if="svc.key === 'secret-santa'"
                :href="`/${project.slug}/exchange-gift-ideas/secret-santa`"
                class="inline-flex items-center justify-center w-full px-3 py-2 rounded-xl bg-gradient-to-r from-sky-400 to-sky-200
                    text-slate-900 text-xs font-semibold shadow-md hover:from-sky-300 hover:to-sky-100
                    active:scale-[0.98] transition"
                >
                Open “Secret Santa”
                </Link>

                <Link
                v-else-if="svc.key === 'something'"
                :href="`/${project.slug}/exchange-gift-ideas/something`"
                class="inline-flex items-center justify-center w-full px-3 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-300
                    text-slate-900 text-xs font-semibold shadow-md hover:from-emerald-400 hover:to-emerald-200
                    active:scale-[0.98] transition"
                >
                Open “Something...” Exchange
                </Link>

                <!-- 🏆 Awards page link -->
                <Link
                v-else-if="svc.key === 'awards'"
                :href="`/${project.slug}/awards`"
                class="inline-flex items-center justify-center w-full px-3 py-2 rounded-xl
                    bg-gradient-to-r from-yellow-400 via-amber-300 to-emerald-300
                    text-slate-900 text-xs font-semibold shadow-md
                    hover:from-yellow-300 hover:via-amber-200 hover:to-emerald-200
                    active:scale-[0.98] transition"
                >
                Open Awards
                </Link>
                <Link
                v-else-if="svc.key === 'food-planning'"
                :href="`/${project.slug}/food-planning`"
                class="inline-flex items-center justify-center w-full px-3 py-2 rounded-xl
                    bg-gradient-to-r from-amber-300 via-emerald-300 to-sky-200
                    text-slate-900 text-xs font-semibold shadow-md
                    hover:from-yellow-200 hover:via-emerald-200 hover:to-sky-100
                    active:scale-[0.98] transition"
                >
                Open Food Planning
                </Link>
                <Link
                v-else-if="svc.key === 'wishlist'"
                :href="`/${project.slug}/wishlist`"
                class="inline-flex items-center justify-center w-full px-3 py-2 rounded-xl
                    bg-gradient-to-r from-pink-500 via-rose-400 to-amber-300
                    text-slate-900 text-xs font-semibold shadow-md
                    hover:from-pink-400 hover:via-rose-300 hover:to-amber-200
                    active:scale-[0.98] transition"
                >
                Open Wishlists Hub
                </Link>
                <button
                    v-else
                    type="button"
                    class="inline-flex items-center justify-center w-full px-3 py-2 rounded-xl bg-slate-800 text-slate-400 text-xs font-semibold
                    border border-slate-600 cursor-not-allowed"
                    disabled
                >
                    Not yet available
                </button>

            </div>
          </article>
        </section>

        <!-- Back link -->
        <section class="pt-2">
          <Link
            href="/"
            class="inline-flex items-center gap-1 text-[11px] text-slate-300 hover:text-amber-200"
          >
            ⬅ Back to Home
          </Link>
        </section>
      </div>
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

/* Scrollbar */
.custom-scroll::-webkit-scrollbar {
  width: 6px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.5);
  border-radius: 999px;
}
</style>
