/* MercadoLibre Sync — admin (ES5 + jQuery). Datos de PHP en window.MLS_ADMIN. */
jQuery(function ($) {
    if (typeof window.MLS_ADMIN === 'undefined') {
        return;
    }
    var A = window.MLS_ADMIN;

    function mls_show($msg, ok, text) {
        $msg.removeClass('is-ok is-error').addClass(ok ? 'is-ok' : 'is-error').text(text);
    }

    function mls_post(data, $msg, onOk) {
        $msg.removeClass('is-ok is-error').text(A.processing);
        $.post(A.ajax, data).done(function (res) {
            var ok = res && res.success;
            var text = ok ? (res.data && res.data.message ? res.data.message : 'Listo.') : (res && res.data ? res.data : A.error);
            if (typeof text !== 'string') {
                text = ok ? 'Listo.' : A.error;
            }
            mls_show($msg, ok, text);
            if (ok && onOk) {
                onOk(res.data || {});
            }
        }).fail(function (xhr) {
            var text = xhr && xhr.responseJSON && typeof xhr.responseJSON.data === 'string' ? xhr.responseJSON.data : A.error;
            mls_show($msg, false, text);
        });
    }

    function mls_reload(delay) {
        window.setTimeout(function () { window.location.reload(); }, delay || 1200);
    }

    // ── Configuración ───────────────────────────────────────────────────
    var $page = $('.mls-page');
    $page.on('click', '.nav-tab', function () {
        $('#mls-tab').val($(this).data('tab'));
    });
    $('#mls-test').on('click', function (e) {
        e.preventDefault();
        mls_post({ action: 'mls_test', nonce: A.nonce_settings }, $('#mls-connection-message'));
    });

    // ── Subpágina Productos ─────────────────────────────────────────────
    $('#mls-check-all').on('change', function () {
        $('.mls-check').prop('checked', $(this).is(':checked'));
    });

    $('.mls-bulk').on('click', function (e) {
        e.preventDefault();
        var action = $(this).data('do');
        var $msg = $('#mls-products-message');
        var filtered = $('#mls-scope-filtered').is(':checked');
        var data = { action: 'mls_bulk', nonce: A.nonce_products, 'do': action, scope: filtered ? 'filtered' : 'selected' };
        var ids = [];
        $('.mls-check:checked').each(function () {
            ids.push($(this).val());
        });
        if (!ids.length && !filtered) {
            mls_show($msg, false, A.select);
            return;
        }
        if (action === 'pause' && !window.confirm(A.confirm_pause)) {
            return;
        }
        if (action === 'unlink' && !window.confirm(A.confirm_unlink)) {
            return;
        }
        if (filtered) {
            // Los filtros actuales de la URL definen el conjunto.
            var query = window.location.search.replace(/^\?/, '').split('&');
            $.each(query, function (i, pair) {
                var parts = pair.split('=');
                var key = decodeURIComponent(parts[0] || '');
                if (key && key !== 'page' && key !== 'paged' && typeof data[key] === 'undefined') {
                    data[key] = decodeURIComponent((parts[1] || '').replace(/\+/g, ' '));
                }
            });
        } else {
            data.ids = ids;
        }
        mls_post(data, $msg, function (res) {
            if (typeof res.pending !== 'undefined') {
                $('.mls-pending').text(res.pending);
            }
            mls_reload(1500);
        });
    });

    // ── Caja de la ficha de producto ────────────────────────────────────
    var $box = $('.mls-metabox');
    if (!$box.length) {
        return;
    }
    var productId = $box.data('id');
    var $boxMsg = $box.find('.mls-message');

    $box.on('click', '.mls-action', function (e) {
        e.preventDefault();
        var action = $(this).data('do');
        if (action === 'unlink' && !window.confirm(A.confirm_unlink)) {
            return;
        }
        var data = { action: 'mls_product', nonce: A.nonce_products, id: productId, 'do': action };
        if (action === 'link') {
            data.item = $('#mls-link').val();
        }
        var $msg = $boxMsg.length ? $boxMsg : $('<p class="mls-message"></p>').appendTo($box);
        mls_post(data, $msg, function () {
            mls_reload(1500);
        });
    });

    $box.on('click', '.mls-predict', function (e) {
        e.preventDefault();
        var $list = $box.find('.mls-predictions');
        var title = $('#title').val() || '';
        $list.removeClass('is-error').text(A.processing);
        $.post(A.ajax, { action: 'mls_predict', nonce: A.nonce_products, id: productId, title: title }).done(function (res) {
            $list.empty();
            if (!res || !res.success) {
                $list.addClass('mls-error').text(res && typeof res.data === 'string' ? res.data : A.error);
                return;
            }
            $.each(res.data.categories, function (i, row) {
                $('<button type="button" class="button-link mls-pick"></button>')
                    .attr('data-id', row.category_id)
                    .text(row.category_id + ' — ' + (row.domain_name || row.category_name))
                    .appendTo($list);
            });
        }).fail(function () {
            $list.addClass('mls-error').text(A.error);
        });
    });

    $box.on('click', '.mls-pick', function (e) {
        e.preventDefault();
        $('#mls-category').val($(this).data('id'));
        $box.find('.mls-predictions').empty();
    });

    $box.on('click', '.mls-required', function (e) {
        e.preventDefault();
        var category = $('#mls-category').val() || $('#mls-category').attr('placeholder') || '';
        var $list = $box.find('.mls-required-list');
        if (!/^[A-Za-z]{3}\d+$/.test(category)) {
            $list.addClass('mls-error').text(A.no_category);
            return;
        }
        $list.removeClass('mls-error').text(A.processing);
        $.post(A.ajax, { action: 'mls_required', nonce: A.nonce_products, category: category }).done(function (res) {
            $list.empty();
            if (!res || !res.success) {
                $list.addClass('mls-error').text(res && typeof res.data === 'string' ? res.data : A.error);
                return;
            }
            if (!res.data.attributes.length) {
                $list.text(A.none_required);
                return;
            }
            $('<p></p>').text(A.required_title).appendTo($list);
            var $ul = $('<ul></ul>').appendTo($list);
            $.each(res.data.attributes, function (i, attr) {
                var text = attr.id + ' (' + attr.name + ')' + (attr.conditional ? ' *' : '');
                if (attr.values.length) {
                    text += ': ' + attr.values.slice(0, 6).join(', ');
                }
                $('<li></li>').text(text).appendTo($ul);
            });
        }).fail(function () {
            $list.addClass('mls-error').text(A.error);
        });
    });
});
