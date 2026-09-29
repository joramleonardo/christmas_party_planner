<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import ExchangeNav from "@/Components/ExchangeNav.vue";
import confetti from "canvas-confetti";
import { playChime, playCelebration, playDrumroll, playPop } from "@/Utils/soundFx";

const props = defineProps({
  projects: { type: Array, required: true },
  currentProjectId: { type: Number, required: true },
  currentProjectSlug: { type: String, required: true },
  currentPage: { type: String, required: true },
});

const LIST_TYPE = "classic";

/* -------------------- Participants / voting state -------------------- */
const participants = ref([]);
const loadingParticipants = ref(false);
const loadError = ref("");

const voterCode = ref("");
const voter = ref(null);
const codeError = ref("");
const submitting = ref(false);
const submitMessage = ref("");
const submitError = ref("");

// Session/progress
const sessionClosed = ref(false);
const votersTotal = ref(0);
const participantsTotal = ref(0);
const hasVoted = ref(false);

// Results modal
const showResultModal = ref(false);
const modalAwardKey = ref(null);
const resultsMap = ref({});
const loadingResults = ref(false);
const resultsError = ref("");

const awardCategories = [
    // ---------------- ATTENDANCE ----------------
    { key: "early_bird_of_the_year", label: "EARLY BIRD OF THE YEAR", description: "#LagingMaaga #WalaBangPasok?", category: "attendance" },
    { key: "laging_late", label: "LAGING LATE", description: "#TrafficSaSLEX #BuzzerBeater", category: "attendance" },
    { key: "best_in_half_day", label: "BEST IN HALF DAY", description: "#PuyatAkoKagabi #GinawaKoKasiYungThesisNgKapatidKo", category: "attendance" },
    { key: "best_in_lunchbreak", label: "BEST IN LUNCHBREAK", description: "Tara kain na #BreakIsLife #BRB1Hour", category: "attendance" },

    // ---------------- PERSONALITY ----------------
    { key: "best_extra_rice", label: "BEST IN EXTRA RICE", description: "May Kanin ka pa? #DalawangRiceAko", category: "personality" },
    { key: "most_lutang", label: "MOST LUTANG", description: "Ano? Haaa? Ano Yun? Ano raw? #SinoAkoSaanAko", category: "personality" },
    { key: "most_madaldal", label: "'PINAKAMADALDAL' Award", description: "Kasi ganito yun... #ChikaTime", category: "personality" },
    { key: "best_in_pagpapanggap", label: "'GALING MAG PANGGAP' Award", description: "#KunwariBusy #AltTabIsLife", category: "personality" },
    { key: "nonchalant_king", label: "NONCHALANT KING", description: "#Hmm #Ah #Oh #TahimikLangSaGedli", category: "personality" },
    { key: "nonchalant_queen", label: "NONCHALANT QUEEN", description: "#Hmm #Ah #Oh #TahimikLangSaGedli", category: "personality" },
    { key: "laging_naka_headphone_award", label: "'LAGING NAKA-HEADPHONE' Award", description: "#HaTinawagNyoKo? #BusySaPlaylistHindiSaWork #MaySinasabiKa?", category: "personality" },
    { key: "toyo_award", label: "'TOYO' Award", description: "#MinsanMasayaMinsanHindi #WeatherWeatherLang", category: "personality" },
    { key: "adik_sa_energy_drink", label: "'ADIK SA ENERGY DRINK' Award", description: "#ThirdCanToday #HindiAkoInaantokPromise", category: "personality" },
    { key: "coffeeholic_award", label: "COFFEEHOLIC AWARD", description: "#TaraKape #3in1IsLife #MayKapeKaBaJan?", category: "personality" },
    { key: "galit_na_galit_gustong_manaket_award", label: "'GALIT NA GALIT GUSTONG MANAKET' Award", description: "#HuwagMoAkongUubusin #WalaLangTalagangBudget", category: "personality" },
    { key: "pinaka_oa", label: "PINAKA OA OF THE YEAR", description: "#BestInDramaSeries #KahitAntsLangNagulat", category: "personality" },
    { key: "reklamador_of_the_year", label: "REKLAMADOR OF THE YEAR", description: "#BakitGanito #PwedeBangIreklamo", category: "personality" },
    { key: "marites_of_the_year", label: "MARITES OF THE YEAR", description: "Eto na nga... Alam niyo na ba? #ChikadoraAward #BreakingNews", category: "personality" },
    { key: "joker_of_the_year", label: "JOKER OF THE YEAR", description: "#DiNakakatawaPeroTuloy #HAHAHAHA", category: "personality" },
    { key: "buraot_of_the_year", label: "BURAOT OF THE YEAR", description: "#UyAnoYan? #SarapYan? #ManlibreKaNaman", category: "personality" },
    { key: "jollibee_of_the_year", label: "JOLLIBEE OF THE YEAR", description: "#BidaBidaYarns #AyAlamKoYan #TabiAkoNa #KayaKoYan", category: "personality" },
    { key: "banyo_queen", label: "BANYO QUEEN", description: "Sama ka? Tara #BestInCR #BRBLang", category: "personality" },
    { key: "banyo_king", label: "BANYO KING", description: "Sama ka? Tara #BestInCR #BRBLang", category: "personality" },
    { key: "soft_spoken", label: "SOFT SPOKEN QUEEN", description: "#MahinhinLang #SweetVoice", category: "personality" },
    { key: "boss_survivor", label: "BOSS SURVIVOR AWARD", description: "#KinayaKami #SalamatBoss", category: "personality" },
    { key: "madaming_kamukha", label: "'MADAMING KAMUKHANG ARTISTA' Award", description: "Parang kamukha mo si ano pre.. #UyMayKamukhaKa", category: "personality" },
    { key: "paimportante_award", label: "PAIMPORTANTE AWARD", description: "#WaitLangHa #AkoNaBahala", category: "personality" },
    { key: "saks_lang", label: "SAKS LANG AWARD", description: "#PwedeNaYan #DiNaKailanganPerfect", category: "personality" },
    { key: "overthinker_award", label: "OVERTHINKER OF THE YEAR", description: "#PaanoKung #WhatIfLangNaman", category: "personality" },
    { key: "queen_of_selfies", label: "SELFIE QUEEN", description: "#WaitLangIsaPa #BestAngle", category: "personality" },

    // ---------------- FOOD ----------------
    { key: "merienda_queen_king", label: "MERYENDA QUEEN/KING", description: "#OhAmbaganNa #3PMIsLife #BiliNaMeryenda #AnongGustoNiyongKainin?", category: "food" },
    { key: "budol_queen_king", label: "BUDOL QUEEN/KING", description: "#OrderTayo #AddToCartNaYan #FreeShippingBa #BilhinMoNaYan", category: "food" },
    { key: "best_in_pares", label: "BEST IN PARES", description: "Tara Pares tayo #GabiNaNaman #KantoGoals", category: "food" },
    { key: "alak_ay_tubig_award", label: "'ALAK AY TUBIG' Award", description: "#TubigLangToPromise #OneForTheRoadPa", category: "food" },
    { key: "pinaka_malaking_tiyan_award", label: "'PINAKA MALAKING TIYAN' Award", description: "Kahit saan may dalang mesa #BusogLagi #RiceIsLife #MeriendaPaMore ", category: "food" },
    { key: "last_piece_grabber", label: "LAST PIECE GRABBER", description: "#SaAkinNaYan #WalaNamangMayGusto", category: "food" },

    // ---------------- MONEY ----------------
    { key: "gastador_of_the_year", label: "GASTADOR OF THE YEAR", description: "#SwipeNaLangNgSwipe #AddToCartIsLife #ParangGustoKoTongBilhin", category: "money" },
    { key: "pinaka_kuripot_award", label: "PINAKA KURIPOT AWARD", description: "#BenteNaLangPeraKoEh #SingkwentaPwedeNaYan #PwedxeBaBaryaLang", category: "money" },
    { key: "best_in_utang", label: "BEST IN UTANG", description: "#MayFormKayoNgEA? #UtangTayoJan!", category: "money" },
    { key: "may_gc_ba", label: "MAY GCASH BA? AWARD", description: "#MamayaTransfer #WalaPaLoad", category: "money" },
    { key: "wala_nang_budget", label: "WALA NANG BUDGET AWARD", description: "#NextCutoffNaLang #PetsaDePeligro", category: "money" },
];

