<!-- FoodPlanning.vue -->
<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import ExchangeNav from "@/Components/ExchangeNav.vue";
import { playChime, playCelebration, playPop } from "@/Utils/soundFx";

const props = defineProps({
  currentProjectSlug: { type: String, required: false, default: "" },
  project: { type: Object, required: true },
});

const LIST_TYPE = "classic";

/* -------------------- Back link -------------------- */
const effectiveSlug = computed(() => {
  return props.currentProjectSlug || props.project?.slug || "";
});

const backHref = computed(() => {
  return effectiveSlug.value ? `/${effectiveSlug.value}/menu` : `/`;
});

/* -------------------- State -------------------- */
const loading = ref(false);
const savingBoss = ref(false);
const savingItem = ref(false);
const deletingItemId = ref(null);

const participants = ref([]);
const items = ref([]); // from DB: {id, name, unit_price, qty, notes}
const serverSummary = ref({});
const serverShares = ref([]);
const projectGcash = ref({
  gcash_name: props.project?.gcash_name || "",
  gcash_number: props.project?.gcash_number || "",
  gcash_qr_note: props.project?.gcash_qr_note || "",
});

// Boss config (client editing)
const bossIds = ref(new Set());
const bossPay = ref({}); // TOTAL pay (not extra)

// Add item form
const newItem = ref({ name: "", unit_price: "", qty: 1, notes: "" });

// GCash modal
const showGcashModal = ref(false);
const savingGcash = ref(false);
const gcashForm = ref({
  gcash_name: "",
  gcash_number: "",
  gcash_qr_note: "",
});
const gcashCopied = ref(false);

// Payment status update modal
const showPaymentModal = ref(false);
const paymentTarget = ref(null);
const paymentStatusForm = ref({
  payment_status: "paid",
  payment_reference: "",
  payment_notes: "",
});
const updatingPayment = ref(false);
const copySummarySuccess = ref(false);

/* -------------------- Helpers -------------------- */
const toNum = (v) => {
  const n = Number(String(v ?? "").replace(/,/g, ""));
  return Number.isFinite(n) ? n : 0;
};

const money = (n) =>
  Number(n || 0).toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

const cents = (n) => Math.round(toNum(n) * 100);

/* -------------------- Derived totals -------------------- */
const participantCount = computed(() => participants.value.length);

const total = computed(() => {
  return items.value.reduce((sum, it) => {
    const unit = toNum(it.unit_price);
    const qty = Math.max(1, Math.floor(toNum(it.qty)));
    return sum + unit * qty;
  }, 0);
});

const bossPaySum = computed(() => {
  let sum = 0;
  for (const p of participants.value) {
    if (bossIds.value.has(p.id)) sum += toNum(bossPay.value[p.id]);
  }
  return sum;
});

const nonBossCount = computed(() => {
  return participants.value.reduce(
    (count, p) => count + (bossIds.value.has(p.id) ? 0 : 1),
    0
  );
});

const splitError = computed(() => {
  if (!participantCount.value) return "No participants found yet.";
  if (total.value <= 0) return "Add at least one food item with a price.";

  if (bossPaySum.value > total.value) {
    return "Extra total pay exceeds food total. Reduce Extra pay amounts.";
  }

  if (nonBossCount.value === 0) {
    const diff = cents(total.value) - cents(bossPaySum.value);
    if (diff !== 0) {
      return "All participants are bosses. The sum of Extra pay must equal the food total.";
    }
  }

  return "";
});

const nonBossShare = computed(() => {
  if (!participantCount.value) return 0;
  if (nonBossCount.value <= 0) return 0;

  const totalCents = cents(total.value);
  const bossCents = cents(bossPaySum.value);
  const pool = totalCents - bossCents;
  if (pool <= 0) return 0;

  const shareCents = Math.floor(pool / nonBossCount.value);
  return shareCents / 100;
});

