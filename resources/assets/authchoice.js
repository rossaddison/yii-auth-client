// Shallow-merges own enumerable properties of source over target into a new object.
function extend(target, source) {
    var result = {};
    var key;
    for (key in target) {
        if (Object.prototype.hasOwnProperty.call(target, key)) {
            result[key] = target[key];
        }
    }
    for (key in source) {
        if (Object.prototype.hasOwnProperty.call(source, key)) {
            result[key] = source[key];
        }
    }
    return result;
}

// Main function to initialize the authchoice widget
function authchoice(container, options) {
    var defaults = {
        triggerSelector: 'a.auth-link',
        popup: {
            resizable: 'yes',
            scrollbars: 'no',
            toolbar: 'no',
            menubar: 'no',
            location: 'no',
            directories: 'no',
            status: 'yes',
            width: 450,
            height: 380
        }
    };

    options = extend(defaults, options || {});
    options.popup = extend(defaults.popup, options.popup || {});

    var triggers = container.querySelectorAll(options.triggerSelector);

    triggers.forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();

            var authChoicePopup = container._authChoicePopup;

            if (authChoicePopup) {
                authChoicePopup.close();
            }

            var url = trigger.href;
            var popupOptions = extend({}, options.popup);

            var localPopupWidth = trigger.getAttribute('data-popup-width');
            if (localPopupWidth) {
                popupOptions.width = localPopupWidth;
            }
            var localPopupHeight = trigger.getAttribute('data-popup-height');
            if (localPopupHeight) {
                popupOptions.height = localPopupHeight;
            }

            popupOptions.left = (window.screen.width - popupOptions.width) / 2;
            popupOptions.top = (window.screen.height - popupOptions.height) / 2;

            var popupFeatureParts = [];
            for (var propName in popupOptions) {
                if (Object.prototype.hasOwnProperty.call(popupOptions, propName)) {
                    popupFeatureParts.push(propName + '=' + popupOptions[propName]);
                }
            }
            var popupFeature = popupFeatureParts.join(',');

            authChoicePopup = window.open(url, 'yii_auth_choice', popupFeature);
            if (authChoicePopup) {
                authChoicePopup.focus();
                container._authChoicePopup = authChoicePopup;
            }
        });
    });
}

// Attach to window for usage
window.authchoice = authchoice;

// Auto-init for DOM elements with a [data-authchoice] attribute; a non-empty
// value is parsed as the JSON options object (see AuthChoice::init(), which
// sets this attribute instead of registering an inline <script> so the
// widget works under a CSP with no 'unsafe-inline'/'unsafe-eval').
document.addEventListener('DOMContentLoaded', function() {
    var containers = document.querySelectorAll('[data-authchoice]');
    containers.forEach(function(container) {
        var options = {};
        var raw = container.getAttribute('data-authchoice');
        if (raw) {
            try {
                options = JSON.parse(raw);
            } catch (e) {
                options = {};
            }
        }
        window.authchoice(container, options);
    });
});