/* ---- Group awards by category for UI ---- */
const awardCategoryGroups = computed(() => {
  const groups = {};
  for (const award of awardCategories) {
    if (!groups[award.category]) groups[award.category] = [];
    groups[award.category].push(award);
  }

  Object.values(groups).forEach((awards) =>
    awards.sort((a, b) => a.label.localeCompare(b.label))
  );

  const order = ["attendance", "personality", "food", "money"];
  const labels = {
    attendance: "Attendance Awards",
    personality: "Personality & Vibes",
    food: "Food & Inuman Awards",
    money: "Money Matters Awards",
  };

  return order
    .filter((key) => groups[key])
    .map((key) => ({ key, label: labels[key] || key, awards: groups[key] }));
});

const selectedNominees = ref({}); // { [awardKey]: nomineeId }

/* -------------------- Category Tabs & Search -------------------- */
const selectedTab = ref("all");
const searchQuery = ref("");

const tabCounts = computed(() => {
  const counts = { all: awardCategories.length, attendance: 0, personality: 0, food: 0, money: 0 };
  for (const a of awardCategories) {
    if (counts[a.category] !== undefined) counts[a.category]++;
  }
  return counts;
});

const filteredCategoryGroups = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  return awardCategoryGroups.value
    .filter((group) => {
      if (selectedTab.value !== "all" && group.key !== selectedTab.value) return false;
      return true;
    })
    .map((group) => {
      let awards = group.awards;
      if (q) {
        awards = awards.filter(
          (a) =>
            a.label.toLowerCase().includes(q) ||
            a.description.toLowerCase().includes(q)
        );
      }
      return { ...group, awards };
    })
    .filter((group) => group.awards.length > 0);
});

const votedCount = computed(() => {
  return Object.values(selectedNominees.value).filter(Boolean).length;
});

const votingPercentage = computed(() => {
  if (!awardCategories.length) return 0;
  return Math.round((votedCount.value / awardCategories.length) * 100);
});

/* -------------------- Snowflakes -------------------- */
const SNOWFLAKE_COUNT = 80;
const snowflakes = ref([]);