const previewPayMap = computed(() => {
  const m = new Map();
  if (splitError.value) {
    for (const p of participants.value) m.set(p.id, 0);
    return m;
  }

  const totalCents = cents(total.value);
  const bossCents = cents(bossPaySum.value);
  const pool = totalCents - bossCents;
  const n = nonBossCount.value;

  const baseCents = n > 0 ? Math.floor(pool / n) : 0;
  const rem = n > 0 ? pool % n : 0;

  let extraGiven = 0;
  for (const p of participants.value) {
    if (bossIds.value.has(p.id)) {
      m.set(p.id, toNum(bossPay.value[p.id]));
    } else {
      let c = baseCents;
      if (extraGiven < rem) {
        c += 1;
        extraGiven++;
      }
      m.set(p.id, c / 100);
    }
  }
  return m;
});

// Shares map with payment status
const sharesMap = computed(() => {
  const m = new Map();
  for (const s of serverShares.value || []) {
    m.set(s.participant_id, s);
  }
  return m;
});

// Payment stats
const paidStats = computed(() => {
  let paid = 0;
  let verified = 0;
  let unpaid = 0;

  for (const p of participants.value) {
    const s = sharesMap.value.get(p.id);
    const status = s?.payment_status || "unpaid";
    if (status === "verified") verified++;
    else if (status === "paid") paid++;
    else unpaid++;
  }

  return { paid, verified, unpaid };
});

/* -------------------- API -------------------- */
const fetchFoodPlanningData = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get("/exchange/food-planning/data", {
      params: { project_id: props.project.id, list_type: LIST_TYPE },
    });

    participants.value = data.participants || [];
    items.value = data.items || [];
    serverSummary.value = data.summary || {};
    serverShares.value = data.shares || [];

    if (data.project) {
      projectGcash.value = {
        gcash_name: data.project.gcash_name || "",
        gcash_number: data.project.gcash_number || "",
        gcash_qr_note: data.project.gcash_qr_note || "",
      };
    }

    // hydrate boss selections
    const set = new Set();
    const pay = { ...bossPay.value };

    const payers = data.payers || [];
    for (const row of payers) {
      if (row?.is_boss) {
        set.add(row.exchange_participant_id);
        pay[row.exchange_participant_id] = toNum(row.pay_amount);
      }
    }

    for (const p of participants.value) {
      if (pay[p.id] == null) pay[p.id] = 0;
    }

    bossIds.value = set;
    bossPay.value = pay;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

const addItem = async () => {
  const name = newItem.value.name.trim();
  const unit_price = toNum(newItem.value.unit_price);
  const qty = Math.max(1, Math.floor(toNum(newItem.value.qty)));
  const notes = newItem.value.notes?.trim() || null;

  if (!name || unit_price <= 0) return;

  savingItem.value = true;
  try {
    await axios.post("/exchange/food-planning/items", {
      project_id: props.project.id,
      name,
      unit_price,
      qty,
      notes,
    });

    newItem.value = { name: "", unit_price: "", qty: 1, notes: "" };
    playPop();
    await fetchFoodPlanningData();
  } catch (e) {
    console.error(e);
  } finally {
    savingItem.value = false;
  }
};

const removeItem = async (itemId) => {
  deletingItemId.value = itemId;
  try {
    await axios.delete(`/exchange/food-planning/items/${itemId}`);
    playPop();
    await fetchFoodPlanningData();
  } catch (e) {
    console.error(e);
  } finally {
    deletingItemId.value = null;
  }
};

const toggleBoss = (pid) => {
  const s = new Set(bossIds.value);
  if (s.has(pid)) {
    s.delete(pid);
  } else {
    s.add(pid);
    if (!toNum(bossPay.value[pid])) bossPay.value[pid] = Math.max(0, nonBossShare.value);
  }
  bossIds.value = s;
};

const saveBossSettings = async () => {
  if (splitError.value) return;

  const bosses = [];
  for (const pid of bossIds.value) {
    bosses.push({
      participant_id: pid,
      pay_amount: toNum(bossPay.value[pid]),
    });
  }

  savingBoss.value = true;
  try {
    await axios.post("/exchange/food-planning/payers", {
      project_id: props.project.id,
      bosses,
    });
    playChime();
    await fetchFoodPlanningData();
  } catch (e) {
    console.error(e);
  } finally {
    savingBoss.value = false;
  }
};

/* -------------------- GCash Management -------------------- */
const openGcashModal = () => {
  gcashForm.value = {
    gcash_name: projectGcash.value.gcash_name || "",
    gcash_number: projectGcash.value.gcash_number || "",
    gcash_qr_note: projectGcash.value.gcash_qr_note || "",
  };
  showGcashModal.value = true;
};

