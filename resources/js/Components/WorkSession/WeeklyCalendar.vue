<script setup>
/**
 * WeeklyCalendar Component
 * Wrapper for DayPilot Lite Vue Calendar.
 * Maps backend WorkSession and Pause data into DayPilot events.
 * Enforces a minimum visual height of 60 minutes for visibility, while retaining actual duration text.
 */
import { ref, computed } from 'vue';
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
 * Formats a JS Date object to HH:mm.
 */
const formatTime = (dateObj) => {
    return dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
};

/**
 * Converts minutes into a readable string (e.g., "1 min", "1h 15m").
 */
const getDurationText = (diffMins) => {
    if (diffMins < 60) return `${diffMins} min`;
    const h = Math.floor(diffMins / 60);
    const m = diffMins % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
};

/**
 * Transforms the API sessions into DayPilot events with minimum visual block heights.
 */
const mappedEvents = computed(() => {
    const events = [];
    
    props.sessions.forEach(session => {
        const startObj = new Date(session.started_at_iso);
        const isOngoing = !session.ended_at_iso;
        const endObj = new Date(session.ended_at_iso || new Date().toISOString());
        
        // Calculate real duration in minutes
        const diffMs = endObj.getTime() - startObj.getTime();
        const diffMins = Math.max(1, Math.round(diffMs / 60000)); // Enforce at least 1 min mathematically
        
        const displayDuration = getDurationText(diffMins);
        const timeRangeText = `${formatTime(startObj)} - ${isOngoing ? 'Active' : formatTime(endObj)}`;
        
        // ARCHITECTURE RULE: Visual Minimum of 60 minutes for Work Sessions
        let visualEndObj = new Date(endObj.getTime());
        if (diffMins < 60) {
            visualEndObj = new Date(startObj.getTime() + 60 * 60000); // Add 60 mins to the visual block
        }

        // Base styling for Work Session
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

        // Advanced HTML injected directly into the calendar block
        const htmlContent = `
            <div style="padding: 2px 4px; display: flex; flex-direction: column; gap: 2px;">
                <div style="font-weight: 800; font-size: 11px;">${sessionTitle}</div>
                <div style="font-size: 10px; opacity: 0.9;">${timeRangeText}</div>
                <div>
                    <span style="font-size: 10px; font-weight: bold; background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px;">
                        ⏱ ${displayDuration}
                    </span>
                </div>
            </div>
        `;

        // Tooltip shown when the user hovers over the block
        const toolTipText = `Supporter: ${sessionTitle}\nStatus: ${session.status.toUpperCase()}\nReal Time: ${timeRangeText}\nTotal Worked: ${displayDuration}`;

        // Add Main Work Session Event
        events.push({
            id: `session_${session.id}`,
            start: startObj.toISOString(),
            end: visualEndObj.toISOString(), // The artificially expanded visual end time
            html: htmlContent, // Overrides 'text'
            toolTip: toolTipText,
            backColor: backColor,
            borderColor: borderColor,
            tags: { type: 'session' }
        });

        // Add Pauses (We enforce a smaller minimum of 15 mins for visual harmony inside the expanded session)
        if (session.pauses && session.pauses.length > 0) {
            session.pauses.forEach(pause => {
                const pStart = new Date(pause.started_at_iso);
                const pEnd = new Date(pause.ended_at_iso || new Date().toISOString());
                const pDiffMins = Math.max(1, Math.round((pEnd.getTime() - pStart.getTime()) / 60000));
                
                let pVisualEnd = new Date(pEnd.getTime());
                if (pDiffMins < 15) {
                    pVisualEnd = new Date(pStart.getTime() + 15 * 60000); // 15 mins visual minimum
                }

                const pauseHover = `PAUSE\nReal Time: ${formatTime(pStart)} - ${pause.ended_at_iso ? formatTime(pEnd) : 'Active'}\nDuration: ${getDurationText(pDiffMins)}`;

                events.push({
                    id: `pause_${pause.id}`,
                    start: pStart.toISOString(),
                    end: pVisualEnd.toISOString(),
                    html: `<div style="text-align:center; font-weight:bold; font-size:9px; margin-top:2px;">PAUSE (${getDurationText(pDiffMins)})</div>`,
                    toolTip: pauseHover,
                    backColor: '#ea580c',
                    borderColor: '#c2410c',
                    tags: { type: 'pause' }
                });
            });
        }
    });
    
    return events;
});

/**
 * Emits the date change event to the parent when user clicks the mini-calendar.
 */
const onNavTimeRangeSelected = (args) => {
    emit('update:weekStartDate', args.day.toString('yyyy-MM-dd'));
};

/**
 * Intercepts event rendering to inject custom styling.
 */
const onBeforeEventRender = (args) => {
    args.data.fontColor = '#ffffff';
    args.data.backColor += 'e6'; // Adds slight transparency
    
    // Distinct styling for Pause blocks
    if (args.data.tags && args.data.tags.type === 'pause') {
        args.data.cssClass = 'dp-custom-pause';
    }
};
</script>

<template>
    <div class="flex flex-col md:flex-row gap-6 bg-white dark:bg-gray-800 p-4 rounded-2xl shadow border border-gray-200 dark:border-gray-700 transition-colors duration-200">
        
        <div class="w-full md:w-64 flex-shrink-0">
            <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-2 border border-gray-100 dark:border-gray-700 transition-colors duration-200">
                <DayPilotNavigator
                    selectMode="Week"
                    :showMonths="1"
                    :skipMonths="1"
                    :selectionDay="weekStartDate"
                    @timeRangeSelected="onNavTimeRangeSelected"
                />
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
            <DayPilotCalendar
                ref="calendarRef"
                viewType="Week"
                :startDate="weekStartDate"
                :events="mappedEvents"
                :durationBarVisible="false"
                :headerHeight="50"
                :hourWidth="60"
                eventMoveHandling="Disabled"
                eventResizeHandling="Disabled"
                timeRangeSelectedHandling="Disabled"
                @beforeEventRender="onBeforeEventRender"
            />
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

/* =========================================================
   DARK MODE OVERRIDES FOR DAYPILOT LITE VUE
   ========================================================= */

/* Main Calendar Dark Mode */
.dark .calendar_default_main {
    border-color: #374151 !important;
}
.dark .calendar_default_rowheader,
.dark .calendar_default_colheader,
.dark .calendar_default_corner {
    background-color: #111827 !important; /* Tailwind gray-900 */
    color: #e5e7eb !important; /* Tailwind gray-200 */
    border-color: #374151 !important; /* Tailwind gray-700 */
}
.dark .calendar_default_cell {
    background-color: #1f2937 !important; /* Tailwind gray-800 */
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
    border-color: #4f46e5 !important; /* Tailwind indigo-600 */
}
.dark .navigator_default_select .navigator_default_day {
    background-color: #374151 !important;
    color: #ffffff !important;
}
</style>