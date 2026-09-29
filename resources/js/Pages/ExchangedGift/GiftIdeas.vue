<!-- GiftIdeas.vue -->
<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { Link, router } from "@inertiajs/vue3";

const props = defineProps({
  projects: {
    type: Array,
    default: () => [],
  },
  currentProjectId: {
    type: Number,
    required: true,
  },
  currentPage: {
    type: String,
    default: "ideas",
  },
  currentProjectSlug: {
    type: String,
    required: true,
  },
});

const LIST_TYPE = "classic";

const projects = ref(props.projects);
const currentProjectId = ref(props.currentProjectId);
const currentProjectSlug = ref(props.currentProjectSlug);

const participants = ref([]);
const giftIdeas = ref([]);
const loading = ref(false);

const newProjectName = ref("");

// Self-lookup (optional; kept in case you use it elsewhere)
const lookupEmail = ref("");
const lookupMessage = ref("");
const lookupError = ref("");
const sendingTheme = ref(false);

// bulk generate + send
const bulkSending = ref(false);
const bulkMessage = ref("");
const bulkError = ref("");

// --- Santa Secret Code modal state (generate only) ---
const showCodeModal = ref(false);
const codeInput = ref("");
const codeError = ref("");
const verifyingCode = ref(false);

// --- Current project name ---
const currentProject = computed(() =>
  projects.value.find((p) => Number(p.id) === Number(currentProjectId.value))
);

const currentProjectName = computed(() =>
  currentProject.value ? currentProject.value.name : "Untitled Project"
);

const participantCount = computed(() => participants.value.length);

// has at least one idea (for email button if you use it)
const hasIdeas = computed(() => giftIdeas.value.length > 0);

// all participants already have ideas (for disabling Generate button)
const allHaveIdeas = computed(() => {
  if (!participants.value.length) return false;
  const ideaIds = new Set(giftIdeas.value.map((i) => i.id)); // id = participant_id
  return participants.value.every((p) => ideaIds.has(p.id));
});

// helper for status column
const hasIdeaFor = (participantId) => giftIdeas.value.some((i) => i.id === participantId);

// ---------------------
// Word pools
// ---------------------
const descriptorWords = [
  "smooth",
  "to drink",
  "fluffy",
  "rough",
  "soft",
  "tiny",
  "huge",
  "brown",
  "to eat",
  "orange",
  "square",
  "round",
  "sparkly",
  "useful",
  "shiny",
  "to smell",
  "silver",
  "white",
  "black",
  "cute",
  "minimalist",
  "red",
  "green",
  "funny",
  "to wear",
];

const colorWords = [
  "red",
  "green",
  "blue",
  "yellow",
  "gold",
  "silver",
  "white",
  "black",
  "pink",
  "purple",
  "teal",
  "brown",
  "orange",
];

const randomFrom = (arr) => arr[Math.floor(Math.random() * arr.length)];

