import './bootstrap';
import {
    Chart,
    BarController,
    BarElement,
    DoughnutController,
    PieController,
    ArcElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend
} from 'chart.js';
window.Chart = Chart;
import { TreemapController, TreemapElement } from 'chartjs-chart-treemap';
import ChartDataLabels from 'chartjs-plugin-datalabels';
window.ChartDataLabels = ChartDataLabels;


import Alpine from 'alpinejs';


// Register Chart.js components
Chart.register(
    BarController,
    BarElement,
    DoughnutController,
    PieController,
    ArcElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    TreemapController,
    TreemapElement,
    ChartDataLabels
);


window.Alpine = Alpine;


Alpine.start();