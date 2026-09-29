<!-- GenerateNames.vue -->
<script setup>
import { ref, computed, onMounted } from "vue";
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
    default: "classic",
  },
  currentProjectSlug: {
    type: String,
    required: true,
  },
});

// This page uses the "classic" list (Small Gifts)
const LIST_TYPE = "classic";
const MAX_GIFTS_PER_PERSON = 5;

const projects = ref(props.projects);
const currentProjectId = ref(props.currentProjectId);
const currentProjectSlug = ref(props.currentProjectSlug);

const participants = ref([]); // still needed for counts + emailing
const assignments = ref([]);
const selectedCard = ref(null);
const loading = ref(false);

const newProjectName = ref("");

// email sending for secret list (per email field at bottom)
const emailForCard = ref("");
const emailSendMessage = ref("");
const emailSendError = ref("");
const sendingCardEmail = ref(false);

// bulk sending assignments right after generation
const sendingAllAssignments = ref(false);
const sendAllMessage = ref("");
const sendAllError = ref("");

// for per-row resend
const resendMessage = ref("");
const resendError = ref("");
const resendLoadingId = ref(null);

// --- Santa Secret Code modal state ---
const isSecretModalOpen = ref(false);
const secretCodeInput = ref("");
const secretCodeError = ref("");
const secretCodeLoading = ref(false);

// what action are we protecting? "generate" | "view-card"
const secretAction = ref(null);
/**
 * payload structure by action:
 * - generate:   null
 * - view-card:  { giverId: number }
 */
const secretPayload = ref(null);

// --- Current project name ---
const currentProject = computed(() =>
  projects.value.find((p) => Number(p.id) === Number(currentProjectId.value))
);

const currentProjectName = computed(() =>
  currentProject.value ? currentProject.value.name : "Untitled Project"
);

// --- Load data for this project ---
const fetchData = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get("/exchange/data", {
      params: {
        project_id: currentProjectId.value,
        list_type: LIST_TYPE,
      },
    });

    participants.value = data.participants || [];
    assignments.value = data.assignments || [];
  } catch (e) {
    console.error("Error loading exchange data:", e);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);

// --- Computed helpers ---
const participantCount = computed(() => participants.value.length);

// Dynamic gifts per person: capped at MAX_GIFTS_PER_PERSON.
const giftsEach = computed(() => {
  const n = participantCount.value;
  if (n <= 1) return 0;
  const dynamic = n - 1;
  return dynamic > MAX_GIFTS_PER_PERSON ? MAX_GIFTS_PER_PERSON : dynamic;
});

// Have we already generated assignments for this project?
const hasAssignments = computed(() => assignments.value.length > 0);

// Only generate if: at least 2 participants, gifts > 0, and not generated yet
const canGenerateAssignments = computed(
  () =>
    participantCount.value >= 2 &&
    giftsEach.value > 0 &&
    !hasAssignments.value
);

// -----------------------------
// Santa Secret Code helpers
// -----------------------------
const openSecretModal = (actionType, payload = null) => {
  secretAction.value = actionType;
  secretPayload.value = payload;
  secretCodeInput.value = "";
  secretCodeError.value = "";
  isSecretModalOpen.value = true;
};

const closeSecretModal = () => {
  isSecretModalOpen.value = false;
  secretAction.value = null;
  secretPayload.value = null;
  secretCodeInput.value = "";
  secretCodeError.value = "";
};

// openers for specific actions
const askSecretCodeForGenerate = () => {
  if (!canGenerateAssignments.value) return;
  openSecretModal("generate", null);
};

const askSecretCodeForViewCard = (giverId) => {
  openSecretModal("view-card", { giverId });
};

