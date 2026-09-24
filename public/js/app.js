/**
 * School Financial Management System (SFMS)
 * Main JavaScript File
 */

(function () {
    'use strict';

    /* ----------------------------------------
       1. SIDEBAR TOGGLE (Mobile)
       ---------------------------------------- */
    function initSidebar() {
        var hamburger = document.querySelector('.hamburger');
        var sidebar = document.querySelector('.sidebar');
        var overlay = document.querySelector('.sidebar-overlay');

        if (!hamburger || !sidebar) return;

        hamburger.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });

        if (overlay) {
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            });
        }
    }

    /* ----------------------------------------
       2. MODAL OPEN / CLOSE
       ---------------------------------------- */
    window.openModal = function (modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function (modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    function initModals() {
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
                document.body.style.overflow = '';
            }

            if (e.target.closest('.modal-close')) {
                var modal = e.target.closest('.modal-overlay');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var activeModals = document.querySelectorAll('.modal-overlay.active');
                activeModals.forEach(function (modal) {
                    modal.classList.remove('active');
                });
                document.body.style.overflow = '';
            }
        });
    }

    /* ----------------------------------------
       3. CUSTOM CONFIRM DIALOG
       ---------------------------------------- */
    window.sfmsConfirm = function (message, callback, options) {
        options = options || {};
        var title = options.title || 'Confirm Action';
        var confirmText = options.confirmText || 'Confirm';
        var cancelText = options.cancelText || 'Cancel';
        var confirmClass = options.confirmClass || 'btn-danger';

        var overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.style.zIndex = '5000';

        overlay.innerHTML =
            '<div class="modal modal-confirm" style="max-width:400px;">' +
                '<div class="modal-header">' +
                    '<h3>' + title + '</h3>' +
                '</div>' +
                '<div class="modal-body">' +
                    '<div class="confirm-icon">⚠️</div>' +
                    '<p class="confirm-message">' + message + '</p>' +
                '</div>' +
                '<div class="modal-footer">' +
                    '<button class="btn btn-secondary sfms-confirm-cancel">' + cancelText + '</button>' +
                    '<button class="btn ' + confirmClass + ' sfms-confirm-ok">' + confirmText + '</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        function cleanup() {
            overlay.remove();
            document.body.style.overflow = '';
        }

        overlay.querySelector('.sfms-confirm-cancel').addEventListener('click', function () {
            cleanup();
            if (typeof callback === 'function') callback(false);
        });

        overlay.querySelector('.sfms-confirm-ok').addEventListener('click', function () {
            cleanup();
            if (typeof callback === 'function') callback(true);
        });

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                cleanup();
                if (typeof callback === 'function') callback(false);
            }
        });
    };

    /* ----------------------------------------
       4. USER DROPDOWN MENU
       ---------------------------------------- */
    function initUserDropdown() {
        var userMenu = document.querySelector('.user-menu');
        var dropdown = document.querySelector('.user-dropdown');

        if (!userMenu || !dropdown) return;

        userMenu.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });

        document.addEventListener('click', function () {
            dropdown.classList.remove('show');
        });
    }

    /* ----------------------------------------
       5. NOTIFICATION DROPDOWN
       ---------------------------------------- */
    function initNotificationDropdown() {
        var bell = document.querySelector('.notification-bell');
        var notifDropdown = document.querySelector('.notification-dropdown');

        if (!bell || !notifDropdown) return;

        bell.addEventListener('click', function (e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
        });

        document.addEventListener('click', function () {
            notifDropdown.classList.remove('show');
        });

        notifDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    /* ----------------------------------------
       6. AUTO-DISMISS FLASH MESSAGES
       ---------------------------------------- */
    function initFlashMessages() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function (alert) {
            var closeBtn = alert.querySelector('.alert-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    alert.classList.add('alert-dismiss');
                    setTimeout(function () { alert.remove(); }, 300);
                });
            }

            setTimeout(function () {
                if (alert.parentNode) {
                    alert.classList.add('alert-dismiss');
                    setTimeout(function () { alert.remove(); }, 300);
                }
            }, 6000);
        });
    }

    /* ----------------------------------------
       7. FORM VALIDATION HELPERS
       ---------------------------------------- */
    window.sfmsValidate = {
        required: function (value) {
            return value !== null && value !== undefined && String(value).trim() !== '';
        },

        email: function (value) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(String(value).toLowerCase());
        },

        minLength: function (value, min) {
            return String(value).length >= min;
        },

        maxLength: function (value, max) {
            return String(value).length <= max;
        },

        numeric: function (value) {
            return !isNaN(parseFloat(value)) && isFinite(value);
        },

        numericPositive: function (value) {
            return this.numeric(value) && parseFloat(value) > 0;
        },

        showFieldError: function (field, message) {
            var group = field.closest('.form-group');
            if (!group) return;

            field.classList.add('is-invalid');
            var existing = group.querySelector('.error-message');
            if (existing) existing.remove();

            var errorSpan = document.createElement('span');
            errorSpan.className = 'error-message';
            errorSpan.textContent = message;
            group.appendChild(errorSpan);
        },

        clearFieldError: function (field) {
            var group = field.closest('.form-group');
            if (!group) return;

            field.classList.remove('is-invalid');
            var error = group.querySelector('.error-message');
            if (error) error.remove();
        },

        clearAllErrors: function (form) {
            var invalidFields = form.querySelectorAll('.is-invalid');
            invalidFields.forEach(function (f) {
                f.classList.remove('is-invalid');
            });
            var errors = form.querySelectorAll('.error-message');
            errors.forEach(function (e) { e.remove(); });
        }
    };

    /* ----------------------------------------
       8. SEARCH / FILTER FUNCTIONALITY
       ---------------------------------------- */
    function initSearchFilters() {
        var searchInputs = document.querySelectorAll('[data-search-table]');
        searchInputs.forEach(function (input) {
            var targetSelector = input.getAttribute('data-search-table');
            input.addEventListener('input', function () {
                var query = this.value.toLowerCase();
                var rows = document.querySelectorAll(targetSelector + ' tbody tr');
                rows.forEach(function (row) {
                    var text = row.textContent.toLowerCase();
                    row.style.display = text.indexOf(query) !== -1 ? '' : 'none';
                });
            });
        });
    }

    /* ----------------------------------------
       9. DYNAMIC FORM FIELDS
       ---------------------------------------- */
    window.sfmsAddField = function (containerId, fieldHtml) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var wrapper = document.createElement('div');
        wrapper.className = 'form-group dynamic-field';
        wrapper.innerHTML = fieldHtml;
        container.appendChild(wrapper);
    };

    window.sfmsRemoveField = function (btn) {
        var field = btn.closest('.dynamic-field') || btn.closest('.form-group');
        if (field) {
            field.style.opacity = '0';
            field.style.transform = 'translateX(20px)';
            field.style.transition = 'all 0.2s ease';
            setTimeout(function () { field.remove(); }, 200);
        }
    };

    /* ----------------------------------------
       10. IMAGE PREVIEW FOR FILE UPLOADS
       ---------------------------------------- */
    function initImagePreview() {
        var fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(function (input) {
            input.addEventListener('change', function (e) {
                var file = e.target.files[0];
                var previewArea = input.closest('.form-group').querySelector('.image-preview-area') ||
                                  document.getElementById('imagePreviewArea');
                var previewDiv = document.getElementById('imagePreview');

                if (!previewArea || !previewDiv) return;

                if (file) {
                    previewArea.style.display = 'block';
                    previewDiv.innerHTML = '';

                    if (file.type.startsWith('image/')) {
                        var reader = new FileReader();
                        reader.onload = function (event) {
                            previewDiv.innerHTML = '<img src="' + event.target.result + '" alt="Preview" class="preview-image">';
                        };
                        reader.readAsDataURL(file);
                    } else if (file.type === 'application/pdf') {
                        previewDiv.innerHTML = '<p class="pdf-indicator">PDF file selected: ' + file.name + '</p>';
                    } else {
                        previewDiv.innerHTML = '<p class="pdf-indicator">File selected: ' + file.name + '</p>';
                    }
                } else {
                    previewArea.style.display = 'none';
                    previewDiv.innerHTML = '';
                }
            });
        });
    }

    /* ----------------------------------------
       11. PRINT FUNCTIONALITY
       ---------------------------------------- */
    window.sfmsPrint = function () {
        window.print();
    };

    window.sfmsPrintElement = function (elementId) {
        var element = document.getElementById(elementId);
        if (!element) return;

        var printWindow = window.open('', '_blank');
        printWindow.document.write(
            '<html><head><title>Print</title>' +
            '<style>' +
            'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; font-size: 12px; }' +
            'table { width: 100%; border-collapse: collapse; margin-top: 10px; }' +
            'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }' +
            'th { background-color: #f5f5f5; font-weight: 600; }' +
            'h1, h2, h3 { margin-bottom: 10px; }' +
            '.badge { padding: 2px 8px; border-radius: 12px; font-size: 10px; }' +
            '</style></head><body>' +
            element.innerHTML +
            '</body></html>'
        );
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function () {
            printWindow.print();
            printWindow.close();
        }, 500);
    };

    /* ----------------------------------------
       12. TABLE SORTING
       ---------------------------------------- */
    function initTableSorting() {
        var sortableHeaders = document.querySelectorAll('th.sortable');
        sortableHeaders.forEach(function (header, index) {
            header.addEventListener('click', function () {
                var table = header.closest('table');
                var tbody = table.querySelector('tbody');
                var rows = Array.from(tbody.querySelectorAll('tr'));
                var isAsc = header.classList.contains('sort-asc');

                table.querySelectorAll('th.sortable').forEach(function (th) {
                    th.classList.remove('sort-asc', 'sort-desc');
                });

                header.classList.add(isAsc ? 'sort-desc' : 'sort-asc');

                rows.sort(function (a, b) {
                    var aText = a.cells[index] ? a.cells[index].textContent.trim() : '';
                    var bText = b.cells[index] ? b.cells[index].textContent.trim() : '';

                    var aNum = parseFloat(aText.replace(/[₱,]/g, ''));
                    var bNum = parseFloat(bText.replace(/[₱,]/g, ''));

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAsc ? bNum - aNum : aNum - bNum;
                    }

                    var comparison = aText.localeCompare(bText);
                    return isAsc ? -comparison : comparison;
                });

                rows.forEach(function (row) {
                    tbody.appendChild(row);
                });
            });
        });
    }

    /* ----------------------------------------
       13. BUDGET PLANNER FORM (AJAX)
       ---------------------------------------- */
    function initBudgetForm() {
        var budgetForm = document.getElementById('budgetForm');
        if (!budgetForm) return;

        budgetForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var form = this;
            var url = form.action;
            var formData = new FormData(form);

            var submitBtn = form.querySelector('button[type="submit"]');
            var originalText = submitBtn ? submitBtn.textContent : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner spinner-sm" style="display:inline-block;vertical-align:middle;margin-right:8px;border-top-color:#fff;border-color:rgba(255,255,255,0.3);border-top-color:#fff;"></span> Calculating...';
            }

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                if (data.success) {
                    var results = document.getElementById('budgetResults');
                    if (results) results.style.display = 'block';
                } else {
                    alert('Error calculating budget plan. Please try again.');
                }
            })
            .catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                form.submit();
            });
        });
    }

    /* ----------------------------------------
       14. DELETE FORMS WITH CONFIRMATION
       ---------------------------------------- */
    function initDeleteForms() {
        var deleteForms = document.querySelectorAll('form[data-confirm]');
        deleteForms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var message = form.getAttribute('data-confirm') || 'Are you sure you want to delete this?';
                sfmsConfirm(message, function (confirmed) {
                    if (confirmed) form.submit();
                }, { confirmText: 'Delete', confirmClass: 'btn-danger' });
            });
        });
    }

    /* ----------------------------------------
       15. DROPDOWN MENUS
       ---------------------------------------- */
    function initDropdowns() {
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-dropdown]');
            if (toggle) {
                e.stopPropagation();
                var targetId = toggle.getAttribute('data-dropdown');
                var menu = document.getElementById(targetId);
                if (menu) {
                    document.querySelectorAll('.dropdown-menu.show').forEach(function (m) {
                        if (m !== menu) m.classList.remove('show');
                    });
                    menu.classList.toggle('show');
                }
            }
        });

        document.addEventListener('click', function () {
            document.querySelectorAll('.dropdown-menu.show').forEach(function (m) {
                m.classList.remove('show');
            });
        });
    }

    /* ----------------------------------------
       16. TOGGLE VISIBILITY
       ---------------------------------------- */
    window.sfmsToggle = function (elementId) {
        var el = document.getElementById(elementId);
        if (el) {
            el.style.display = el.style.display === 'none' || el.style.display === '' ? 'block' : 'none';
        }
    };

    /* ----------------------------------------
       17. FORMAT CURRENCY
       ---------------------------------------- */
    window.sfmsFormatCurrency = function (amount) {
        return '\u20B1' + parseFloat(amount).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    /* ----------------------------------------
       18. CSRF TOKEN HELPER
       ---------------------------------------- */
    window.sfmsGetCsrf = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    /* ----------------------------------------
       19. AJAX POST HELPER
       ---------------------------------------- */
    window.sfmsPost = function (url, data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', sfmsGetCsrf());
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                var response;
                try { response = JSON.parse(xhr.responseText); } catch (e) { response = null; }
                if (typeof callback === 'function') {
                    callback(xhr.status, response);
                }
            }
        };
        xhr.send(JSON.stringify(data));
    };

    /* ----------------------------------------
       20. INITIALIZE EVERYTHING
       ---------------------------------------- */
    function init() {
        initSidebar();
        initModals();
        initUserDropdown();
        initNotificationDropdown();
        initFlashMessages();
        initSearchFilters();
        initImagePreview();
        initTableSorting();
        initBudgetForm();
        initDeleteForms();
        initDropdowns();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