const saveGcashDetails = async () => {
  savingGcash.value = true;
  try {
    const { data } = await axios.post("/exchange/food-planning/gcash", {
      project_id: props.project.id,
      ...gcashForm.value,
    });

    projectGcash.value = {
      gcash_name: data.project.gcash_name,
      gcash_number: data.project.gcash_number,
      gcash_qr_note: data.project.gcash_qr_note,
    };
    showGcashModal.value = false;
    playChime();
  } catch (e) {
    console.error(e);
  } finally {
    savingGcash.value = false;
  }
};

const copyGcashNumber = async () => {
  if (!projectGcash.value.gcash_number) return;
  try {
    await navigator.clipboard.writeText(projectGcash.value.gcash_number);
    gcashCopied.value = true;
    playChime();
    setTimeout(() => {
      gcashCopied.value = false;
    }, 2500);
  } catch (e) {
    console.error(e);
  }
};

/* -------------------- Payment Status Modal -------------------- */
const openPaymentModal = (participant) => {
  paymentTarget.value = participant;
  const existing = sharesMap.value.get(participant.id);
  paymentStatusForm.value = {
    payment_status: existing?.payment_status || "paid",
    payment_reference: existing?.payment_reference || "",
    payment_notes: existing?.payment_notes || "",
  };
  showPaymentModal.value = true;
};

const submitPaymentStatus = async () => {
  if (!paymentTarget.value) return;

  updatingPayment.value = true;
  try {
    await axios.post("/exchange/food-planning/payment-status", {
      project_id: props.project.id,
      participant_id: paymentTarget.value.id,
      payment_status: paymentStatusForm.value.payment_status,
      payment_reference: paymentStatusForm.value.payment_reference,
      payment_notes: paymentStatusForm.value.payment_notes,
    });

    showPaymentModal.value = false;
    playCelebration();
    await fetchFoodPlanningData();
  } catch (e) {
    console.error(e);
  } finally {
    updatingPayment.value = false;
  }
};

/* -------------------- Group Chat Summary -------------------- */
const copySummary = async () => {
  const gcashInfo = projectGcash.value.gcash_number
    ? `\n📲 GCash: ${projectGcash.value.gcash_number} (${projectGcash.value.gcash_name || "Organizer"})`
    : "";

  const lines = [
    `🎄 *${props.project.name} - FOOD & AMBAGAN SUMMARY* 🍽️`,
    `Total Food Bill: ₱${money(total.value)}`,
    bossPaySum.value > 0 ? `Sponsor / Boss Contribution: ₱${money(bossPaySum.value)}` : null,
    `Per Person Share: ₱${money(nonBossShare.value)} (${nonBossCount.value} participants)`,
    `Total Participants: ${participantCount.value}`,
    gcashInfo,
    `\n📊 *Payment Status:*`,
    `✅ Verified: ${paidStats.value.verified}`,
    `🟡 Paid (Pending): ${paidStats.value.paid}`,
    `🔴 Unpaid: ${paidStats.value.unpaid}`,
    `\nView details & items: ${window.location.origin}/${effectiveSlug.value}/food-planning`,
  ]
    .filter(Boolean)
    .join("\n");

  try {
    await navigator.clipboard.writeText(lines);
    copySummarySuccess.value = true;
    playChime();
    setTimeout(() => {
      copySummarySuccess.value = false;
    }, 3000);
  } catch (e) {
    console.error("Copy failed", e);
  }
};

onMounted(() => {
  fetchFoodPlanningData();
});
</script>