const verifySantaAndRun = async () => {
  secretCodeError.value = "";
  const code = secretCodeInput.value.trim();

  if (!code) {
    secretCodeError.value = "Please enter the Santa Secret Code.";
    return;
  }

  secretCodeLoading.value = true;

  try {
    const { data } = await axios.post("/exchange/verify-santa-code", {
      project_id: currentProjectId.value,
      code,
    });

    if (!data.valid) {
      secretCodeError.value = "Incorrect Santa Secret Code.";
      return;
    }

    // ✅ Valid code – perform the pending action
    const actionType = secretAction.value;
    const payload = secretPayload.value;

    closeSecretModal();

    if (actionType === "generate") {
      await doGenerateAssignments();
    } else if (actionType === "view-card" && payload?.giverId) {
      openCard(payload.giverId);
    }
  } catch (e) {
    console.error("Error verifying Santa Secret Code:", e);
    secretCodeError.value =
      "Unable to verify code right now. Please try again.";
  } finally {
    secretCodeLoading.value = false;
  }
};

// --- Assignments ---
const openCard = (giverId) => {
  const row = assignments.value.find((a) => a.giverId === giverId) || null;
  selectedCard.value = row;
};

// bulk: send assignments to all participants with email
const sendAssignmentsToAllParticipants = async (mode = "initial") => {
  // mode: "initial" (right after generate) or "reminder" (Remind Everyone button)
  sendingAllAssignments.value = true;
  sendAllMessage.value = "";
  sendAllError.value = "";

  const recipients = participants.value.filter(
    (p) => p.email && p.email.trim() !== ""
  );

  if (!recipients.length) {
    sendAllMessage.value =
      mode === "initial"
        ? "Assignments generated, but no participants with email addresses were found."
        : "No participants with email addresses were found for reminders.";
    sendingAllAssignments.value = false;
    return;
  }

  let successCount = 0;

  for (const p of recipients) {
    try {
      await axios.post("/exchange/assignments/email", {
        project_id: currentProjectId.value,
        email: p.email,
        reminder: mode === "reminder",
      });
      successCount++;
    } catch (e) {
      console.error("Error sending assignment email to", p.email, e);
      // continue to next participant
    }
  }

  sendAllMessage.value =
    mode === "initial"
      ? `Assignments generated and emailed to ${successCount} participant${
          successCount === 1 ? "" : "s"
        }.`
      : `Reminder emails sent to ${successCount} participant${
          successCount === 1 ? "" : "s"
        }.`;

  sendingAllAssignments.value = false;
};

// real generate logic (protected by secret modal)
const doGenerateAssignments = async () => {
  if (!canGenerateAssignments.value || giftsEach.value === 0) return;

  loading.value = true;
  sendAllMessage.value = "";
  sendAllError.value = "";

  try {
    // 1) Generate assignments
    const { data } = await axios.post("/exchange/generate", {
      project_id: currentProjectId.value,
      gifts_each: giftsEach.value, // let backend know our capped value
    });

    assignments.value = Array.isArray(data) ? data : data.assignments || [];
    selectedCard.value = null;

    // 2) After successful generation, email assignments to all participants
    await sendAssignmentsToAllParticipants("initial");
  } catch (e) {
    console.error("Error generating assignments:", e);
    sendAllError.value =
      "Assignments could not be generated and emailed. Please try again.";
  } finally {
    loading.value = false;
  }
};

// Remind everyone (re-send assignment emails to all with email)
const remindEveryone = async () => {
  if (!hasAssignments.value) {
    sendAllMessage.value =
      "You can only remind everyone after assignments have been generated.";
    return;
  }

  await sendAssignmentsToAllParticipants("reminder");
};

