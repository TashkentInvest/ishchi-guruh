// E-IMZO Application JavaScript

var EIMZO_MAJOR = 3;
var EIMZO_MINOR = 37;

var errorCAPIWS = 'E-IMZO bilan ulanishda xatolik. E-IMZO moduli o\'rnatilmagan bo\'lishi mumkin.';
var errorBrowserWS = 'Brauzer WebSocket texnologiyasini qo\'llab-quvvatlamaydi.';
var errorUpdateApp = 'E-IMZO dasturining yangi versiyasini o\'rnating.';
var errorWrongPassword = 'Parol noto\'g\'ri.';

// Global state
var selectedKeyId = null;
var selectedKeyVo = null;
var selectedCardVo = null; // Used in card-based UI

// Detect if page uses card-based key list (login page) vs select dropdown (show page)
function isCardMode() {
    return document.getElementById('eimzo-keys-list') !== null;
}

// Initialize E-IMZO on page load
function AppLoad() {
    EIMZOClient.API_KEYS = [
        'null', 'E0A205EC4E7B78BBB56AFF83A733A1BB9FD39D562E67978CC5E7D73B0951DB1954595A20672A63332535E13CC6EC1E1FC8857BB09E0855D7E76E411B6FA16E9D',
        'localhost', '96D0C1491615C82B9A54D9989779DF825B690748224C2B04F500F370D51827CE2644D8D4A82C18184D73AB8530BB8ED537269603F61DB0D03D2104ABF789970B',
        '127.0.0.1', 'A7BCFA5D490B351BE0754130DF03A068F855DB4333D43921125B9CF2670EF6A40370C646B90401955E1F7BC9CDBF59CE0B2C5467D820BE189C845D0B79CFC96F'
    ];

    uiLoading();
    EIMZOClient.checkVersion(function(major, minor) {
        var newVersion = EIMZO_MAJOR * 100 + EIMZO_MINOR;
        var installedVersion = parseInt(major) * 100 + parseInt(minor);
        if (installedVersion < newVersion) {
            uiUpdateApp();
        } else {
            EIMZOClient.installApiKeys(function() {
                uiAppLoad();
            }, function(e, r) {
                if (r) { uiShowMessage(r); } else { wsError(e); }
            });
        }
    }, function(e, r) {
        if (r) { uiShowMessage(r); } else { uiNotLoaded(e); }
    });
}

// UI Functions
function uiLoading() {
    var el = document.getElementById('eimzo-status');
    if (el) {
        el.innerHTML = '<div class="status-message status-loading">E-IMZO yuklanmoqda...</div>';
    }
}

function uiLoaded() {
    var el = document.getElementById('eimzo-status');
    if (el) {
        el.innerHTML = '<div class="status-message status-success">E-IMZO tayyor</div>';
    }
    var btn = document.getElementById('login-btn');
    if (btn) btn.disabled = false;
}

function uiNotLoaded(e) {
    // Show a friendly message in the keys list area
    var list = document.getElementById('eimzo-keys-list');
    if (list) {
        list.innerHTML =
            '<div style="padding:20px;text-align:center;">' +
            '<div style="font-size:2rem;margin-bottom:10px;">&#128274;</div>' +
            '<div style="font-weight:700;color:#721c24;margin-bottom:8px;font-size:.95rem;">E-IMZO dasturi topilmadi</div>' +
            '<div style="font-size:.82rem;color:#6e788b;margin-bottom:14px;line-height:1.5;">' +
            'E-IMZO ilovasi kompyuteringizda ishlamayapti yoki o\'rnatilmagan.<br>' +
            'Yuklab oling va qayta urinib ko\'ring.' +
            '</div>' +
            '<a href="https://e-imzo.uz/main/downloads/" target="_blank" ' +
            'style="display:inline-block;padding:9px 20px;background:#018c87;color:#fff;border-radius:8px;text-decoration:none;font-size:.85rem;font-weight:600;margin-bottom:8px;">' +
            'E-IMZO yuklab olish ↓</a><br>' +
            '<button onclick="AppLoad()" style="background:none;border:none;color:#018c87;cursor:pointer;font-size:.82rem;text-decoration:underline;">Qayta ulanib ko\'rish</button>' +
            '</div>';
    }
    var el = document.getElementById('eimzo-status');
    if (el) { el.innerHTML = ''; }
}

function uiUpdateApp() {
    var el = document.getElementById('eimzo-status');
    if (el) {
        el.innerHTML = '<div class="status-message status-error">' + errorUpdateApp + ' <a href="https://e-imzo.uz/main/downloads/" target="_blank">Yuklab olish</a></div>';
    }
}

function uiShowMessage(message) {
    var el = document.getElementById('eimzo-message');
    if (el) {
        el.innerHTML = '<div class="status-message status-error">' + message + '</div>';
    } else {
        alert(message);
    }
}

function uiClearMessage() {
    var el = document.getElementById('eimzo-message');
    if (el) { el.innerHTML = ''; }
}

