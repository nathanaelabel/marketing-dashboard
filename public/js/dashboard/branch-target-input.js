document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('target-form');
    const submitBtn = document.getElementById('submit-btn');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');
    const alertContainer = document.getElementById('alert-container');

    function showAlert(message, type = 'error') {
        const alertClass = type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800';
        const iconClass = type === 'success' ? 'fa-check-circle text-green-400' : 'fa-exclamation-circle text-red-400';
        
        const alertHtml = `
            <div class="mb-4 p-4 border rounded-md ${alertClass}">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium">${message}</p>
                    </div>
                </div>
            </div>
        `;
        
        alertContainer.innerHTML = alertHtml;
        alertContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearAlert() {
        alertContainer.innerHTML = '';
    }

    function clearFieldErrors() {
        const errorMessages = document.querySelectorAll('.error-message');
        errorMessages.forEach(error => {
            error.classList.add('hidden');
            error.textContent = '';
        });

        const inputs = document.querySelectorAll('input[name^="targets"]');
        inputs.forEach(input => {
            input.classList.remove('border-red-300', 'focus:border-red-300', 'focus:ring-red-200');
            input.classList.add('border-gray-300', 'focus:border-indigo-300', 'focus:ring-indigo-200');
        });
    }

    function showFieldError(fieldName, message) {
        const input = document.querySelector(`input[name="targets[${fieldName}]"]`);
        const errorElement = document.getElementById(`error_${fieldName.replace(' ', '_')}`);
        
        if (input) {
            input.classList.remove('border-gray-300', 'focus:border-indigo-300', 'focus:ring-indigo-200');
            input.classList.add('border-red-300', 'focus:border-red-300', 'focus:ring-red-200');
        }
        
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
        }
    }

    function validateForm() {
        clearFieldErrors();
        let isValid = true;
        const inputs = document.querySelectorAll('input[name^="targets"]');
        
        inputs.forEach(input => {
            const value = input.value.trim();
            const fieldName = input.name.match(/\[(.*?)\]/)[1];
            
            if (!value || value === '' || parseFloat(value) < 0) {
                showFieldError(fieldName, 'Target amount is required and must be greater than or equal to 0');
                isValid = false;
            }
        });

        return isValid;
    }

    function setLoading(loading) {
        if (loading) {
            submitBtn.disabled = true;
            submitText.textContent = 'Saving...';
            submitSpinner.classList.remove('hidden');
        } else {
            submitBtn.disabled = false;
            submitText.textContent = 'Save Targets';
            submitSpinner.classList.add('hidden');
        }
    }

    function formatNumber(input) {
        // Remove any non-digit characters except decimal point
        let value = input.value.replace(/[^\d.]/g, '');
        
        // Ensure only one decimal point
        const parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        
        // Limit decimal places to 2
        if (parts[1] && parts[1].length > 2) {
            value = parts[0] + '.' + parts[1].substring(0, 2);
        }
        
        input.value = value;
    }

    // Add input formatting for all target inputs
    const targetInputs = document.querySelectorAll('input[name^="targets"]');
    targetInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatNumber(this);
        });

        input.addEventListener('blur', function() {
            // Format the number for display
            const value = parseFloat(this.value);
            if (!isNaN(value)) {
                this.value = value.toFixed(2);
            }
        });
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        clearAlert();
        
        if (!validateForm()) {
            showAlert('Please fill in all required fields correctly.');
            return;
        }

        setLoading(true);

        const formData = new FormData(form);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch('/branch-target/save', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            setLoading(false);
            
            if (data.success) {
                showAlert('Targets saved successfully!', 'success');
                
                // Redirect after a short delay
                setTimeout(() => {
                    window.location.href = data.redirect_url || '/dashboard';
                }, 1500);
            } else {
                if (data.errors) {
                    // Handle validation errors
                    Object.keys(data.errors).forEach(field => {
                        const fieldName = field.replace('targets.', '');
                        showFieldError(fieldName, data.errors[field][0]);
                    });
                    showAlert('Please correct the errors below.');
                } else {
                    showAlert(data.message || 'Failed to save targets. Please try again.');
                }
            }
        })
        .catch(error => {
            console.error('Error saving targets:', error);
            setLoading(false);
            showAlert('An error occurred while saving targets. Please try again.');
        });
    });

    // Auto-save functionality (optional)
    let autoSaveTimeout;
    targetInputs.forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                // Could implement auto-save here if needed
                console.log('Auto-save triggered for', input.name);
            }, 2000);
        });
    });
});