// Re-send a single participant's assignment email
const resendSingleAssignment = async (giverId) => {
  resendMessage.value = "";
  resendError.value = "";

  const participant = participants.value.find(
    (p) => Number(p.id) === Number(giverId)
  );

  if (!participant) {
    resendError.value = "Could not find this participant in the list.";
    return;
  }

  if (!participant.email || !participant.email.trim()) {
    resendError.value =
      "This participant has no email address on record. Please add an email first.";
    return;
  }

  resendLoadingId.value = giverId;

  try {
    const { data } = await axios.post("/exchange/assignments/email", {
      project_id: currentProjectId.value,
      email: participant.email,
      reminder: true,
    });

    resendMessage.value =
      data.message ||
      `Assignment email resent to ${participant.name}. Please remind them to check their inbox.`;
  } catch (e) {
    console.error("Error resending assignment email:", e);
    resendError.value =
      e.response?.data?.message ||
      "Sorry, we couldn't resend the assignment email right now.";
  } finally {
    resendLoadingId.value = null;
  }
};

// Send secret list to a participant's email (self-lookup)
const sendCardEmail = async () => {
  emailSendMessage.value = "";
  emailSendError.value = "";

  const email = emailForCard.value.trim();
  if (!email) return;

  sendingCardEmail.value = true;

  try {
    const { data } = await axios.post("/exchange/assignments/email", {
      project_id: currentProjectId.value,
      email,
    });

    emailSendMessage.value =
      data.message || "Your gift list has been emailed. Please check your inbox.";
    emailForCard.value = "";
  } catch (e) {
    if (e.response?.data?.message) {
      emailSendError.value = e.response.data.message;
    } else {
      emailSendError.value = "Sorry, we couldn't send the email right now.";
    }
  } finally {
    sendingCardEmail.value = false;
  }
};

