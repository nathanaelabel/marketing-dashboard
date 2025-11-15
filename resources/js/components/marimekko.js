import { BarController, BarElement } from "chart.js";

export class MarimekkoController extends BarController {
    static id = "marimekko";

    static defaults = {
        ...BarController.defaults,
        scales: {
            x: {
                type: "linear",
                min: 0,
                max: 1,
                display: false,
            },
            y: {
                type: "linear",
                min: 0,
                max: 1,
                ticks: {
                    callback: function (value) {
                        return value * 100 + "%";
                    },
                },
            },
        },
    };

    update(mode) {
        const meta = this.getMeta();
        const data = this.chart.data;

        // Calculate total revenue across all branches
        const branchTotals = {};
        let grandTotal = 0;

        data.labels.forEach((branch, branchIndex) => {
            let branchTotal = 0;
            data.datasets.forEach((dataset) => {
                if (dataset.data[branchIndex] && dataset.data[branchIndex].v) {
                    branchTotal += dataset.data[branchIndex].v;
                }
            });
            branchTotals[branch] = branchTotal;
            grandTotal += branchTotal;
        });

        // Calculate positions for each bar segment and create elements
        let elementIndex = 0;
        let currentX = 0;

        data.labels.forEach((branch, branchIndex) => {
            const branchTotal = branchTotals[branch];
            const barWidth = grandTotal > 0 ? branchTotal / grandTotal : 0;

            let currentY = 0;

            data.datasets.forEach((dataset, datasetIndex) => {
                const dataPoint = dataset.data[branchIndex];
                if (dataPoint) {
                    const segmentHeight =
                        branchTotal > 0 ? (dataPoint.v || 0) / branchTotal : 0;

                    if (!meta.data[elementIndex]) {
                        meta.data[elementIndex] = new MarimekkoElement();
                    }

                    const element = meta.data[elementIndex];
                    element.x = currentX + barWidth / 2;
                    element.y = currentY + segmentHeight / 2;
                    element.width = barWidth;
                    element.height = segmentHeight;
                    element.base = currentY;
                    element.$context = {
                        chart: this.chart,
                        dataIndex: branchIndex,
                        dataset: dataset,
                        datasetIndex: datasetIndex,
                        element: element,
                        index: elementIndex,
                        mode: mode,
                        type: "data",
                        raw: dataPoint,
                        parsed: dataPoint,
                    };

                    currentY += segmentHeight;
                    elementIndex++;
                }
            });

            currentX += barWidth;
        });

        // Trim excess elements
        meta.data.splice(elementIndex);

        super.update(mode);
    }

    draw() {
        const ctx = this.chart.ctx;
        const meta = this.getMeta();
        const xScale = this.chart.scales.x;
        const yScale = this.chart.scales.y;
        const data = this.chart.data;

        // Calculate branch positions for labels
        const branchTotals = {};
        let grandTotal = 0;

        data.labels.forEach((branch, branchIndex) => {
            let branchTotal = 0;
            data.datasets.forEach((dataset) => {
                if (dataset.data[branchIndex] && dataset.data[branchIndex].v) {
                    branchTotal += dataset.data[branchIndex].v;
                }
            });
            branchTotals[branch] = branchTotal;
            grandTotal += branchTotal;
        });

        // Draw chart segments
        meta.data.forEach((element, index) => {
            if (element && element.width > 0 && element.height > 0) {
                const x = xScale.getPixelForValue(
                    element.x - element.width / 2
                );
                const y = yScale.getPixelForValue(
                    element.y + element.height / 2
                );
                const width =
                    xScale.getPixelForValue(element.width) -
                    xScale.getPixelForValue(0);
                const height =
                    yScale.getPixelForValue(element.base) -
                    yScale.getPixelForValue(element.base + element.height);

                const datasetIndex = Math.floor(index / data.labels.length);
                const dataset = data.datasets[datasetIndex];

                ctx.fillStyle = dataset.backgroundColor;
                ctx.fillRect(x, y, width, height);

                // Draw border
                ctx.strokeStyle = "#fff";
                ctx.lineWidth = 1;
                ctx.strokeRect(x, y, width, height);
            }
        });

        // Draw branch labels at the bottom
        let currentX = 0;
        ctx.fillStyle = "#374151";
        ctx.font = "12px Arial";
        ctx.textAlign = "center";

        data.labels.forEach((branch, branchIndex) => {
            const branchTotal = branchTotals[branch];
            const barWidth = grandTotal > 0 ? branchTotal / grandTotal : 0;

            const x = xScale.getPixelForValue(currentX + barWidth / 2);
            const y = yScale.bottom + 20;

            ctx.fillText(branch, x, y);

            currentX += barWidth;
        });
    }
}

export class MarimekkoElement extends BarElement {
    static id = "marimekkoElement";
}