function uiShowProgress(message) {
    var el = document.getElementById('eimzo-progress');
    if (el) {
        el.innerHTML = '<div class="status-message status-info">' + (message || 'Iltimos kuting...') + '</div>';
    }
}

function uiHideProgress() {
    var el = document.getElementById('eimzo-progress');
    if (el) { el.innerHTML = ''; }
}

function uiAppLoad() {
    uiClearCombo();
    EIMZOClient.listAllUserKeys(function(o, i) {
        return "itm-" + o.serialNumber + "-" + i;
    }, function(itemId, v) {
        return uiCreateItem(itemId, v);
    }, function(items, firstId) {
        uiFillCombo(items);
        uiLoaded();
        if (firstId) { uiComboSelect(firstId); }
    }, uiHandleError);
}

function uiClearCombo() {
    if (isCardMode()) {
        var list = document.getElementById('eimzo-keys-list');
        if (list) {
            list.innerHTML =
                '<div class="keys-loader">' +
                '<div class="keys-spinner"></div>' +
                '<span>Kalitlar yuklanmoqda...</span>' +
                '</div>';
        }
        selectedCardVo = null;
    } else {
        var combo = document.getElementById('eimzo-keys');
        if (combo) { combo.innerHTML = '<option value="">-- Kalitni tanlang --</option>'; }
    }
}

function uiFillCombo(items) {
    if (isCardMode()) {
        var list = document.getElementById('eimzo-keys-list');
        if (list) {
            list.innerHTML = '';
            if (items.length === 0) {
                list.innerHTML = '<div class="keys-empty">Kalitlar topilmadi</div>';
            } else {
                for (var i = 0; i < items.length; i++) {
                    list.appendChild(items[i]);
                }
            }
        }
    } else {
        var combo = document.getElementById('eimzo-keys');
        if (combo) {
            for (var i = 0; i < items.length; i++) {
                combo.appendChild(items[i]);
            }
        }
    }
}

function uiComboSelect(itmId) {
    if (isCardMode()) {
        var card = document.getElementById(itmId);
        if (card) { card.click(); }
    } else {
        var el = document.getElementById(itmId);
        if (el) { el.selected = true; }
    }
}

function uiCreateItem(itmkey, vo) {
    var now = new Date();
    vo.expired = dates.compare(now, vo.validTo) > 0;

    if (isCardMode()) {
        var isYuridik = !!(vo.O || vo.T);
        var typeLabel = isYuridik ? 'Yuridik shaxs' : 'Jismoniy shaxs';
        var typeClass = isYuridik ? 'badge-yuridik' : 'badge-jismoniy';

        var validFrom = vo.validFrom ? new Date(vo.validFrom).toLocaleDateString('ru-RU') : '';
        var validTo   = vo.validTo   ? new Date(vo.validTo).toLocaleDateString('ru-RU')   : '';

        var card = document.createElement('div');
        card.className = 'key-card' + (vo.expired ? ' key-card-expired' : '');
        card.id = itmkey;
        card.setAttribute('data-vo', JSON.stringify(vo));

        var stir = vo.TIN || vo.UID || '';
        var stirRow = stir ? '<div class="key-card-stir">STIR: ' + stir + '</div>' : '';

        card.innerHTML =
            '<div class="key-card-name">' + (vo.CN || '') + '</div>' +
            '<span class="key-card-badge ' + typeClass + '">' + typeLabel + '</span>' +
            stirRow +
            '<div class="key-card-meta">' +
            '<div class="key-card-row"><span>Sertifikat raqami:</span><strong>' + (vo.serialNumber || 'N/A') + '</strong></div>' +
            '<div class="key-card-row"><span>Sertifikatning amal qilish muddati:</span><strong>' + validFrom + ' - ' + validTo + '</strong></div>' +
            '</div>' +
            (vo.expired ? '<div class="key-expired-warn">&#9888; Muddati tugagan</div>' : '');

        card.onclick = function () {
            document.querySelectorAll('.key-card').forEach(function (c) { c.classList.remove('key-card-selected'); });
            card.classList.add('key-card-selected');
            selectedCardVo = vo;
            var btn = document.getElementById('login-btn');
            if (btn) btn.disabled = false;
        };

        return card;
    }

    // Fallback: option element for <select>
    var itm = document.createElement('option');
    itm.value = itmkey;
    itm.text = vo.CN;
    if (vo.O) { itm.text += ' (' + vo.O + ')'; }
    if (vo.expired) {
        itm.style.color = 'gray';
        itm.text += ' - muddati tugagan';
    }
    itm.setAttribute('data-vo', JSON.stringify(vo));
    itm.setAttribute('id', itmkey);
    return itm;
}

function uiHandleError(e, r) {
    uiHideProgress();
    if (r) {
        if (r.indexOf("BadPaddingException") != -1) {
            uiShowMessage(errorWrongPassword);
        } else {
            uiShowMessage(r);
        }
    } else if (e) {
        wsError(e);
    }
}

