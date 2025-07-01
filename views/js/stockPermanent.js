
$(document).ready(function() {
    console.log(config)
    setTimeout(2000)
    addHeaderTabFieldSp()
})

function getSp(reference){
    const obj = config.orderProducts.filter(item => item.reference === reference);
    if (obj.length > 0 && obj[0].stock_permanent > 0){
        return `
            <span class="sp-badge" style="
                display: inline-block;
                background-color: #28a745;
                color: white;
                border: 2px solid #28a745;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                text-align: center;
                line-height: 20px;
                font-size: 10px;
                font-weight: bold;
                margin: 2px;
            " title="Stock Permanent">
                SP
            </span>
        `;
    }
    return `<span style="width: 24px; height: 24px; display: inline-block;"></span>`;
}

function addHeaderTabFieldSp() {
    $('.cellProduct').each(function() {
        const $row = $(this);
        const $productNameCell = $row.find('.cellProductAvailableQuantity.text-center');
        const $refElement = $row.find('p.mb-0.productReference');
        const referenceText = $refElement.text()
            .replace('Référence :', '')
            .replace(/\s+/g, ' ')
            .trim();
        $productNameCell.append(getSp(referenceText));
    });
}