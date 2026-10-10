import { bars, line, donut, heat, fmt } from '../shared/charts.js';

/** Alpine magic helpers so templates can write x-html="$chart.bars(data)". */
export function charts(Alpine) {
    Alpine.magic('chart', () => ({ bars, line, donut, heat, fmt }));
}
