document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('target-form');
    const submitBtn = document.getElementById('submit-btn');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');
    const alertContainer = document.getElementById('alert-container');

    // Delete functionality elements
    const deleteBtn = document.getElementById('delete-btn');
    const deleteModal = document.getElementById('delete-modal');
    const deletePeriodText = document.getElementById('delete-period-text');
    const confirmDeleteBtn = document.getElementById('confirm-delete');
    const cancelDeleteBtn = document.getElementById('cancel-delete');
    const deleteText = document.getElementById('delete-text');
    const deleteSpinner = document.getElementById('delete-spinner');

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

            if (!value || value === '') {
                showFieldError(fieldName, 'Target amount is required');
                isValid = false;
            } else {
                const numericValue = parseFormattedNumber(value);
                if (numericValue < 0) {
                    showFieldError(fieldName, 'Target amount must be greater than or equal to 0');
                    isValid = false;
                }
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

    function formatNumberForDisplay(value) {
        // Format number with Indonesian thousand separators (periods)
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value);
    }

    function parseFormattedNumber(formattedValue) {
        // Remove all periods (thousand separators) and convert back to number
        return parseFloat(formattedValue.replace(/\./g, '')) || 0;
    }

    function formatNumber(input) {
        // Store the raw numeric value (remove all non-digits)
        let rawValue = input.value.replace(/[^\d]/g, '');

        if (rawValue === '') {
            input.value = '';
            return;
        }

        // Convert to number and format with thousand separators
        const numericValue = parseInt(rawValue);
        const formattedValue = formatNumberForDisplay(numericValue);
        input.value = formattedValue;
    }

    function validateNumericInput(input) {
        // Only allow numeric input, remove any non-digits
        let rawValue = input.value.replace(/[^\d]/g, '');
        input.value = rawValue;
    }

    // Add input formatting for all target inputs
    const targetInputs = document.querySelectorAll('input[name^="targets"]');
    targetInputs.forEach(input => {
        // Format existing values on page load
        if (input.value && input.value.trim() !== '') {
            const numericValue = parseFloat(input.value);
            if (!isNaN(numericValue)) {
                input.value = formatNumberForDisplay(numericValue);
            }
        }

        input.addEventListener('input', function (e) {
            // Only validate numeric input while typing, don't format
            validateNumericInput(this);
        });

        input.addEventListener('blur', function () {
            // Format the number only when field loses focus
            formatNumber(this);
        });

        // Also add keydown event to handle special keys
        input.addEventListener('keydown', function (e) {
            // Allow: backspace, delete, tab, escape, enter
            if ([46, 8, 9, 27, 13].indexOf(e.keyCode) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                // Allow: home, end, left, right
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        clearAlert();

        if (!validateForm()) {
            showAlert('Please fill in all required fields correctly.');
            return;
        }

        setLoading(true);

        // Convert formatted numbers back to numeric values before submission
        const formData = new FormData();
        const originalFormData = new FormData(form);

        for (let [key, value] of originalFormData.entries()) {
            if (key.startsWith('targets[')) {
                // Convert formatted number back to numeric value
                formData.append(key, parseFormattedNumber(value));
            } else {
                formData.append(key, value);
            }
        }

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

    // Delete functionality
    function showDeleteModal() {
        console.log('showDeleteModal called');

        const month = document.querySelector('input[name="month"]').value;
        const year = document.querySelector('input[name="year"]').value;
        const category = document.querySelector('input[name="category"]').value;

        console.log('Form data:', { month, year, category });

        // Get month name
        const months = [
            '', 'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        const monthName = months[parseInt(month)] || 'Unknown';

        // Update period text in modal
        if (deletePeriodText) {
            deletePeriodText.textContent = `${monthName} ${year} - ${category}`;
        }

        // Show modal
        if (deleteModal) {
            console.log('Showing delete modal');
            deleteModal.classList.remove('hidden');
        } else {
            console.log('Delete modal element not found');
        }
    }

    function hideDeleteModal() {
        deleteModal.classList.add('hidden');
    }

    function setDeleteLoading(loading) {
        if (loading) {
            confirmDeleteBtn.disabled = true;
            deleteText.textContent = 'Deleting...';
            deleteSpinner.classList.remove('hidden');
        } else {
            confirmDeleteBtn.disabled = false;
            deleteText.textContent = 'Delete';
            deleteSpinner.classList.add('hidden');
        }
    }

    function performDelete() {
        const month = document.querySelector('input[name="month"]').value;
        const year = document.querySelector('input[name="year"]').value;
        const category = document.querySelector('input[name="category"]').value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        setDeleteLoading(true);

        fetch('/branch-target/delete', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                month: month,
                year: year,
                category: category
            })
        })
            .then(response => response.json())
            .then(data => {
                setDeleteLoading(false);
                hideDeleteModal();

                if (data.success) {
                    showAlert('Targets deleted successfully!', 'success');

                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = data.redirect_url || '/dashboard';
                    }, 1500);
                } else {
                    showAlert(data.message || 'Failed to delete targets. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error deleting targets:', error);
                setDeleteLoading(false);
                hideDeleteModal();
                showAlert('An error occurred while deleting targets. Please try again.');
            });
    }

    // Delete button event listeners
    if (deleteBtn) {
        console.log('Delete button found, adding event listener');
        deleteBtn.addEventListener('click', function (e) {
            e.preventDefault();
            console.log('Delete button clicked');
            showDeleteModal();
        });
    } else {
        console.log('Delete button not found');
    }

    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function (e) {
            e.preventDefault();
            hideDeleteModal();
        });
    }

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function (e) {
            e.preventDefault();
            performDelete();
        });
    }

    // Close modal when clicking outside
    if (deleteModal) {
        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) {
                hideDeleteModal();
            }
        });
    }

    // Auto-save functionality (optional)
    let autoSaveTimeout;
    targetInputs.forEach(input => {
        input.addEventListener('input', function () {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                // Could implement auto-save here if needed
                console.log('Auto-save triggered for', input.name);
            }, 2000);
        });
    });
});
