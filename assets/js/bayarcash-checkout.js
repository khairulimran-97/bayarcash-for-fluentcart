/**
 * Bayarcash for FluentCart - Checkout Handler
 */
(function($) {
    'use strict';

    /**
     * Bayarcash Checkout Handler
     */
    const BayarcashCheckout = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Listen for Bayarcash payment method selection
            document.addEventListener('fluent_cart_load_payments_bayarcash', this.handlePaymentMethod.bind(this));
        },

        /**
         * Handle payment method selection
         *
         * @param {Event} e - Custom event from FluentCart
         */
        handlePaymentMethod: function(e) {
            const form = e.detail.form;
            const paymentLoader = e.detail.paymentLoader;
            const paymentInfoUrl = e.detail.paymentInfoUrl;
            const nonce = e.detail.nonce;

            // Show processing state
            paymentLoader.start();

            // Get payment info and process
            this.processPayment(form, paymentInfoUrl, nonce)
                .then(response => {
                    if (response.success && response.redirect_url) {
                        // Redirect to Bayarcash payment page
                        window.location.href = response.redirect_url;
                    } else {
                        paymentLoader.stop();
                        this.showError(response.message || 'Payment processing failed');
                    }
                })
                .catch(error => {
                    paymentLoader.stop();
                    this.showError(error.message || 'An error occurred while processing payment');
                    console.error('Bayarcash payment error:', error);
                });
        },

        /**
         * Process payment
         *
         * @param {HTMLFormElement} form - Checkout form
         * @param {string} paymentInfoUrl - Payment info endpoint URL
         * @param {string} nonce - Security nonce
         * @returns {Promise}
         */
        processPayment: function(form, paymentInfoUrl, nonce) {
            return fetch(paymentInfoUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    payment_method: 'bayarcash'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            });
        },

        /**
         * Show error message
         *
         * @param {string} message - Error message
         */
        showError: function(message) {
            // Create or update error notice
            let errorDiv = document.querySelector('.bayarcash-error-notice');

            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'bayarcash-error-notice fluent-cart-notice fluent-cart-notice-error';

                const form = document.querySelector('.fluent-cart-checkout-form');
                if (form) {
                    form.insertBefore(errorDiv, form.firstChild);
                }
            }

            errorDiv.innerHTML = `
                <div class="fluent-cart-notice-content">
                    <strong>${message}</strong>
                </div>
            `;

            // Scroll to error
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Auto-hide after 5 seconds
            setTimeout(() => {
                if (errorDiv && errorDiv.parentNode) {
                    errorDiv.remove();
                }
            }, 5000);
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BayarcashCheckout.init();
        });
    } else {
        BayarcashCheckout.init();
    }

})(jQuery);