/* -------------------- Helpers -------------------- */
const isVotingLocked = computed(() => {
  return !voter.value || hasVoted.value || sessionClosed.value;
});

const voterDisplayName = computed(() => (voter.value ? voter.value.name : ""));

const awardMetaMap = computed(() => {
  const m = new Map();
  for (const a of awardCategories) m.set(a.key, a);
  return m;
});

const resetVoteForm = () => {
  selectedNominees.value = {};
  submitMessage.value = "";
  submitError.value = "";
};

/* -------------------- Fetch participants -------------------- */
const fetchParticipants = async () => {
  loadingParticipants.value = true;
  loadError.value = "";

  try {
    const { data } = await axios.get("/exchange/data", {
      params: {
        project_id: props.currentProjectId,
        list_type: LIST_TYPE,
      },
    });

    participants.value = data.participants || [];
    participantsTotal.value = participants.value.length || 0;
  } catch (e) {
    console.error("Error loading participants:", e);
    loadError.value =
      e.response?.data?.message || "Unable to load participants right now.";
  } finally {
    loadingParticipants.value = false;
  }
};

/* -------------------- Status (progress) -------------------- */
const fetchAwardsStatus = async () => {
  try {
    const { data } = await axios.get("/exchange/awards/status", {
      params: { project_id: props.currentProjectId },
    });

    participantsTotal.value = data.participants_total ?? participantsTotal.value;
    votersTotal.value = data.voters_total ?? votersTotal.value;
    sessionClosed.value = !!data.session_closed;
  } catch (e) {
    // silent (status is optional)
  }
};

/* -------------------- Verify Code (SERVER-SIDE) -------------------- */
const verifyCode = async () => {
  codeError.value = "";
  voter.value = null;
  hasVoted.value = false;
  submitMessage.value = "";
  submitError.value = "";
  resetVoteForm();

  const code = voterCode.value.trim().toUpperCase();
  if (!code) {
    codeError.value = "Please enter the unique code sent to your email.";
    return;
  }

  try {
    const { data } = await axios.post("/exchange/awards/verify", {
      project_id: props.currentProjectId,
      code,
    });

    participantsTotal.value = data.participants_total || 0;
    votersTotal.value = data.voters_total || 0;
    sessionClosed.value = !!data.session_closed;

    if (!data.valid) {
      codeError.value = data.message || "Invalid code.";
      return;
    }

    voter.value = data.participant;
    hasVoted.value = !!data.has_voted;

    if (sessionClosed.value) {
      codeError.value = "Voting session is CLOSED. You can only view results.";
    } else if (hasVoted.value) {
      codeError.value = "You already voted. Voting is one-time only.";
    }
  } catch (e) {
    codeError.value =
      e.response?.data?.message || "Unable to verify code right now.";
  }
};

/* -------------------- Voting -------------------- */
const handleNomineeChange = (awardKey, nomineeId) => {
  selectedNominees.value = {
    ...selectedNominees.value,
    [awardKey]: nomineeId || null,
  };
};

const canSubmit = computed(() => {
  if (!voter.value) return false;
  if (hasVoted.value || sessionClosed.value) return false;
  return Object.values(selectedNominees.value).some((v) => !!v);
});

const submitVotes = async () => {
  submitMessage.value = "";
  submitError.value = "";

  if (!voter.value) {
    submitError.value = "Please verify your unique code first.";
    return;
  }
  if (sessionClosed.value) {
    submitError.value = "Voting session is closed.";
    return;
  }
  if (hasVoted.value) {
    submitError.value = "You already voted. Voting is one-time only.";
    return;
  }

  const votesPayload = Object.entries(selectedNominees.value)
    .filter(([, nomineeId]) => !!nomineeId)
    .map(([awardKey, nomineeId]) => ({
      award_key: awardKey,
      nominee_id: nomineeId,
    }));

  if (!votesPayload.length) {
    submitError.value = "Please select at least one award nominee.";
    return;
  }

  submitting.value = true;
  try {
    const { data } = await axios.post("/exchange/awards/vote", {
      project_id: props.currentProjectId,
      voter_code: voterCode.value.trim(),
      votes: votesPayload,
    });

    submitMessage.value = data.message || "Votes saved.";

    // lock after successful submit
    hasVoted.value = true;

    // refresh progress + close status
    participantsTotal.value = data.participants_total ?? participantsTotal.value;
    votersTotal.value = data.voters_total ?? votersTotal.value;
    sessionClosed.value = !!data.session_closed;

    // also refresh results cache (optional)
    resultsMap.value = {};
  } catch (e) {
    submitError.value =
      e.response?.data?.message || "Unable to save your votes right now.";
  } finally {
    submitting.value = false;
  }
};

/* -------------------- Results & Ceremony Mode -------------------- */
const fetchAwardsResults = async () => {
  if (Object.keys(resultsMap.value || {}).length) return;

  loadingResults.value = true;
  try {
    const { data } = await axios.get("/exchange/awards/results", {
      params: { project_id: props.currentProjectId },
    });

    participantsTotal.value = data.participants_total ?? participantsTotal.value;
    votersTotal.value = data.voters_total ?? votersTotal.value;
    sessionClosed.value = !!data.session_closed;

    resultsMap.value = data.results || {};
  } catch (e) {
    console.error(e);
    resultsError.value =
      e.response?.data?.message || "Unable to load results right now.";
  } finally {
    loadingResults.value = false;
  }
};