// --- Projects (change + create) ---
const changeProject = (id) => {
  const numericId = Number(id);

  const project = projects.value.find((p) => Number(p.id) === numericId);
  if (!project) return;

  currentProjectId.value = project.id;

  router.get(`/exchange-gift/${project.slug}`, {}, { preserveScroll: true });
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

// --- Snowflakes ---
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
    <div
      class="pointer-events-none fixed inset-0 z-0 overflow-hidden snow-container"
    >
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
      <div
        class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between gap-4"
      >
        <div class="flex items-center gap-3">
          <div
            class="h-11 w-11 rounded-2xl bg-white/20 flex items-center justify-center text-2xl shadow-inner"
          >
            🎄
          </div>
          <div>
            <h1 class="text-2xl font-bold tracking-tight">
              Exchange Gift Idea | Small Gifts Exchange
            </h1>
            <p class="text-xs md:text-sm text-red-100/90">
              {{ participantCount }} participant<span v-if="participantCount !== 1"
                >s</span
              >
              · {{ giftsEach }} gift<span v-if="giftsEach !== 1">s</span> each
              (max {{ MAX_GIFTS_PER_PERSON }})
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
            >1</span
          >
          <span>Add participants</span>
        </div>
        <span class="hidden md:inline text-red-100/60">•</span>
        <div class="flex items-center gap-1.5">
          <span
            class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-300 text-red-900 text-[10px] font-bold"
            >2</span
          >
          <span>Generate assignments</span>
        </div>
        <span class="hidden md:inline text-red-100/60">•</span>
        <div class="flex items-center gap-1.5">
          <span
            class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-sky-300 text-slate-900 text-[10px] font-bold"
            >3</span
          >
          <span>Send Lists to Participants</span>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <main class="relative z-10 max-w-5xl mx-auto px-4 py-6 space-y-6">
      <!-- 1) Christmas Rules (full width at top) -->
      <section
        class="bg-slate-900/80 backdrop-blur rounded-3xl shadow-xl border border-red-500/40 p-5"
      >
        <h2 class="font-semibold text-lg flex items-center gap-2 mb-2">
          <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600/90 text-white text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-text" viewBox="0 0 16 16">
                <path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5M5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/>
                <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1"/>
            </svg>
          </span>
          Christmas Rules
        </h2>
        <ul class="text-sm space-y-2 list-disc ml-4 text-slate-100">
          <li>We have a fixed list of participants for this exchange-gift theme.</li>
          <li>
            Each person will
            <strong>
              bring {{ giftsEach }} gift<span v-if="giftsEach !== 1">s</span>
            </strong>
            (max {{ MAX_GIFTS_PER_PERSON }}).
          </li>
          <li>
            The system assigns
            <strong>
              {{ giftsEach }} unique recipient<span v-if="giftsEach !== 1">s</span>
            </strong>
            to each giver.
          </li>
          <li>
            Each person must receive
            <strong>
              {{ giftsEach }} gift<span v-if="giftsEach !== 1">s</span> from
              {{ giftsEach }} different person<span v-if="giftsEach !== 1">s</span>
            </strong>
            .
          </li>
        </ul>

        <div
          class="mt-4 p-3 rounded-2xl bg-red-900/60 border border-red-500/60 text-[11px] text-red-100 flex flex-col gap-1"
        >
          <div class="flex items-center gap-2">
            <span>🎅</span>
            <span class="font-semibold">Note for the participants:</span>
          </div>
          <p>
            Everyone gives and receives the same number of gifts (up to
            {{ MAX_GIFTS_PER_PERSON }} each) with no self-assignments, for any
            group with at least 2 participants.
          </p>
        </div>
      </section>

      <!-- 2) Assignments (full width below) -->
      <section
        class="bg-slate-900/85 backdrop-blur rounded-3xl shadow-xl border border-red-500/40 p-5 space-y-4"
      >
        <div
          class="flex flex-col md:flex-row md:items-center md:justify-between gap-3"
        >
          <div class="space-y-1">
            <h2 class="font-semibold text-lg flex items-center gap-2">
              <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-400 text-red-900 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-gift" viewBox="0 0 16 16">
                        <path d="M3 2.5a2.5 2.5 0 0 1 5 0 2.5 2.5 0 0 1 5 0v.006c0 .07 0 .27-.038.494H15a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1v7.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 14.5V7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h2.038A3 3 0 0 1 3 2.506zm1.068.5H7v-.5a1.5 1.5 0 1 0-3 0c0 .085.002.274.045.43zM9 3h2.932l.023-.07c.043-.156.045-.345.045-.43a1.5 1.5 0 0 0-3 0zM1 4v2h6V4zm8 0v2h6V4zm5 3H9v8h4.5a.5.5 0 0 0 .5-.5zm-7 8V7H2v7.5a.5.5 0 0 0 .5.5z"/>
                    </svg>
              </span>
              Assignments
            </h2>
            <p class="text-xs text-slate-300">
              Generate once per group. After that, participants can request their
              list by email.
            </p>
            <p v-if="hasAssignments" class="text-[11px] text-emerald-200 mt-1">
              ✅ Assignments for this project have already been generated. Add or
              remove a participant if you need to reset and generate again.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-3 text-sm">
            <div
              class="flex items-center gap-2 bg-slate-800/80 rounded-2xl px-3 py-2 border border-slate-600"
            >
              <div class="flex flex-col">
                <span class="text-[11px] text-slate-400 uppercase tracking-[0.18em]"
                  >Gifts each</span
                >
                <span class="font-semibold text-slate-50">
                  {{ giftsEach }}
                </span>
              </div>
            </div>

            <button
              type="button"
              class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-red-500 via-red-400 to-amber-300 text-slate-900 text-sm font-semibold shadow-sm
                     hover:from-red-400 hover:via-amber-300 hover:to-yellow-200 active:scale-[0.98] transition
                     disabled:opacity-40 disabled:cursor-not-allowed"
              :disabled="!canGenerateAssignments || loading"
              @click="askSecretCodeForGenerate"
            >
              Generate Assignments
            </button>

            <button
              type="button"
              class="px-4 py-2.5 rounded-xl bg-emerald-400 text-slate-900 text-sm font-semibold shadow-sm
                     hover:bg-emerald-300 active:scale-[0.98] transition
                     disabled:opacity-40 disabled:cursor-not-allowed"
              :disabled="!hasAssignments || sendingAllAssignments || loading"
              @click="remindEveryone"
            >
              Remind Everyone
            </button>
          </div>
        </div>

        <!-- After assignments -->
        <div v-if="assignments.length" class="border-t border-slate-700 pt-4 space-y-4">
          <p class="text-[11px] text-slate-300">
            To keep things secret, the receiver list is not shown here. You can
            open an individual card for each giver, or let participants request
            their list via email.
          </p>

          <!-- Simple table: Giver + actions (no receiver columns) -->
          <div
            class="overflow-x-auto rounded-2xl border border-slate-700 shadow-sm bg-slate-950/60"
          >
            <table class="min-w-full text-xs md:text-sm">
              <thead class="bg-slate-900/80">
                <tr>
                  <th class="px-3 py-2 text-left font-semibold border-b border-slate-700">
                    Giver
                  </th>
                  <th class="px-3 py-2 text-left font-semibold border-b border-slate-700">
                    Secret Card
                  </th>
                  <th class="px-3 py-2 text-left font-semibold border-b border-slate-700">
                    Email
                  </th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="row in assignments"
                  :key="row.giverId"
                  class="odd:bg-slate-900/80 even:bg-slate-900/60"
                >
                  <!-- Giver -->
                  <td class="px-3 py-2 border-b border-slate-800 font-semibold text-slate-50">
                    {{ row.giverName }}
                  </td>

                  <!-- Secret Card -->
                  <td class="px-3 py-2 border-b border-slate-800">
                    <button
                      type="button"
                      class="text-[11px] px-2.5 py-1.5 rounded-full bg-amber-400 text-red-900 hover:bg-amber-300 transition"
                      @click="askSecretCodeForViewCard(row.giverId)"
                    >
                      View Card
                    </button>
                  </td>

                  <!-- Resend Assignment -->
                  <td class="px-3 py-2 border-b border-slate-800">
                    <button
                      type="button"
                      class="text-[11px] px-2.5 py-1.5 rounded-full bg-emerald-400 text-slate-900 hover:bg-emerald-300 transition
                             disabled:opacity-40 disabled:cursor-not-allowed"
                      :disabled="resendLoadingId === row.giverId"
                      @click="resendSingleAssignment(row.giverId)"
                    >
                      <span v-if="resendLoadingId === row.giverId">Sending…</span>
                      <span v-else>Resend assignment</span>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Bulk send status messages -->
          <p v-if="sendingAllAssignments" class="text-[11px] text-amber-200">
            Sending assignments to participants by email…
          </p>
          <p v-if="sendAllMessage && !sendingAllAssignments" class="text-[11px] text-emerald-300">
            {{ sendAllMessage }}
          </p>
          <p v-if="sendAllError && !sendingAllAssignments" class="text-[11px] text-red-300">
            {{ sendAllError }}
          </p>

          <p v-if="resendMessage" class="text-[11px] text-emerald-300">
            {{ resendMessage }}
          </p>
          <p v-if="resendError" class="text-[11px] text-red-300">
            {{ resendError }}
          </p>
        </div>

        <!-- No assignments yet -->
        <div
          v-else
          class="text-xs text-slate-300 italic bg-slate-900/70 rounded-2xl px-4 py-3 border border-dashed border-slate-700"
        >
          No assignments yet. Click
          <strong>Generate Assignments</strong> once you have at least 2 participants
          so everyone can give and receive gifts fairly (up to {{ MAX_GIFTS_PER_PERSON }}
          gifts each). After generation, the button will be disabled until you change
          the participant list.
        </div>
      </section>

      <!-- Person card modal -->
      <div
        v-if="selectedCard"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-40 px-4"
      >
        <div
          class="bg-slate-950/95 rounded-3xl shadow-2xl w-full max-w-sm p-6 space-y-4 relative border border-amber-400/40"
        >
          <button
            class="absolute top-3 right-3 text-slate-400 hover:text-slate-100 text-sm"
            @click="selectedCard = null"
          >
            ✕
          </button>

          <div class="flex flex-col items-center gap-2">
            <div
              class="h-11 w-11 rounded-2xl bg-amber-400 flex items-center justify-center text-2xl text-red-900 shadow-inner"
            >
              🎅
            </div>
            <h3 class="font-semibold text-lg text-center text-amber-100">
              Secret Santa Card for {{ selectedCard.giverName }}
            </h3>
            <p class="text-[11px] text-slate-300 text-center max-w-xs">
              Bring
              <strong>
                {{ giftsEach }} gift<span v-if="giftsEach !== 1">s</span>
              </strong>
              and give them to the people below. Keep this card secret! 🤫
            </p>
          </div>

          <ul class="text-sm space-y-1.5 text-center">
            <li
              v-for="(r, idx) in selectedCard.recipients"
              :key="idx"
              class="font-medium bg-slate-900 rounded-full px-3 py-1 border border-slate-700 text-slate-50"
            >
              {{ r || "-" }}
            </li>
          </ul>

          <p class="text-[10px] text-slate-400 text-center mt-1">
            Tip: Screenshot or print this card before the party.
          </p>

          <div class="flex justify-center pt-1">
            <button
              type="button"
              class="px-4 py-2.5 rounded-xl bg-amber-400 text-red-900 text-xs font-semibold hover:bg-amber-300 active:scale-[0.98] transition"
              @click="selectedCard = null"
            >
              Close
            </button>
          </div>
        </div>
      </div>

      <!-- Santa Secret Code Modal -->
      <div
        v-if="isSecretModalOpen"
        class="fixed inset-0 bg-black/55 flex items-center justify-center z-50 px-4"
      >
        <div
          class="bg-slate-950/95 rounded-3xl shadow-2xl w-full max-w-sm p-6 space-y-4 border border-amber-400/60 relative"
        >
          <button
            type="button"
            class="absolute top-3 right-3 text-slate-400 hover:text-slate-100 text-sm"
            @click="closeSecretModal"
          >
            ✕
          </button>

          <div class="flex items-center gap-3">
            <div
              class="h-11 w-11 rounded-2xl bg-amber-400 flex items-center justify-center text-2xl text-red-900 shadow-inner"
            >
              🔐
            </div>
            <div>
              <h3 class="font-semibold text-lg text-amber-100">
                Enter Santa's Secret Code
              </h3>
              <p class="text-[11px] text-slate-300">
                This action is for the organizer only. Please type the Santa's
                Secret Code sent to your email.
              </p>
            </div>
          </div>

          <div class="space-y-1 mt-2">
            <label class="text-[11px] text-slate-200">Santa's Secret Code</label>
            <input
              v-model="secretCodeInput"
              type="password"
              autocomplete="off"
              class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-900/80 text-sm text-slate-50
                     focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
              placeholder="Enter code here"
            />
          </div>

          <p v-if="secretCodeError" class="text-[11px] text-red-300 mt-1">
            {{ secretCodeError }}
          </p>

          <div class="mt-4 flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
            <button
              type="button"
              class="px-3 py-2 rounded-xl text-xs text-slate-200 hover:bg-slate-800/80"
              @click="closeSecretModal"
              :disabled="secretCodeLoading"
            >
              Cancel
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-xl bg-amber-400 text-red-900 text-xs font-semibold hover:bg-amber-300 active:scale-[0.98] transition
                     disabled:opacity-40 disabled:cursor-not-allowed"
              @click="verifySantaAndRun"
              :disabled="secretCodeLoading"
            >
              <span v-if="!secretCodeLoading">Confirm</span>
              <span v-else>Verifying…</span>
            </button>
          </div>
        </div>
      </div>
    </main>
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
