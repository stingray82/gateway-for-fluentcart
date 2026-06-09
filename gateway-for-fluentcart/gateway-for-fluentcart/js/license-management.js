jQuery(document).ready(function($) {
    function normalizeSites(sites) {
        return Array.isArray(sites) ? sites : [];
    }

    function formatActivationCount(count, limit) {
        limit = parseInt(limit, 10);
        if (!limit) {
            return count + ' / ∞ Used';
        }
        return count + ' / ' + limit + ' Used';
    }

    function updateActivationCounter(license, sites) {
        var count = normalizeSites(sites).length;
        var counter = $('.rup-gateway-fc-activation-count[data-license-id="' + license + '"]');
        if (!counter.length) {
            return;
        }
        var limit = counter.data('activation-limit');
        counter.text(formatActivationCount(count, limit));
    }

    function setButtonBusy(button, busyText) {
        var btn = $(button);
        if (!btn.data('original-text')) {
            btn.data('original-text', btn.text());
        }
        btn.prop('disabled', true).text(busyText);
    }

    function resetButton(button) {
        var btn = $(button);
        btn.prop('disabled', false).text(btn.data('original-text') || btn.text());
    }

    function renderSiteList(license, sites, form) {
        sites = normalizeSites(sites);
        var siteList = $('#site-list-' + license);

        if (!sites.length) {
            siteList.remove();
            if (!$('#no-sites-' + license).length) {
                $('.manage-license[data-license-id="' + license + '"]').find('.add-site-form').before('<p class="rup-gateway-fc-muted" id="no-sites-' + license + '">No sites added yet.</p>');
            }
            return;
        }

        if (siteList.length === 0) {
            if (form && form.length) {
                form.before('<ul class="site-list" id="site-list-' + license + '"></ul>');
            } else {
                $('.manage-license[data-license-id="' + license + '"]').find('.add-site-form').before('<ul class="site-list" id="site-list-' + license + '"></ul>');
            }
            siteList = $('#site-list-' + license);
        }

        $('#no-sites-' + license).remove();
        siteList.empty();

        sites.forEach(function(site) {
            var li = $('<li/>');
            $('<span/>').text(site).appendTo(li);
            $('<button/>', {
                type: 'button',
                class: 'remove-site-btn',
                text: 'Remove'
            }).attr('data-license-id', license).attr('data-site', site).appendTo(li);
            siteList.append(li);
        });
    }

    // Handle add-site form submission.
    $('.add-site-form').on('submit', function(e) {
        e.preventDefault();
        var form    = $(this);
        var license = form.data('license-id');
        var newSite = form.find('input[name="new_site"]').val();
        var submitButton = form.find('button[type="submit"], button').first();

        setButtonBusy(submitButton, 'Adding...');

        $.ajax({
            type: 'POST',
            url: licenseManagement.ajaxurl,
            data: {
                action: 'manage_license_ajax',
                security: licenseManagement.nonce,
                license_id: license,
                license_action: 'add_site',
                new_site: newSite
            },
            success: function(response) {
                if ( response.success ) {
                    var sites = normalizeSites(response.data.sites);
                    form.find('input[name="new_site"]').val('');
                    renderSiteList(license, sites, form);
                    updateActivationCounter(license, sites);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('AJAX error occurred.');
            },
            complete: function() {
                resetButton(submitButton);
            }
        });
    });

    // Handle remove-site button click.
    $(document).on('click', '.remove-site-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var btn     = $(this);
        var license = btn.data('license-id');
        var site    = btn.data('site');

        setButtonBusy(btn, 'Removing...');

        $.ajax({
            type: 'POST',
            url: licenseManagement.ajaxurl,
            data: {
                action: 'manage_license_ajax',
                security: licenseManagement.nonce,
                license_id: license,
                license_action: 'remove_site',
                site: site
            },
            success: function(response) {
                if ( response.success ) {
                    var sites = normalizeSites(response.data.sites);
                    renderSiteList(license, sites, $('.add-site-form[data-license-id="' + license + '"]'));
                    updateActivationCounter(license, sites);
                } else {
                    alert('Error: ' + response.data);
                    resetButton(btn);
                }
            },
            error: function() {
                alert('AJAX error occurred.');
                resetButton(btn);
            }
        });
    });

    function markCopyButton(btn, success) {
        var originalText = btn.getAttribute('data-original-text') || btn.textContent || 'Copy';
        btn.setAttribute('data-original-text', originalText);
        btn.textContent = success ? 'Copied ✓' : 'Copy failed';
        btn.classList.toggle('is-copied', !!success);
        window.setTimeout(function() {
            btn.textContent = originalText;
            btn.classList.remove('is-copied');
        }, 2500);
    }

    function fallbackCopyText(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.top = '0';
        textarea.style.left = '0';
        textarea.style.width = '1px';
        textarea.style.height = '1px';
        textarea.style.padding = '0';
        textarea.style.border = '0';
        textarea.style.outline = '0';
        textarea.style.boxShadow = 'none';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);

        var copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (err) {
            copied = false;
        }

        document.body.removeChild(textarea);
        return copied;
    }

    function getLicenseKeyFromButton(btn) {
        var key = btn.getAttribute('data-license-key') || btn.getAttribute('data-clipboard-text') || '';
        if (!key) {
            var wrap = btn.closest('.rup-gateway-fc-license-key');
            var code = wrap ? wrap.querySelector('code') : null;
            key = code ? code.textContent : '';
        }
        return (key || '').trim();
    }

    function copyLicenseKey(e) {
        var btn = e.target.closest ? e.target.closest('.rup-gateway-fc-copy-license-key') : null;
        if (!btn) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        if (e.stopImmediatePropagation) {
            e.stopImmediatePropagation();
        }

        var key = getLicenseKeyFromButton(btn);
        if (!key) {
            markCopyButton(btn, false);
            return false;
        }

        if (fallbackCopyText(key)) {
            markCopyButton(btn, true);
            return false;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(key).then(function() {
                markCopyButton(btn, true);
            }).catch(function() {
                markCopyButton(btn, false);
            });
        } else {
            markCopyButton(btn, false);
        }

        return false;
    }

    // Use capture so this runs before the card/header accordion click handler.
    document.addEventListener('click', copyLicenseKey, true);
    document.addEventListener('mousedown', function(e) {
        if (e.target.closest && e.target.closest('.rup-gateway-fc-copy-license-key')) {
            e.stopPropagation();
        }
    }, true);
    document.addEventListener('touchstart', function(e) {
        if (e.target.closest && e.target.closest('.rup-gateway-fc-copy-license-key')) {
            e.stopPropagation();
        }
    }, true);
});