<template>
  <div class="relative min-h-screen bg-gradient-to-b from-red-900 via-slate-950 to-slate-900 text-slate-100 overflow-hidden">
    <!-- Unified Navigation Bar -->
    <ExchangeNav
      :projectSlug="effectiveSlug"
      :projectName="project.name"
      currentPage="food-planning"
    />

    <!-- Header Banner -->
    <header class="relative z-10 bg-gradient-to-r from-red-700 via-red-600 to-rose-600/90 text-white shadow-lg border-b border-red-500/50">
      <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="h-11 w-11 rounded-2xl bg-white/20 flex items-center justify-center text-2xl shadow-inner">
            🍱
          </div>
          <div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight">
              Food Planning & Ambagan
            </h1>
            <p class="text-xs text-red-100/90">
              {{ project.name }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <Link
            :href="backHref"
            class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-medium transition"
          >
            ← Back to Dashboard
          </Link>
        </div>
      </div>
    </header>

    <main class="relative z-10 max-w-6xl mx-auto px-4 py-6 space-y-6">
      <!-- Title & Quick Stats -->
      <section class="grid gap-4 md:grid-cols-4">
        <!-- Total Bill -->
        <div class="bg-slate-900/80 border border-slate-700/80 rounded-2xl p-4 shadow-lg backdrop-blur">
          <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Food Cost</div>
          <div class="text-2xl font-black text-amber-300 mt-1">₱{{ money(total) }}</div>
          <div class="text-[11px] text-slate-400 mt-1">{{ items.length }} food items listed</div>
        </div>

        <!-- Per Person Share -->
        <div class="bg-slate-900/80 border border-emerald-500/40 rounded-2xl p-4 shadow-lg backdrop-blur">
          <div class="text-[11px] text-emerald-300 font-semibold uppercase tracking-wider">Share Per Person</div>
          <div class="text-2xl font-black text-emerald-200 mt-1">₱{{ money(nonBossShare) }}</div>
          <div class="text-[11px] text-slate-400 mt-1">For {{ nonBossCount }} non-sponsor members</div>
        </div>

        <!-- Extra / Sponsor Contribution -->
        <div class="bg-slate-900/80 border border-amber-500/40 rounded-2xl p-4 shadow-lg backdrop-blur">
          <div class="text-[11px] text-amber-300 font-semibold uppercase tracking-wider">Boss / Extra Sponsor</div>
          <div class="text-2xl font-black text-amber-200 mt-1">₱{{ money(bossPaySum) }}</div>
          <div class="text-[11px] text-slate-400 mt-1">{{ bossIds.size }} sponsors helping fund</div>
        </div>

        <!-- Collection Tracker Status -->
        <div class="bg-slate-900/80 border border-sky-500/40 rounded-2xl p-4 shadow-lg backdrop-blur">
          <div class="text-[11px] text-sky-300 font-semibold uppercase tracking-wider">Collection Progress</div>
          <div class="flex items-center gap-2 mt-1">
            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/40 font-semibold">
              {{ paidStats.verified }} Verified
            </span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-400/40 font-semibold">
              {{ paidStats.paid }} Paid
            </span>
          </div>
          <div class="text-[11px] text-slate-400 mt-1.5">{{ paidStats.unpaid }} pending payment</div>
        </div>
      </section>

      <!-- GCash Payment Info Card -->
      <section class="bg-gradient-to-r from-blue-950/80 to-slate-900/90 border border-blue-500/40 rounded-3xl p-5 shadow-xl backdrop-blur">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600/30 text-2xl border border-blue-400/40 shadow-inner">
              📲
            </span>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-base font-bold text-slate-100">GCash / Maya Payment Info</h3>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300 font-semibold border border-blue-400/30">Official Party Account</span>
              </div>
              <p class="text-xs text-slate-300 mt-0.5">
                Send your ambagan here and click "Update Payment" to record your reference number.
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 w-full sm:w-auto">
            <button
              v-if="projectGcash.gcash_number"
              type="button"
              @click="copyGcashNumber"
              class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-blue-500 text-white font-semibold text-xs hover:bg-blue-400 shadow-md active:scale-95 transition"
            >
              <span v-if="gcashCopied">✅ Copied Number!</span>
              <template v-else>
                <span>📋</span>
                <span>Copy GCash Number</span>
              </template>
            </button>

            <button
              type="button"
              @click="openGcashModal"
              class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-200 text-xs font-semibold hover:bg-slate-700/70 transition"
              title="Edit GCash payment settings"
            >
              ⚙️ Setup GCash
            </button>
          </div>
        </div>

        <!-- GCash Details Display -->
        <div v-if="projectGcash.gcash_number" class="mt-4 pt-4 border-t border-slate-800/80 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
          <div class="bg-slate-800/60 p-3 rounded-xl border border-slate-700/60">
            <span class="text-slate-400 text-[10px] uppercase font-semibold">Account Name</span>
            <div class="text-sm font-bold text-slate-100">{{ projectGcash.gcash_name || 'Not specified' }}</div>
          </div>
          <div class="bg-slate-800/60 p-3 rounded-xl border border-slate-700/60">
            <span class="text-slate-400 text-[10px] uppercase font-semibold">Account Number</span>
            <div class="text-sm font-mono font-bold text-amber-300">{{ projectGcash.gcash_number }}</div>
          </div>
          <div class="bg-slate-800/60 p-3 rounded-xl border border-slate-700/60">
            <span class="text-slate-400 text-[10px] uppercase font-semibold">Remittance Note</span>
            <div class="text-xs text-slate-200 truncate">{{ projectGcash.gcash_qr_note || 'Include your name in receipt' }}</div>
          </div>
        </div>
      </section>

      <!-- Main Columns: Food Items & Payment Collection -->
      <section class="grid gap-6 lg:grid-cols-12 items-start">
        <!-- Left: Food items list (5 cols) -->
        <div class="lg:col-span-5 bg-slate-900/80 border border-slate-700/80 rounded-3xl p-5 space-y-4 shadow-xl backdrop-blur">
          <div class="flex items-center justify-between">
            <h2 class="font-bold text-sm text-slate-100 flex items-center gap-2">
              <span>🍗</span>
              <span>Food & Drink Items</span>
            </h2>
            <div class="text-xs">
              <span class="text-slate-400">Total:</span>
              <span class="ml-1 font-bold text-amber-300">₱{{ money(total) }}</span>
            </div>
          </div>

          <!-- Add item input form -->
          <div class="space-y-2 bg-slate-800/60 p-3 rounded-2xl border border-slate-700/70">
            <div class="text-[11px] text-slate-300 font-semibold">Add Menu Item</div>
            <input
              v-model="newItem.name"
              placeholder="Item name (e.g. Lechon Belly, 2x Coke 1.5L)"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs focus:ring-2 focus:ring-amber-400 focus:outline-none"
            />
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model="newItem.unit_price"
                placeholder="Price (₱)"
                inputmode="decimal"
                class="px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs focus:ring-2 focus:ring-amber-400 focus:outline-none"
              />
              <input
                v-model="newItem.qty"
                placeholder="Qty"
                type="number"
                min="1"
                class="px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs focus:ring-2 focus:ring-amber-400 focus:outline-none"
              />
            </div>
            <button
              type="button"
              class="w-full px-3 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-400 text-slate-900 text-xs font-bold hover:from-emerald-400 hover:to-emerald-300 disabled:opacity-50 transition"
              :disabled="savingItem || !newItem.name.trim() || toNum(newItem.unit_price) <= 0"
              @click="addItem"
            >
              <span v-if="savingItem">Adding item...</span>
              <span v-else>+ Add to Menu</span>
            </button>
          </div>

          <!-- Item list -->
          <div v-if="loading" class="text-xs text-slate-400 italic py-4 text-center">Loading items...</div>
          <div
            v-else-if="!items.length"
            class="text-xs text-slate-400 italic py-8 text-center border border-dashed border-slate-700 rounded-2xl"
          >
            No food items yet. Add food, drinks, and party supplies above.
          </div>
          <div v-else class="space-y-2 max-h-80 overflow-y-auto pr-1">
            <div
              v-for="it in items"
              :key="it.id"
              class="flex items-center justify-between gap-3 bg-slate-800/70 border border-slate-700 rounded-2xl px-3 py-2 text-xs"
            >
              <div class="min-w-0">
                <div class="font-semibold text-slate-100 truncate">{{ it.name }}</div>
                <div class="text-[11px] text-slate-400">
                  ₱{{ money(it.unit_price) }} × {{ it.qty }} =
                  <span class="text-amber-200 font-medium">₱{{ money(toNum(it.unit_price) * toNum(it.qty)) }}</span>
                </div>
              </div>

              <button
                type="button"
                class="text-[11px] text-red-300 hover:text-red-100 hover:underline disabled:opacity-50"
                :disabled="deletingItemId === it.id"
                @click="removeItem(it.id)"
              >
                {{ deletingItemId === it.id ? "..." : "Remove" }}
              </button>
            </div>
          </div>
        </div>

        <!-- Right: Ambagan Split & Payment Tracking (7 cols) -->
        <div class="lg:col-span-7 bg-slate-900/80 border border-slate-700/80 rounded-3xl p-5 space-y-4 shadow-xl backdrop-blur">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-bold text-sm text-slate-100 flex items-center gap-2">
                <span>💰</span>
                <span>Ambagan Collection & Payment Tracking</span>
              </h2>
              <p class="text-[11px] text-slate-400">Track who paid, reference numbers, and sponsor amounts.</p>
            </div>

            <div class="text-xs font-semibold text-emerald-300 bg-emerald-500/10 border border-emerald-500/30 px-2.5 py-1 rounded-xl">
              ₱{{ money(nonBossShare) }} / person
            </div>
          </div>

          <div
            v-if="splitError"
            class="text-[11px] bg-red-500/20 text-red-200 border border-red-400/60 rounded-xl px-3 py-2"
          >
            ⚠️ {{ splitError }}
          </div>

          <!-- Participants Collection Table -->
          <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
            <div
              v-for="p in participants"
              :key="p.id"
              class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-800/80 border border-slate-700/80 rounded-2xl px-3.5 py-2.5 text-xs"
            >
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span class="font-bold text-slate-100 truncate">{{ p.name }}</span>
                  <span
                    v-if="bossIds.has(p.id)"
                    class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-400/20 text-amber-300 font-bold border border-amber-400/40"
                  >
                    👑 Sponsor
                  </span>
                </div>
                <div class="text-[11px] text-slate-300 mt-0.5">
                  Share Due: <span class="font-bold text-emerald-300">₱{{ money(previewPayMap.get(p.id) || 0) }}</span>
                  <span
                    v-if="sharesMap.get(p.id)?.payment_reference"
                    class="ml-2 text-slate-400 font-mono text-[10px]"
                  >
                    Ref: {{ sharesMap.get(p.id).payment_reference }}
                  </span>
                </div>
              </div>

              <!-- Payment Status Badge + Actions -->
              <div class="flex items-center gap-2 shrink-0">
                <!-- Status Badge -->
                <button
                  type="button"
                  @click="openPaymentModal(p)"
                  :class="[
                    'px-2.5 py-1 rounded-xl text-[11px] font-bold border transition active:scale-95',
                    sharesMap.get(p.id)?.payment_status === 'verified'
                      ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50 hover:bg-emerald-500/30'
                      : sharesMap.get(p.id)?.payment_status === 'paid'
                      ? 'bg-amber-500/20 text-amber-300 border-amber-500/50 hover:bg-amber-500/30'
                      : 'bg-rose-500/20 text-rose-300 border-rose-500/40 hover:bg-rose-500/30'
                  ]"
                  title="Click to update payment status"
                >
                  <span v-if="sharesMap.get(p.id)?.payment_status === 'verified'">✅ Verified</span>
                  <span v-else-if="sharesMap.get(p.id)?.payment_status === 'paid'">🟡 Paid (Ref)</span>
                  <span v-else>🔴 Unpaid</span>
                </button>

                <!-- Sponsor toggle -->
                <label class="text-[11px] text-slate-400 inline-flex items-center gap-1 cursor-pointer">
                  <input
                    type="checkbox"
                    :checked="bossIds.has(p.id)"
                    @change="toggleBoss(p.id)"
                    class="h-3.5 w-3.5 rounded text-amber-400 focus:ring-amber-400 border-slate-600 bg-slate-900"
                  />
                  <span>Sponsor</span>
                </label>

                <input
                  v-if="bossIds.has(p.id)"
                  v-model="bossPay[p.id]"
                  inputmode="decimal"
                  placeholder="Total pay"
                  class="w-20 px-2 py-1 rounded-xl bg-slate-900 border border-slate-700 text-xs text-amber-200 font-bold focus:outline-none"
                />
              </div>
            </div>
          </div>

          <!-- Bottom Actions -->
          <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-800 text-xs">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-200 font-semibold hover:bg-slate-700/70 transition"
              @click="copySummary"
            >
              <span v-if="copySummarySuccess">✅ Copied for Group Chat!</span>
              <template v-else>
                <span>📋</span>
                <span>Copy Summary for Messenger/Viber</span>
              </template>
            </button>

            <button
              type="button"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-400 to-yellow-300 text-slate-950 font-bold hover:from-amber-300 hover:to-yellow-200 shadow-md transition disabled:opacity-50"
              :disabled="savingBoss || !!splitError"
              @click="saveBossSettings"
            >
              <span v-if="savingBoss">Saving...</span>
              <span v-else>💾 Save Sponsor Settings</span>
            </button>
          </div>
        </div>
      </section>
    </main>

    <!-- GCash Setup Modal -->
    <div
      v-if="showGcashModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
    >
      <div class="relative w-full max-w-md bg-slate-900 border border-blue-500/40 rounded-3xl p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-bold text-slate-100 flex items-center gap-2">
            <span>📲</span>
            <span>Configure GCash / Maya</span>
          </h3>
          <button
            type="button"
            class="h-8 w-8 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700"
            @click="showGcashModal = false"
          >
            ✕
          </button>
        </div>

        <p class="text-xs text-slate-300">
          This payment info will be shown to all party members so they know where to send their food ambagan.
        </p>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-300 mb-1">Account Holder Name</label>
            <input
              v-model="gcashForm.gcash_name"
              placeholder="e.g. Juan Dela Cruz"
              class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400"
            />
          </div>

          <div>
            <label class="block text-slate-300 mb-1">GCash / Maya Mobile Number</label>
            <input
              v-model="gcashForm.gcash_number"
              placeholder="e.g. 09171234567"
              class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400 font-mono"
            />
          </div>

          <div>
            <label class="block text-slate-300 mb-1">Special Remittance Instructions</label>
            <textarea
              v-model="gcashForm.gcash_qr_note"
              rows="2"
              placeholder="e.g. Please put your name in message and message screenshot to Viber"
              class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700"
            @click="showGcashModal = false"
          >
            Cancel
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-blue-500 text-white text-xs font-bold hover:bg-blue-400 disabled:opacity-50"
            :disabled="savingGcash"
            @click="saveGcashDetails"
          >
            <span v-if="savingGcash">Saving...</span>
            <span v-else>Save GCash Info</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Payment Status Modal -->
    <div
      v-if="showPaymentModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
    >
      <div class="relative w-full max-w-sm bg-slate-900 border border-emerald-500/40 rounded-3xl p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-bold text-slate-100 flex items-center gap-2">
            <span>💳</span>
            <span>Update Payment</span>
          </h3>
          <button
            type="button"
            class="h-8 w-8 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700"
            @click="showPaymentModal = false"
          >
            ✕
          </button>
        </div>

        <div class="bg-slate-800/80 p-3 rounded-2xl border border-slate-700/60">
          <div class="text-xs text-slate-400">Participant:</div>
          <div class="text-sm font-bold text-slate-100">{{ paymentTarget?.name }}</div>
          <div class="text-xs text-emerald-300 mt-1">
            Amount: ₱{{ money(previewPayMap.get(paymentTarget?.id) || 0) }}
          </div>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-300 mb-1">Status</label>
            <select
              v-model="paymentStatusForm.payment_status"
              class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-400"
            >
              <option value="unpaid">🔴 Unpaid</option>
              <option value="paid">🟡 Paid (Sent / Pending Check)</option>
              <option value="verified">🟢 Verified (Received by Treasurer)</option>
            </select>
          </div>

          <div>
            <label class="block text-slate-300 mb-1">GCash / Bank Reference Number</label>
            <input
              v-model="paymentStatusForm.payment_reference"
              placeholder="e.g. 90214820123"
              class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-emerald-400"
            />
          </div>

          <div>
            <label class="block text-slate-300 mb-1">Optional Notes</label>
            <input
              v-model="paymentStatusForm.payment_notes"
              placeholder="e.g. Paid in cash to organizer"
              class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-400"
            />
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700"
            @click="showPaymentModal = false"
          >
            Cancel
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-400 text-slate-950 text-xs font-bold hover:from-emerald-400 hover:to-emerald-300 disabled:opacity-50"
            :disabled="updatingPayment"
            @click="submitPaymentStatus"
          >
            <span v-if="updatingPayment">Updating...</span>
            <span v-else>Save Payment Status</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