function wsErroCodeDesc(code) {
    var reason;
    if (code == 1000) reason = "Normal closure";
    else if (code == 1001) reason = "Endpoint going away";
    else if (code == 1002) reason = "Protocol error";
    else if (code == 1003) reason = "Unsupported data type";
    else if (code == 1006) reason = "Connection closed abnormally";
    else if (code == 1015) reason = "TLS handshake failure";
    else reason = "Error code: " + code;
    return reason;
}

function wsError(e) {
    if (e) {
        uiShowMessage(errorCAPIWS + " : " + wsErroCodeDesc(e));
    } else {
        uiShowMessage(errorBrowserWS);
    }
}

// Get selected key
function getSelectedKey() {
    if (isCardMode()) {
        if (!selectedCardVo) {
            uiShowMessage('Iltimos, kalitni tanlang');
            return null;
        }
        return selectedCardVo;
    }
    var combo = document.getElementById('eimzo-keys');
    if (!combo || !combo.value) {
        uiShowMessage('Iltimos, kalitni tanlang');
        return null;
    }
    var option = combo.options[combo.selectedIndex];
    return JSON.parse(option.getAttribute('data-vo'));
}

// Authentication - Client-side only
function eimzoLogin() {
    uiClearMessage();
    var vo = getSelectedKey();
    if (!vo) return;

    if (vo.expired) {
        uiShowMessage('Bu kalitning muddati tugagan');
        return;
    }

    console.log('Selected key for login:', {
        name: vo.CN, pinfl: vo.PINFL,
        inn: vo.TIN || vo.UID,
        organization: vo.O,
        serialNumber: vo.serialNumber, type: vo.type
    });

    uiShowProgress('Kalit yuklanmoqda...');
    var challenge = generateChallenge();

    EIMZOClient.loadKey(vo, function(keyId) {
        uiShowProgress('Imzolanmoqda...');
        EIMZOClient.createPkcs7(keyId, challenge, null, function(pkcs7) {
            uiShowProgress('Autentifikatsiya...');
            fetch('/eimzo/authenticate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    pkcs7: pkcs7, challenge: challenge,
                    expected_pinfl: vo.PINFL, expected_name: vo.CN
                })
            })
            .then(response => response.json())
            .then(result => {
                uiHideProgress();
                if (result.success || result.status === 1) {
                    window.location.href = result.redirect || '/dashboard';
                } else {
                    uiShowMessage(result.message || 'Autentifikatsiya xatosi');
                }
            })
            .catch(err => { uiHideProgress(); uiShowMessage('Server xatosi: ' + err.message); });
        }, uiHandleError, false);
    }, uiHandleError, true);
}

// Generate random challenge string
function generateChallenge() {
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    var challenge = '';
    for (var i = 0; i < 64; i++) {
        challenge += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return challenge;
}

// Document signing
function signDocument(documentId) {
    uiClearMessage();
    var vo = getSelectedKey();
    if (!vo) return;

    if (vo.expired) {
        uiShowMessage('Bu kalitning muddati tugagan');
        return;
    }

    uiShowProgress('Hujjat ma\'lumotlari olinmoqda...');

    // Get document data
    fetch('/documents/' + documentId + '/data')
        .then(response => response.json())
        .then(data => {
            if (data.status !== 1) {
                uiHideProgress();
                uiShowMessage(data.message || 'Hujjat ma\'lumotlarini olishda xatolik');
                return;
            }

            var documentData = data.data;
            uiShowProgress('Kalit yuklanmoqda...');

            EIMZOClient.loadKey(vo, function(keyId) {
                uiShowProgress('Imzolanmoqda...');

                EIMZOClient.createPkcs7(keyId, documentData, null, function(pkcs7) {
                    uiShowProgress('Imzo tekshirilmoqda...');

                    fetch('/documents/' + documentId + '/sign', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ pkcs7: pkcs7 })
                    })
                    .then(response => response.json())
                    .then(result => {
                        uiHideProgress();
                        if (result.status === 1) {
                            uiShowMessage('Hujjat muvaffaqiyatli imzolandi!');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            uiShowMessage(result.message || 'Imzolashda xatolik');
                        }
                    })
                    .catch(err => {
                        uiHideProgress();
                        uiShowMessage('Server xatosi: ' + err.message);
                    });
                }, uiHandleError, false, true);
            }, uiHandleError, true);
        })
        .catch(err => {
            uiHideProgress();
            uiShowMessage('Server bilan ulanishda xatolik: ' + err.message);
        });
}

// Initialize on DOM ready - runs on any page with the E-IMZO key dropdown
document.addEventListener('DOMContentLoaded', function() {
    if (typeof CAPIWS !== 'undefined' &&
        (document.getElementById('eimzo-keys') || document.getElementById('eimzo-keys-list'))) {
        AppLoad();
    }
});
