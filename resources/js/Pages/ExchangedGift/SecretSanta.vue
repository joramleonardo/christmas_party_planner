<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import ExchangeNav from "@/Components/ExchangeNav.vue";
import confetti from "canvas-confetti";
import { playChime, playCelebration, playDrumroll, playPop } from "@/Utils/soundFx";

const props = defineProps({
  projects: { type: Array, default: () => [] },
  currentProjectId: { type: Number, required: true },
  currentProjectSlug: { type: String, required: true },
  currentPage: { type: String, default: "secret-santa" },
});

// Master participants list
const LIST_TYPE = "classic";

const participants = ref([]);
const assignedGivers = ref([]); // giver IDs that have secret assignment
const secretPairs = ref([]); // full giver -> receiver pairs
const totalBought = ref(0);
const totalAssigned = ref(0);
const isUnwrapping = ref(false);
const isGiftOpened = ref(false);
const togglingBought = ref(false);
const loading = ref(false);

const bulkSending = ref(false);
const bulkMessage = ref("");
const bulkError = ref("");

// Secret Santa card modal
const showCardModal = ref(false);
const selectedCard = ref({
  participant: null,
  receiver: null,
  is_bought: false,
});

// Wishlist-only modal
const showWishlistModal = ref(false);
const wishlistParticipant = ref(null);

// one-time organizer access flag
const hasSecretAccess = ref(false);

// organizer code card state
const organizerCode = ref("");
const organizerCodeError = ref("");
const organizerCodeMessage = ref("");
const organizerVerifying = ref(false);

const participantCount = computed(() => participants.value.length);

const assignedSet = computed(
  () => new Set((assignedGivers.value || []).map(Number)),
);

const allAssigned = computed(() => {
  if (!participants.value.length) return false;
  return participants.value.every((p) => assignedSet.value.has(Number(p.id)));
});

const hasAssignmentFor = (participantId) =>
  assignedSet.value.has(Number(participantId));

// map: giver_id -> { receiver, is_bought }
const secretPairsMap = computed(() => {
  const map = new Map();
  for (const item of secretPairs.value || []) {
    if (!item) continue;
    const gid = Number(item.giver_id);
    if (!gid) continue;
    map.set(gid, {
      receiver: item.receiver || null,
      is_bought: Boolean(item.is_bought),
    });
  }
  return map;
});

const getReceiverFor = (participantId) =>
  secretPairsMap.value.get(Number(participantId))?.receiver || null;

const getIsBoughtFor = (participantId) =>
  Boolean(secretPairsMap.value.get(Number(participantId))?.is_bought);

const fetchData = async () => {
  loading.value = true;
  try {
    const [dataRes, ssRes] = await Promise.all([
      axios.get("/exchange/data", {
        params: { project_id: props.currentProjectId, list_type: LIST_TYPE },
      }),
      axios.get("/exchange/secret-santa", {
        params: { project_id: props.currentProjectId },
      }),
    ]);

    participants.value = dataRes.data.participants || [];
    assignedGivers.value = ssRes.data.assigned_givers || [];
    secretPairs.value = ssRes.data.secret_pairs || [];
    totalBought.value = ssRes.data.total_bought || 0;
    totalAssigned.value = ssRes.data.total_assigned || 0;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);

/* ------------------------------------------------------------------
 * ORGANIZER SECRET CODE (inline card)
 * ------------------------------------------------------------------ */

const verifyOrganizerCode = async () => {
  organizerCodeError.value = "";
  organizerCodeMessage.value = "";

  const code = organizerCode.value.trim();
  if (!code) {
    organizerCodeError.value = "Please enter Santa's Secret Code.";
    return;
  }

  organizerVerifying.value = true;
  try {
    const { data } = await axios.post("/exchange/verify-santa-code", {
      project_id: props.currentProjectId,
      code,
    });

    if (!data.valid) {
      organizerCodeError.value = "Santa's Secret Code is incorrect.";
      return;
    }

    hasSecretAccess.value = true;
    organizerCodeMessage.value =
      "Organizer tools unlocked for this session. You can now generate emails, view wishlists, and open cards.";
  } catch (e) {
    organizerCodeError.value =
      "Unable to verify code right now. Please try again.";
  } finally {
    organizerVerifying.value = false;
  }
};

/* ------------------------------------------------------------------
 * GENERATE & SEND
 * ------------------------------------------------------------------ */

const generateAndSendAll = async () => {
  if (!participants.value.length || bulkSending.value || !hasSecretAccess.value)
    return;

  bulkMessage.value = "";
  bulkError.value = "";
  bulkSending.value = true;
  loading.value = true;

  try {
    // 1) generate secret assignments
    await axios.post("/exchange/secret-santa/generate", {
      project_id: props.currentProjectId,
    });

    // 2) email everyone
    const { data } = await axios.post("/exchange/secret-santa/send-all", {
      project_id: props.currentProjectId,
    });

    bulkMessage.value = data.message || "Secret Santa generated and emailed!";
    await fetchData();
  } catch (e) {
    bulkError.value = e.response?.data?.message || "Something went wrong.";
  } finally {
    bulkSending.value = false;
    loading.value = false;
  }
};

/* ------------------------------------------------------------------
 * CARD MODALS
 * ------------------------------------------------------------------ */

const revealRecipient = ref(false);

const openCard = (participant) => {
  const pairInfo = secretPairsMap.value.get(Number(participant.id));
  selectedCard.value = {
    participant,
    receiver: pairInfo?.receiver || null,
    is_bought: Boolean(pairInfo?.is_bought),
  };
  revealRecipient.value = false; // 🔐 always hidden at first
  isUnwrapping.value = false;
  isGiftOpened.value = false;
  showCardModal.value = true;
};

const closeCard = () => {
  showCardModal.value = false;
  selectedCard.value = {
    participant: null,
    receiver: null,
    is_bought: false,
  };
  revealRecipient.value = false;
  isUnwrapping.value = false;
  isGiftOpened.value = false;
};

const handleUnwrap = () => {
  if (isUnwrapping.value || isGiftOpened.value) return;
  isUnwrapping.value = true;
  playDrumroll();
  setTimeout(() => {
    isUnwrapping.value = false;
    isGiftOpened.value = true;
    revealRecipient.value = true;
    playCelebration();
    confetti({
      particleCount: 80,
      spread: 70,
      origin: { y: 0.6 },
    });
  }, 1000);
};

const handleToggleBought = async () => {
  if (!selectedCard.value.participant || togglingBought.value) return;
  togglingBought.value = true;
  try {
    const { data } = await axios.post("/exchange/secret-santa/toggle-bought", {
      project_id: props.currentProjectId,
      giver_id: selectedCard.value.participant.id,
    });
    selectedCard.value.is_bought = data.is_bought;
    playPop();
    const pair = secretPairs.value.find(
      (p) => Number(p.giver_id) === Number(selectedCard.value.participant.id),
    );
    if (pair) {
      pair.is_bought = data.is_bought;
    }
    totalBought.value = secretPairs.value.filter((p) => p.is_bought).length;
  } catch (e) {
    alert(e?.response?.data?.message || "Failed to update gift status.");
  } finally {
    togglingBought.value = false;
  }
};

const openWishlistModal = (participant) => {
  if (!hasSecretAccess.value) return;
  wishlistParticipant.value = participant;
  showWishlistModal.value = true;
};

const closeWishlistModal = () => {
  showWishlistModal.value = false;
  wishlistParticipant.value = null;
};

const asUrl = (value) => {
  if (!value) return null;
  const trimmed = String(value).trim();
  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    return trimmed;
  }
  return null;
};

