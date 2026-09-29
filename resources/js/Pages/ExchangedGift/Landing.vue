<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { router } from "@inertiajs/vue3";

const creating = ref(false);
const joining = ref(false);

const groupTitle = ref("");
const organizerName = ref("");
const organizerEmail = ref("");
const createError = ref("");
const createdProject = ref(null); // holds returned project with unique_code

const joinCode = ref("");
const joinError = ref("");

// Snowflakes
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
});

// Create group
const canCreate = computed(() =>
  groupTitle.value.trim() &&
  organizerName.value.trim() &&
  organizerEmail.value.trim()
);

const createGroup = async () => {
  createError.value = "";
  createdProject.value = null;

  if (!canCreate.value) return;

  creating.value = true;
  try {
    const { data } = await axios.post("/exchange/projects", {
      name: groupTitle.value.trim(),
      organizer_name: organizerName.value.trim(),
      organizer_email: organizerEmail.value.trim(),
    });

    createdProject.value = data;

    // optional: clear form
    // groupTitle.value = "";
    // organizerName.value = "";
    // organizerEmail.value = "";
  } catch (e) {
    if (e.response?.data?.message) {
      createError.value = e.response.data.message;
    } else if (e.response?.data?.errors) {
      createError.value = "Please check the fields and try again.";
    } else {
      createError.value = "Something went wrong while creating the group.";
    }
    console.error("Create group error:", e);
  } finally {
    creating.value = false;
  }
};

const openCreatedGroup = () => {
  if (!createdProject.value) return;
  router.get(`/${createdProject.value.slug}/menu`);
};

// Join group
const normalizedJoinCode = computed(() =>
  joinCode.value.trim().toUpperCase()
);

const canJoin = computed(() => !!normalizedJoinCode.value);

