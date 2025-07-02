var commandeRef = null
var config = null

$(document).ready(function() {
    config = window.moduleConfig
    setTimeout(function() {
        commandeRef = config.orderId
        // console.log(config)
        addHeaderTabField();
        addCheckbox();
        addButtonModal();
    }, 500);
});

const referenceChecked = new Set();
let email = '';

function addCheckbox() {
    $('.cellProduct').each(function(index) {
        const $row = $(this);

        const $editBtn = $row.find('.js-order-product-edit-btn');
        const orderDetailId = $editBtn.data('order-detail-id');

        const $refElement = $row.find('p.mb-0.productReference');
        const referenceText = $refElement.text()
            .replace('Référence :', '')
            .replace(/\s+/g, ' ')
            .trim();

        const [delay, productId] = getProductDelay(referenceText, config.orderProducts)

        const nameElement = $row.find('p.mb-0.productName').text()

        const delayHtml = delay && delay !== 'Non définie'
            ? `<p class="delay-info" style="font-size: 11px; color: #666; margin: 2px 0;">${delay}</p>`
            : delay === 'Non définie'
            ? `<p class="delay-info" style="font-size: 11px; color: #999; margin: 2px 0;">Date non définie</p>`
            : '';

        if (productId && referenceText) {
            const checkboxHtml = `
                <div class="mq-supplier-section">
                    <div class="form-check">
                        <input type="checkbox" 
                               class="form-check-input mq-supplier-checkbox" 
                               id="mq_checkbox_${productId}" 
                               data-product-id="${productId}"
                               data-order-detail-id="${orderDetailId}"
                               data-reference="${referenceText}"
                               data-name="${nameElement}">
                               ${delayHtml}
                    </div>
                </div>
            `;
            const $productNameCell = $row.find('.mq-supplier-cell');
            $productNameCell.append(checkboxHtml);
        }
    });

    $(document).on('change', '.mq-supplier-checkbox', function() {

        const $checkbox = $(this);
        const productId = $checkbox.data('product-id');
        const orderDetailId = $checkbox.data('order-detail-id');
        const reference = $checkbox.data('reference');
        const name = $checkbox.data('name');

        const $delayField = $checkbox.closest('.mq-supplier-section').find('.mq-delay-field');

        if ($checkbox.is(':checked')) {
            const productData = {
                productId: productId,
                productName: name,
                orderDetailId: orderDetailId,
                reference: reference,
                delay: ''
            };
            referenceChecked.add(JSON.stringify(productData));
            $delayField.show();
        } else {
            for (let item of referenceChecked) {
                const data = JSON.parse(item);
                if (data.productId === productId) {
                    referenceChecked.delete(item);
                    break;
                }
            }
            $delayField.hide();
            $delayField.find('input').val('');
        }
        updateHeaderCount();
    });

    $(document).on('input', '.mq-delay-field input', function() {
        const $input = $(this);
        const productId = $input.data('product-id');
        const delayValue = $input.val();

        const updatedSet = new Set();
        for (let item of referenceChecked) {
            const data = JSON.parse(item);
            if (data.productId === productId) {
                data.delay = delayValue;
            }
            updatedSet.add(JSON.stringify(data));
        }
        referenceChecked.clear();
        updatedSet.forEach(item => referenceChecked.add(item));

    });
}

function getProductDelay(reference, productsList) {
    const product = productsList.find(item => item.reference === reference);
    if (product){
        return [product.date_available, product.product_id]
    }
}

function addHeaderTabField() {
    const $table = $('#orderProductsTable');
    const $headerRow = $table.find('thead tr');

    const $productHeader = $headerRow.find('th').eq(0);
    $productHeader.before(`
        <th class="mq-supplier-header" style="background: #e3f2fd;">
            <p class="mb-0">Delai</p>
            <small class="text-muted mq-count">0 sélectionné(s)</small>
        </th>
    `);

    $('.cellProduct').each(function() {
        const $row = $(this);
        const $productNameCell = $row.find('.cellProductImg');
        $productNameCell.before('<td class="mq-supplier-cell" style="background: #f8f9fa; vertical-align: top;"></td>');
    });
}

function updateHeaderCount() {
    const count = referenceChecked.size;
    $('.mq-count').text(`${count} sélectionné(s)`);

    const $header = $('.mq-supplier-header');
    if (count > 0) {
        $header.css('background', '#c8e6c9');
    } else {
        $header.css('background', '#e3f2fd');
    }
}


function addButtonModal(){
    const button = `
        <button type="button" class="btn btn-outline-secondary mr-3" id="demande-delai-btn">Demande de delai</button>
    `;
    $('.col-xl-6.text-xl-right.discount-action').append(button);

    $('#demande-delai-btn').on('click', function() {
        openDemandeDelaiModal();
    });
}

function openDemandeDelaiModal() {
    if ($('#demandeDelaiModal').length === 0) {
        const modalHTML = `
            <div class="modal fade" id="demandeDelaiModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Demande de délai</h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <!-- Select pour le fournisseur -->
                            <div class="form-group">
                                <label for="fournisseur-select">Sélection du fournisseur :</label>
                                <select id="fournisseur-select" class="form-control">
                                    <option value="">-- Choisir un fournisseur --</option>
                                    <option value="Deltamics">Deltamics</option>
                                    <option value="GDFrance">GDFrance</option>
                                </select>
                            </div>
                            
                            <!-- Zone de texte -->
                            <div class="form-group">
                                <label for="motif-textarea">Motif de la demande :</label>
                                <textarea id="motif-textarea" class="form-control" rows="10" placeholder="Veuillez préciser le motif de votre demande de délai..."></textarea>
                            </div>
                            
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                            <button type="button" id="sendEmail" class="btn btn-primary">Confirmer</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('body').append(modalHTML);
        $('#fournisseur-select').on('change', function() {
            email = $(this).val()
            buildMailText()
        })
    }
    $('#demandeDelaiModal').modal('show');
}

function buildMailText() {
    let [text, subject] = genererEmailDelai(referenceChecked, email, "Demande de delai", )
    $('#motif-textarea').val(text)
    $('#sendEmail').on('click', function(){
        sendEmailToBack(text, subject, email)
    })
}

function genererEmailDelai(referenceChecked) {
    if (commandeRef){
        const references = Array.from(referenceChecked).map(item => JSON.parse(item));
        const listeReferences = references.map(ref =>
            `- ${ref.reference} : ${ref.productName || 'Produit'}`
        ).join('\n');
        return [
    `Bonjour,

Nous souhaitons faire une demande de délai pour les articles suivants :

${listeReferences}

Cordialement.`.trim(),
    `Demande de délai ${commandeRef}`
];
    } else {
        return alert("pas d'id de commande")
    }
}

function sendEmailToBack(text, subject, receiver) {

    const ajaxUrl = baseAdminDir + 'index.php?controller=AdminModules&configure=' + config.moduleName + '&token=' + config.moduleToken + '&ajax=1&action=sendEmail';

    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
            text: text,
            subject: subject,
            receiver: receiver,
        },
        success: function(response) {
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                alert('Succès: ' + data.message);
            } catch(e) {
                alert('Réponse reçue mais pas JSON: ' + response);
            }
        },
        error: function(xhr, status, error) {
            alert('Erreur: ' + xhr.status + ' - ' + xhr.responseText);
        }
    });
}

window.getSelectedSupplierRequests = function() {
    const result = Array.from(referenceChecked).map(item => JSON.parse(item));
    return result;
};

window.clearAllSelections = function() {
    $('.mq-supplier-checkbox').prop('checked', false).trigger('change');
};