<script setup>
/**
 * SessionStatusBadge Component
 * Exibe o estado da sessão de trabalho atual e calcula os tempos de forma reativa.
 */
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
    session: {
        type: Object,
        required: true
    }
});

const now = ref(new Date());
let timer = null;

onMounted(() => {
    // Atualiza o relógio interno a cada segundo
    timer = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

/**
 * Calcula o tempo de trabalho efetivo (ignorando e subtraindo o tempo das pausas).
 * Se a sessão estiver pausada, o tempo de trabalho congela automaticamente.
 */
const workingTimeFormatted = computed(() => {
    if (!props.session) return '00:00:00';
    
    // Fallback defensivo para garantir que a data vem no formato correto
    const startObj = new Date(props.session.started_at_iso || props.session.started_at);
    if (isNaN(startObj.getTime())) return '00:00:00';

    const startMs = startObj.getTime();
    const endMs = props.session.ended_at_iso ? new Date(props.session.ended_at_iso).getTime() : now.value.getTime();
    
    let totalWorkedMs = endMs - startMs;
    
    // Subtrair o tempo de todas as pausas
    if (props.session.pauses && props.session.pauses.length > 0) {
        props.session.pauses.forEach(pause => {
            const pStart = new Date(pause.started_at_iso || pause.started_at).getTime();
            // Se a pausa ainda estiver a decorrer, subtrai até ao segundo atual
            const pEnd = pause.ended_at_iso || pause.ended_at 
                            ? new Date(pause.ended_at_iso || pause.ended_at).getTime() 
                            : endMs; 
            
            totalWorkedMs -= (pEnd - pStart);
        });
    }

    if (totalWorkedMs < 0) totalWorkedMs = 0;

    const hours = Math.floor(totalWorkedMs / 3600000);
    const minutes = Math.floor((totalWorkedMs % 3600000) / 60000);
    const seconds = Math.floor((totalWorkedMs % 60000) / 1000);

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

/**
 * Calcula o tempo decorrido da pausa em curso (se o utilizador estiver em pausa).
 */
const currentPauseTimeFormatted = computed(() => {
    if (props.session?.status !== 'paused') return null;
    
    // Procura a pausa que não tem data de fim definida
    const currentPause = props.session.pauses?.find(p => !p.ended_at_iso && !p.ended_at);
    if (!currentPause) return '00:00';

    const pStart = new Date(currentPause.started_at_iso || currentPause.started_at).getTime();
    const pauseElapsedMs = now.value.getTime() - pStart;

    const minutes = Math.floor(pauseElapsedMs / 60000);
    const seconds = Math.floor((pauseElapsedMs % 60000) / 1000);
    const hours = Math.floor(pauseElapsedMs / 3600000);

    // Se a pausa já durar mais de uma hora
    if (hours > 0) {
        return `${hours}h ${String(minutes).padStart(2, '0')}m`;
    }
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});
</script>

<template>
    <div class="flex items-center gap-2 px-3 py-1.5 rounded-full border shadow-sm text-sm font-medium transition-colors duration-300"
         :class="[
             session.status === 'active' 
                ? 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900/40 dark:text-green-200 dark:border-green-700' 
                : 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-900/40 dark:text-yellow-200 dark:border-yellow-700'
         ]">
        
        <span class="relative flex h-2.5 w-2.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                  :class="session.status === 'active' ? 'bg-green-500' : 'bg-yellow-500'"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5"
                  :class="session.status === 'active' ? 'bg-green-600' : 'bg-yellow-600'"></span>
        </span>

        <span class="uppercase tracking-wider text-[11px] font-bold">
            {{ session.status === 'active' ? 'Active' : 'Paused' }}
        </span>

        <span class="opacity-30">|</span>

        <span class="font-mono tabular-nums tracking-tight flex items-center gap-1" title="Worked Time">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            {{ workingTimeFormatted }}
        </span>

        <template v-if="session.status === 'paused'">
            <span class="opacity-30">|</span>
            <span class="text-xs opacity-90 font-mono tabular-nums flex items-center gap-1" title="Break Duration">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ currentPauseTimeFormatted }}
            </span>
        </template>
        
    </div>
</template>