/* ------------------------------------------------------------------
 * Snowflakes
 * ------------------------------------------------------------------ */

const SNOWFLAKE_COUNT = 70;
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
});

const wishlistCount = (p) => {
  if (!p) return 0;
  let count = 0;

  if (p.wishlist_1 || p.wishlist_1_description) count++;
  if (p.wishlist_2 || p.wishlist_2_description) count++;
  if (p.wishlist_3 || p.wishlist_3_description) count++;

  return count;
};

const wishlistItems = (p) => {
  if (!p) return [];

  const items = [];

  if (p.wishlist_1 || p.wishlist_1_description) {
    items.push({
      label: p.wishlist_1,
      description: p.wishlist_1_description,
    });
  }

  if (p.wishlist_2 || p.wishlist_2_description) {
    items.push({
      label: p.wishlist_2,
      description: p.wishlist_2_description,
    });
  }

  if (p.wishlist_3 || p.wishlist_3_description) {
    items.push({
      label: p.wishlist_3,
      description: p.wishlist_3_description,
    });
  }

  return items;
};

/* ------------------------------------------------------------------
 * Wishlist Edit via Unique Code
 * ------------------------------------------------------------------ */

const showWishlistEditModal = ref(false);
const wishlistEditParticipant = ref(null);
const wishlistEditCode = ref("");
const wishlistEditStep = ref("verify"); // 'verify' | 'form'
const wishlistEditError = ref("");
const wishlistEditSuccess = ref("");
const wishlistEditLoading = ref(false);

const wishlistEditForm = ref({
  wishlist_1: "",
  wishlist_1_description: "",
  wishlist_2: "",
  wishlist_2_description: "",
  wishlist_3: "",
  wishlist_3_description: "",
});

const openWishlistEdit = (participant) => {
  wishlistEditParticipant.value = participant;
  showWishlistEditModal.value = true;

  wishlistEditCode.value = "";
  wishlistEditStep.value = "verify";
  wishlistEditError.value = "";
  wishlistEditSuccess.value = "";

  wishlistEditForm.value = {
    wishlist_1: "",
    wishlist_1_description: "",
    wishlist_2: "",
    wishlist_2_description: "",
    wishlist_3: "",
    wishlist_3_description: "",
  };
};

const closeWishlistEditModal = () => {
  showWishlistEditModal.value = false;
  wishlistEditParticipant.value = null;
  wishlistEditCode.value = "";
  wishlistEditError.value = "";
  wishlistEditSuccess.value = "";
  wishlistEditStep.value = "verify";
};

const verifyWishlistCode = async () => {
  wishlistEditError.value = "";
  wishlistEditSuccess.value = "";

  const code = wishlistEditCode.value.trim();
  if (!code) {
    wishlistEditError.value = "Please enter your unique code.";
    return;
  }

  wishlistEditLoading.value = true;

  try {
    const { data } = await axios.post("/exchange/wishlist/lookup-by-code", {
      project_id: props.currentProjectId,
      code,
    });

    // Optional safety check: code must belong to the row they clicked
    if (
      wishlistEditParticipant.value &&
      data.participant &&
      data.participant.id !== wishlistEditParticipant.value.id
    ) {
      wishlistEditError.value =
        "This code belongs to a different participant. Please double-check.";
      return;
    }

    wishlistEditForm.value = {
      wishlist_1: data.participant.wishlist_1 || "",
      wishlist_1_description: data.participant.wishlist_1_description || "",
      wishlist_2: data.participant.wishlist_2 || "",
      wishlist_2_description: data.participant.wishlist_2_description || "",
      wishlist_3: data.participant.wishlist_3 || "",
      wishlist_3_description: data.participant.wishlist_3_description || "",
    };

    wishlistEditStep.value = "form";
  } catch (e) {
    wishlistEditError.value =
      e?.response?.data?.message || "Invalid code. Please check and try again.";
  } finally {
    wishlistEditLoading.value = false;
  }
};

