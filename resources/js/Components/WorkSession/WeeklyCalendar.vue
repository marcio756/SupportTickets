<script setup>
/**
 * WeeklyCalendar Component
 * Utiliza o padrão de reatividade oficial (config) do DayPilot Lite Vue.
 * Garante a renderização absoluta dos eventos eliminando conflitos de timezone.
 */
import { ref, watch, reactive } from 'vue';
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
 * Utilitário crucial: Remove as timezones (+00:00, Z) geradas pelo backend.
 * Garante que "10:00" no Laravel é lido exatamente como "10:00" na grelha do calendário, sem conversões fantasma.
 */
const getLiteralTime = (isoString) => {
    if (!isoString) return null;
    return isoString.split('+')[0].split('Z')[0].split('.')[0].replace(' ', 'T');
};

const getDurationText = (diffMins) => {
    if (diffMins < 60) return `${diffMins} min`;
    const h = Math.floor(diffMins / 60);
    const m = diffMins % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
};

// ==========================================
// CONFIGURAÇÕES REATIVAS DO DAYPILOT (Vue 3)
// ==========================================

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
    events: [],
    
    // Intercetação visual
    onBeforeEventRender: (args) => {
        args.data.fontColor = '#ffffff';
        args.data.backColor += 'e6'; 
        if (args.data.tags && args.data.tags.type === 'pause') {
            args.data.cssClass = 'dp-custom-pause';
        }
    }
});

// ==========================================
// OBSERVADORES DE ESTADO (Atualização Dinâmica)
// ==========================================

// Atualiza o dia selecionado se mudar nos filtros
watch(() => props.weekStartDate, (newDate) => {
    calendarConfig.startDate = newDate;
    navigatorConfig.selectionDay = newDate;
});

// Observa ativamente as sessões recebidas do backend e recalcula a grelha instantaneamente
watch(() => props.sessions, (newSessions) => {
    const mappedEvents = [];

    if (!newSessions || !Array.isArray(newSessions)) {
        calendarConfig.events = [];
        return;
    }

    newSessions.forEach(session => {
        const startLiteral = getLiteralTime(session.started_at_iso || session.started_at);
        if (!startLiteral) return;

        const dpStart = new DayPilot.Date(startLiteral);
        const isOngoing = !(session.ended_at_iso || session.ended_at);
        
        // Se estiver ativo, usamos a hora literal local exata atual
        const dpEnd = isOngoing 
            ? new DayPilot.Date() 
            : new DayPilot.Date(getLiteralTime(session.ended_at_iso || session.ended_at));

        const diffMins = Math.max(1, Math.round((dpEnd.getTime() - dpStart.getTime()) / 60000));
        
        // Regra Visual: Expandir blocos muito curtos para, no mínimo, 60 minutos
        let visualEnd = dpEnd;
        if (diffMins < 60) {
            visualEnd = dpStart.addMinutes(60); 
        }

        let backColor = '#4f46e5'; 
        let borderColor = '#3730a3';
        
        if (session.status === 'active') {
            backColor = '#16a34a';
            borderColor = '#15803d';
        } else if (session.status === 'paused') {
            backColor = '#ca8a04';
            borderColor = '#a16207';
        }

        const sessionTitle = session.user?.name ? `${session.user.name}` : 'Session';
        const displayDuration = getDurationText(diffMins);
        const timeRangeText = `${dpStart.toString('HH:mm')} - ${isOngoing ? 'Active' : dpEnd.toString('HH:mm')}`;

        mappedEvents.push({
            id: `session_${session.id}`,
            start: dpStart.toString(),
            end: visualEnd.toString(),
            html: `
                <div style="padding: 2px 4px; display: flex; flex-direction: column; gap: 2px;">
                    <div style="font-weight: 800; font-size: 11px;">${sessionTitle}</div>
                    <div style="font-size: 10px; opacity: 0.9;">${timeRangeText}</div>
                    <div>
                        <span style="font-size: 10px; font-weight: bold; background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px; color: white;">
                            ⏱ ${displayDuration}
                        </span>
                    </div>
                </div>
            `,
            toolTip: `Supporter: ${sessionTitle}\nStatus: ${session.status.toUpperCase()}\nReal Time: ${timeRangeText}\nWorked: ${displayDuration}`,
            backColor: backColor,
            borderColor: borderColor,
            tags: { type: 'session' }
        });

        if (session.pauses && session.pauses.length > 0) {
            session.pauses.forEach(pause => {
                const pStartLiteral = getLiteralTime(pause.started_at_iso || pause.started_at);
                if (!pStartLiteral) return;

                const dpPauseStart = new DayPilot.Date(pStartLiteral);
                const pOngoing = !(pause.ended_at_iso || pause.ended_at);
                
                const dpPauseEnd = pOngoing 
                    ? new DayPilot.Date() 
                    : new DayPilot.Date(getLiteralTime(pause.ended_at_iso || pause.ended_at));

                const pDiffMins = Math.max(1, Math.round((dpPauseEnd.getTime() - dpPauseStart.getTime()) / 60000));
                
                let pVisualEnd = dpPauseEnd;
                if (pDiffMins < 15) {
                    pVisualEnd = dpPauseStart.addMinutes(15);
                }

                mappedEvents.push({
                    id: `pause_${pause.id}`,
                    start: dpPauseStart.toString(),
                    end: pVisualEnd.toString(),
                    html: `<div style="text-align:center; font-weight:bold; font-size:9px; margin-top:2px; color:white;">PAUSE (${getDurationText(pDiffMins)})</div>`,
                    toolTip: `PAUSE\nDuration: ${getDurationText(pDiffMins)}`,
                    backColor: '#ea580c',
                    borderColor: '#c2410c',
                    tags: { type: 'pause' }
                });
            });
        }
    });

    calendarConfig.events = mappedEvents;
}, { immediate: true, deep: true });

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
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-[#ca8a04]"></div>
                    <span class="text-xs text-gray-600 dark:text-gray-300 font-medium">Paused Session</span>
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
/* Global custom styling for the injected DayPilot events */
.dp-custom-pause {
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
}

.calendar_default_event_inner {
    border-radius: 6px !important;
    padding: 4px !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.2) !important;
    overflow: hidden !important;
}

/* Main Calendar Dark Mode */
.dark .calendar_default_main {
    border-color: #374151 !important;
}
.dark .calendar_default_rowheader,
.dark .calendar_default_colheader,
.dark .calendar_default_corner {
    background-color: #111827 !important;
    color: #e5e7eb !important;
    border-color: #374151 !important;
}
.dark .calendar_default_cell {
    background-color: #1f2937 !important;
    border-color: #374151 !important; 
}
.dark .calendar_default_cell_business {
    background-color: #1f2937 !important;
}
.dark .calendar_default_allday {
    background-color: #111827 !important;
    border-color: #374151 !important;
}
.dark .calendar_default_scroll {
    background-color: #1f2937 !important;
}

/* Navigator (Mini Calendar) Dark Mode */
.dark .navigator_default_main {
    border-color: transparent !important;
    background-color: transparent !important;
}
.dark .navigator_default_month {
    color: #e5e7eb !important;
}
.dark .navigator_default_day {
    color: #d1d5db !important;
}
.dark .navigator_default_day_other {
    color: #4b5563 !important;
}
.dark .navigator_default_dayheader {
    color: #9ca3af !important;
}
.dark .navigator_default_todaybox {
    border-color: #4f46e5 !important;
}
.dark .navigator_default_select .navigator_default_day {
    background-color: #374151 !important;
    color: #ffffff !important;
}
</style>