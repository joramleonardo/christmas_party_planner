// Web Audio API Synthesizer - Zero dependencies, zero external mp3 files
let audioCtx = null;
let soundEnabled = true;

if (typeof window !== "undefined") {
  const saved = localStorage.getItem("exchange_sound_enabled");
  if (saved !== null) {
    soundEnabled = saved === "true";
  }
}

function getAudioContext() {
  if (typeof window === "undefined") return null;
  if (!audioCtx) {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (AudioContextClass) {
      audioCtx = new AudioContextClass();
    }
  }
  if (audioCtx && audioCtx.state === "suspended") {
    audioCtx.resume();
  }
  return audioCtx;
}

export function isSoundEnabled() {
  return soundEnabled;
}

export function toggleSound() {
  soundEnabled = !soundEnabled;
  if (typeof window !== "undefined") {
    localStorage.setItem("exchange_sound_enabled", String(soundEnabled));
  }
  if (soundEnabled) {
    playChime();
  }
  return soundEnabled;
}

// Gentle festive chime
export function playChime() {
  if (!soundEnabled) return;
  const ctx = getAudioContext();
  if (!ctx) return;

  const notes = [523.25, 659.25, 783.99, 1046.5]; // C5, E5, G5, C6
  notes.forEach((freq, idx) => {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = "sine";
    osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.08);

    gain.gain.setValueAtTime(0.001, ctx.currentTime + idx * 0.08);
    gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + idx * 0.08 + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + idx * 0.08 + 0.5);

    osc.connect(gain);
    gain.connect(ctx.destination);

    osc.start(ctx.currentTime + idx * 0.08);
    osc.stop(ctx.currentTime + idx * 0.08 + 0.55);
  });
}

// Celebration / Fanfare for winner / reveal
export function playCelebration() {
  if (!soundEnabled) return;
  const ctx = getAudioContext();
  if (!ctx) return;

  const melody = [
    { freq: 523.25, time: 0, dur: 0.12 },    // C5
    { freq: 659.25, time: 0.12, dur: 0.12 }, // E5
    { freq: 783.99, time: 0.24, dur: 0.12 }, // G5
    { freq: 1046.5, time: 0.36, dur: 0.35 }, // C6
    { freq: 880.00, time: 0.72, dur: 0.15 }, // A5
    { freq: 1046.5, time: 0.90, dur: 0.60 }, // C6
  ];

  melody.forEach((n) => {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = "triangle";
    osc.frequency.setValueAtTime(n.freq, ctx.currentTime + n.time);

    gain.gain.setValueAtTime(0.001, ctx.currentTime + n.time);
    gain.gain.exponentialRampToValueAtTime(0.22, ctx.currentTime + n.time + 0.03);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + n.time + n.dur);

    osc.connect(gain);
    gain.connect(ctx.destination);

    osc.start(ctx.currentTime + n.time);
    osc.stop(ctx.currentTime + n.time + n.dur + 0.05);
  });
}

// Dramatic drumroll / roll for ceremony mode
export function playDrumroll(durationSec = 2) {
  if (!soundEnabled) return;
  const ctx = getAudioContext();
  if (!ctx) return;

  const bufferSize = ctx.sampleRate * durationSec;
  const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
  const data = buffer.getChannelData(0);

  // Generate noise modulated with rapid beats
  for (let i = 0; i < bufferSize; i++) {
    const t = i / ctx.sampleRate;
    const beat = Math.sin(2 * Math.PI * 18 * t);
    data[i] = (Math.random() * 2 - 1) * 0.3 * (0.5 + 0.5 * beat) * (0.2 + 0.8 * (t / durationSec));
  }

  const noise = ctx.createBufferSource();
  noise.buffer = buffer;

  const filter = ctx.createBiquadFilter();
  filter.type = "lowpass";
  filter.frequency.setValueAtTime(350, ctx.currentTime);
  filter.frequency.exponentialRampToValueAtTime(900, ctx.currentTime + durationSec);

  const gain = ctx.createGain();
  gain.gain.setValueAtTime(0.15, ctx.currentTime);
  gain.gain.linearRampToValueAtTime(0.35, ctx.currentTime + durationSec);
  gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + durationSec + 0.1);

  noise.connect(filter);
  filter.connect(gain);
  gain.connect(ctx.destination);

  noise.start();
  noise.stop(ctx.currentTime + durationSec + 0.15);
}

// Pop sound for interactions / unwrap
export function playPop() {
  if (!soundEnabled) return;
  const ctx = getAudioContext();
  if (!ctx) return;

  const osc = ctx.createOscillator();
  const gain = ctx.createGain();

  osc.type = "sine";
  osc.frequency.setValueAtTime(400, ctx.currentTime);
  osc.frequency.exponentialRampToValueAtTime(80, ctx.currentTime + 0.12);

  gain.gain.setValueAtTime(0.25, ctx.currentTime);
  gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);

  osc.connect(gain);
  gain.connect(ctx.destination);

  osc.start(ctx.currentTime);
  osc.stop(ctx.currentTime + 0.13);
}

