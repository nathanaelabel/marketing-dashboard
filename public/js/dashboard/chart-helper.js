const ChartHelper = {
    calculateYAxisMax(datasets, increment) {
        let maxTotal = 0;
        if (datasets.length > 0) {
            const dataLength = datasets[0].data.length;
            for (let i = 0; i < dataLength; i++) {
                let total = 0;
                for (const dataset of datasets) {
                    total += dataset.data[i] || 0;
                }
                if (total > maxTotal) {
                    maxTotal = total;
                }
            }
        }
        return Math.ceil(maxTotal / increment) * increment;
    },

    formatCurrency(value, divisor) {
        const scaledValue = value / divisor;
        if (scaledValue >= 1) {
            return scaledValue.toFixed(0);
        } else if (scaledValue > 0) {
            return scaledValue.toFixed(1);
        } else {
            return '0';
        }
    }
};
