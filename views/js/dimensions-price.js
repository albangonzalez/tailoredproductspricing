(function () {
    'use strict';

    var DEBOUNCE_MS = 300;
    var CONTAINER_ID = 'tpp-dimensions-form';
    var PRICE_SCOPE_SELECTOR = '.js-product-container';
    var PRICE_SELECTOR = '.js-product-prices .product__price';
    var BASE_HTML_KEY = 'tppBasePrice'; // data-tpp-base-price

    var debounceTimer = null;

    function getContainer() {
        return document.getElementById(CONTAINER_ID);
    }

    function getWidthInput() {
        return document.getElementById('tpp_width');
    }

    function getHeightInput() {
        return document.getElementById('tpp_height');
    }

    function getCassetteValue() {
        var checked = document.querySelector('input[name="tpp_cassette"]:checked');

        return checked ? checked.value : '';
    }

    function normalizeDecimal(raw) {
        if (raw === null || raw === undefined) {
            return null;
        }
        var value = String(raw).trim();
        if (value === '') {
            return null;
        }
        var normalized = value.replace(',', '.');
        var parsed = parseFloat(normalized);

        return isNaN(parsed) ? null : parsed;
    }

    function safeParseJson(raw) {
        if (!raw) {
            return {};
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function getLiveProductData() {
        var el = document.querySelector('.js-product-details[data-product]');
        if (!el) {
            return null;
        }
        try {
            return JSON.parse(el.getAttribute('data-product'));
        } catch (e) {
            return null;
        }
    }

    function readConfig(container) {
        var dataset = container.dataset;
        var live = getLiveProductData() || {};

        return {
            idProduct: parseInt(live.id_product, 10) || 0,
            idProductAttribute: parseInt(live.id_product_attribute, 10) || 0,
            ajaxUrl: dataset.ajaxUrl || '',
            messages: safeParseJson(dataset.messages),
        };
    }

    function getThemePriceElement(container) {
        var scope = container.closest(PRICE_SCOPE_SELECTOR) || document;

        return scope.querySelector(PRICE_SELECTOR);
    }

    function writeThemePrice(container, priceHtmlText) {
        var el = getThemePriceElement(container);
        if (!el) {
            return;
        }
        if (el.dataset[BASE_HTML_KEY] === undefined) {
            // Cached on the node itself, not in a JS variable: core replaces this
            // whole subtree on combination change, so the cache must die with it.
            el.dataset[BASE_HTML_KEY] = el.innerHTML;
        }

        var label = el.querySelector('.visually-hidden');
        el.innerHTML = '';
        if (label) {
            el.appendChild(label);
        }
        var valueNode = document.createElement('span');
        valueNode.className = 'tpp-price-value';
        valueNode.textContent = priceHtmlText || '';
        el.appendChild(valueNode);
    }

    function restoreThemePrice(container) {
        var el = getThemePriceElement(container);
        if (!el || el.dataset[BASE_HTML_KEY] === undefined) {
            return;
        }
        el.innerHTML = el.dataset[BASE_HTML_KEY];
        delete el.dataset[BASE_HTML_KEY];
    }

    function updatePriceDisplay(container, state, priceText) {
        if (state === 'ok') {
            writeThemePrice(container, priceText);
        } else {
            restoreThemePrice(container);
        }
    }

    function getFieldNode(container, field) {
        return container.querySelector('.tpp-dimension-field[data-field="' + field + '"]');
    }

    function clearFieldError(container, field) {
        var fieldNode = getFieldNode(container, field);
        if (!fieldNode) {
            return;
        }
        fieldNode.classList.remove('tpp-dimension-field--error');
        var errorNode = fieldNode.querySelector('[data-role="error"]');
        if (errorNode) {
            errorNode.textContent = '';
        }
    }

    function showFieldError(container, field, message) {
        var fieldNode = getFieldNode(container, field);
        if (!fieldNode) {
            return;
        }
        fieldNode.classList.add('tpp-dimension-field--error');
        var errorNode = fieldNode.querySelector('[data-role="error"]');
        if (errorNode) {
            errorNode.textContent = message || '';
        }
    }

    function validateDimension(container, input, field, messages) {
        clearFieldError(container, field);

        var value = normalizeDecimal(input.value);
        if (value === null) {
            return { value: null, valid: false };
        }

        var min = input.min !== '' ? parseFloat(input.min) : null;
        if (min !== null && value < min) {
            showFieldError(container, field, messages[field + '.min'] || '');

            return { value: value, valid: false };
        }

        var max = input.max !== '' ? parseFloat(input.max) : null;
        if (max !== null && value > max) {
            showFieldError(container, field, messages[field + '.max'] || '');

            return { value: value, valid: false };
        }

        return { value: value, valid: true };
    }

    function buildSignature(idProductAttribute, width, height, cassette) {
        return idProductAttribute + '|' + width + '|' + height + '|' + cassette;
    }

    function postAjax(ajaxUrl, action, params) {
        var separator = ajaxUrl.indexOf('?') === -1 ? '?' : '&';
        var url = ajaxUrl + separator + 'action=' + encodeURIComponent(action) + '&ajax=1';

        var body = new URLSearchParams();
        Object.keys(params).forEach(function (key) {
            body.append(key, params[key]);
        });

        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: body,
        }).then(function (response) {
            return response.json().then(function (json) {
                return { httpStatus: response.status, body: json };
            });
        });
    }

    function scheduleQuote(container, config, width, height) {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(function () {
            requestQuote(container, config, width, height);
        }, DEBOUNCE_MS);
    }

    function requestQuote(container, config, width, height) {
        if (!config.ajaxUrl || !config.idProductAttribute) {
            return;
        }
        var cassette = getCassetteValue();
        var signature = buildSignature(config.idProductAttribute, width, height, cassette);

        postAjax(config.ajaxUrl, 'quote', {
            id_product: config.idProduct,
            id_product_attribute: config.idProductAttribute,
            width: width,
            height: height,
            cassette: cassette,
        }).then(function (result) {
            // Ignore stale responses — only trust this result if the
            // inputs still match what produced the request.
            if (!isCurrentSignature(config, signature)) {
                return;
            }

            if (result.httpStatus !== 200 || !result.body || result.body.success === false) {
                updatePriceDisplay(container, 'error', '');

                return;
            }

            updatePriceDisplay(container, 'ok', result.body.priceFormatted);
        }).catch(function () {
            if (!isCurrentSignature(config, signature)) {
                return;
            }
            updatePriceDisplay(container, 'error', '');
        });
    }

    function isCurrentSignature(config, signature) {
        var widthInput = getWidthInput();
        var heightInput = getHeightInput();
        if (!widthInput || !heightInput) {
            return false;
        }
        var width = normalizeDecimal(widthInput.value);
        var height = normalizeDecimal(heightInput.value);
        var cassette = getCassetteValue();

        return buildSignature(config.idProductAttribute, width, height, cassette) === signature;
    }

    function onDimensionInput(container) {
        var widthInput = getWidthInput();
        var heightInput = getHeightInput();
        if (!widthInput || !heightInput) {
            return;
        }

        // Read fresh on every keystroke rather than closing over a config
        // snapshot: the container/inputs are never actually recreated on
        // combination change (see getLiveProductData above), so a stale
        // closure would keep sending the combination that was selected
        // when the listener was first bound.
        var config = readConfig(container);

        var width = validateDimension(container, widthInput, 'width', config.messages);
        var height = validateDimension(container, heightInput, 'height', config.messages);

        if (!width.valid || !height.valid) {
            updatePriceDisplay(container, 'idle', '');

            return;
        }

        scheduleQuote(container, config, width.value, height.value);
    }

    function bindDimensionInputs(container) {
        if (container.dataset.tppInputsBound === '1') {
            return;
        }

        var widthInput = getWidthInput();
        var heightInput = getHeightInput();
        var handler = function () {
            onDimensionInput(container);
        };

        if (widthInput) {
            widthInput.addEventListener('input', handler);
            widthInput.addEventListener('change', handler);
        }
        if (heightInput) {
            heightInput.addEventListener('input', handler);
            heightInput.addEventListener('change', handler);
        }

        container.dataset.tppInputsBound = '1';
    }

    var cassetteBound = false;

    function bindCassetteInput() {
        if (cassetteBound) {
            return;
        }
        document.addEventListener('change', function (event) {
            if (!event.target || event.target.name !== 'tpp_cassette') {
                return;
            }
            var container = getContainer();
            if (container) {
                onDimensionInput(container);
            }
        });
        cassetteBound = true;
    }

    function init(preservedValues) {
        var container = getContainer();
        if (!container) {
            return;
        }

        var config = readConfig(container);
        var widthInput = getWidthInput();
        var heightInput = getHeightInput();

        if (preservedValues && widthInput && heightInput) {
            widthInput.value = preservedValues.width;
            heightInput.value = preservedValues.height;
        }

        bindDimensionInputs(container);
        bindCassetteInput();

        updatePriceDisplay(container, 'idle', '');

        if (widthInput && heightInput) {
            var width = validateDimension(container, widthInput, 'width', config.messages);
            var height = validateDimension(container, heightInput, 'height', config.messages);

            if (width.valid && height.valid) {
                scheduleQuote(container, config, width.value, height.value);
            }
        }
    }

    function currentValues() {
        var widthInput = getWidthInput();
        var heightInput = getHeightInput();

        return {
            width: widthInput ? widthInput.value : '',
            height: heightInput ? heightInput.value : '',
        };
    }

    function boot() {
        init(null);

        if (typeof window.prestashop !== 'undefined' && window.prestashop.on) {
            var pendingValues = null;

            // `updateProduct` fires BEFORE the combination-refresh AJAX call
            // (the old container/inputs are still in the DOM here) — this is
            // the only point the customer's entered values can still be
            // read before the container is replaced.
            window.prestashop.on('updateProduct', function () {
                pendingValues = currentValues();
            });

            // `updatedProduct` fires AFTER the AJAX completes and the DOM
            // (including #tpp-dimensions-form) has already been replaced —
            // re-bind against the fresh markup and restore what was cached
            // above.
            window.prestashop.on('updatedProduct', function () {
                init(pendingValues);
                pendingValues = null;
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