const openWinnerModal = async (awardKey) => {
  modalAwardKey.value = awardKey;
  showResultModal.value = true;
  resultsError.value = "";
  await fetchAwardsResults();
};

const closeWinnerModal = () => {
  showResultModal.value = false;
  modalAwardKey.value = null;
};

/* -------------------- Fullscreen Ceremony / Projector Mode -------------------- */
const showCeremonyMode = ref(false);
const ceremonyIndex = ref(0);
const ceremonyRevealed = ref(false);
const revealingWinner = ref(false);

const currentCeremonyAward = computed(() => {
  return awardCategories[ceremonyIndex.value] || null;
});

const currentCeremonyResult = computed(() => {
  if (!currentCeremonyAward.value) return null;
  return resultsMap.value?.[currentCeremonyAward.value.key] || null;
});

const openCeremonyMode = async () => {
  showCeremonyMode.value = true;
  ceremonyIndex.value = 0;
  ceremonyRevealed.value = false;
  revealingWinner.value = false;
  playChime();
  await fetchAwardsResults();
};

const closeCeremonyMode = () => {
  showCeremonyMode.value = false;
};

const nextCeremonyAward = () => {
  if (ceremonyIndex.value < awardCategories.length - 1) {
    ceremonyIndex.value++;
    ceremonyRevealed.value = false;
    revealingWinner.value = false;
    playPop();
  }
};

const prevCeremonyAward = () => {
  if (ceremonyIndex.value > 0) {
    ceremonyIndex.value--;
    ceremonyRevealed.value = false;
    revealingWinner.value = false;
    playPop();
  }
};

const triggerRevealCeremonyWinner = () => {
  revealingWinner.value = true;
  playDrumroll(1.6);

  setTimeout(() => {
    revealingWinner.value = false;
    ceremonyRevealed.value = true;
    playCelebration();
    confetti({
      particleCount: 130,
      spread: 85,
      origin: { y: 0.6 },
    });
  }, 1600);
};