// ---------------------
// Data loading (per project)
// ---------------------
const fetchData = async () => {
  loading.value = true;
  try {
    const [dataRes, ideasRes] = await Promise.all([
      axios.get("/exchange/data", {
        params: { project_id: currentProjectId.value, list_type: LIST_TYPE },
      }),
      axios.get("/exchange/ideas", {
        params: { project_id: currentProjectId.value, list_type: LIST_TYPE },
      }),
    ]);

    participants.value = dataRes.data.participants || [];
    giftIdeas.value = ideasRes.data.ideas || [];
  } catch (e) {
    console.error("Error loading data:", e);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);

// ---------------------
// Project handling (slug-based like GenerateNames.vue)
// ---------------------
const changeProject = (id) => {
  const numericId = Number(id);
  const project = projects.value.find((p) => Number(p.id) === numericId);
  if (!project) return;

  currentProjectId.value = project.id;
  currentProjectSlug.value = project.slug;

  router.get(`/exchange-gift/${project.slug}/ideas`, {}, { preserveScroll: true });
};

const createProject = async () => {
  const name = newProjectName.value.trim();
  if (!name) return;

  try {
    const { data } = await axios.post("/exchange/projects", { name });
    projects.value.push(data);
    newProjectName.value = "";
    changeProject(data.id);
  } catch (e) {
    console.error("Error creating project:", e);
  }
};

// ---------------------
// Theme generator (unique + bulk email)
// -> Only generate for participants who don't have an idea yet
// -> Ensure themes are unique per project (no duplicates)
// ---------------------
const doGenerateGiftIdeas = async () => {
  if (!participants.value.length || bulkSending.value) return;

  bulkMessage.value = "";
  bulkError.value = "";
  bulkSending.value = true;
  loading.value = true;

  try {
    // map existing ideas by participant id, keep them stable
    const existingById = new Map(giftIdeas.value.map((i) => [i.id, i]));

    // Track already used theme strings (case-insensitive)
    const usedThemes = new Set(
      giftIdeas.value
        .map((i) => (i.something || "").trim().toLowerCase())
        .filter(Boolean)
    );

    const newIdeas = participants.value.map((p) => {
      const existing = existingById.get(p.id);
      if (existing) {
        if (existing.something) usedThemes.add(existing.something.trim().toLowerCase());
        return existing;
      }

      // Generate a unique theme
      let something = "";
      let attempts = 0;

      do {
        const descriptor = randomFrom(descriptorWords);
        const color = randomFrom(colorWords);

        // pick one style:
        // something = `${descriptor} and ${color}`;
        something = `${descriptor}`;
        // If you want color included, uncomment:
        // something = Math.random() < 0.5 ? `${descriptor}` : `${color}`;

        attempts++;
      } while (usedThemes.has(something.toLowerCase()) && attempts < 200);

      usedThemes.add(something.toLowerCase());

      return {
        id: p.id,
        name: p.name,
        something,
        phrase: `${something} something`,
      };
    });

    giftIdeas.value = newIdeas;

    // 1) Save all themes
    await axios.post("/exchange/ideas/save", {
      project_id: currentProjectId.value,
      ideas: giftIdeas.value.map((idea) => ({
        participant_id: idea.id,
        something: idea.something,
        phrase: idea.phrase,
      })),
    });

    // 2) Email themes to all participants
    const { data } = await axios.post("/exchange/ideas/send-all", {
      project_id: currentProjectId.value,
      list_type: LIST_TYPE,
    });

    bulkMessage.value =
      data.message || "Themes generated, saved, and emailed to participants.";
  } catch (e) {
    console.error("Error saving or emailing ideas:", e);
    bulkError.value =
      e.response?.data?.message ||
      "Themes were saved, but we couldn’t send all emails.";
  } finally {
    bulkSending.value = false;
    loading.value = false;
  }
};

const askSecretCodeForGenerate = () => {
  codeInput.value = "";
  codeError.value = "";
  showCodeModal.value = true;
};

// ---------------------
// Verify Santa Secret Code & run action
// ---------------------
const verifyCodeAndRun = async () => {
  codeError.value = "";
  const code = codeInput.value.trim();

  if (!code) {
    codeError.value = "Please enter Santa's Secret Code.";
    return;
  }

  verifyingCode.value = true;

  try {
    const { data } = await axios.post("/exchange/verify-santa-code", {
      project_id: currentProjectId.value,
      code,
    });

    if (!data.valid) {
      codeError.value = "Santa's Secret Code is incorrect.";
      return;
    }

    // ✅ Code is valid: generate
    showCodeModal.value = false;
    await doGenerateGiftIdeas();
  } catch (e) {
    console.error("Error verifying Santa Secret Code:", e);
    codeError.value = "Unable to verify code right now. Please try again.";
  } finally {
    verifyingCode.value = false;
  }
};

const closeCodeModal = () => {
  if (verifyingCode.value) return;
  showCodeModal.value = false;
  codeInput.value = "";
  codeError.value = "";
};

// ---------------------
// Send my result to email (self-lookup) - OPTIONAL
// ---------------------
const sendMyTheme = async () => {
  lookupMessage.value = "";
  lookupError.value = "";

  const email = lookupEmail.value.trim();
  if (!email || !hasIdeas.value || sendingTheme.value) return;

  sendingTheme.value = true;

  try {
    const { data } = await axios.post("/exchange/ideas/send", {
      project_id: currentProjectId.value,
      email,
    });

    lookupMessage.value = data.message || "Theme sent to your email.";
    lookupEmail.value = "";
  } catch (e) {
    lookupError.value =
      e.response?.data?.message || "Something went wrong sending the email.";
  } finally {
    sendingTheme.value = false;
  }
};

// ---------------------
// Snowflakes
// ---------------------
const SNOWFLAKE_COUNT = 70;
const snowflakes = ref([]);

onMounted(() => {
  const flakeArray = [];
  for (let i = 0; i < SNOWFLAKE_COUNT; i++) {
    flakeArray.push({
      id: i,
      left: Math.random() * 100,
      size: 2 + Math.random() * 4,
      duration: 8 + Math.random() * 10,
      delay: Math.random() * -20,
      opacity: 0.3 + Math.random() * 0.7,
    });
  }
  snowflakes.value = flakeArray;
});
</script>

<template>
  <div
    class="relative min-h-screen bg-gradient-to-b from-red-900 via-slate-950 to-slate-900 text-slate-100 overflow-hidden"
  >
    <!-- Random falling snow -->
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden snow-container">
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

    <!-- Header -->
    <header
      class="relative z-10 bg-gradient-to-r from-red-700 via-red-600 to-rose-600/90 text-white shadow-lg border-b border-red-500/50"
    >
      <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div
            class="h-11 w-11 rounded-2xl bg-white/20 flex items-center justify-center text-2xl shadow-inner"
          >
            🎄
          </div>
          <div>
            <h1 class="text-2xl font-bold tracking-tight">
              Exchange Gift Idea | Something... Gift Exchange
            </h1>
            <p class="text-xs md:text-sm text-red-100/90">
              {{ participantCount }} participant<span v-if="participantCount !== 1">s</span>
              · generate a themed “something” for each person
            </p>
          </div>
        </div>

        <div class="hidden md:flex flex-col items-end text-[11px] text-red-50/90">
          <span class="uppercase tracking-[0.18em] text-[10px]">
            <Link :href="`/${currentProjectSlug}/menu`">&lt;&lt; BACK</Link>
          </span>
        </div>
      </div>
    </header>

    <!-- Step indicator -->
    <div class="relative z-10 max-w-5xl mx-auto px-4 mt-4">
      <div
        class="bg-white/10 backdrop-blur rounded-2xl border border-white/20 shadow-sm px-3 py-2 flex flex-wrap items-center justify-center gap-3 text-[11px] md:text-xs text-red-50"
      >
        <div class="flex items-center gap-1.5">
          <span
            class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400 text-white text-[10px] font-bold"
          >
            1
          </span>
          <span>Confirm participants</span>
        </div>
        <span class="hidden md:inline text-red-100/60">•</span>
        <div class="flex items-center gap-1.5">
          <span
            class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-300 text-red-900 text-[10px] font-bold"
          >
            2
          </span>
          <span>Generate “Something...”</span>
        </div>
        <span class="hidden md:inline text-red-100/60">•</span>
        <div class="flex items-center gap-1.5">
          <span
            class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-sky-300 text-slate-900 text-[10px] font-bold"
          >
            3
          </span>
          <span>Send themes to participants</span>
        </div>
      </div>
    </div>

    <main class="relative z-10 max-w-5xl mx-auto px-4 py-6 space-y-6">
      <!-- Rules (kept) -->
      <section class="bg-slate-900/80 backdrop-blur rounded-3xl shadow-xl border border-red-500/40 p-5">
        <h2 class="font-semibold text-lg flex items-center gap-2 mb-2">
          <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600/90 text-white text-sm">
            📜
          </span>
          How it works
        </h2>
        <ul class="text-sm space-y-2 list-disc ml-4 text-slate-100">
          <li>Participants are managed in the main Group Services page.</li>
          <li>Each participant gets one secret themed “something”.</li>
          <li>The theme is emailed privately to each participant.</li>
          <li>This page intentionally does not show the actual theme text.</li>
        </ul>
      </section>

      <!-- Themes section -->
      <section class="bg-slate-900/80 backdrop-blur rounded-3xl shadow-xl border border-red-500/40 p-5 space-y-4">
        <div class="flex flex-col gap-3">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <span
                class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-400 text-red-900 text-sm"
              >
                🎁
              </span>
              <h2 class="font-semibold text-lg">Themed “Something...” List</h2>
            </div>

            <div
              class="flex items-center gap-2 bg-slate-800/80 rounded-2xl px-3 py-2 border border-slate-600 text-sm"
            >
              <div class="flex flex-col text-right">
                <span class="text-[11px] text-slate-400 uppercase tracking-[0.18em]">
                  Participants
                </span>
                <span class="font-semibold text-slate-50">
                  {{ participants.length }}
                </span>
              </div>
            </div>
          </div>

          <p class="text-xs text-slate-300">
            Generate one theme per participant (unique per project), save it, then email everyone.
          </p>

          <button
            type="button"
            class="self-start px-4 py-2.5 rounded-xl bg-gradient-to-r from-red-500 via-red-400 to-amber-300 text-slate-900 text-sm font-semibold shadow-sm
                   hover:from-red-400 hover:via-amber-300 hover:to-yellow-200 active:scale-[0.98] transition
                   disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="!participants.length || loading || allHaveIdeas || bulkSending"
            @click="askSecretCodeForGenerate"
          >
            <span v-if="!bulkSending">Generate “Something...” &amp; Send to Participants</span>
            <span v-else>Generating &amp; emailing…</span>
          </button>
        </div>

        <p v-if="bulkMessage" class="text-[11px] text-emerald-300 mt-1">
          {{ bulkMessage }}
        </p>
        <p v-if="bulkError" class="text-[11px] text-red-300 mt-1">
          {{ bulkError }}
        </p>

        <!-- Table WITHOUT exposing actual "something" -->
        <div class="overflow-x-auto rounded-2xl border border-slate-700 shadow-sm bg-slate-950/60">
          <table v-if="participants.length" class="min-w-full text-xs md:text-sm">
            <thead class="bg-slate-900/80">
              <tr>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-700">
                  Participant
                </th>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-700">
                  Theme Status
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="p in participants"
                :key="p.id"
                class="odd:bg-slate-900/80 even:bg-slate-900/60"
              >
                <td class="px-3 py-2 border-b border-slate-800 font-semibold text-slate-50">
                  {{ p.name }}
                </td>

                <td class="px-3 py-2 border-b border-slate-800 text-slate-100">
                  <span
                    v-if="hasIdeaFor(p.id)"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-400/50 text-[11px] text-emerald-200"
                  >
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300" />
                    Theme generated
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-700/40 border border-slate-500/70 text-[11px] text-slate-200"
                  >
                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400" />
                    Pending
                  </span>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-if="allHaveIdeas && participants.length" class="text-[11px] text-emerald-300 px-4 py-3">
            Themes have been generated and distributed to all participants in this group. 🎁
          </p>

          <p v-else-if="!participants.length" class="text-[11px] text-slate-300 italic px-4 py-3">
            No participants found for this project yet. Please add participants from Group Services.
          </p>

          <p v-else class="text-[11px] text-slate-300 italic px-4 py-3">
            Click <strong>Generate “Something...” &amp; Send to Participants</strong> to assign themes to members that are still <strong>Pending</strong>.
          </p>
        </div>
      </section>
    </main>

    <!-- Santa Secret Code Modal -->
    <div
      v-if="showCodeModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
    >
      <div
        class="bg-slate-950/95 border border-amber-400/60 rounded-3xl shadow-2xl w-full max-w-sm p-5 space-y-4 relative"
      >
        <button
          type="button"
          class="absolute top-3 right-3 text-slate-400 hover:text-slate-100 text-sm"
          @click="closeCodeModal"
          :disabled="verifyingCode"
        >
          ✕
        </button>

        <div class="flex items-center gap-3">
          <div
            class="h-9 w-9 rounded-2xl bg-amber-300 flex items-center justify-center text-xl text-red-900 shadow-inner"
          >
            🔐
          </div>
          <div>
            <h3 class="text-base font-semibold text-amber-100">Enter Santa's Secret Code</h3>
            <p class="text-[11px] text-slate-300">
              This action is for the organizer only. Please type the Santa Secret Code sent to your email.
            </p>
          </div>
        </div>

        <div class="space-y-2">
          <label class="text-[11px] text-slate-200">Santa's Secret Code</label>
          <input
            v-model="codeInput"
            type="password"
            autocomplete="one-time-code"
            placeholder="••••••••"
            class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-900/80 text-sm text-slate-50
                   focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                   placeholder:text-slate-500"
          />
          <p v-if="codeError" class="text-[11px] text-red-300 mt-1">
            {{ codeError }}
          </p>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button
            type="button"
            class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-200 hover:text-white hover:bg-slate-800/80"
            @click="closeCodeModal"
            :disabled="verifyingCode"
          >
            Cancel
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-amber-300 text-red-900 text-xs font-semibold hover:bg-amber-200
                   disabled:opacity-40 disabled:cursor-not-allowed"
            @click="verifyCodeAndRun"
            :disabled="verifyingCode"
          >
            <span v-if="!verifyingCode">Confirm</span>
            <span v-else>Checking…</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.snow-container {
  pointer-events: none;
}

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
