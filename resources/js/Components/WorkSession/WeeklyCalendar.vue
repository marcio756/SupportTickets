<script setup>
/**
 * WeeklyCalendar Component
 * Includes strict chronological splitting to guarantee zero overlaps.
 * Work sessions are sliced mathematically around pauses.
 */
import { ref, watch, reactive, onMounted } from 'vue';
import { DayPilot, DayPilotCalendar, DayPilotNavigator } from '@daypilot/daypilot-lite-vue';

const props = defineProps({
    weekStartDate: {
        type: String,
        required: true
    },
    sessions: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['update:weekStartDate']);

const calendarRef = ref(null);

/**
 * CONVERSOR ESTRITO DE FUSO HORÁRIO COM PRECISÃO DE MILISSEGUNDOS
 * Preserva a ordem cronológica exata para o layout engine do DayPilot não sobrepor blocos.
 */
const getLocalDayPilotTime = (dateStr) => {
    if (!dateStr) return null;
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return null;
    const pad = (n) => String(n).padStart(2, '0');
    const padMs = (n) => String(n).padStart(3, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}.${padMs(d.getMilliseconds())}`;
};

const getDurationText = (diffMins) => {
    if (diffMins <= 0) return `0 min`;
    if (diffMins < 60) return `${diffMins} min`;
    const h = Math.floor(diffMins / 60);
    const m = diffMins % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
};

const navigatorConfig = reactive({
    selectMode: "Week",
    showMonths: 1,
    skipMonths: 1,
    selectionDay: props.weekStartDate,
    onTimeRangeSelected: (args) => {
        emit('update:weekStartDate', args.day.toString('yyyy-MM-dd'));
    }
});

const calendarConfig = reactive({
    viewType: "Week",
    startDate: props.weekStartDate,
    durationBarVisible: false,
    headerHeight: 50,
    hourWidth: 60,
    eventMoveHandling: "Disabled",
    eventResizeHandling: "Disabled",
    timeRangeSelectedHandling: "Disabled",
    // Configurações para garantir largura máxima caso haja conflitos não previstos
    eventArrangement: "Full", 
    allowEventOverlap: false,
    events: [],
    onBeforeEventRender: (args) => {
        args.data.fontColor = '#ffffff';
        args.data.backColor += 'e6'; 
        if (args.data.tags && args.data.tags.type === 'pause') {
            args.data.cssClass = 'dp-custom-pause';
        }
    }
});

/**
 * LÓGICA DE FATIAMENTO LINEAR
 * Garante que os eventos são puramente adjacentes, sem NENHUMA sobreposição temporal.
 */
const processEvents = () => {
    const mappedEvents = [];

    if (!props.sessions || !Array.isArray(props.sessions)) {
        return mappedEvents;
    }

    props.sessions.forEach(session => {
        const sStartLiteral = getLocalDayPilotTime(session.started_at_iso || session.started_at);
        if (!sStartLiteral) return;

        const dpSessionStart = new DayPilot.Date(sStartLiteral);
        const isSessionOngoing = !(session.ended_at_iso || session.ended_at);
        const dpSessionEnd = isSessionOngoing 
            ? new DayPilot.Date() 
            : new DayPilot.Date(getLocalDayPilotTime(session.ended_at_iso || session.ended_at));

        const sessionTitle = session.user?.name ? `${session.user.name}` : 'Session';
        let cursor = dpSessionStart; // O cursor viaja no tempo sem nunca recuar

        // Função geradora de blocos individuais na linha do tempo
        const pushBlock = (start, end, type, pauseData = null) => {
            if (start.getTime() >= end.getTime()) return; // Ignora blocos com duração zero ou negativa

            const diffMins = Math.round((end.getTime() - start.getTime()) / 60000);
            if (diffMins < 1 && type === 'work') return; // Ignora micro-trabalhos de segundos para limpeza visual

            const displayDuration = getDurationText(Math.max(1, diffMins));
            const timeRangeText = `${start.toString('HH:mm')} - ${end.toString('HH:mm')}`;

            if (type === 'work') {
                const isFinalBlock = end.getTime() === dpSessionEnd.getTime();
                const isBlockActive = isSessionOngoing && isFinalBlock && session.status !== 'paused';
                
                const backColor = isBlockActive ? '#16a34a' : '#4f46e5';
                const borderColor = isBlockActive ? '#15803d' : '#3730a3';
                const statusText = isBlockActive ? 'ACTIVE' : 'COMPLETED';

                mappedEvents.push({
                    id: `work_${session.id}_${start.getTime()}`,
                    start: start.toString(),
                    end: end.toString(),
                    html: `
                        <div style="padding: 4px; display: flex; flex-direction: column; gap: 2px;">
                            <div style="font-weight: 800; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${sessionTitle}</div>
                            <div style="font-size: 10px; opacity: 0.9;">${isBlockActive ? start.toString('HH:mm') + ' - Active' : timeRangeText}</div>
                            <div>
                                <span style="font-size: 10px; font-weight: bold; background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px; color: white;">
                                    ⏱ ${displayDuration}
                                </span>
                            </div>
                        </div>
                    `,
                    toolTip: `Supporter: ${sessionTitle}\nStatus: ${statusText}\nTime: ${timeRangeText}\nWorked: ${displayDuration}`,
                    backColor: backColor,
                    borderColor: borderColor,
                    tags: { type: 'session' }
                });
            } else if (type === 'pause') {
                mappedEvents.push({
                    id: `pause_${pauseData.id}_${start.getTime()}`,
                    start: start.toString(),
                    end: end.toString(),
                    html: `<div style="text-align:center; font-weight:bold; font-size:9px; margin-top:2px; color:white;">PAUSE<br/>${displayDuration}</div>`,
                    toolTip: `PAUSE\nTime: ${timeRangeText}\nDuration: ${displayDuration}`,
                    backColor: '#ea580c',
                    borderColor: '#c2410c',
                    tags: { type: 'pause' }
                });
            }
        };

        // Extrai e ordena as pausas cronologicamente
        if (session.pauses && session.pauses.length > 0) {
            const sortedPauses = [...session.pauses].sort((a, b) => {
                return new Date(a.started_at_iso || a.started_at).getTime() - new Date(b.started_at_iso || b.started_at).getTime();
            });

            sortedPauses.forEach(pause => {
                const pStartLiteral = getLocalDayPilotTime(pause.started_at_iso || pause.started_at);
                if (!pStartLiteral) return;

                let dpPauseStart = new DayPilot.Date(pStartLiteral);
                const pOngoing = !(pause.ended_at_iso || pause.ended_at);
                let dpPauseEnd = pOngoing 
                    ? new DayPilot.Date() 
                    : new DayPilot.Date(getLocalDayPilotTime(pause.ended_at_iso || pause.ended_at));

                // Validação estrita para impedir corrupção temporal
                if (dpPauseStart.getTime() < cursor.getTime()) dpPauseStart = cursor;
                if (dpPauseEnd.getTime() < dpPauseStart.getTime()) dpPauseEnd = dpPauseStart;
                if (dpPauseEnd.getTime() > dpSessionEnd.getTime()) dpPauseEnd = dpSessionEnd;

                // 1. Cria o bloco de trabalho que aconteceu ANTES desta pausa
                pushBlock(cursor, dpPauseStart, 'work');

                // 2. Cria o bloco da pausa exata
                pushBlock(dpPauseStart, dpPauseEnd, 'pause', pause);

                // 3. Atualiza o cursor do tempo para o fim da pausa
                cursor = dpPauseEnd;
            });
        }

        // Cria o bloco final de trabalho (após a última pausa até ao fim da sessão)
        pushBlock(cursor, dpSessionEnd, 'work');
    });

    return mappedEvents;
};

// ==========================================
// REATIVIDADE E ATUALIZAÇÃO DO DAYPILOT
// ==========================================

watch(() => props.weekStartDate, (newDate) => {
    calendarConfig.startDate = newDate;
    navigatorConfig.selectionDay = newDate;
});

watch(() => props.sessions, () => {
    const newEvents = processEvents();
    if (calendarRef.value && calendarRef.value.control) {
        calendarRef.value.control.update({ events: newEvents });
    } else {
        calendarConfig.events = newEvents;
    }
}, { immediate: true, deep: true });

onMounted(() => {
    setTimeout(() => {
        if (calendarRef.value && calendarRef.value.control) {
            calendarRef.value.control.update({ events: processEvents() });
        }
    }, 150);
});
</script>

<template>
    <div class="flex flex-col md:flex-row gap-6 bg-white dark:bg-gray-800 p-4 rounded-2xl shadow border border-gray-200 dark:border-gray-700 transition-colors duration-200">
        
        <div class="w-full md:w-64 flex-shrink-0">
            <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-2 border border-gray-100 dark:border-gray-700 transition-colors duration-200">
                <DayPilotNavigator :config="navigatorConfig" />
            </div>
            
            <div class="mt-6 px-2 space-y-3">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Legend</h4>
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-[#4f46e5]"></div>
                    <span class="text-xs text-gray-600 dark:text-gray-300 font-medium">Completed Session</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-[#16a34a]"></div>
                    <span class="text-xs text-gray-600 dark:text-gray-300 font-medium">Active Session</span>
                </div>
                <div class="flex items-center gap-3 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <div class="w-3 h-3 rounded-sm bg-[#ea580c]"></div>
                    <span class="text-xs text-gray-600 dark:text-gray-300 font-bold">Pause (Block)</span>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 relative z-0">
            <DayPilotCalendar ref="calendarRef" :config="calendarConfig" />
        </div>

    </div>
</template>

<style>
/* Forçar ocupação horizontal completa por segurança */
.calendar_default_event {
    left: 0 !important;
    width: 100% !important;
}

.dp-custom-pause {
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
}

.calendar_default_event_inner {
    border-radius: 6px !important;
    padding: 2px !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.2) !important;
    overflow: hidden !important;
}

/* DARK MODE OVERRIDES */
.dark .calendar_default_main { border-color: #374151 !important; }
.dark .calendar_default_rowheader,
.dark .calendar_default_colheader,
.dark .calendar_default_corner { background-color: #111827 !important; color: #e5e7eb !important; border-color: #374151 !important; }
.dark .calendar_default_cell { background-color: #1f2937 !important; border-color: #374151 !important; }
.dark .calendar_default_cell_business { background-color: #1f2937 !important; }
.dark .calendar_default_allday { background-color: #111827 !important; border-color: #374151 !important; }
.dark .calendar_default_scroll { background-color: #1f2937 !important; }
.dark .navigator_default_main { border-color: transparent !important; background-color: transparent !important; }
.dark .navigator_default_month { color: #e5e7eb !important; }
.dark .navigator_default_day { color: #d1d5db !important; }
.dark .navigator_default_day_other { color: #4b5563 !important; }
.dark .navigator_default_dayheader { color: #9ca3af !important; }
.dark .navigator_default_todaybox { border-color: #4f46e5 !important; }
.dark .navigator_default_select .navigator_default_day { background-color: #374151 !important; color: #ffffff !important; }
</style>