const saveWishlistViaCode = async () => {
  wishlistEditError.value = "";
  wishlistEditSuccess.value = "";

  const code = wishlistEditCode.value.trim();
  if (!code) {
    wishlistEditError.value = "Unique code missing. Please verify again.";
    wishlistEditStep.value = "verify";
    return;
  }

  wishlistEditLoading.value = true;

  try {
    const payload = {
      project_id: props.currentProjectId,
      code,
      ...wishlistEditForm.value,
    };

    const { data } = await axios.post(
      "/exchange/wishlist/update-by-code",
      payload,
    );

    wishlistEditSuccess.value =
      data.message || "Wishlist updated successfully! 🎁";
    await fetchData();
  } catch (e) {
    wishlistEditError.value =
      e?.response?.data?.message ||
      "Unable to save wishlist right now. Please try again.";
  } finally {
    wishlistEditLoading.value = false;
  }
  showWishlistEditModal.value = false;
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
          animationDuration:
            flake.duration + 's, ' + flake.duration / 2 + 's',
          animationDelay: flake.delay + 's, ' + flake.delay + 's',
          opacity: flake.opacity,
        }"
      />
    </div>

    <!-- Navigation -->
    <ExchangeNav
      :projectSlug="currentProjectSlug"
      :projectName="(props.projects.find((p) => p.id === props.currentProjectId) || {}).name || 'Secret Santa'"
      currentPage="secret-santa"
    />

    <main class="relative z-10 max-w-5xl mx-auto px-4 py-6 space-y-6">
      <!-- Top grid: How it works + Organizer code -->
      <section
        class="grid gap-4 lg:gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] items-start"
      >
        <!-- How it works -->
        <div
          class="bg-slate-900/80 backdrop-blur rounded-3xl shadow-xl border border-red-500/40 p-5"
        >
          <h2 class="font-semibold text-lg flex items-center gap-2 mb-2">
            <span
              class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600/90 text-white text-sm"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                fill="currentColor"
                class="bi bi-patch-question-fill"
                viewBox="0 0 16 16"
              >
                <path
                  d="M5.933.87a2.89 2.89 0 0 1 4.134 0l.622.638.89-.011a2.89 2.89 0 0 1 2.924 2.924l-.01.89.636.622a2.89 2.89 0 0 1 0 4.134l-.637.622.011.89a2.89 2.89 0 0 1-2.924 2.924l-.89-.01-.622.636a2.89 2.89 0 0 1-4.134 0l-.622-.637-.89.011a2.89 2.89 0 0 1-2.924-2.924l.01-.89-.636-.622a2.89 2.89 0 0 1 0-4.134l.637-.622-.011-.89a2.89 2.89 0 0 1 2.924-2.924l.89.01zM7.002 11a1 1 0 1 0 2 0 1 1 0 0 0-2 0m1.602-2.027c.04-.534.198-.815.846-1.26.674-.475 1.05-1.09 1.05-1.986 0-1.325-.92-2.227-2.262-2.227-1.02 0-1.792.492-2.1 1.29A1.7 1.7 0 0 0 6 5.48c0 .393.203.64.545.64.272 0 .455-.147.564-.51.158-.592.525-.915 1.074-.915.61 0 1.03.446 1.03 1.084 0 .563-.208.885-.822 1.325-.619.433-.926.914-.926 1.64v.111c0 .428.208.745.585.745.336 0 .504-.24.554-.627"
                />
              </svg>
            </span>
            How it works
          </h2>
          <ul class="text-sm space-y-2 list-disc ml-4 text-slate-100">
            <li>
              Participants are managed in the Group Services page.
            </li>
            <li>Each participant gets exactly <strong>one</strong> recipient.</li>
            <li>No self-assignments.</li>
            <li>
              This page lets the organizer generate pairings,
              email everyone, view each participant’s wishlist, and open their
              Secret Santa card.
            </li>
            <li>
              Each participant can update their wishlist using their unique code too.
            </li>
          </ul>
        </div>

        <!-- Organizer Secret Code card -->
        <div
          class="bg-slate-900/85 border border-amber-500/50 rounded-3xl shadow-xl p-5 space-y-3"
        >
          <h2 class="text-sm font-semibold flex items-center gap-2">
            <span
              class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-400 text-slate-900 text-sm"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                fill="currentColor"
                class="bi bi-lock-fill"
                viewBox="0 0 16 16"
              >
                <path
                  fill-rule="evenodd"
                  d="M8 0a4 4 0 0 1 4 4v2.05a2.5 2.5 0 0 1 2 2.45v5a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 2 13.5v-5a2.5 2.5 0 0 1 2-2.45V4a4 4 0 0 1 4-4m0 1a3 3 0 0 0-3 3v2h6V4a3 3 0 0 0-3-3"
                />
              </svg>
            </span>
            Organizer Secret Code
          </h2>

          <p class="text-[12px] text-slate-200">
            Use the
            <span class="font-semibold text-amber-200">Santa Secret Code</span>
            that was emailed to the organizer. Once verified, you can:
          </p>

          <ul class="text-[12px] text-slate-200 list-disc ml-4 space-y-1">
            <li>Generate Secret Santa pairs and email everyone</li>
            <li>Open the wishlist of any participant</li>
            <li>Open Secret Santa cards after generation</li>
          </ul>

          <div class="space-y-2 pt-2">
            <label class="text-[11px] text-slate-400"
              >Santa's Secret Code</label
            >
            <input
              v-model="organizerCode"
              type="password"
              placeholder="••••••••"
              class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
                     focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                     placeholder:text-slate-500"
              @keydown.enter.prevent="verifyOrganizerCode"
            />
          </div>

          <button
            type="button"
            class="px-3 py-2 rounded-xl bg-gradient-to-r from-amber-400 to-emerald-300 text-slate-900 text-xs font-semibold
                   hover:from-amber-300 hover:to-emerald-200 active:scale-[0.98] transition
                   disabled:opacity-40 disabled:cursor-not-allowed"
            @click="verifyOrganizerCode"
            :disabled="organizerVerifying"
          >
            <span v-if="!organizerVerifying">Verify Organizer Code</span>
            <span v-else>Checking…</span>
          </button>

          <p
            v-if="organizerCodeError"
            class="text-[11px] bg-red-500/20 text-red-200 border border-red-400/60 rounded-xl px-3 py-2"
          >
            ⚠️ {{ organizerCodeError }}
          </p>

          <p
            v-if="organizerCodeMessage"
            class="text-[11px] bg-emerald-500/15 text-emerald-100 border border-emerald-400/60 rounded-xl px-3 py-2"
          >
            {{ organizerCodeMessage }}
          </p>
        </div>
      </section>

      <!-- Main card -->
      <section
        class="bg-slate-900/80 backdrop-blur rounded-3xl shadow-xl border border-red-500/40 p-5 space-y-4"
      >
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <span
              class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-sky-300 text-slate-900 text-sm"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                fill="currentColor"
                class="bi bi-gift-fill"
                viewBox="0 0 16 16"
              >
                <path
                  d="M3 2.5a2.5 2.5 0 0 1 5 0 2.5 2.5 0 0 1 5 0v.006c0 .07 0 .27-.038.494H15a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h2.038A3 3 0 0 1 3 2.506zm1.068.5H7v-.5a1.5 1.5 0 1 0-3 0c0 .085.002.274.045.43zM9 3h2.932l.023-.07c.043-.156.045-.345.045-.43a1.5 1.5 0 0 0-3 0zm6 4v7.5a1.5 1.5 0 0 1-1.5 1.5H9V7zM2.5 16A1.5 1.5 0 0 1 1 14.5V7h6v9z"
                />
              </svg>
            </span>
            <h2 class="font-semibold text-lg">Secret Santa Status</h2>
          </div>

          <div class="flex items-center gap-2">
            <div
              class="flex items-center gap-2 bg-slate-800/80 rounded-2xl px-3 py-2 border border-slate-600 text-sm"
            >
              <div class="flex flex-col text-right">
                <span
                  class="text-[11px] text-slate-400 uppercase tracking-[0.18em]"
                  >Participants</span
                >
                <span class="font-semibold text-slate-50">
                  {{ participantCount }}
                </span>
              </div>
            </div>

            <div
              v-if="assignedGivers.length"
              class="flex items-center gap-2 bg-emerald-950/60 rounded-2xl px-3 py-2 border border-emerald-500/40 text-sm"
            >
              <div class="flex flex-col text-right">
                <span
                  class="text-[11px] text-emerald-400 uppercase tracking-[0.18em]"
                  >Gifts Bought</span
                >
                <span class="font-semibold text-emerald-200">
                  {{ totalBought }} / {{ assignedGivers.length }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <p class="text-xs text-slate-300">
          Generate 1-to-1 Secret Santa and email everyone their recipient
          (organizer-only).
        </p>

        <div class="space-y-1">
          <button
            type="button"
            class="self-start px-4 py-2.5 rounded-xl bg-gradient-to-r from-sky-400 to-sky-200 text-slate-900 text-sm font-semibold shadow-sm
                   hover:from-sky-300 hover:to-sky-100 active:scale-[0.98] transition disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="
              !participantCount || loading || bulkSending || !hasSecretAccess
            "
            @click="generateAndSendAll"
          >
            <span v-if="!bulkSending">
              Generate Secret Santa &amp; Email Everyone
            </span>
            <span v-else>Generating &amp; emailing…</span>
          </button>

          <p v-if="!hasSecretAccess" class="text-[11px] text-amber-200">
            🔒 Enter and verify the Secret Code above to enable this button,
            wishlist view, and Secret Santa cards.
          </p>
        </div>

        <p v-if="bulkMessage" class="text-[11px] text-emerald-300 mt-1">
          {{ bulkMessage }}
        </p>
        <p v-if="bulkError" class="text-[11px] text-red-300 mt-1">
          {{ bulkError }}
        </p>

        <!-- Status table -->
        <div
          class="overflow-x-auto rounded-2xl border border-slate-700 shadow-sm bg-slate-950/60"
        >
          <table
            v-if="participants.length"
            class="min-w-full text-xs md:text-sm"
          >
            <thead class="bg-slate-900/80">
              <tr>
                <th
                  class="px-3 py-2 text-left font-semibold border-b border-slate-700"
                >
                  Participant
                </th>
                <th
                  class="px-3 py-2 text-left font-semibold border-b border-slate-700"
                >
                  Update Wishlist
                </th>
                <th
                  class="px-3 py-2 text-left font-semibold border-b border-slate-700"
                >
                  Wishlist
                </th>
                <th
                  class="px-3 py-2 text-left font-semibold border-b border-slate-700"
                >
                  View Card
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="p in participants"
                :key="p.id"
                class="odd:bg-slate-900/80 even:bg-slate-900/60"
              >
                <td
                  class="px-3 py-2 border-b border-slate-800 font-semibold text-slate-50"
                >
                  <div class="flex items-center gap-2 flex-wrap">
                    <span>{{ p.name }}</span>
                    <span
                      v-if="hasAssignmentFor(p.id) && getIsBoughtFor(p.id)"
                      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-normal"
                      title="Gift already bought"
                    >
                      🎁 Bought
                    </span>
                  </div>
                </td>

                <!-- Update Wishlist column -->
                <td class="px-3 py-2 border-b border-slate-800">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-[11px] font-semibold
                           border shadow-sm
                           bg-emerald-500/15 border-emerald-400/70 text-emerald-200
                           hover:bg-emerald-400/20 hover:border-emerald-300/80
                           disabled:opacity-40 disabled:cursor-not-allowed"
                    @click="openWishlistEdit(p)"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="14"
                      height="14"
                      fill="currentColor"
                      class="bi bi-pencil-square"
                      viewBox="0 0 16 16"
                    >
                      <path
                        d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293z"
                      />
                      <path d="M11.5 2.5 4 10v2h2l7.5-7.5-2-2z" />
                      <path
                        fill-rule="evenodd"
                        d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"
                      />
                    </svg>
                    Update
                  </button>
                </td>

                <!-- Wishlist column -->
                <td class="px-3 py-2 border-b border-slate-800">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-[11px] font-semibold
                           border shadow-sm
                           bg-violet-500/15 border-violet-400/70 text-violet-200
                           hover:bg-violet-400/20 hover:border-violet-300/80
                           disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="!hasSecretAccess"
                    @click="openWishlistModal(p)"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      fill="currentColor"
                      class="bi bi-list-ol"
                      viewBox="0 0 16 16"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5"
                      />
                      <path
                        d="M1.713 11.865v-.474H2c.217 0 .363-.137.363-.317 0-.185-.158-.31-.361-.31-.223 0-.367.152-.373.31h-.59c.016-.467.373-.787.986-.787.588-.002.954.291.957.703a.595.595 0 0 1-.492.594v.033a.615.615 0 0 1 .569.631c.003.533-.502.8-1.051.8-.656 0-1-.37-1.008-.794h.582c.008.178.186.306.422.309.254 0 .424-.145.422-.35-.002-.195-.155-.348-.414-.348h-.3zm-.004-4.699h-.604v-.035c0-.408.295-.844.958-.844.583 0 .96.326.96.756 0 .389-.257.617-.476.848l-.537.572v.03h1.054V9H1.143v-.395l.957-.99c.138-.142.293-.304.293-.508 0-.18-.147-.32-.342-.32a.33.33 0 0 0-.342.338zM2.564 5h-.635V2.924h-.031l-.598.42v-.567l.629-.443h.635z"
                      />
                    </svg>
                    Wishlist
                  </button>
                </td>

                <!-- View Card column -->
                <td class="px-3 py-2 border-b border-slate-800">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-[11px] font-semibold
                           border shadow-sm
                           disabled:opacity-40 disabled:cursor-not-allowed
                           bg-sky-500/15 border-sky-400/70 text-sky-200
                           hover:bg-sky-400/20 hover:border-sky-300/80"
                    :disabled="!hasAssignmentFor(p.id) || !hasSecretAccess"
                    @click="openCard(p)"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      fill="currentColor"
                      class="bi bi-eye-fill"
                      viewBox="0 0 16 16"
                    >
                      <path
                        d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"
                      />
                      <path
                        d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"
                      />
                    </svg>
                    View Card
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-else class="text-[11px] text-slate-300 italic px-4 py-3">
            No participants found for this project yet. Please add participants
            from Group Services.
          </p>

          <p
            v-if="allAssigned && participants.length"
            class="text-[11px] text-emerald-300 px-4 py-3"
          >
            Secret Santa is generated. Recipients are ready – use “View Card” to
            see each pairing.

          </p>
        </div>
      </section>
    </main>

    <!-- Wishlist-only Modal -->
    <div
      v-if="showWishlistModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4"
    >
      <div
        class="bg-slate-950/95 border border-violet-400/60 rounded-3xl shadow-2xl w-full max-w-md p-5 space-y-4 relative"
      >
        <button
          type="button"
          class="absolute top-3 right-3 text-slate-400 hover:text-slate-100 text-sm"
          @click="closeWishlistModal"
        >
          ✕
        </button>

        <div class="flex items-center gap-3">
          <div
            class="h-9 w-9 rounded-2xl bg-violet-300 flex items-center justify-center text-xl text-slate-900 shadow-inner"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              fill="currentColor"
              class="bi bi-list-ol"
              viewBox="0 0 16 16"
            >
              <path
                fill-rule="evenodd"
                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5"
              />
              <path
                d="M1.713 11.865v-.474H2c.217 0 .363-.137.363-.317 0-.185-.158-.31-.361-.31-.223 0-.367.152-.373.31h-.59c.016-.467.373-.787.986-.787.588-.002.954.291.957.703a.595.595 0 0 1-.492.594v.033a.615.615 0 0 1 .569.631c.003.533-.502.8-1.051.8-.656 0-1-.37-1.008-.794h.582c.008.178.186.306.422.309.254 0 .424-.145.422-.35-.002-.195-.155-.348-.414-.348h-.3zm-.004-4.699h-.604v-.035c0-.408.295-.844.958-.844.583 0 .96.326.96.756 0 .389-.257.617-.476.848l-.537.572v.03h1.054V9H1.143v-.395l.957-.99c.138-.142.293-.304.293-.508 0-.18-.147-.32-.342-.32a.33.33 0 0 0-.342.338zM2.564 5h-.635V2.924h-.031l-.598.42v-.567l.629-.443h.635z"
              />
            </svg>
          </div>
          <div>
            <h3 class="text-base font-semibold text-violet-100">
              <span class="text-yellow-400 font-bold">
                {{ wishlistParticipant.name }}
              </span>
              's Wishlist
            </h3>
            <p class="text-[11px] text-slate-300">
              Quick view of this participant’s wishlist items.
            </p>
          </div>
        </div>

        <div v-if="wishlistParticipant" class="space-y-3">
          <div class="mt-1 space-y-1">
            <p
              class="text-[11px] text-slate-300 font-semibold flex items-center gap-2"
            >
              Wishlist Items
              <span
                v-if="wishlistCount(wishlistParticipant) > 0"
                class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px]
                       bg-violet-500/20 text-violet-200 border border-violet-400/60"
              >
                {{ wishlistCount(wishlistParticipant) }}
              </span>
            </p>

            <!-- Numbered wishlist items -->
            <ol
              v-if="wishlistItems(wishlistParticipant).length"
              class="space-y-2 text-xs text-slate-200 break-words whitespace-pre-line list-decimal list-inside"
            >
              <li
                v-for="(item, idx) in wishlistItems(wishlistParticipant)"
                :key="idx"
              >
                <!-- Title -->
                <div v-if="item.label">
                  <template v-if="asUrl(item.label)">
                    <a
                      :href="asUrl(item.label)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="underline underline-offset-2 decoration-violet-400 hover:text-violet-300"
                    >
                      Link here
                    </a>
                  </template>
                  <template v-else>
                    <strong>{{ item.label }}</strong>
                  </template>
                </div>

                <!-- Description or link -->
                <div
                  v-if="item.description"
                  class="text-[11px] text-slate-400 mt-0.5"
                >
                  <template v-if="asUrl(item.description)">
                    <a
                      :href="asUrl(item.description)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="underline underline-offset-2 decoration-violet-400 hover:text-violet-300"
                    >
                      Link here
                    </a>
                  </template>
                  <template v-else>
                    {{ item.description }}
                  </template>
                </div>
              </li>
            </ol>

            <!-- No wishlist fallback -->
            <p v-else class="text-[11px] text-slate-500 italic">
              No wishlist set.
            </p>
          </div>
        </div>

        <div class="flex justify-end pt-1">
          <button
            type="button"
            class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-200 hover:text-white hover:bg-slate-800/80"
            @click="closeWishlistModal"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- 🔐 Participant Wishlist Edit via Unique Code -->
    <div
      v-if="showWishlistEditModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4"
    >
      <div
        class="bg-slate-950/95 border border-emerald-400/60 rounded-3xl shadow-2xl w-full max-w-md p-5 space-y-4 relative"
      >
        <button
          type="button"
          class="absolute top-3 right-3 text-slate-400 hover:text-slate-100 text-sm"
          @click="closeWishlistEditModal"
        >
          ✕
        </button>

        <div class="flex items-center gap-3">
          <div
            class="h-9 w-9 rounded-2xl bg-emerald-300 flex items-center justify-center text-xl text-slate-900 shadow-inner"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-gift-fill" viewBox="0 0 16 16">
            <path d="M3 2.5a2.5 2.5 0 0 1 5 0 2.5 2.5 0 0 1 5 0v.006c0 .07 0 .27-.038.494H15a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h2.038A3 3 0 0 1 3 2.506zm1.068.5H7v-.5a1.5 1.5 0 1 0-3 0c0 .085.002.274.045.43zM9 3h2.932l.023-.07c.043-.156.045-.345.045-.43a1.5 1.5 0 0 0-3 0zm6 4v7.5a1.5 1.5 0 0 1-1.5 1.5H9V7zM2.5 16A1.5 1.5 0 0 1 1 14.5V7h6v9z"/>
            </svg>
          </div>
          <div>
            <h3 class="text-base font-semibold text-emerald-100">
              Update Wishlist
            </h3>
            <p class="text-[11px] text-slate-300">
              Enter your unique code to securely update your wishlist items.
            </p>
            <p
              class="text-[11px] text-slate-400 mt-0.5"
              v-if="wishlistEditParticipant"
            >
              Participant:
              <span class="font-semibold text-slate-100">
                {{ wishlistEditParticipant.name }}
              </span>
            </p>
          </div>
        </div>

        <!-- Step 1: Verify unique code -->
        <div v-if="wishlistEditStep === 'verify'" class="space-y-3 pt-1">
          <div class="space-y-1">
            <label class="text-[11px] text-slate-400"
              >Unique Code (from your email)</label
            >
            <input
              v-model="wishlistEditCode"
              type="text"
              placeholder="e.g. ABCD1234"
              class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
                     focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400
                     placeholder:text-slate-500 uppercase"
              @keydown.enter.prevent="verifyWishlistCode"
            />
          </div>

          <button
            type="button"
            class="w-full px-3 py-2.5 rounded-xl bg-emerald-500 text-slate-900 text-xs font-semibold
                   hover:bg-emerald-400 active:scale-[0.98] transition
                   disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="wishlistEditLoading"
            @click="verifyWishlistCode"
          >
            <span v-if="!wishlistEditLoading">Verify Code</span>
            <span v-else>Verifying…</span>
          </button>
        </div>

        <!-- Step 2: Show form once verified -->
        <div v-else class="space-y-3 pt-1">
          <div class="flex items-center justify-between gap-2">
            <p class="text-[11px] text-emerald-200">
              ✅ Code verified. You can now edit your wishlist.
            </p>
            <span
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px]
                     border border-emerald-400/70 bg-emerald-500/10 text-emerald-100"
            >
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
              Verified
            </span>
          </div>

          <div class="space-y-2 max-h-[320px] overflow-y-auto pr-1">
            <!-- Wishlist 1 -->
            <div class="space-y-1">
              <label class="text-[11px] text-slate-300">Wishlist 1</label>
              <input
                v-model="wishlistEditForm.wishlist_1"
                type="text"
                class="w-full px-3 py-2 rounded-xl border border-slate-600 bg-slate-800/80 text-xs text-slate-50
                       focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400"
              />
              <textarea
                v-model="wishlistEditForm.wishlist_1_description"
                rows="2"
                class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-700 bg-slate-900/80 text-[11px] text-slate-100
                       focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 resize-none"
                placeholder="Description or link (optional)"
              ></textarea>
            </div>

            <!-- Wishlist 2 -->
            <div class="space-y-1">
              <label class="text-[11px] text-slate-300">Wishlist 2</label>
              <input
                v-model="wishlistEditForm.wishlist_2"
                type="text"
                class="w-full px-3 py-2 rounded-xl border border-slate-600 bg-slate-800/80 text-xs text-slate-50
                       focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400"
              />
              <textarea
                v-model="wishlistEditForm.wishlist_2_description"
                rows="2"
                class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-700 bg-slate-900/80 text-[11px] text-slate-100
                       focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 resize-none"
                placeholder="Description or link (optional)"
              ></textarea>
            </div>

            <!-- Wishlist 3 -->
            <div class="space-y-1">
              <label class="text-[11px] text-slate-300">Wishlist 3</label>
              <input
                v-model="wishlistEditForm.wishlist_3"
                type="text"
                class="w-full px-3 py-2 rounded-xl border border-slate-600 bg-slate-800/80 text-xs text-slate-50
                       focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400"
              />
              <textarea
                v-model="wishlistEditForm.wishlist_3_description"
                rows="2"
                class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-700 bg-slate-900/80 text-[11px] text-slate-100
                       focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 resize-none"
                placeholder="Description or link (optional)"
              ></textarea>
            </div>
          </div>

          <button
            type="button"
            class="w-full px-3 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-sky-300 text-slate-900 text-xs font-semibold
                   hover:from-emerald-300 hover:to-sky-200 active:scale-[0.98] transition
                   disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="wishlistEditLoading"
            @click="saveWishlistViaCode"
          >
            <span v-if="!wishlistEditLoading">Save Wishlist</span>
            <span v-else>Saving…</span>
          </button>
        </div>

        <!-- Messages -->
        <p
          v-if="wishlistEditError"
          class="text-[11px] bg-red-500/15 text-red-200 border border-red-500/50 rounded-xl px-3 py-2"
        >
          ⚠️ {{ wishlistEditError }}
        </p>

        <p
          v-if="wishlistEditSuccess && wishlistEditStep === 'form'"
          class="text-[11px] bg-emerald-500/15 text-emerald-100 border border-emerald-500/50 rounded-xl px-3 py-2"
        >
          {{ wishlistEditSuccess }}
        </p>

        <div class="flex justify-end pt-1">
          <button
            type="button"
            class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-200 hover:text-white hover:bg-slate-800/80"
            @click="closeWishlistEditModal"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Secret Santa Card Modal -->
    <div
      v-if="showCardModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4"
    >
      <div
        class="bg-slate-950/95 border border-sky-400/60 rounded-3xl shadow-2xl w-full max-w-lg p-5 space-y-4 relative"
      >
        <button
          type="button"
          class="absolute top-3 right-3 text-slate-400 hover:text-slate-100 text-sm"
          @click="closeCard"
        >
          ✕
        </button>

        <div class="flex items-center gap-3">
          <div
            class="h-9 w-9 rounded-2xl bg-sky-300 flex items-center justify-center text-xl text-slate-900 shadow-inner"
          >
            🎴
          </div>
          <div>
            <h3 class="text-base font-semibold text-sky-100">
              Secret Santa Card
            </h3>
            <p class="text-[11px] text-slate-300">
              Internal organizer view of this participant’s pairing and
              wishlists.
            </p>
          </div>
        </div>

        <div class="space-y-4">
          <!-- Participant card -->
          <div
            class="rounded-2xl border border-slate-700 bg-slate-900/70 p-3 space-y-2"
          >
            <h4
              class="text-xs font-semibold text-slate-200 flex items-center gap-1"
            >
              <span class="text-sky-300">🎁</span>
              Participant
            </h4>

            <p class="text-sm font-semibold text-slate-50">
              {{ selectedCard.participant?.name || "—" }}
            </p>
            <p class="text-xs text-slate-300">
              Codename:
              <span class="font-mono text-sky-200">
                {{ selectedCard.participant?.codename || "—" }}
              </span>
            </p>

            <div class="mt-2 space-y-1">
              <p class="text-[11px] text-slate-300 font-semibold">
                Participant Wishlist
              </p>

              <ul
                class="space-y-2 text-xs text-slate-200 break-words whitespace-pre-line"
              >
                <li
                  v-if="
                    selectedCard.participant?.wishlist_1 ||
                    selectedCard.participant?.wishlist_1_description
                  "
                >
                  <div v-if="selectedCard.participant?.wishlist_1">
                    <strong>{{ selectedCard.participant.wishlist_1 }}</strong>
                  </div>
                  <div
                    v-if="selectedCard.participant?.wishlist_1_description"
                    class="text-[11px] text-slate-400 mt-0.5"
                  >
                    <template
                      v-if="
                        asUrl(
                          selectedCard.participant.wishlist_1_description,
                        )
                      "
                    >
                      <a
                        :href="
                          asUrl(
                            selectedCard.participant.wishlist_1_description,
                          )
                        "
                        target="_blank"
                        rel="noopener noreferrer"
                        class="underline underline-offset-2 decoration-sky-400 hover:text-sky-300"
                      >
                        Link here
                      </a>
                    </template>
                    <template v-else>
                      {{ selectedCard.participant.wishlist_1_description }}
                    </template>
                  </div>
                </li>

                <li
                  v-if="
                    selectedCard.participant?.wishlist_2 ||
                    selectedCard.participant?.wishlist_2_description
                  "
                >
                  <div v-if="selectedCard.participant?.wishlist_2">
                    <strong>{{ selectedCard.participant.wishlist_2 }}</strong>
                  </div>
                  <div
                    v-if="selectedCard.participant?.wishlist_2_description"
                    class="text-[11px] text-slate-400 mt-0.5"
                  >
                    <template
                      v-if="
                        asUrl(
                          selectedCard.participant.wishlist_2_description,
                        )
                      "
                    >
                      <a
                        :href="
                          asUrl(
                            selectedCard.participant.wishlist_2_description,
                          )
                        "
                        target="_blank"
                        rel="noopener noreferrer"
                        class="underline underline-offset-2 decoration-sky-400 hover:text-sky-300"
                      >
                        Link here
                      </a>
                    </template>
                    <template v-else>
                      {{ selectedCard.participant.wishlist_2_description }}
                    </template>
                  </div>
                </li>

                <li
                  v-if="
                    selectedCard.participant?.wishlist_3 ||
                    selectedCard.participant?.wishlist_3_description
                  "
                >
                  <div v-if="selectedCard.participant?.wishlist_3">
                    <strong>{{ selectedCard.participant.wishlist_3 }}</strong>
                  </div>
                  <div
                    v-if="selectedCard.participant?.wishlist_3_description"
                    class="text-[11px] text-slate-400 mt-0.5"
                  >
                    <template
                      v-if="
                        asUrl(
                          selectedCard.participant.wishlist_3_description,
                        )
                      "
                    >
                      <a
                        :href="
                          asUrl(
                            selectedCard.participant.wishlist_3_description,
                          )
                        "
                        target="_blank"
                        rel="noopener noreferrer"
                        class="underline underline-offset-2 decoration-sky-400 hover:text-sky-300"
                      >
                        Link here
                      </a>
                    </template>
                    <template v-else>
                      {{ selectedCard.participant.wishlist_3_description }}
                    </template>
                  </div>
                </li>

                <li
                  v-if="
                    !(
                      selectedCard.participant?.wishlist_1 ||
                      selectedCard.participant?.wishlist_1_description ||
                      selectedCard.participant?.wishlist_2 ||
                      selectedCard.participant?.wishlist_2_description ||
                      selectedCard.participant?.wishlist_3 ||
                      selectedCard.participant?.wishlist_3_description
                    )
                  "
                  class="text-[11px] text-slate-500 italic"
                >
                  No wishlist set.
                </li>
              </ul>
            </div>
          </div>

          <!-- Recipient card (hidden first) -->
          <div
            class="rounded-2xl border border-slate-700 bg-slate-900/70 p-3 space-y-2"
          >
            <div class="flex items-center justify-between gap-2">
              <h4
                class="text-xs font-semibold text-slate-200 flex items-center gap-1"
              >
                <span class="text-emerald-300">🎄</span>
                Assigned Recipient
              </h4>

              <span
                v-if="selectedCard.receiver"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] border"
                :class="
                  revealRecipient
                    ? 'border-emerald-400/70 bg-emerald-500/10 text-emerald-100'
                    : 'border-slate-500/70 bg-slate-800/80 text-slate-200'
                "
              >
                <span
                  class="h-1.5 w-1.5 rounded-full"
                  :class="revealRecipient ? 'bg-emerald-300' : 'bg-slate-400'"
                />
                {{ revealRecipient ? "Revealed" : "Hidden" }}
              </span>
            </div>

            <!-- No assignment at all -->
            <template v-if="!selectedCard.receiver">
              <p class="text-xs text-slate-400 italic">
                No Secret Santa assignment found yet for this participant.
              </p>
            </template>

            <!-- With assignment, but still hidden (Interactive 3D Gift Box) -->
            <template v-else-if="!revealRecipient">
              <div
                class="flex flex-col items-center justify-center py-6 px-4 text-center rounded-2xl bg-gradient-to-b from-slate-900 to-slate-950/80 border border-slate-700/60 my-2"
              >
                <div
                  class="relative cursor-pointer select-none transition-all duration-300 p-2"
                  :class="{
                    'animate-bounce': isUnwrapping,
                    'hover:scale-105 active:scale-95': !isUnwrapping,
                  }"
                  @click="handleUnwrap"
                >
                  <!-- 3D Box Visual -->
                  <div
                    class="w-24 h-24 rounded-3xl bg-gradient-to-tr from-rose-600 via-red-500 to-amber-500 flex items-center justify-center shadow-2xl shadow-red-900/60 border-2 border-amber-300/70 text-5xl relative overflow-hidden"
                  >
                    <!-- Ribbon Cross -->
                    <div
                      class="absolute inset-x-0 h-4 bg-amber-300/90 top-1/2 -translate-y-1/2 shadow-inner"
                    ></div>
                    <div
                      class="absolute inset-y-0 w-4 bg-amber-300/90 left-1/2 -translate-x-1/2 shadow-inner"
                    ></div>
                    <span class="relative z-10 filter drop-shadow">🎁</span>
                  </div>
                  <!-- Floating sparkle -->
                  <span class="absolute -top-1 -right-1 text-2xl animate-pulse"
                    >✨</span
                  >
                </div>

                <p class="text-sm font-bold text-amber-200 mt-4">
                  {{
                    isUnwrapping
                      ? "Unwrapping secret gift box..."
                      : "Tap the Gift Box to Unwrap!"
                  }}
                </p>
                <p class="text-[11px] text-slate-300 mt-1 max-w-xs">
                  Discover who {{ selectedCard.participant?.name }} got as their
                  Secret Santa recipient.
                </p>

                <button
                  type="button"
                  class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-xs font-bold
                         bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 shadow-lg shadow-emerald-950/60 hover:from-emerald-400 hover:to-teal-300
                         active:scale-95 transition disabled:opacity-50"
                  :disabled="isUnwrapping"
                  @click="handleUnwrap"
                >
                  <span>{{ isUnwrapping ? "🥁" : "✨" }}</span>
                  <span>{{
                    isUnwrapping ? "Unwrapping Drumroll..." : "Unwrap to Reveal"
                  }}</span>
                </button>
              </div>
            </template>

            <!-- Revealed recipient details -->
            <template v-else>
              <p class="text-sm font-semibold text-slate-50">
                {{ selectedCard.receiver.name || "—" }}
              </p>
              <p class="text-xs text-slate-300">
                Codename:
                <span class="font-mono text-emerald-200">
                  {{ selectedCard.receiver.codename || "—" }}
                </span>
              </p>

              <div class="mt-2 space-y-1">
                <p class="text-[11px] text-slate-300 font-semibold">
                  Recipient Wishlist
                </p>

                <ul
                  class="space-y-2 text-xs text-slate-200 break-words whitespace-pre-line"
                >
                  <li
                    v-if="
                      selectedCard.receiver.wishlist_1 ||
                      selectedCard.receiver.wishlist_1_description
                    "
                  >
                    <div v-if="selectedCard.receiver.wishlist_1">
                      <strong>{{ selectedCard.receiver.wishlist_1 }}</strong>
                    </div>
                    <div
                      v-if="selectedCard.receiver.wishlist_1_description"
                      class="text-[11px] text-slate-400 mt-0.5"
                    >
                      <template
                        v-if="
                          asUrl(
                            selectedCard.receiver.wishlist_1_description,
                          )
                        "
                      >
                        <a
                          :href="
                            asUrl(
                              selectedCard.receiver.wishlist_1_description,
                            )
                          "
                          target="_blank"
                          rel="noopener noreferrer"
                          class="underline underline-offset-2 decoration-sky-400 hover:text-sky-300"
                        >
                          Link here
                        </a>
                      </template>
                      <template v-else>
                        {{ selectedCard.receiver.wishlist_1_description }}
                      </template>
                    </div>
                  </li>

                  <li
                    v-if="
                      selectedCard.receiver.wishlist_2 ||
                      selectedCard.receiver.wishlist_2_description
                    "
                  >
                    <div v-if="selectedCard.receiver.wishlist_2">
                      <strong>{{ selectedCard.receiver.wishlist_2 }}</strong>
                    </div>
                    <div
                      v-if="selectedCard.receiver.wishlist_2_description"
                      class="text-[11px] text-slate-400 mt-0.5"
                    >
                      <template
                        v-if="
                          asUrl(
                            selectedCard.receiver.wishlist_2_description,
                          )
                        "
                      >
                        <a
                          :href="
                            asUrl(
                              selectedCard.receiver.wishlist_2_description,
                            )
                          "
                          target="_blank"
                          rel="noopener noreferrer"
                          class="underline underline-offset-2 decoration-sky-400 hover:text-sky-300"
                        >
                          Link here
                        </a>
                      </template>
                      <template v-else>
                        {{ selectedCard.receiver.wishlist_2_description }}
                      </template>
                    </div>
                  </li>

                  <li
                    v-if="
                      selectedCard.receiver.wishlist_3 ||
                      selectedCard.receiver.wishlist_3_description
                    "
                  >
                    <div v-if="selectedCard.receiver.wishlist_3">
                      <strong>{{ selectedCard.receiver.wishlist_3 }}</strong>
                    </div>
                    <div
                      v-if="selectedCard.receiver.wishlist_3_description"
                      class="text-[11px] text-slate-400 mt-0.5"
                    >
                      <template
                        v-if="
                          asUrl(
                            selectedCard.receiver.wishlist_3_description,
                          )
                        "
                      >
                        <a
                          :href="
                            asUrl(
                              selectedCard.receiver.wishlist_3_description,
                            )
                          "
                          target="_blank"
                          rel="noopener noreferrer"
                          class="underline underline-offset-2 decoration-sky-400 hover:text-sky-300"
                        >
                          Link here
                        </a>
                      </template>
                      <template v-else>
                        {{ selectedCard.receiver.wishlist_3_description }}
                      </template>
                    </div>
                  </li>

                  <li
                    v-if="
                      !(
                        selectedCard.receiver.wishlist_1 ||
                        selectedCard.receiver.wishlist_1_description ||
                        selectedCard.receiver.wishlist_2 ||
                        selectedCard.receiver.wishlist_2_description ||
                        selectedCard.receiver.wishlist_3 ||
                        selectedCard.receiver.wishlist_3_description
                      )
                    "
                  class="text-[11px] text-slate-500 italic"
                >
                  No wishlist set.
                </li>
              </ul>
            </div>

            <!-- Bought status toggle -->
            <div
              class="mt-4 pt-3 border-t border-slate-700/80 flex flex-wrap items-center justify-between gap-3 bg-slate-900/90 rounded-2xl p-3 border border-slate-700/80"
            >
              <div class="flex items-center gap-2.5">
                <div
                  class="h-8 w-8 rounded-xl flex items-center justify-center text-lg"
                  :class="
                    selectedCard.is_bought
                      ? 'bg-emerald-500/20 text-emerald-300'
                      : 'bg-amber-500/20 text-amber-300'
                  "
                >
                  {{ selectedCard.is_bought ? "✅" : "🛍️" }}
                </div>
                <div>
                  <p class="text-xs font-semibold text-slate-100">
                    {{
                      selectedCard.is_bought
                        ? "Gift Status: Bought!"
                        : "Gift Status: Still Shopping"
                    }}
                  </p>
                  <p class="text-[11px] text-slate-400">
                    {{
                      selectedCard.is_bought
                        ? "Gift has been acquired."
                        : "Toggle once you have acquired this gift."
                    }}
                  </p>
                </div>
              </div>

              <button
                type="button"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold border transition active:scale-95 flex items-center gap-1.5"
                :class="
                  selectedCard.is_bought
                    ? 'bg-emerald-500/20 border-emerald-400/60 text-emerald-200 hover:bg-emerald-500/30'
                    : 'bg-sky-500/20 border-sky-400/60 text-sky-200 hover:bg-sky-500/30'
                "
                :disabled="togglingBought"
                @click="handleToggleBought"
              >
                <span v-if="togglingBought">Updating...</span>
                <span v-else-if="selectedCard.is_bought"
                  >✓ Gift Bought (Click to Undo)</span
                >
                <span v-else>🎁 Mark as Bought</span>
              </button>
            </div>
          </template>
          </div>
        </div>

        <div class="flex justify-end pt-1">
          <button
            type="button"
            class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-200 hover:text-white hover:bg-slate-800/80"
            @click="closeCard"
          >
            Close
          </button>
        </div>
      </div>
    </div>
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
