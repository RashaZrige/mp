document.addEventListener('DOMContentLoaded', () => {
  const inputs = document.querySelectorAll('.otp input');
  inputs.forEach((inp, i) => {
    inp.addEventListener('input', () => {
      inp.value = inp.value.replace(/\D/g,'').slice(0,1);
      if (inp.value && i < inputs.length-1) inputs[i+1].focus();
    });
    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !inp.value && i > 0) inputs[i-1].focus();
    });
  });
});