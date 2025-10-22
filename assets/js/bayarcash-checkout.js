(function($) {
    'use strict';

    class BayarcashCheckout {
        constructor(form, orderHandler, data, paymentLoader) {
            this.form = form;
            this.orderHandler = orderHandler;
            this.data = data;
            this.paymentArgs = (data && data.payment_args) || {};
            this.intent = (data && data.intent) || {};
            this.paymentLoader = paymentLoader;
        }

        translate(text) {
            const translations = (window.fct_bayarcash_data && window.fct_bayarcash_data.translations) || {};
            return translations[text] || text;
        }

        init() {
            const container = document.querySelector('.fluent-cart-checkout_embed_payment_container_bayarcash');
            if (!container) {
                console.error('Bayarcash container not found');
                return;
            }

            container.innerHTML = '';

            
            const paymentMethods = this.form.querySelector('.fluent_cart_payment_methods');
            if (paymentMethods) {
                paymentMethods.style.display = 'none';
            }

            this.createBayarcashButton(container);
        }

        createBayarcashButton(container) {
            
            if (this.paymentArgs.available_channels && this.paymentArgs.available_channels.length > 0) {
                this.createChannelsAccordion(container);
            }

            const button = document.createElement('button');
            button.id = 'bayarcash-pay-button';
            button.className = 'bayarcash-checkout-button';
            button.innerHTML = this.paymentArgs.bayarcash_checkout_button_text || this.translate('Pay with Bayarcash');

            button.style.cssText = `
                width: 100%;
                padding: 12px 24px;
                background: ${this.paymentArgs.bayarcash_checkout_button_color || '#00a651'};
                color: ${this.paymentArgs.bayarcash_checkout_button_text_color || '#fff'};
                border: none;
                border-radius: 6px;
                font-size: ${this.paymentArgs.bayarcash_checkout_button_font_size || '16px'};
                font-weight: 500;
                cursor: pointer;
                transition: background-color 0.2s;
                margin-bottom: 10px;
            `;

            button.addEventListener('mouseenter', () => {
                button.style.backgroundColor = this.paymentArgs.bayarcash_checkout_button_hover_color || '#008a41';
            });

            button.addEventListener('mouseleave', () => {
                button.style.backgroundColor = this.paymentArgs.bayarcash_checkout_button_color || '#00a651';
            });

            button.addEventListener('click', async (e) => {
                e.preventDefault();
                await this.handleButtonClick();
            });

            container.appendChild(button);

            const secureText = document.createElement('p');
            secureText.style.cssText = `
                text-align: center;
                margin-top: 10px;
                font-size: 14px;
                color: #666;
            `;
            secureText.textContent = this.translate('Secure payment powered by Bayarcash');
            container.appendChild(secureText);

            
            const loadingElement = document.getElementById('fct_loading_payment_processor');
            if (loadingElement) {
                loadingElement.remove();
            }
        }

        createChannelsAccordion(container) {
            const accordionWrapper = document.createElement('div');
            accordionWrapper.className = 'bayarcash-channels-accordion';
            accordionWrapper.style.cssText = `
                margin-bottom: 20px;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                overflow: hidden;
            `;

            
            const accordionHeader = document.createElement('div');
            accordionHeader.className = 'bayarcash-accordion-header';
            accordionHeader.style.cssText = `
                padding: 15px;
                background: #f9f9f9;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-weight: 500;
                user-select: none;
            `;

            const headerText = document.createElement('span');
            headerText.textContent = this.translate('Select Payment Method') + ` (${this.paymentArgs.available_channels.length})`;
            accordionHeader.appendChild(headerText);

            const toggleIcon = document.createElement('span');
            toggleIcon.innerHTML = '▼';
            toggleIcon.style.cssText = `
                transition: transform 0.3s;
                font-size: 12px;
            `;
            accordionHeader.appendChild(toggleIcon);

            
            const accordionContent = document.createElement('div');
            accordionContent.className = 'bayarcash-accordion-content';
            accordionContent.style.cssText = `
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease-out;
                background: #fff;
            `;

            const channelsList = document.createElement('div');
            channelsList.style.cssText = `
                padding: 10px;
            `;

            
            this.paymentArgs.available_channels.forEach((channel, index) => {
                const channelLabel = document.createElement('label');
                channelLabel.className = 'bayarcash-channel-option';
                channelLabel.style.cssText = `
                    padding: 10px 15px;
                    margin-bottom: ${index < this.paymentArgs.available_channels.length - 1 ? '8px' : '0'};
                    background: #f5f5f5;
                    border: 2px solid transparent;
                    border-radius: 4px;
                    font-size: 14px;
                    display: flex;
                    align-items: center;
                    cursor: pointer;
                    transition: all 0.2s;
                `;

                const radioInput = document.createElement('input');
                radioInput.type = 'radio';
                radioInput.name = 'bayarcash_payment_channel';
                radioInput.value = channel.id;
                radioInput.style.cssText = `
                    margin-right: 10px;
                    cursor: pointer;
                    width: 16px;
                    height: 16px;
                `;

                const channelName = document.createElement('span');
                channelName.textContent = channel.name;
                channelName.style.cssText = `
                    flex: 1;
                `;

                
                const updateSelectedState = (label, isSelected) => {
                    if (isSelected) {
                        label.style.backgroundColor = '#e6f7ed';
                        label.style.borderColor = '#00a651';
                        label.style.fontWeight = '500';
                    } else {
                        label.style.backgroundColor = '#f5f5f5';
                        label.style.borderColor = 'transparent';
                        label.style.fontWeight = 'normal';
                    }
                };

                
                channelLabel.addEventListener('mouseenter', () => {
                    if (!radioInput.checked) {
                        channelLabel.style.backgroundColor = '#e8e8e8';
                    }
                });

                channelLabel.addEventListener('mouseleave', () => {
                    if (!radioInput.checked) {
                        channelLabel.style.backgroundColor = '#f5f5f5';
                    }
                });

                
                radioInput.addEventListener('change', () => {
                    this.selectedChannel = channel.id;

                    
                    const allLabels = channelsList.querySelectorAll('.bayarcash-channel-option');
                    allLabels.forEach(label => {
                        const radio = label.querySelector('input[type="radio"]');
                        updateSelectedState(label, radio.checked);
                    });

                    
                    const errorDiv = container.querySelector('.bayarcash-error-message');
                    if (errorDiv) {
                        errorDiv.remove();
                    }
                });

                channelLabel.appendChild(radioInput);
                channelLabel.appendChild(channelName);
                channelsList.appendChild(channelLabel);

                
            });

            accordionContent.appendChild(channelsList);

            
            let isOpen = true;
            accordionHeader.addEventListener('click', () => {
                isOpen = !isOpen;
                if (isOpen) {
                    accordionContent.style.maxHeight = accordionContent.scrollHeight + 'px';
                    toggleIcon.style.transform = 'rotate(180deg)';
                } else {
                    accordionContent.style.maxHeight = '0';
                    toggleIcon.style.transform = 'rotate(0deg)';
                }
            });

            accordionWrapper.appendChild(accordionHeader);
            accordionWrapper.appendChild(accordionContent);
            container.appendChild(accordionWrapper);

            
            setTimeout(() => {
                accordionContent.style.maxHeight = accordionContent.scrollHeight + 'px';
                toggleIcon.style.transform = 'rotate(180deg)';
            }, 100);
        }

        async handleButtonClick() {
            const container = document.querySelector('.fluent-cart-checkout_embed_payment_container_bayarcash');

            try {
                
                if (!this.selectedChannel) {
                    this.displayErrorMessage(container, this.translate('Please select a payment method'));
                    return;
                }

                if (this.paymentLoader) {
                    this.paymentLoader.changeLoaderStatus('processing');
                }

                
                if (this.selectedChannel) {
                    const channelInput = document.createElement('input');
                    channelInput.type = 'hidden';
                    channelInput.name = 'bayarcash_selected_channel';
                    channelInput.value = this.selectedChannel;
                    this.form.appendChild(channelInput);
                }

                if (typeof this.orderHandler === 'function') {
                    const orderData = await this.orderHandler();

                    
                    console.log('Bayarcash Order Response:', orderData);

                    if (!orderData) {
                        throw new Error(this.translate('Failed to create order'));
                    }

                    
                    if (orderData.status === 'failed' || orderData.error) {
                        const errorMessage = orderData.message || orderData.error || this.translate('Failed to create order');
                        console.error('Bayarcash Error:', errorMessage);
                        throw new Error(errorMessage);
                    }

                    
                    if (orderData.redirect_to) {
                        if (this.paymentLoader) {
                            this.paymentLoader.changeLoaderStatus('redirecting');
                        }
                        window.location.href = orderData.redirect_to;
                    } else {
                        console.error('No redirect URL in response:', orderData);
                        throw new Error(this.translate('Payment URL not received'));
                    }
                } else {
                    throw new Error(this.translate('Order handler not available'));
                }
            } catch (error) {
                console.error('Bayarcash Checkout Error:', error);

                if (this.paymentLoader) {
                    this.paymentLoader.changeLoaderStatus('Error: ' + error.message);
                    this.paymentLoader.hideLoader();
                    this.paymentLoader.enableCheckoutButton();
                }
                this.displayErrorMessage(container, error.message);
            }
        }

        displayErrorMessage(container, message) {
            
            const existingError = container.querySelector('.bayarcash-error-message');
            if (existingError) {
                existingError.remove();
            }

            const errorDiv = document.createElement('div');
            errorDiv.className = 'bayarcash-error-message';
            errorDiv.style.cssText = `
                color: #d8000c;
                padding: 15px;
                border: 1px solid #d8000c;
                border-radius: 4px;
                background: #ffeaea;
                margin-bottom: 15px;
            `;
            errorDiv.innerHTML = `<strong>Error:</strong> ${message}`;
            container.insertBefore(errorDiv, container.firstChild);
        }
    }

    
    window.addEventListener('fluent_cart_load_payments_bayarcash', function(event) {
        const container = document.querySelector('.fluent-cart-checkout_embed_payment_container_bayarcash');

        if (container) {
            container.innerHTML = '<div id="fct_loading_payment_processor">Loading Bayarcash...</div>';
        }

        
        fetch(event.detail.paymentInfoUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': event.detail.nonce
            },
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            const checkout = new BayarcashCheckout(
                event.detail.form,
                event.detail.orderHandler,
                data,
                event.detail.paymentLoader
            );
            checkout.init();
        })
        .catch(error => {
            console.error('Bayarcash initialization error:', error);
            if (container) {
                container.innerHTML = `
                    <div style="color: red; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #ffeaea;">
                        <strong>Error:</strong> An error occurred while loading Bayarcash.
                    </div>
                `;
            }
        });
    });

})(jQuery);
