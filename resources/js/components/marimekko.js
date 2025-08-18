import { BarController, BarElement } from 'chart.js';

export class MarimekkoController extends BarController {
    draw() {
        const meta = this.getMeta();
        const xAxis = this.getScaleForId(meta.xAxisID);
        const yAxis = this.getScaleForId(meta.yAxisID);
        const total = this.chart.data.datasets.reduce((acc, dataset) => {
            return acc + dataset.data.reduce((a, b) => a + b, 0);
        }, 0);

        let currentX = xAxis.left;

        this.chart.data.labels.forEach((label, index) => {
            const categoryTotal = this.chart.data.datasets.reduce((acc, dataset) => acc + (dataset.data[index] || 0), 0);
            const width = (categoryTotal / total) * (xAxis.right - xAxis.left);

            let currentY = yAxis.bottom;

            meta.data.forEach((element, i) => {
                const dataset = this.chart.data.datasets[i];
                const value = dataset.data[index] || 0;

                if (element.$context.index === index) {
                    const barHeight = (value / categoryTotal) * (yAxis.bottom - yAxis.top);

                    element.x = currentX + width / 2;
                    element.y = currentY - barHeight / 2;
                    element.width = width - 1; //-1 for spacing
                    element.height = barHeight;

                    currentY -= barHeight;
                }
            });

            currentX += width;
        });

        super.draw();
    }
}

MarimekkoController.id = 'marimekko';
MarimekkoController.defaults = {
    ...BarController.defaults,
    scales: {
        x: {
            type: 'linear',
            stacked: false,
            min: 0,
            max: 100,
            ticks: {
                callback: (value) => `${value}%`
            }
        },
        y: {
            type: 'category',
            stacked: true,
            offset: true
        }
    },
    indexAxis: 'y'
};

export class MarimekkoElement extends BarElement {}
MarimekkoElement.id = 'marimekkoElement';
