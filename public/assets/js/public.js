(function () {
  var amountInput = document.getElementById('amount');
  if (!amountInput) return;
  amountInput.addEventListener('input', function () {
    var digits = this.value.replace(/\D/g, '');
    if (digits === '') { this.value = ''; return; }
    this.value = Number(digits).toLocaleString('en-US');
  });
  var form = document.getElementById('payForm');
  if (form) {
    form.addEventListener('submit', function () {
      if (amountInput) amountInput.value = amountInput.value.replace(/\D/g, '');
    });
  }
})();