/* -------------------- onMounted: snow + participants + status -------------------- */
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
  fetchAwardsStatus();
});
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
      :projectName="(props.projects.find((p) => p.id === props.currentProjectId) || {}).name || 'Party Planner'"
      currentPage="awards"
    />

    <!-- Header -->
    <header
      class="relative z-10 bg-gradient-to-r from-red-700 via-red-600 to-rose-600/90 text-white shadow-lg border-b border-red-500/50"
    >
      <div
        class="max-w-5xl mx-auto px-4 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
      >
        <div class="flex items-center gap-3">
          <div
            class="h-11 w-11 rounded-2xl bg-white/20 flex items-center justify-center text-2xl shadow-inner"
          >
            🏆
          </div>

          <div>
            <h1 class="text-2xl font-bold tracking-tight">Awards & Voting</h1>
            <p class="text-xs text-red-100">
              {{ (props.projects.find((p) => p.id === props.currentProjectId) || {}).name || 'Your Group' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- Ceremony Mode Launch Button -->
          <button
            type="button"
            @click="openCeremonyMode"
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-gradient-to-r from-yellow-400 via-amber-300 to-yellow-200 text-slate-950 font-bold text-xs shadow-lg hover:from-yellow-300 hover:to-amber-100 active:scale-95 transition"
            title="Launch full-screen presentation mode for party TV / projector"
          >
            <span>🎥</span>
            <span>Live Ceremony Mode</span>
          </button>

          <Link
            :href="`/${currentProjectSlug}/menu`"
            class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition"
          >
            ← Back
          </Link>
        </div>
      </div>
    </header>

    <main class="relative z-10 max-w-5xl mx-auto px-4 py-6 space-y-6">
      <div>
        <!-- Header -->
        <section class="space-y-4">
          <div
            class="flex flex-col md:flex-row md:items-end md:justify-between gap-4"
          >
            <div class="space-y-2">
              <h1 class="text-3xl md:text-4xl font-extrabold leading-tight">
                Party Awards for
                <span class="block text-amber-300">
                  {{
                    (props.projects.find((p) => p.id === props.currentProjectId) ||
                      {}).name || "Your Group"
                  }}
                </span>
              </h1>

              <p class="max-w-2xl text-sm md:text-base text-slate-200">
                Use your
                <span class="font-semibold text-amber-200">unique code</span>
                to vote for fun awards.
                <span class="font-semibold text-amber-200">One-time voting only</span>
                per code.
              </p>
            </div>
          </div>
        </section>

        <!-- Code + Awards -->
        <section
          class="grid gap-6 lg:grid-cols-[minmax(0,0.65fr)_minmax(0,1.35fr)] items-start mt-5"
        >
          <!-- Left: Code panel -->
          <div
            class="bg-slate-900/80 border border-amber-500/40 rounded-3xl shadow-xl p-5 flex flex-col gap-4 lg:gap-3"
          >
            <h2 class="text-xs lg:text-sm font-semibold flex items-center gap-2">
              <span
                class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-800 text-amber-300 text-sm"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  fill="currentColor"
                  class="bi bi-ticket-fill"
                  viewBox="0 0 16 16"
                >
                  <path
                    d="M1.5 3A1.5 1.5 0 0 0 0 4.5V6a.5.5 0 0 0 .5.5 1.5 1.5 0 1 1 0 3 .5.5 0 0 0-.5.5v1.5A1.5 1.5 0 0 0 1.5 13h13a1.5 1.5 0 0 0 1.5-1.5V10a.5.5 0 0 0-.5-.5 1.5 1.5 0 0 1 0-3A.5.5 0 0 0 16 6V4.5A1.5 1.5 0 0 0 14.5 3z"
                  />
                </svg>
              </span>
              Enter your unique code
            </h2>

            <p class="text-[12px] text-slate-300">
              Each participant receives a
              <span class="font-semibold text-amber-200">unique code</span>
              in their email when they join the group.
            </p>

            <div class="space-y-2">
              <label class="text-[11px] text-slate-400">Unique Code</label>
              <input
                v-model="voterCode"
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
                     hover:from-emerald-400 hover:to-emerald-200 active:scale-[0.98] transition"
              @click="verifyCode"
            >
              Verify Code
            </button>

            <!-- Progress -->
            <div
              class="mt-1 inline-flex items-center justify-between gap-3 px-3 py-2 rounded-2xl border border-slate-700 bg-slate-800/60 text-[11px]"
            >
              <div class="text-slate-200">
                🗳️ <span class="font-semibold">{{ votersTotal }}</span>/<span class="font-semibold">{{ participantsTotal }}</span>
                voted
              </div>
              <div
                v-if="sessionClosed"
                class="px-2 py-0.5 rounded-full bg-red-500/20 border border-red-400/60 text-red-200"
              >
                CLOSED
              </div>
            </div>

            <p
              v-if="codeError"
              class="text-[11px] bg-red-500/20 text-red-200 border border-red-400/60 rounded-xl px-3 py-2"
            >
              ⚠️ {{ codeError }}
            </p>

            <div
              v-if="voter"
              class="mt-2 text-[11px] bg-slate-800/80 border border-emerald-500/40 rounded-2xl px-3 py-2 text-emerald-100"
            >
              <div class="font-semibold mb-1">
                Code verified for:
                <span class="text-amber-200">
                  {{ voterDisplayName }}
                </span>
              </div>

              <p v-if="sessionClosed">
                Voting is closed. You can still view winners per award.
              </p>
              <p v-else-if="hasVoted">
                You already voted. Voting is one-time only.
              </p>
              <p v-else>
                You can now select nominees in the awards list. You’re allowed one-time voting per code.
              </p>
            </div>

            <div class="pt-4 border-t border-slate-800 text-[11px] text-slate-400">
              <p class="mb-1 font-semibold text-slate-300">Tip</p>
              <ul class="list-disc list-inside space-y-1">
                <li>Don’t share your unique code with others.</li>
                <li>Votes are counted per unique code, not per device.</li>
                <li>Once submitted, the code can’t vote again.</li>
              </ul>
            </div>
          </div>

          <!-- Right: Awards list -->
          <aside
            class="bg-slate-900/85 border border-slate-700 rounded-3xl shadow-xl p-5 space-y-4"
          >
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-lg font-semibold flex items-center gap-2">
                <span
                  class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-500 text-slate-900 text-sm"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    fill="currentColor"
                    class="bi bi-list-task"
                    viewBox="0 0 16 16"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"
                    />
                    <path
                      d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"
                    />
                    <path
                      fill-rule="evenodd"
                      d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"
                    />
                  </svg>
                </span>
                List of Awards
              </h2>

              <span
                v-if="voter"
                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] bg-emerald-500/15 border border-emerald-400/60 text-emerald-200"
              >
                ✅ Code Verified as:
                <span class="font-semibold">
                  {{ voterDisplayName }}
                </span>
              </span>
            </div>

            <p class="text-[12px] text-slate-300">
              Select one person per award.
              <span v-if="sessionClosed" class="text-red-200 font-semibold">
                Voting is closed — view winners.
              </span>
              <span v-else-if="hasVoted" class="text-amber-200 font-semibold">
                You already voted — locked.
              </span>
            </p>

            <div
              v-if="loadingParticipants"
              class="text-xs text-slate-300 italic py-6 text-center"
            >
              Loading participants...
            </div>

            <div
              v-else-if="loadError"
              class="text-xs text-red-200 bg-red-500/20 border border-red-400/60 rounded-2xl px-3 py-3"
            >
              {{ loadError }}
            </div>

            <div
              v-else-if="!participants.length"
              class="text-xs text-slate-300 italic py-6 text-center bg-slate-800/70 rounded-2xl border border-dashed border-slate-600"
            >
              No participants yet for this group.
            </div>

            <div v-else class="space-y-4">
              <!-- Voting Progress (when voter verified) -->
              <div
                v-if="voter && !hasVoted && !sessionClosed"
                class="p-3.5 bg-slate-800/90 rounded-2xl border border-amber-500/40 space-y-1.5"
              >
                <div class="flex items-center justify-between text-xs">
                  <span class="text-slate-200 font-semibold">Voting Progress</span>
                  <span class="text-amber-300 font-bold">
                    {{ votedCount }} / {{ awardCategories.length }} awards ({{ votingPercentage }}%)
                  </span>
                </div>
                <div class="h-2 rounded-full bg-slate-900 overflow-hidden">
                  <div
                    class="h-full rounded-full bg-gradient-to-r from-amber-400 to-emerald-400 transition-all duration-300"
                    :style="{ width: votingPercentage + '%' }"
                  ></div>
                </div>
              </div>

              <!-- Search Bar -->
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
                <input
                  v-model="searchQuery"
                  placeholder="Search awards by keyword or hashtag..."
                  class="w-full pl-8 pr-8 py-2 rounded-xl bg-slate-800 border border-slate-700 text-xs text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400"
                />
                <button
                  v-if="searchQuery"
                  type="button"
                  @click="searchQuery = ''"
                  class="absolute right-3 top-2 text-xs text-slate-400 hover:text-slate-200"
                >
                  ✕
                </button>
              </div>

              <!-- Category Filter Tabs -->
              <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                <button
                  type="button"
                  @click="selectedTab = 'all'"
                  :class="[
                    'px-3 py-1.5 rounded-xl font-semibold transition shrink-0',
                    selectedTab === 'all'
                      ? 'bg-amber-400 text-slate-950 shadow-md font-bold'
                      : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                  ]"
                >
                  🌟 All ({{ tabCounts.all }})
                </button>
                <button
                  type="button"
                  @click="selectedTab = 'attendance'"
                  :class="[
                    'px-3 py-1.5 rounded-xl font-semibold transition shrink-0',
                    selectedTab === 'attendance'
                      ? 'bg-amber-400 text-slate-950 shadow-md font-bold'
                      : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                  ]"
                >
                  🕒 Attendance ({{ tabCounts.attendance }})
                </button>
                <button
                  type="button"
                  @click="selectedTab = 'personality'"
                  :class="[
                    'px-3 py-1.5 rounded-xl font-semibold transition shrink-0',
                    selectedTab === 'personality'
                      ? 'bg-amber-400 text-slate-950 shadow-md font-bold'
                      : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                  ]"
                >
                  🎭 Personality ({{ tabCounts.personality }})
                </button>
                <button
                  type="button"
                  @click="selectedTab = 'food'"
                  :class="[
                    'px-3 py-1.5 rounded-xl font-semibold transition shrink-0',
                    selectedTab === 'food'
                      ? 'bg-amber-400 text-slate-950 shadow-md font-bold'
                      : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                  ]"
                >
                  🍻 Food & Drink ({{ tabCounts.food }})
                </button>
                <button
                  type="button"
                  @click="selectedTab = 'money'"
                  :class="[
                    'px-3 py-1.5 rounded-xl font-semibold transition shrink-0',
                    selectedTab === 'money'
                      ? 'bg-amber-400 text-slate-950 shadow-md font-bold'
                      : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                  ]"
                >
                  💸 Money ({{ tabCounts.money }})
                </button>
              </div>

              <!-- Empty state if search/filter yields zero results -->
              <div
                v-if="!filteredCategoryGroups.length"
                class="text-xs text-slate-400 italic py-6 text-center bg-slate-800/40 rounded-2xl border border-dashed border-slate-700"
              >
                No awards match your filter or search query "{{ searchQuery }}".
              </div>

              <!-- Loop per filtered category group -->
              <section
                v-for="group in filteredCategoryGroups"
                :key="group.key"
                class="space-y-3"
              >
                <!-- Group header -->
                <div class="flex items-center gap-2">
                  <div class="h-px flex-1 bg-slate-700/60"></div>
                  <span
                    class="text-[11px] uppercase tracking-[0.16em] text-amber-300/80"
                  >
                    {{ group.label }}
                  </span>
                  <div class="h-px flex-1 bg-slate-700/60"></div>
                </div>

                <!-- Awards inside group -->
                <article
                  v-for="award in group.awards"
                  :key="award.key"
                  class="rounded-2xl border border-slate-700 bg-slate-900/80 p-4 space-y-3"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <h3 class="text-sm font-semibold text-amber-200">
                        {{ award.label }}
                      </h3>
                      <p class="text-[11px] text-slate-300 mt-0.5">
                        {{ award.description }}
                      </p>
                    </div>

                    <button
                      v-if="sessionClosed"
                      type="button"
                      class="shrink-0 text-[11px] px-3 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-200 hover:bg-slate-700/60"
                      @click="openWinnerModal(award.key)"
                    >
                      View Winner
                    </button>
                  </div>

                  <div class="mt-1">
                    <label class="text-[11px] text-slate-400 mb-1 block">
                      Choose a nominee
                    </label>

                    <select
                      class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm text-slate-50
                             focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
                      :value="selectedNominees[award.key] || ''"
                      @change="
                        handleNomineeChange(
                          award.key,
                          Number($event.target.value) || null
                        )
                      "
                      :disabled="isVotingLocked"
                    >
                      <option value="">-- Select participant --</option>
                      <option v-for="p in participants" :key="p.id" :value="p.id">
                        {{ p.name }}
                      </option>
                    </select>

                    <p v-if="!voter" class="text-[10px] text-slate-400 mt-1 italic">
                      Enter and verify your unique code first to enable voting.
                    </p>
                    <p v-else-if="sessionClosed" class="text-[10px] text-red-200 mt-1 italic">
                      Voting is closed. You can only view winners.
                    </p>
                    <p v-else-if="hasVoted" class="text-[10px] text-amber-200 mt-1 italic">
                      You already voted. Voting is one-time only.
                    </p>
                  </div>
                </article>
              </section>

              <!-- Submit section -->
              <div class="pt-2 flex flex-col gap-2">
                <button
                  type="button"
                  class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl
                         bg-gradient-to-r from-amber-500 via-yellow-400 to-emerald-300
                         text-slate-900 text-sm font-semibold shadow-md
                         hover:from-amber-400 hover:via-yellow-300 hover:to-emerald-200
                         active:scale-[0.98] transition
                         disabled:opacity-40 disabled:cursor-not-allowed"
                  :disabled="!canSubmit || submitting"
                  @click="submitVotes"
                >
                  <span v-if="submitting">Submitting votes...</span>
                  <span v-else>Submit Votes</span>
                </button>

                <p
                  v-if="submitMessage"
                  class="text-[11px] bg-emerald-500/15 text-emerald-100 border border-emerald-400/60 rounded-xl px-3 py-2"
                >
                  {{ submitMessage }}
                </p>

                <p
                  v-if="submitError"
                  class="text-[11px] bg-red-500/20 text-red-200 border border-red-400/60 rounded-xl px-3 py-2"
                >
                  ⚠️ {{ submitError }}
                </p>
              </div>
            </div>
          </aside>
        </section>
      </div>
    </main>

    <!-- Winner Modal -->
    <div v-if="showResultModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/60" @click="closeWinnerModal"></div>

      <div class="relative w-full max-w-xl rounded-3xl border border-slate-700 bg-slate-900 text-slate-100 shadow-2xl p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h3 class="text-lg font-bold truncate">
              🏆 Winner — {{ awardMetaMap.get(modalAwardKey)?.label || modalAwardKey }}
            </h3>
            <p class="text-[12px] text-slate-300">
              {{ awardMetaMap.get(modalAwardKey)?.description || "" }}
            </p>
          </div>

          <button class="text-slate-300 hover:text-white" @click="closeWinnerModal">✕</button>
        </div>

        <div class="mt-4" v-if="loadingResults">
          <p class="text-sm text-slate-300 italic">Loading results...</p>
        </div>

        <div class="mt-4" v-else>
          <p v-if="resultsError" class="text-[11px] bg-red-500/20 text-red-200 border border-red-400/60 rounded-xl px-3 py-2">
            ⚠️ {{ resultsError }}
          </p>

          <template v-else-if="resultsMap?.[modalAwardKey]">
            <div class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4">
              <div class="text-[12px] text-slate-200">
                <span class="font-semibold">Top:</span>
                <span
                  v-for="(w, idx) in resultsMap[modalAwardKey].winners"
                  :key="w.id"
                >
                  <span class="font-semibold text-amber-200">{{ w.name }}</span>
                  <span class="text-slate-300"> ({{ w.votes }} votes)</span>
                  <span v-if="idx < resultsMap[modalAwardKey].winners.length - 1">, </span>
                </span>
                <span v-if="resultsMap[modalAwardKey].is_tie" class="ml-2 text-red-200">
                  (Tie)
                </span>
              </div>
            </div>

            <div class="mt-4">
              <h4 class="text-sm font-semibold text-slate-200 mb-2">Breakdown</h4>
              <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                <div
                  v-for="n in resultsMap[modalAwardKey].nominees"
                  :key="n.id"
                  class="flex items-center justify-between rounded-2xl border border-slate-700 bg-slate-800/60 px-3 py-2"
                >
                  <span class="text-sm">{{ n.name }}</span>
                  <span class="text-sm font-semibold text-emerald-200">{{ n.votes }}</span>
                </div>
              </div>
            </div>
          </template>

          <p v-else class="text-sm text-slate-300 italic">
            No votes yet for this award.
          </p>
        </div>
      </div>
    </div>

    <!-- 🏆 Fullscreen Ceremony / Projector Mode Modal -->
    <div
      v-if="showCeremonyMode"
      class="fixed inset-0 z-50 flex flex-col bg-slate-950 text-slate-100 overflow-y-auto"
    >
      <!-- Top Bar of Ceremony -->
      <div class="px-6 py-4 flex items-center justify-between border-b border-amber-500/30 bg-slate-900/90 backdrop-blur">
        <div class="flex items-center gap-3">
          <span class="text-2xl animate-pulse">🏆</span>
          <div>
            <div class="text-xs uppercase font-extrabold tracking-widest text-amber-400">
              Live Stage Ceremony
            </div>
            <div class="text-sm font-bold text-slate-200">
              Award {{ ceremonyIndex + 1 }} of {{ awardCategories.length }}
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- Stepper buttons -->
          <button
            type="button"
            :disabled="ceremonyIndex === 0"
            @click="prevCeremonyAward"
            class="px-3 py-1.5 rounded-xl bg-slate-800 text-xs font-semibold hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition"
          >
            ← Prev
          </button>
          <button
            type="button"
            :disabled="ceremonyIndex === awardCategories.length - 1"
            @click="nextCeremonyAward"
            class="px-3 py-1.5 rounded-xl bg-slate-800 text-xs font-semibold hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition"
          >
            Next →
          </button>
          <button
            type="button"
            @click="closeCeremonyMode"
            class="ml-2 px-3 py-1.5 rounded-xl bg-red-600/30 text-red-200 border border-red-500/40 text-xs font-semibold hover:bg-red-600/50 transition"
          >
            Exit Stage
          </button>
        </div>
      </div>

      <!-- Main Stage Container -->
      <div class="flex-1 flex flex-col items-center justify-center p-6 max-w-4xl mx-auto w-full text-center space-y-6">
        <!-- Category Badge -->
        <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-amber-400/20 text-amber-300 font-bold uppercase tracking-wider text-xs border border-amber-400/40">
          <span>✨</span>
          <span>{{ currentCeremonyAward?.category.toUpperCase() }}</span>
        </div>

        <!-- Award Title -->
        <div class="space-y-2">
          <h2 class="text-3xl sm:text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-200 via-yellow-300 to-amber-100 tracking-tight">
            {{ currentCeremonyAward?.label }}
          </h2>
          <p class="text-base sm:text-lg text-slate-300 italic font-mono">
            {{ currentCeremonyAward?.description }}
          </p>
        </div>

        <!-- Stage Card: Winner Reveal or Suspense -->
        <div class="w-full max-w-xl mx-auto bg-gradient-to-b from-slate-900 to-slate-900/90 border-2 border-amber-400/50 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 relative overflow-hidden">
          <div class="absolute -top-12 -right-12 w-40 h-40 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

          <!-- If loading results -->
          <div v-if="loadingResults" class="py-12 text-slate-400 italic">
            Tallying votes from participants...
          </div>

          <!-- If no votes for this award -->
          <div v-else-if="!currentCeremonyResult || !currentCeremonyResult.winners?.length" class="py-10 text-slate-400 italic">
            No votes have been submitted for this category yet.
          </div>

          <!-- Unrevealed State: Mystery Stage -->
          <div v-else-if="!ceremonyRevealed" class="space-y-6 py-4">
            <div class="text-6xl animate-bounce">
              🎁
            </div>
            <div class="space-y-1">
              <h3 class="text-xl font-bold text-amber-200">The envelope is sealed!</h3>
              <p class="text-xs text-slate-400">Total votes counted: {{ currentCeremonyResult.total_votes || currentCeremonyResult.nominees?.reduce((a, b) => a + b.votes, 0) || 'Active' }}</p>
            </div>

            <!-- Reveal Trigger -->
            <button
              type="button"
              @click="triggerRevealCeremonyWinner"
              :disabled="revealingWinner"
              class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-amber-400 via-yellow-300 to-amber-400 text-slate-950 font-black text-base uppercase tracking-wider shadow-2xl hover:scale-105 active:scale-95 transition disabled:opacity-60"
            >
              <span v-if="revealingWinner">🥁 Drumroll... Unveiling Winner! 🥁</span>
              <span v-else>🏆 Open Envelope & Reveal Winner!</span>
            </button>
          </div>

          <!-- Revealed Winner Presentation -->
          <div v-else class="space-y-6 animate-fade-in">
            <div class="inline-flex items-center justify-center h-20 w-20 rounded-3xl bg-amber-400 text-slate-950 text-4xl shadow-xl shadow-amber-400/20">
              👑
            </div>

            <div class="space-y-2">
              <div class="text-xs uppercase tracking-widest text-amber-400 font-extrabold">The Winner is</div>
              <div class="text-3xl sm:text-4xl font-extrabold text-white">
                <span v-for="(w, idx) in currentCeremonyResult.winners" :key="w.id">
                  {{ w.name }}
                  <span v-if="idx < currentCeremonyResult.winners.length - 1"> & </span>
                </span>
              </div>
              <div class="text-xs text-emerald-300 font-semibold">
                {{ currentCeremonyResult.winners[0]?.votes }} votes won!
                <span v-if="currentCeremonyResult.is_tie" class="ml-1 text-amber-300">(Tie Winner)</span>
              </div>
            </div>

            <!-- Nominees Runner-up Breakdown -->
            <div class="pt-4 border-t border-slate-800 text-left space-y-2">
              <div class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold">Top Nominees Breakdown:</div>
              <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                <div
                  v-for="n in currentCeremonyResult.nominees"
                  :key="n.id"
                  class="flex items-center justify-between px-3 py-1.5 rounded-xl bg-slate-800/80 text-xs border border-slate-700/60"
                >
                  <span class="text-slate-200 font-medium">{{ n.name }}</span>
                  <span class="text-amber-300 font-bold font-mono">{{ n.votes }} votes</span>
                </div>
              </div>
            </div>

            <!-- Stage Control in Revealed State -->
            <div class="flex items-center justify-center gap-3 pt-2">
              <button
                type="button"
                @click="triggerRevealCeremonyWinner"
                class="px-4 py-2 rounded-xl bg-slate-800 text-amber-300 text-xs font-bold border border-slate-700 hover:bg-slate-700"
              >
                🎉 Confetti Encore
              </button>
              <button
                v-if="ceremonyIndex < awardCategories.length - 1"
                type="button"
                @click="nextCeremonyAward"
                class="px-5 py-2 rounded-xl bg-gradient-to-r from-amber-400 to-yellow-300 text-slate-950 text-xs font-bold shadow hover:from-amber-300 hover:to-yellow-200"
              >
                Next Award →
              </button>
            </div>
          </div>
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
