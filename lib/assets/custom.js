var VendiPayPaymentWindow = VendiPayPaymentWindow ||
    (function() {
        var _epayArgsJson = {};
        return {
            init: function (epayArgsJson) {
                _epayArgsJson = epayArgsJson;
            },
            getJsonData: function() {
                return _epayArgsJson;
            },
        }
    }());

var isPaymentWindowReady = false;
var timerOpenWindow;

function PaymentWindowReady() {
    paymentwindow = new PaymentWindow(VendiPayPaymentWindow.getJsonData());

    isPaymentWindowReady = true;
}
function openPaymentWindow() {
    if (isPaymentWindowReady) {
        clearInterval(timerOpenWindow);
        paymentwindow.open();
    }
}

document.addEventListener('readystatechange', function (event) {
    if (event.target.readyState === "complete") {
        timerOpenWindow = setInterval("openPaymentWindow()", 500);
    }
});
