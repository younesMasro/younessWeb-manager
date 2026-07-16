/* Vendbase Manager — Main JS */
(function($){
    'use strict';

    /* ── Status quick-change ── */
    $(document).on('change', '.vb-status-select', function() {
        var id     = $(this).data('id');
        var status = $(this).val();
        $.post(VB.ajax, {action:'vb_update_status', nonce:VB.nonce, id:id, status:status}, function(r) {
            if (r.success) vbToast('Statut mis à jour ✓', 'success');
        });
    });

    /* ── Delete project ── */
    $(document).on('click', '.vb-btn-delete', function() {
        if (!confirm('Supprimer ce projet définitivement?')) return;
        var id  = $(this).data('id');
        var row = $(this).closest('tr');
        $.post(VB.ajax, {action:'vb_delete_project', nonce:VB.nonce, id:id}, function(r) {
            if (r.success) {
                row.fadeOut(300, function() { $(this).remove(); });
                vbToast('Projet supprimé', 'success');
            }
        });
    });

    /* ── Quick pay modal ── */
    var $payModal = $('#vb-pay-modal');
    var payCurrentId = 0;

    $(document).on('click', '.vb-pay-quick', function() {
        payCurrentId = $(this).data('id');
        var prix   = parseFloat($(this).data('prix')) || 0;
        var avance = parseFloat($(this).data('avance')) || 0;
        $('#vb-pay-prix').text(prix.toLocaleString('fr-MA'));
        $('#vb-pay-avance').val(avance);
        updatePayReste(prix, avance);
        $payModal.show();
    });

    $('#vb-pay-avance').on('input', function() {
        var prix   = parseFloat($('#vb-pay-prix').text().replace(/\s/g,'').replace(',','.')) || 0;
        var avance = parseFloat($(this).val()) || 0;
        updatePayReste(prix, avance);
    });

    function updatePayReste(prix, avance) {
        var reste = prix - avance;
        $('#vb-pay-reste-preview').html(
            'Reste après paiement: <strong style="color:' + (reste>0?'#f59e0b':'#10b981') + '">' +
            reste.toLocaleString('fr-MA') + ' MAD</strong>'
        );
    }

    $('#vb-pay-save').on('click', function() {
        var avance = parseFloat($('#vb-pay-avance').val()) || 0;
        $.post(VB.ajax, {action:'vb_update_avance', nonce:VB.nonce, id:payCurrentId, avance:avance}, function(r) {
            if (r.success) {
                $payModal.hide();
                vbToast('Avance mise à jour ✓', 'success');
                setTimeout(function() { location.reload(); }, 800);
            }
        });
    });

    $(document).on('click', '.vb-modal-close, .vb-modal-backdrop', function() {
        $payModal.hide();
    });

    /* ── Toast ── */
    window.vbToast = function(msg, type) {
        var $t = $('<div class="vb-toast vb-toast-'+(type||'success')+'">'+msg+'</div>');
        $('body').append($t);
        setTimeout(function() { $t.fadeOut(300, function() { $t.remove(); }); }, 3000);
    };

})(jQuery);