const joinGroup = async () => {
    console.log("JOIN CLICKED");

  joinError.value = "";
  if (!canJoin.value) return;

  joining.value = true;
  try {
    const { data } = await axios.post("/exchange/join", {
      code: normalizedJoinCode.value,
    });


    router.get(`/${data.project_slug}/menu`);
  } catch (e) {
    if (e.response?.status === 404) {
      joinError.value =
        e.response.data?.message ||
        "Group not found. Please check the code.";
    } else {
      joinError.value = "Unable to join the group right now.";
    }
    console.error("Join group error:", e);
  } finally {
    joining.value = false;
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
          animationDuration: flake.duration + 's, ' + flake.duration / 2 + 's',
          animationDelay: flake.delay + 's, ' + flake.delay + 's',
          opacity: flake.opacity,
        }"
      />
    </div>

    <!-- Content -->
    <main class="relative z-10">
      <div class="max-w-5xl mx-auto px-4 py-10 md:py-16 space-y-10">
        <!-- Hero -->
        <section class="text-center space-y-4">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-800/60 border border-red-400/60 text-[11px] uppercase tracking-[0.18em] text-red-100">
            <span>🎄</span>
            <span>Christmas Party Planner</span>
            <span>🎄</span>
          </div>

          <h1 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight">
            Your all-in-one app to plan your<br class="hidden md:block" />
            <span class="text-amber-300">Christmas Party</span>.
          </h1>

          <p class="max-w-2xl mx-auto text-sm md:text-base text-slate-200">
            Create a group for your family, barkada, or office team.
            Everyone can join with a simple code, get a secret gift list,
            and keep everything fair, fun, and organized.
          </p>
        </section>

        <!-- Cards -->
        <section class="grid gap-6 md:grid-cols-2 items-stretch">
          <!-- Create group -->
          <div class="bg-slate-900/85 border border-red-500/50 rounded-3xl shadow-xl p-6 flex flex-col justify-between backdrop-blur">
            <div class="space-y-4">
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-emerald-400 flex items-center justify-center text-2xl text-slate-900 shadow-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                        <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/>
                    </svg>
                </div>
                <div>
                  <h2 class="text-lg md:text-xl font-semibold">
                    Create a new group
                  </h2>
                  <p class="text-xs md:text-sm text-slate-300">
                    Set up a group, invite people, and we’ll generate a unique
                    join code for everyone.
                  </p>
                </div>
              </div>

              <form
                class="space-y-3 mt-2"
                @submit.prevent="createGroup"
              >
                <div class="space-y-1">
                  <label class="text-xs text-slate-200">Group title</label>
                  <input
                    v-model="groupTitle"
                    type="text"
                    placeholder="e.g. Christmas Party 2025"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm
                           text-slate-50 placeholder:text-slate-500 focus:outline-none
                           focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
                  />
                </div>

                <div class="space-y-1">
                  <label class="text-xs text-slate-200">Your name</label>
                  <input
                    v-model="organizerName"
                    type="text"
                    placeholder="Organizer's name"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm
                           text-slate-50 placeholder:text-slate-500 focus:outline-none
                           focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
                  />
                </div>

                <div class="space-y-1">
                  <label class="text-xs text-slate-200">Your email</label>
                  <input
                    v-model="organizerEmail"
                    type="email"
                    placeholder="Organizer's email"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm
                           text-slate-50 placeholder:text-slate-500 focus:outline-none
                           focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
                  />
                </div>

                <p
                  v-if="createError"
                  class="text-[11px] text-red-300 bg-red-900/50 border border-red-500/60 rounded-xl px-3 py-2"
                >
                  {{ createError }}
                </p>

                <button
                  type="submit"
                  class="w-full mt-1 px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-300
                         text-slate-900 text-sm font-semibold shadow-md hover:from-emerald-400 hover:to-emerald-200
                         active:scale-[0.98] transition disabled:opacity-40 disabled:cursor-not-allowed"
                  :disabled="!canCreate || creating"
                >
                  {{ creating ? 'Creating group…' : 'Create group' }}
                </button>
              </form>

              <!-- Show generated code -->
              <div
                v-if="createdProject"
                class="mt-4 p-3 rounded-2xl bg-slate-800/90 border border-emerald-500/60 text-[12px] space-y-2"
              >
                <div class="flex items-center justify-between gap-2 flex-wrap">
                  <div>
                    <div class="text-[11px] text-slate-300 uppercase tracking-[0.18em]">
                      Group created
                    </div>
                    <div class="text-sm font-semibold text-emerald-200">
                      {{ createdProject.name }}
                    </div>
                  </div>
                  <button
                    type="button"
                    class="px-3 py-1.5 rounded-full bg-emerald-400 text-slate-900 text-xs font-semibold hover:bg-emerald-300"
                    @click="openCreatedGroup"
                  >
                    Open group
                  </button>
                </div>

                <div class="mt-2">
                  <div class="text-[11px] text-slate-400 mb-1">
                    Share this join code with your participants:
                  </div>
                  <div
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-900 border border-emerald-400/60 text-emerald-200 font-mono text-xs"
                  >
                    <span>🎟️</span>
                    <span>{{ createdProject.unique_code }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Join group -->
          <div
            class="bg-slate-900/85 border border-indigo-500/50 rounded-3xl shadow-xl p-6 flex flex-col justify-between backdrop-blur"
          >
            <div class="space-y-4">
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-indigo-400 flex items-center justify-center text-2xl text-slate-900 shadow-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-snow2" viewBox="0 0 16 16">
                        <path d="M8 16a.5.5 0 0 1-.5-.5v-1.293l-.646.647a.5.5 0 0 1-.707-.708L7.5 12.793v-1.086l-.646.647a.5.5 0 0 1-.707-.708L7.5 10.293V8.866l-1.236.713-.495 1.85a.5.5 0 1 1-.966-.26l.237-.882-.94.542-.496 1.85a.5.5 0 1 1-.966-.26l.237-.882-1.12.646a.5.5 0 0 1-.5-.866l1.12-.646-.884-.237a.5.5 0 1 1 .26-.966l1.848.495.94-.542-.882-.237a.5.5 0 1 1 .258-.966l1.85.495L7 8l-1.236-.713-1.849.495a.5.5 0 1 1-.258-.966l.883-.237-.94-.542-1.85.495a.5.5 0 0 1-.258-.966l.883-.237-1.12-.646a.5.5 0 1 1 .5-.866l1.12.646-.237-.883a.5.5 0 0 1 .966-.258l.495 1.849.94.542-.236-.883a.5.5 0 0 1 .966-.258l.495 1.849 1.236.713V5.707L6.147 4.354a.5.5 0 1 1 .707-.708l.646.647V3.207L6.147 1.854a.5.5 0 1 1 .707-.708l.646.647V.5a.5.5 0 0 1 1 0v1.293l.647-.647a.5.5 0 1 1 .707.708L8.5 3.207v1.086l.647-.647a.5.5 0 1 1 .707.708L8.5 5.707v1.427l1.236-.713.495-1.85a.5.5 0 1 1 .966.26l-.236.882.94-.542.495-1.85a.5.5 0 1 1 .966.26l-.236.882 1.12-.646a.5.5 0 0 1 .5.866l-1.12.646.883.237a.5.5 0 1 1-.26.966l-1.848-.495-.94.542.883.237a.5.5 0 1 1-.26.966l-1.848-.495L9 8l1.236.713 1.849-.495a.5.5 0 0 1 .259.966l-.883.237.94.542 1.849-.495a.5.5 0 0 1 .259.966l-.883.237 1.12.646a.5.5 0 0 1-.5.866l-1.12-.646.236.883a.5.5 0 1 1-.966.258l-.495-1.849-.94-.542.236.883a.5.5 0 0 1-.966.258L9.736 9.58 8.5 8.866v1.427l1.354 1.353a.5.5 0 0 1-.707.708l-.647-.647v1.086l1.354 1.353a.5.5 0 0 1-.707.708l-.647-.647V15.5a.5.5 0 0 1-.5.5"/>
                    </svg>
                </div>
                <div>
                  <h2 class="text-lg md:text-xl font-semibold">
                    Join an existing group
                  </h2>
                  <p class="text-xs md:text-sm text-slate-300">
                    Already invited? Enter the join code shared by your
                    organizer.
                  </p>
                </div>
              </div>

              <form class="space-y-3 mt-2" @submit.prevent="joinGroup">
                <div class="space-y-1">
                  <label class="text-xs text-slate-200">Join code</label>
                  <input
                    v-model="joinCode"
                    type="text"
                    placeholder="Type your group code here"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-600 bg-slate-800/80 text-sm
                           text-slate-50 placeholder:text-slate-500 focus:outline-none
                           focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 uppercase"
                  />
                  <p class="text-[11px] text-slate-400 mt-1">
                    Codes are not case-sensitive. Example: <code>AB12CD34</code>
                  </p>
                </div>

                <p
                  v-if="joinError"
                  class="text-[11px] text-red-300 bg-red-900/50 border border-red-500/60 rounded-xl px-3 py-2"
                >
                  {{ joinError }}
                </p>

                <button
                  type="submit"
                  class="w-full mt-1 px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 via-sky-400 to-cyan-300
                         text-slate-900 text-sm font-semibold shadow-md hover:from-indigo-400 hover:via-sky-300 hover:to-cyan-200
                         active:scale-[0.98] transition disabled:opacity-40 disabled:cursor-not-allowed"
                  :disabled="!canJoin || joining"
                >
                  {{ joining ? 'Joining group…' : 'Join group' }}
                </button>
              </form>

              <!-- <div class="mt-4 text-[11px] text-slate-400 space-y-1.5">

                    What happens next?

                <ul class="list-disc ml-4 space-y-1">
                  <li>You’ll be redirected to your group’s Christmas Party page.</li>
                </ul>
              </div> -->
            </div>
          </div>
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
</style>
