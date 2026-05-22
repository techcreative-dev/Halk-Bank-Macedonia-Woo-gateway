/**
 * Halkbank Payment Gateway - Modern Admin Settings UI
 * Reorganizes the default WooCommerce settings into modern card-based sections
 */
(function($) {
    'use strict';

    // Define sections and their field mappings
    const sections = [
        {
            id: 'basic',
            title: 'Basic Settings',
            icon: 'dashicons-admin-settings',
            description: 'Enable and configure basic options',
            fields: ['enabled', 'nphide', 'title', 'description']
        },
        {
            id: 'credentials',
            title: 'Merchant Credentials',
            icon: 'dashicons-lock',
            description: 'Bank authentication details',
            cssClass: 'halkbank-section-credentials',
            fields: ['merchant_id', 'merchant_username', 'merchant_password', 'store_key']
        },
        {
            id: 'currency',
            title: 'Currency & Language',
            icon: 'dashicons-translation',
            description: 'Regional settings',
            fields: ['merchant_currency', 'conversion_rate_adjust', 'user_language_code']
        },
        {
            id: 'transaction',
            title: 'Transaction Settings',
            icon: 'dashicons-money-alt',
            description: 'Payment processing options',
            cssClass: 'halkbank-section-transaction',
            fields: ['tran_type', 'preauth_means_paid', 'postauth_after_days', 'refreshtime', 'add_bill_to', 'store_type']
        },
        {
            id: 'instalments',
            title: 'Instalment Plans',
            icon: 'dashicons-calendar-alt',
            description: 'Payment by instalments configuration',
            fields: ['instalment_plans', 'installmentonhpp']
        },
        {
            id: 'branding',
            title: 'Branding & Appearance',
            icon: 'dashicons-format-image',
            description: 'Logos and visual elements',
            fields: ['bank_logo', 'cc_logo', 'footer_template']
        },
        {
            id: 'orders',
            title: 'Order Status Mapping',
            icon: 'dashicons-clipboard',
            description: 'Map payment outcomes to order statuses',
            cssClass: 'halkbank-section-orders',
            fields: ['order_completed', 'order_transaction_postauthorised', 'order_transaction_voided', 'order_failed', 'order_abandon_cancel']
        },
        {
            id: 'emails',
            title: 'Email & Display Settings',
            icon: 'dashicons-email-alt',
            description: 'Notification preferences',
            fields: ['auto_proceed_with_form', 'no_capture_void_email', 'no_transaction_email', 'no_transaction_data', 'include_order_details', 'include_order_details_mail']
        },
        {
            id: 'urls',
            title: 'URL Configuration',
            icon: 'dashicons-admin-links',
            description: 'Return and redirect URLs',
            fields: ['override_back_url', 'after_override_timout', 'cancel_url']
        },
        {
            id: 'recaptcha',
            title: 'reCAPTCHA Security',
            icon: 'dashicons-shield',
            description: 'Bot protection settings',
            cssClass: 'halkbank-section-recaptcha',
            fields: ['use_recaptcha', 'recaptcha_site_key', 'recaptcha_secret_key']
        },
        {
            id: 'advanced',
            title: 'Advanced Settings',
            icon: 'dashicons-admin-tools',
            description: 'Debug and advanced options',
            cssClass: 'halkbank-section-advanced',
            collapsible: true,
            collapsed: true,
            fields: ['additional_form_variables', 'override_language', 'debug_mode']
        }
    ];

    function initModernSettings() {
        // Check if we're on the Halkbank settings page
        const $formTable = $('form#mainform table.form-table').first();
        if (!$formTable.length) return;

        // Check if this is the Halkbank section (handles both section names)
        const urlParams = new URLSearchParams(window.location.search);
        const section = urlParams.get('section');
        if (section !== 'halkbank' && section !== 'wc_gateway_halkbank') return;

        // Destroy all Select2 instances before moving elements
        destroySelect2Instances($formTable);

        // Create wrapper
        const $wrapper = $('<div class="halkbank-settings-wrapper"></div>');

        // Add page header
        const $pageHeader = createPageHeader();
        $wrapper.append($pageHeader);

        // Process each section
        sections.forEach(function(section) {
            const $section = createSection(section, $formTable);
            if ($section) {
                $wrapper.append($section);
            }
        });

        // Handle any remaining fields not in sections
        const $remainingRows = $formTable.find('tbody tr');
        if ($remainingRows.length > 0) {
            const $otherSection = createSection({
                id: 'other',
                title: 'Other Settings',
                icon: 'dashicons-admin-generic',
                description: 'Additional configuration',
                fields: []
            }, $formTable, $remainingRows);
            if ($otherSection) {
                $wrapper.append($otherSection);
            }
        }

        // Replace original table with wrapper
        $formTable.before($wrapper);
        $formTable.remove(); // Remove completely instead of hide

        // Re-initialize Select2 on all enhanced selects
        reinitializeSelect2($wrapper);

        // Initialize interactive features
        initCollapsibleSections();
        initPasswordToggles();
        enhanceEnableToggle();
    }

    function destroySelect2Instances($container) {
        // Find all select elements with Select2 initialized
        $container.find('select.wc-enhanced-select, select.select2-hidden-accessible').each(function() {
            const $select = $(this);
            try {
                if ($select.data('select2')) {
                    $select.select2('destroy');
                }
            } catch(e) {
                // Select2 might not be initialized, ignore error
            }
            // Remove any Select2 related classes
            $select.removeClass('select2-hidden-accessible');
            // Remove the Select2 container if it exists
            $select.next('.select2-container').remove();
        });
    }

    function reinitializeSelect2($container) {
        // Re-initialize WooCommerce enhanced selects
        $container.find('select.wc-enhanced-select').each(function() {
            const $select = $(this);

            // Check if select2 function exists
            if (typeof $.fn.select2 !== 'undefined') {
                $select.select2({
                    minimumResultsForSearch: 10,
                    allowClear: $select.data('allow_clear') === true,
                    placeholder: $select.data('placeholder') || ''
                });
            }
        });
    }

    function createPageHeader() {
        const isEnabled = $('#woocommerce_halkbank_enabled').is(':checked');
        const statusClass = isEnabled ? 'enabled' : 'disabled';
        const statusText = isEnabled ? 'Active' : 'Inactive';

        return $(`
            <div class="halkbank-page-header">
                <div class="halkbank-page-header-logo">
                    <span class="dashicons dashicons-money-alt" style="font-size: 40px; width: 40px; height: 40px; color: #1e3a5f;"></span>
                </div>
                <div class="halkbank-page-header-content">
                    <h1>Halkbank Payment Gateway</h1>
                    <p>Configure your payment gateway settings for secure card transactions</p>
                </div>
                <div class="halkbank-status-badge ${statusClass}">
                    <span class="dashicons dashicons-${isEnabled ? 'yes-alt' : 'dismiss'}"></span>
                    ${statusText}
                </div>
            </div>
        `);
    }

    function createSection(section, $formTable, $existingRows) {
        const $rows = [];

        if (!$existingRows) {
            // Find rows for this section's fields
            section.fields.forEach(function(fieldId) {
                const $row = $formTable.find('tr.' + fieldId);
                if ($row.length) {
                    $rows.push($row);
                }
            });
        } else {
            // Use existing rows (for "other" section)
            $existingRows.each(function() {
                $rows.push($(this));
            });
        }

        if ($rows.length === 0) return null;

        const collapsedClass = section.collapsed ? ' collapsed' : '';
        const collapsibleClass = section.collapsible ? ' collapsible' : '';
        const extraClass = section.cssClass ? ' ' + section.cssClass : '';

        const $sectionDiv = $(`
            <div class="halkbank-settings-section${extraClass}" data-section="${section.id}">
                <div class="halkbank-section-header${collapsibleClass}${collapsedClass}">
                    <span class="dashicons ${section.icon}"></span>
                    <span class="section-title">${section.title}</span>
                    <span class="section-description">${section.description}</span>
                </div>
                <div class="halkbank-section-content${collapsedClass}">
                    <table class="form-table">
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        `);

        const $tbody = $sectionDiv.find('tbody');

        // Move rows instead of cloning (using detach to preserve data/events)
        $rows.forEach(function($row) {
            $tbody.append($row.detach());
        });

        return $sectionDiv;
    }

    function initCollapsibleSections() {
        $('.halkbank-section-header.collapsible').on('click', function() {
            const $header = $(this);
            const $content = $header.next('.halkbank-section-content');

            $header.toggleClass('collapsed');
            $content.toggleClass('collapsed');

            if (!$content.hasClass('collapsed')) {
                $content.hide().slideDown(200);
            } else {
                $content.slideUp(200);
            }
        });
    }

    function initPasswordToggles() {
        $('.halkbank-section-content input[type="password"]').each(function() {
            const $input = $(this);
            const $wrapper = $('<div class="halkbank-password-wrapper"></div>');
            const $toggle = $(`
                <button type="button" class="halkbank-password-toggle" tabindex="-1">
                    <span class="dashicons dashicons-visibility"></span>
                </button>
            `);

            $input.wrap($wrapper);
            $input.after($toggle);

            $toggle.on('click', function(e) {
                e.preventDefault();
                const type = $input.attr('type') === 'password' ? 'text' : 'password';
                $input.attr('type', type);
                $toggle.find('.dashicons')
                    .toggleClass('dashicons-visibility', type === 'password')
                    .toggleClass('dashicons-hidden', type === 'text');
            });
        });
    }

    function enhanceEnableToggle() {
        const $enableRow = $('.halkbank-settings-section[data-section="basic"] tr.enabled');
        const $checkbox = $enableRow.find('input[type="checkbox"]');

        // Update status badge when checkbox changes
        $checkbox.on('change', function() {
            const isEnabled = $(this).is(':checked');
            const $badge = $('.halkbank-status-badge');

            $badge.removeClass('enabled disabled')
                  .addClass(isEnabled ? 'enabled' : 'disabled');

            $badge.find('.dashicons')
                  .removeClass('dashicons-yes-alt dashicons-dismiss')
                  .addClass(isEnabled ? 'dashicons-yes-alt' : 'dashicons-dismiss');

            $badge.contents().filter(function() {
                return this.nodeType === 3;
            }).last().replaceWith(isEnabled ? ' Active' : ' Inactive');
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        // Small delay to ensure WooCommerce has initialized Select2
        setTimeout(initModernSettings, 150);
    });

})(jQuery);
