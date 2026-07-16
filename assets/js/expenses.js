/* YounessWeb Manager v2 - Expenses JS */
(function($) {
    'use strict';

    /* Only run on expenses page */
    if (!document.getElementById('vb-exp-modal')) return;

    /* Use the expenses-localized values first, with the global admin object as fallback. */
    var ajaxUrl = window.VB_EXP && VB_EXP.ajaxUrl ? VB_EXP.ajaxUrl : (window.VB && VB.ajax ? VB.ajax : ajaxurl);
    var nonce   = window.VB_EXP && VB_EXP.nonce ? VB_EXP.nonce : (window.VB && VB.nonce ? VB.nonce : '');

    var today   = window.VB_EXP ? VB_EXP.today : new Date().toISOString().slice(0,10);
    var curMonth= window.VB_EXP ? VB_EXP.month : ('0'+(new Date().getMonth()+1)).slice(-2);
    var curYear = window.VB_EXP ? VB_EXP.year  : new Date().getFullYear().toString();

    var $modal = $('#vb-exp-modal');

    /* ── Open ── */
    function openModal(data) {
        data = data || {};
        $('#vb-exp-id').val(       data.id           || '');
        $('#vb-exp-category').val( data.category     || 'ads_facebook');
        $('#vb-exp-amount').val(   data.amount       || '');
        $('#vb-exp-label').val(    data.label        || '');
        $('#vb-exp-date').val(     data.expense_date || today);
        $('#vb-exp-month').val(    data.month ? String(data.month).padStart(2,'0') : curMonth);
        $('#vb-exp-year').val(     data.year         || curYear);
        $('#vb-exp-project').val(  data.project_id   || '');
        $('#vb-exp-note').val(     data.note         || '');
        $('#vb-exp-modal-title').text(data.id ? 'Modifier la dépense' : 'Ajouter une dépense');
        $modal.css('display', 'flex');
        setTimeout(function(){ $('#vb-exp-amount').trigger('focus'); }, 100);
    }

    function closeModal() { $modal.hide(); }

    /* ── Events ── */
    $(document).on('click', '#vb-open-modal-btn, #vb-open-modal-btn2', function(e) {
        e.preventDefault();
        openModal();
    });

    $(document).on('click', '#vb-exp-close-btn, #vb-exp-cancel-btn, #vb-exp-backdrop', closeModal);

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $modal.is(':visible')) closeModal();
    });

    /* ── Edit ── */
    $(document).on('click', '.vb-edit-exp-btn', function() {
        var id = $(this).data('id');
        $.get(ajaxUrl, { action: 'vb_get_expense', id: id, nonce: nonce })
            .done(function(res) {
                if (res && res.success) openModal(res.data);
                else alert('Impossible de charger la dépense.');
            });
    });

    /* ── Delete ── */
    $(document).on('click', '.vb-del-exp-btn', function() {
        if (!confirm('Supprimer cette dépense ?')) return;
        var id = $(this).data('id');
        $.post(ajaxUrl, { action: 'vb_delete_expense', nonce: nonce, id: id })
            .done(function(res) {
                if (res && res.success) location.reload();
                else alert('Erreur suppression.');
            });
    });

    /* ── Save ── */
    $(document).on('click', '#vb-exp-save-btn', function() {
        var amount = parseFloat($('#vb-exp-amount').val());
        if (!amount || amount <= 0) {
            $('#vb-exp-amount').css('border-color','#ef4444').trigger('focus');
            setTimeout(function(){ $('#vb-exp-amount').css('border-color',''); }, 2000);
            alert('Montant obligatoire.');
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).text('Enregistrement...');

        $.post(ajaxUrl, {
            action:       'vb_save_expense',
            nonce:        nonce,
            id:           $('#vb-exp-id').val(),
            category:     $('#vb-exp-category').val(),
            amount:       amount,
            label:        $('#vb-exp-label').val(),
            expense_date: $('#vb-exp-date').val(),
            month:        $('#vb-exp-month').val(),
            year:         $('#vb-exp-year').val(),
            project_id:   $('#vb-exp-project').val(),
            note:         $('#vb-exp-note').val()
        })
        .done(function(res) {
            if (res && res.success) {
                location.reload();
            } else {
                alert('Erreur enregistrement: ' + (res && res.data ? res.data : 'inconnue'));
                $btn.prop('disabled', false).text('Enregistrer');
            }
        })
        .fail(function(xhr) {
            alert('Erreur réseau: ' + xhr.status);
            $btn.prop('disabled', false).text('Enregistrer');
        });
    });

    /* ── Charts ── */
    $(window).on('load', function() {
        if (typeof Chart === 'undefined' || !window.VB_EXP_DATA) return;
        var d = window.VB_EXP_DATA;
        var monthly   = d.monthly   || [];
        var catData   = d.catData   || [];
        var catLabels = d.catLabels || [];
        var catColors = d.catColors || [];

        var labels12 = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        var ads = [0,0,0,0,0,0,0,0,0,0,0,0];
        var other=[0,0,0,0,0,0,0,0,0,0,0,0];
        for (var i=0; i<monthly.length; i++) {
            var m = parseInt(monthly[i].month)-1;
            ads[m]   = parseFloat(monthly[i].ads   || 0);
            other[m] = parseFloat(monthly[i].other || 0);
        }
        var barEl = document.getElementById('vb-exp-chart');
        if (barEl) new Chart(barEl, {
            type:'bar',
            data:{labels:labels12, datasets:[
                {label:'Pub (Ads)',      data:ads,   backgroundColor:'rgba(99,102,241,0.85)', borderRadius:5},
                {label:'Autres charges', data:other, backgroundColor:'rgba(239,68,68,0.7)',   borderRadius:5}
            ]},
            options:{responsive:true, maintainAspectRatio:false,
                plugins:{legend:{position:'bottom'}},
                scales:{x:{stacked:true,grid:{display:false}}, y:{stacked:true,beginAtZero:true}}}
        });
        var pieEl = document.getElementById('vb-exp-pie');
        if (pieEl && catData.length>0) new Chart(pieEl, {
            type:'doughnut',
            data:{labels:catLabels, datasets:[{data:catData, backgroundColor:catColors, borderWidth:2, borderColor:'#fff'}]},
            options:{responsive:true, maintainAspectRatio:false,
                plugins:{legend:{position:'right', labels:{font:{size:12}, padding:14}}}}
        });
    });

})(jQuery);
