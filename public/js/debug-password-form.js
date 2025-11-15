/**
 * Debug script untuk Update Password Form
 *
 * Cara menggunakan:
 * 1. Buka browser console (F12)
 * 2. Paste script ini ke console
 * 3. Submit form dan lihat output di console
 */

(function () {
    console.log("🔍 Password Update Form Debugger loaded");

    // Find the password update form
    const form = document.querySelector('form[action*="password"]');

    if (!form) {
        console.error("❌ Password update form not found!");
        return;
    }

    console.log("✅ Form found:", form);

    // Log form attributes
    console.log("📋 Form attributes:", {
        action: form.getAttribute("action"),
        method: form.getAttribute("method"),
        hasCSRF: !!form.querySelector('input[name="_token"]'),
        hasMethod: !!form.querySelector('input[name="_method"]'),
    });

    // Get all inputs
    const inputs = {
        current_password: form.querySelector('input[name="current_password"]'),
        password: form.querySelector('input[name="password"]'),
        password_confirmation: form.querySelector(
            'input[name="password_confirmation"]'
        ),
        csrf: form.querySelector('input[name="_token"]'),
        method: form.querySelector('input[name="_method"]'),
    };

    console.log("📝 Form inputs:", inputs);

    // Add submit event listener
    form.addEventListener("submit", function (e) {
        console.log("🚀 Form submission started");

        // Log form data
        const formData = new FormData(form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            if (
                key === "current_password" ||
                key === "password" ||
                key === "password_confirmation"
            ) {
                data[key] = value
                    ? "***" + value.length + " chars***"
                    : "empty";
            } else {
                data[key] = value;
            }
        }

        console.log("📦 Form data:", data);

        // Validate inputs
        const validation = {
            current_password_filled: !!inputs.current_password.value,
            password_filled: !!inputs.password.value,
            password_confirmation_filled: !!inputs.password_confirmation.value,
            passwords_match:
                inputs.password.value === inputs.password_confirmation.value,
            csrf_present: !!inputs.csrf.value,
            method_present: !!inputs.method.value,
        };

        console.log("✔️ Validation:", validation);

        // Check for validation errors
        const errors = [];
        if (!validation.current_password_filled)
            errors.push("Current password is empty");
        if (!validation.password_filled) errors.push("New password is empty");
        if (!validation.password_confirmation_filled)
            errors.push("Password confirmation is empty");
        if (!validation.passwords_match) errors.push("Passwords do not match");
        if (!validation.csrf_present) errors.push("CSRF token missing");
        if (!validation.method_present)
            errors.push("HTTP method override missing");

        if (errors.length > 0) {
            console.error("❌ Validation errors:", errors);
        } else {
            console.log("✅ All validations passed");
        }

        // Don't prevent default - let the form submit
        console.log("➡️ Allowing form to submit...");
    });

    console.log(
        "✅ Debug listeners attached. Submit the form to see debug output."
    );
})();
