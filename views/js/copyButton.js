$(document).ready(function() {
    setTimeout(function() {
        addCopyButtons();
    }, 1000);
});

function addCopyButtons() {
    $('.cellProduct').each(function(index) {
        const $row = $(this);

        const $refElement = $row.find('p.mb-0.productReference');

        if ($refElement.length > 0) {
            const referenceText = $refElement.text();

            const cleanRef = referenceText
                .replace('Référence :', '')
                .replace(/\s+/g, ' ')
                .trim();

            const $copyButton = $(`
                <button class="btn btn-sm btn-outline-primary mq-copy-btn" 
                        style="margin-left: 10px; font-size: 11px; padding: 2px 8px;" 
                        title="Copier: ${cleanRef}">
                    📋
                </button>
            `);

            $copyButton.on('click', function(e) {
                e.preventDefault();
                copyToClipboard(cleanRef, $(this));
            });

            $refElement.append($copyButton);

        } else {
            console.log(`Ligne ${index + 1} - Aucune référence trouvée`);
        }
    });
}

function copyToClipboard(text, $button) {
    console.log('Copie de:', `"${text}"`);

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            showCopySuccess($button, text);
        }).catch(function(err) {
            console.log('Erreur clipboard moderne, utilisation fallback:', err);
            fallbackCopy(text, $button);
        });
    } else {
        // Méthode de fallback
        fallbackCopy(text, $button);
    }
}

function fallbackCopy(text, $button) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopySuccess($button, text);
        } else {
            console.log('execCommand failed');
            alert('Référence: ' + text);
        }
    } catch (err) {
        console.error('Erreur de copie:', err);
        alert('Référence: ' + text);
    }

    document.body.removeChild(textArea);
}

function showCopySuccess($button, text) {
    const originalText = $button.html();
    const originalClass = $button.attr('class');

    $button.html('✅');
    $button.removeClass('btn-outline-primary').addClass('btn-success');

    setTimeout(function() {
        $button.html(originalText);
        $button.attr('class', originalClass);
    }, 1500);

    console.log('Référence copiée avec succès:', `"${text}"`);

    showNotification('Copiée: ' + text);
}

function showNotification(message) {
    const $notification = $(`
        <div class="mq-notification" style="
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 10000; 
            background: #28a745; 
            color: white; 
            padding: 8px 12px; 
            border-radius: 4px; 
            font-size: 13px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            max-width: 300px;
            word-break: break-all;
        ">
            ${message}
        </div>
    `);

    $('body').append($notification);

    $notification.hide().fadeIn(200);

    setTimeout(function() {
        $notification.fadeOut(300, function() {
            $(this).remove();
        });
    }, 2000);
}