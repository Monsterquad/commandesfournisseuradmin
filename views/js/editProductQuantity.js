var config = null
$(document).ready(function(){
    config = window.moduleConfig
    const $rows = $(document).find('.table.product > tbody > tr')
    $rows.each(function(){
        const $row = $(this)
        const product_id = $row.find(".form-check-label").text().trim()
        const $cell = $row.find(".product-sav-quantity.text-center")
        const product_stock = $cell.find("a").text().trim()
        $cell.html(`
            <div class="quantity-edit-container">
                <input type="number" class="quantity-input form-control" 
                       value="${product_stock}" 
                       data-product-id="${product_id}" 
                       data-original-value="${product_stock}"
                       style="width: 70px; text-align: center; border: 1px solid #ddd;">
            </div>
        `);
    })
    bindQuantityEditEvents()
})


function bindQuantityEditEvents() {

    $(document).off('blur.quantityEdit keypress.quantityEdit keyup.quantityEdit keydown.quantityEdit focus.quantityEdit');

    let typingTimer;
    const doneTypingInterval = 2000;

    // Événement blur
    $(document).on('blur.quantityEdit', '.quantity-input', function() {
        const $input = $(this);
        const newQuantity = parseInt($input.val());
        const originalQuantity = parseInt($input.data('original-value'));
        const productId = $input.data('product-id');

        console.log('Blur event:', {productId, newQuantity, originalQuantity});

        if (newQuantity !== originalQuantity && !isNaN(newQuantity) && productId) {
            saveQuantity($input, productId);
        }
    });

    // Validation avec Entrée et Échap
    $(document).on('keypress.quantityEdit', '.quantity-input', function(e) {
        if (e.which === 13) { // Entrée
            $(this).blur();
        }
        if (e.which === 27) { // Échap
            cancelEdit($(this));
        }
    });

    // Auto-sauvegarde après 2 secondes d'inactivité
    $(document).on('keyup.quantityEdit', '.quantity-input', function() {
        const $input = $(this);
        clearTimeout(typingTimer);

        typingTimer = setTimeout(function() {
            const newQuantity = parseInt($input.val());
            const originalQuantity = parseInt($input.data('original-value'));
            const productId = $input.data('product-id');

            if (newQuantity !== originalQuantity && !isNaN(newQuantity) && productId) {
                saveQuantity($input, productId);
            }
        }, doneTypingInterval);
    });

    $(document).on('keydown.quantityEdit', '.quantity-input', function() {
        clearTimeout(typingTimer);
    });

    // Styles visuels
    $(document).on('focus.quantityEdit', '.quantity-input', function() {
        $(this).css('border-color', '#007bff');
    });

    $(document).on('blur.quantityEdit', '.quantity-input', function() {
        if (!$(this).hasClass('saving')) {
            $(this).css('border-color', '#ddd');
        }
    });
}


function saveQuantity($input, productId) {
    const newQuantity = parseInt($input.val());
    const originalQuantity = parseInt($input.data('original-value'));

    /*.log('=== SAVE QUANTITY ===');
    console.log('Product ID:', productId);
    console.log('New quantity:', newQuantity);
    console.log('Original quantity:', originalQuantity);*/

    if (!productId) {
        console.error('❌ ID produit manquant');
        showNotification('Erreur: ID produit manquant', 'error');
        return;
    }

    if (isNaN(newQuantity) || newQuantity < 0) {
        alert('La quantité doit être un nombre positif');
        $input.val(originalQuantity).focus();
        return;
    }

    if (newQuantity === originalQuantity) {
        console.log('Pas de changement, annulation');
        return;
    }

    $input.addClass('saving')
          .css('border-color', '#ffc107')
          .prop('disabled', true);

    const ajaxUrl = buildAjaxUrl();
    // const ajaxUrl = baseAdminDir + 'index.php?controller=AdminModules&configure=' + config.moduleName + '&token=' + config.moduleToken + '&ajax=1&action=updateProductQuantity';

    //console.log('URL AJAX:', ajaxUrl);

    $.ajax({
        url: ajaxUrl,
        method: 'POST',
        data: {
            ajax: true,
            action: 'updateProductQuantity',
            id_product: productId,
            quantity: newQuantity,
            token: getToken()
        },
        success: function(response) {
            // console.log('✅ Réponse serveur:', response);

            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                if (data.success) {
                    // Succès
                    $input.data('original-value', newQuantity)
                          .removeClass('saving')
                          .css('border-color', '#28a745')
                          .prop('disabled', false);

                    setTimeout(() => {
                        $input.css('border-color', '#ddd');
                    }, 1000);

                    showNotification(`Quantité mise à jour: ${newQuantity}`, 'success');
                } else {
                    throw new Error(data.message || 'Erreur inconnue');
                }
            } catch (e) {
                console.error('❌ Erreur parsing response:', e);
                showNotification('Erreur lors de la mise à jour', 'error');
                cancelEdit($input);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Erreur AJAX:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                error: error
            });

            showNotification('Erreur de connexion: ' + xhr.status, 'error');
            cancelEdit($input);
        }
    });
}

function buildAjaxUrl() {
    return '/admin704njnfsy/index.php?controller=AdminModules&configure=mqcommandefournisseuradmin&ajax=1&action=updateProductQuantity';
}

function getToken() {

    if (window.moduleConfig && window.moduleConfig.moduleToken) {
       // console.log('Token depuis moduleConfig');
        return window.moduleConfig.moduleToken;
    }

    if (window.config && window.config.moduleToken) {
        //console.log('Token depuis config');
        return window.config.moduleToken;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const tokenFromUrl = urlParams.get('token') || urlParams.get('_token');
    if (tokenFromUrl) {
        //console.log('Token depuis URL');
        return tokenFromUrl;
    }

    const tokenElement = document.querySelector('input[name="token"], input[name="_token"]');
    if (tokenElement) {
        //console.log('Token depuis input');
        return tokenElement.value;
    }

    console.warn('⚠️ Aucun token trouvé');
    return '';
}

function cancelEdit($input) {
    const originalQuantity = $input.data('original-value');

    $input.val(originalQuantity)
          .removeClass('saving')
          .css('border-color', '#dc3545')
          .prop('disabled', false);

    setTimeout(() => {
        $input.css('border-color', '#ddd');
    }, 1000);
}

function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? '✅' : '❌';

    const $notification = $(`
        <div class="alert ${alertClass} alert-dismissible fade show quantity-notification" 
             style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; min-width: 250px; font-size: 12px;">
            ${icon} ${message}
            <button type="button" class="close" data-dismiss="alert" style="font-size: 16px;">
                <span>&times;</span>
            </button>
        </div>
    `);

    $('.quantity-notification').remove();
    $('body').append($notification);

    setTimeout(() => {
        $notification.fadeOut(300, function() {
            $(this).remove();
        });
    }, 2000);
}